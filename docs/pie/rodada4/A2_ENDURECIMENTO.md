# A2 — Endurecimento residual (rodada 4)

Agente A2, 2026-11-20. Escopo: R3-F-06, P2-E-13, P2-E-10, PIE-26 e os
registradores que engoliam falha. Nada foi commitado; a lista de arquivos está no
fim.

Prova: cada teste novo foi rodado **sem** a correção (os 9 arquivos de `app/`
voltaram ao `HEAD` com `git show`) e **com** ela. Sem as correções, a saída foi
`9 failed, 13 passed`: as 9 falhas são exatamente os testes novos, e o 13º
aprovado é o caso de controle (`fechamentos_do_proprio_convenio_seguem_200`).
Com as correções, todos passam. A RLS foi provada no Postgres real, no banco
`pie_a2_rls`.

Suíte completa em sqlite, com a árvore de trabalho no estado do fim desta tarefa
(inclui o trabalho em curso da A1): **2123 passed, 0 falhas, 35 skipped**.

---

## 1. R3-F-06 — subrecurso de pai invisível respondia 200 vazio

**Causa.** A listagem filtrava os filhos por `where('<pai>_id', $id)` sem antes
carregar o pai. A RLS e o escopo global zeravam a lista, mas a rota respondia
200, e não 404. No caso das cidades, a empresa era buscada só pela rede
(`grupo_id`), sem conferir se o usuário tinha concessão nela.

**Correção.**
- `app/Http/Controllers/Api/Admin/ComodatoController.php:265`: `Cliente::query()->findOrFail($cliente)` antes de listar as avaliações.
- `app/Http/Controllers/Api/Admin/ConvenioController.php:64`: `Convenio::query()->findOrFail($id)` antes dos fechamentos.
- `app/Http/Controllers/Api/Admin/CidadeController.php:159`: `abort_unless($user->podeAcessarEmpresa($empresaId), 404)` em `empresaDoGrupo`. É o mesmo padrão de `EmpresaConfigController::empresaDoGrupo`. Vale para o GET e para o PUT `empresas/{id}/cidades`.

**Varredura de outros casos.** Revisei os 49 GETs com `{id}/subrecurso` em
`routes/api.php` e os `where('*_id', $id)` dos controllers. Todos os outros já
carregam o pai antes de listar:
- `findOrFail` escopado: clientes/*, colaboradores/*, veiculos/*, monitora/veiculos/*;
- helpers `doGrupo`, `localizar` e `pedido`;
- `SorteioService::palco`;
- `EstoqueController::porProduto` (com `exists`).

Os demais casos do padrão buscam o filho com `->where('pai_id', $id)->findOrFail($filho)`, que já dá 404.

**Teste.** `tests/Feature/SubrecursoPaiInvisivelTest.php` (4 testes): três rotas
com pai alheio devem responder 404, e o próprio convênio continua respondendo 200.

| | Resultado |
|---|---|
| Sem a correção | 3 falhas (200 em vez de 404); o controle passa |
| Com a correção | 4/4 |

## 2. P2-E-13 — RLS nas filhas de configuração por grupo

**Causa.** `checklist_perguntas` e `sorteio_numeros` só ganhavam RLS em
`TenantLegacyGroupConfigurationProtector::protectChildTable`, que roda pelo
comando pós-carga. Num banco migrado do zero, conferido em `pie_a2_rls`, as duas
tinham `relrowsecurity = f` e nenhuma policy.

**Correção.** Migration
`database/migrations/2026_11_20_000100_rls_nas_filhas_de_configuracao_por_grupo.php`:
- Faz `GRANT SELECT, INSERT, UPDATE, DELETE ... TO erp_app`, `ENABLE` + `FORCE ROW LEVEL SECURITY` e cria a policy `tenant_isolation`.
- A policy é `USING (EXISTS (SELECT 1 FROM <pai> WHERE pai.id = filha.fk))`. A subconsulta passa pela RLS do pai, então a filha é visível exatamente quando o pai é. Isso vale com o pai ainda na policy legada por `app.grupo_id` e também depois que o pós-carga o converte para a canônica.
- O `WITH CHECK` exige o pai visível e `pai.tenant_account_id IS NOT DISTINCT FROM filha.tenant_account_id`. Os models já copiam o tenant do pai. O `IS NOT DISTINCT FROM` aceita os dois nulos do estado anterior ao pós-carga.
- O caso do `empresa_id` nulo legítimo não se aplica: as tabelas não têm `empresa_id`.

Propriedades da migration:
- **Idempotente e não destrutiva.** Se a tabela já tem `tenant_isolation` (o pós-carga já rodou, caso da homologação), ela não é tocada; a policy do pós-carga é mais estrita. Nenhuma linha é alterada.
- **Reversível.** O `down` só remove a policy marcada com `COMMENT ON POLICY ... 'P2-E-13: filha visivel pelo pai'`. A policy do pós-carga fica.
- **Compatível com o pós-carga.** O protetor faz `DROP POLICY IF EXISTS` + `CREATE` e substitui a desta migration normalmente.

**Teste.** `RlsCoberturaTest::test_filhas_de_configuracao_por_grupo_tem_rls_pela_migration`.
O teste já está no portão `composer test:pgsql-rls` e usa `DatabaseTransactions`. Ele confere:
- `pg_class` com `relrowsecurity` e `relforcerowsecurity`;
- `pg_policies` com `tenant_isolation`;
- o efeito observável sob `erp_app` com `app.grupo_id` do grupo A:
  - a sessão vê só a pergunta e o número de A;
  - `update` e `delete` na linha de B afetam 0 linhas;
  - o insert de uma pergunta num checklist de B é recusado;
  - o insert num checklist de A é aceito;
  - o owner confirma que a pergunta de B não foi alterada.

| | Resultado (`pie_a2_rls`) |
|---|---|
| Sem a migration | falha em `0 is identical to 2` (`pg_class`) |
| Sem a migration, com as asserções de metadado desligadas temporariamente | falha em `two arrays are identical`: a sessão via as filhas de B |
| Com a migration | 7/7 no `RlsCoberturaTest`; portão completo **34/34** (era 33), `--fail-on-skipped` |
| `down` | RLS desligada e 0 policies nas duas |
| Idempotência | Com uma `tenant_isolation` do tipo pós-carga criada antes em `sorteio_numeros`, o `up` a manteve intacta e só criou a de `checklist_perguntas`. O `down` removeu só a sua |

## 3. P2-E-10 — atos da plataforma sem trilha

**Causa.** Não havia nenhuma chamada a `AuditoriaPlataforma::registrar` em
`SuperAdmin/MigracaoController` nem no CRUD de cidades da plataforma. O download
do CSV de descartes (dado cru do legado, com PII) não deixava registro.

**Correção.**

`app/Http/Controllers/Api/SuperAdmin/MigracaoController.php`:
- Helper `trilha()` em `:39`.
- Registro em: `store` (`:89`, com origem, host e banco, **sem** usuário ou senha), `conectar` (`:104`), `diagnosticar` (`:126`), `mapeamento` (`:176`), `executar` (`:207`), `simular` (`:217`) e `descartesCsv` (`:255`, com a contagem de linhas).

`app/Http/Controllers/Api/SuperAdmin/PainelController.php`:
- `cidade.salva` (`:60`), `cidade.atualizada` com antes e depois (`:70`) e `cidade.excluida` (`:80`).

**Teste.** Em `tests/Feature/MigracaoFerramentaTest.php`:
- `test_atos_da_ferramenta_ficam_na_trilha_da_plataforma`: criar, mapear, executar e baixar o CSV geram uma linha cada, com `platform_admin_id`. A senha da origem não aparece na trilha.
- `test_crud_de_cidades_da_plataforma_fica_na_trilha`.

| | Resultado |
|---|---|
| Sem a correção | 2 falhas |
| Com a correção | 2/2, e os 15 testes antigos do arquivo seguem verdes |

## 4. PIE-26 — job de geocodificação dentro da transação

**Causa.** `GeocodificarClienteJob` é despachado em `ClienteService:37,61` e
`PedidoMobileService:170`, dentro da transação, e as filas têm
`after_commit=false` (`config/queue.php`). O worker podia rodar antes do commit,
não achar o cliente e sair calado.

**Correção.** `app/Domain/Cliente/GeocodificarClienteJob.php:31` passou a
implementar `ShouldQueueAfterCommit`. A mudança cobre os três pontos de despacho
sem tocar a config global.

**Teste.** `tests/Feature/GeocodificacaoAposCommitTest.php`. A fila `sync` da
suíte respeita o mesmo contrato da redis (`Queue::shouldDispatchAfterCommit`). O
teste despacha dentro de `DB::transaction` e confere que o job não rodou antes
do commit e rodou uma vez depois.

| | Resultado |
|---|---|
| Sem a correção | falha (o job rodou antes do commit) |
| Com a correção | passa |

## 5. Registradores que engoliam falha

**Causa.** Três `catch (\Throwable)` mudos descartavam a falha da trilha.

**Correção.** O `catch` foi mantido (decisão: trilha não derruba a operação) e
ganhou `report($e)`:
- `app/Http/Controllers/Concerns/AutorizaPorPermissao.php:80`: negação 403 cuja trilha falha.
- `app/Domain/Integracao/ConsumoDeIntegracao.php:116` (`registrar`) e `:139` (`resumo`). O `resumo` vazio por erro não pode parecer "zero chamadas".
- `app/Domain/Pedido/PedidoService.php:490`: `AuditLog` da recusa de alçada.

**O que não foi alterado, e por quê.** As linhas citadas em `AuditoriaSeguranca.php:62`, `AuditoriaPlataforma.php:63`, `Auditavel.php:93` e `RegistroAcao.php` (`ipAtual`/`ip`) não são registradores engolindo falha.
- São o `try { Request::ip() }` para rodar fora de HTTP.
- Nesses quatro, a escrita da trilha **não** está dentro de `catch`: a exceção já propaga ao chamador.
- Pôr `report` no `ip()` só geraria ruído.

O "`PedidoService.php:486`" da lista é o mesmo catch da alçada tratado acima.

**Teste.** `tests/Feature/RegistradorFalhaReportaTest.php`, com `Exceptions::fake()`:
- `ConsumoDeIntegracao` sem a tabela: a integração segue, e duas falhas são reportadas.
- `AuditoriaSeguranca` mockada para lançar: a resposta continua 403, e a exceção é reportada.
- `AuditLog::creating` lança só para `alcada_desconto`: o pedido continua recusado (`DomainException`), e a exceção é reportada.

| | Resultado |
|---|---|
| Sem a correção | 3 falhas |
| Com a correção | 3/3 |

---

## Incidente de ambiente: migration aplicada no banco da A1

O arquivo de variáveis `scratchpad/pgenv.sh` é compartilhado entre os agentes da
sessão, e foi sobrescrito pela A1 com `DB_DATABASE=pie_a1_rls`. Um
`php artisan migrate --database=pgsql_owner` meu rodou a migration P2-E-13 em
**`pie_a1_rls`**:
- ela consta no batch 6 da tabela `migrations` de lá;
- as duas filhas estão com RLS e com a policy marcada.

Tentei desfazer com SQL equivalente ao `down`, mas a ferramenta de permissão
bloqueou a escrita no banco de outro agente. Não insisti. Daí em diante usei um
arquivo próprio (`scratchpad/a2/pgenv_a2.sh`), e cada comando confere
`DB_DATABASE=pie_a2_rls` antes de rodar.

O efeito é aditivo: é o mesmo estado que o banco da A1 teria no próximo
`migrate` dela. Se a A1 precisar do banco sem a migration, o coordenador pode
rodar:

```sql
-- em pie_a1_rls, como postgres
DROP POLICY tenant_isolation ON checklist_perguntas;
ALTER TABLE checklist_perguntas NO FORCE ROW LEVEL SECURITY; ALTER TABLE checklist_perguntas DISABLE ROW LEVEL SECURITY;
DROP POLICY tenant_isolation ON sorteio_numeros;
ALTER TABLE sorteio_numeros NO FORCE ROW LEVEL SECURITY; ALTER TABLE sorteio_numeros DISABLE ROW LEVEL SECURITY;
DELETE FROM migrations WHERE migration = '2026_11_20_000100_rls_nas_filhas_de_configuracao_por_grupo';
```

## Pendente

- **`condicaopagamento_parcelas`** está na mesma situação das duas filhas (só o pós-carga a protege). Ficou fora do escopo pedido, e não há model para conferir quem grava nela além do ETL. A correção é acrescentá-la à constante `FILHAS` da migration, depois de confirmar o escritor.
- **Catálogo de cidades pelo painel da revenda** (`Admin/CidadeController::store/update/destroy`, `cidade.*`): um usuário de tenant altera o catálogo GLOBAL e não deixa trilha de plataforma. Fica para decisão: talvez esse CRUD nem devesse existir fora do SuperAdmin.
- **P2-E-10, parte menor:** `conectar`, `diagnosticar` e `simular` ainda devolvem `$e->getMessage()` ao cliente (sondagem de rede pelo superadmin, BAIXA). Não alterei, porque muda o contrato da tela `features/superadmin`, que está fora do meu escopo.
- **P2-E-10:** a trilha não registra as leituras de `show`, `validar` e `descartes` (JSON paginado). Registrei só o CSV, que é a exportação em massa.
- A homologação não foi consultada. A migration P2-E-13 é no-op lá se o pós-carga já tiver rodado.
