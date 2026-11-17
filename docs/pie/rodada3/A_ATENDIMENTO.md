# Rodada 3 — Agente A: Atendimento (operador e gerente comercial)

**Data:** 2026-10-31 · **Commit de partida:** `9fcfde5a` · **Escopo:** busca/cadastro de cliente, Novo pedido, quantidade, condição de pagamento, teclado.
A sessão caiu duas vezes por limite de uso; este relatório registra **só o que foi provado**. O que não foi está na §6.

## 1. Ambiente

- Banco `pie_r3_a` (`create database pie_r3_a template pie_r3_base`), Postgres 15 portátil, porta 55432. **Ficou criado** (o coordenador pode apagar).
- API: `php artisan serve --port=8211` como `erp_app`, `SAAS_ENFORCE_LICENCA=true`, `SAAS_ENFORCE_TENANT_ENVELOPE=true`, `CACHE_STORE=array` (o cache em arquivo é compartilhado entre os 7 agentes, com bancos diferentes e os mesmos ids de tenant).
- SPA: `npx vite --port 5211 --strictPort`. Os dois processos morreram com a queda da sessão; nada meu ficou escutando em 8211/5211.
- Migration aplicada no `pie_r3_a`: `migrate --database=pgsql_owner --force` (inclui rollback + reaplicação da `2026_10_31_1a0100`).
- Carga sintética de 60 mil clientes **só no `pie_r3_a`**, para medir o plano; apagada depois (`clientes` voltou a 201).

## 2. Matriz perfil × jornada

| Jornada | Operador | Gerente | Elo que falta |
|---|---|---|---|
| Achar cliente por nome sem acento (Novo pedido e listagem) | VERIFICADA na API (curl com token do operador) + teste de feature | idem (mesma rota, `cliente.view`) | 1/2 no navegador (spec E2E não escrita) |
| Achar cliente pelo telefone na **listagem** | VERIFICADA na API + teste | idem | navegador |
| Cadastrar cliente com telefone (cadastro completo) | IMPLEMENTADA NÃO VERIFICADA no navegador; servidor já aceitava `telefones` | idem | 1/2/9 no navegador |
| Cadastrar cliente novo sem sair do Novo pedido | IMPLEMENTADA, coberta por vitest | idem | 9 (releitura no banco via UI) |
| Novo pedido: item inválido, Esc, quantidade, teclado | IMPLEMENTADA, coberta por vitest; regra de servidor coberta por feature | idem | navegador |
| CPF/CNPJ com dígito verificador | VERIFICADA (feature + curl reproduziu o defeito antes) | idem | — |
| Desconto/alçada, mudar situação até entregue/cancelado | NÃO EXECUTADA nesta rodada | NÃO EXECUTADA | todos (§6) |

## 3. Achados

### R3-A-01 — Busca de cliente sensível a acento (P2-D-05, confirmado) — ALTA — CORRIGIDO
- **Onde:** `LookupController.php:207` (`LOWER(col) LIKE`) e `ClienteController.php:41-48` (`ilike`).
- **Reprodução (antes):** `GET /api/admin/lookups/clientes?q=joao` → `{"data":[]}`; `q=João` → 7 resultados. `GET /api/admin/clientes?q=joao` → total 0.
- **Depois:** `q=joao` → 7; `joao haick` → 2; `acougue` → 8 (lookup e listagem). `lookups/produtos?q=botijao` → acha "Botijão P13".

### R3-A-02 — O índice trigram nunca é usado pelo runtime sob RLS — ALTA (desempenho) — CORRIGIDO na busca de cliente
- **Achado novo, medido:** sob RLS, o Postgres só aplica antes da policy os operadores **LEAKPROOF**. `textlike` (`LIKE`/`ILIKE`) **não** é (`pg_proc.proleakproof = f`). Com isso, os índices `clientes_{nome,cpf,cnpj}_trgm_idx` (migration `2026_07_03_000400`) **nunca** serviram ao `erp_app`: a busca varre a tabela e roda `app_tenant_can_read()` em cada linha.
- **EXPLAIN ANALYZE como `erp_app`** (`SET LOCAL ROLE` + `set_config`, 60 mil clientes, máquina carregada por 7 agentes):
  | Consulta | Antes | Depois |
  |---|---|---|
  | `LOWER(nome) LIKE '%joao haick%'` (código antigo) | Seq Scan, **5.051 ms** | — |
  | nome sem acento, LIKE direto sob RLS | Seq Scan, 2.920 ms | — |
  | nome sem acento via candidatos | — | **24 ms** (`Bitmap Index Scan on clientes_busca_sem_acento_trgm_idx`) |
  | telefone `94725` (união com LIKE) | 41.958 ms | **7 ms** |
- **Vale para o resto da base:** toda busca `LIKE '%…%'` em tabela grande sob RLS tem o mesmo problema. Ver "Para outros donos".

### R3-A-03 — Listagem de clientes não buscava por telefone — MÉDIA — CORRIGIDO
- `/clientes?q=94725` → total 0 antes; depois → 1 (Patrícia Lustosa). Aceita `94725`, `(42) 94725-0763`, `42947250763`.

### R3-A-04 — CPF/CNPJ sem dígito verificador e gravado com máscara (PIE-35) — ALTA (fiscal) — CORRIGIDO
- **Reprodução (antes):** `POST /clientes {"cpf":"111.111.111-11"}` → 201, cliente 201 gravado com `"111.111.111-11"`.
- **Agravante:** o ETL grava só dígitos (`ClientesMigrator.php:206`) e o `XmlNfeBuilder.php:144` põe o valor como está no `<CPF>` — CPF com máscara gravado pelo painel viraria rejeição de schema na NF-e.
- **Correção:** validação do dígito no servidor e gravação só com dígitos. Na **edição**, um documento legado inválido que **não mudou** passa (senão nenhum cliente migrado com CPF ruim poderia ter o telefone trocado). Mensagens em pt-BR no `ClienteRequest`.

### R3-A-05 — Quantidade 1,5 e 1.000.000 sem teto (P2-D-12) — MÉDIA — CORRIGIDO (regra) / teto configurável sem tela
- Fração recusada quando a unidade do produto é contável (`UN`, `UND`, `PC`, `CX`…); kg/litro continuam aceitando fração.
- Teto por item **é configuração do tenant**: `empresa_configs.dados['pedido_quantidade_maxima_item']`. Sem valor, não há teto. Não inventei um padrão (CLAUDE.md: "convenção de uma revenda não é regra do produto").
- Na SPA, a quantidade deixou de ser `type=number`: "1e3" virava 1.000 botijões. Agora aceita "2", "1,5", "1.5" e recusa notação científica, sinal e texto.

### R3-A-06 — Item inválido descartado sem aviso (R2-09) — MÉDIA — CORRIGIDO
- `NovoPedidoDialog` filtrava em silêncio os itens sem produto ou com quantidade 0, e o toast dizia "Pedido criado". Agora a linha incompleta é apontada no campo, bloqueia o salvar e aparece no "Falta informar…".

### R3-A-07 — Esc/clicar fora/Cancelar descarta o pedido sem perguntar (R2-10) — MÉDIA — CORRIGIDO
- Com cliente ou item escolhido, fechar abre "Descartar este pedido?". "Cancelar" volta ao pedido, com tudo preservado.

### R3-A-08 — Cliente novo exige sair do Novo pedido (P2-A-13) — MÉDIA — CORRIGIDO
- Botão "Cadastrar cliente novo sem sair do pedido" (só com `cliente.create`), com nome e telefone. Usa `POST /clientes` (a porta de identidade): se o cadastro já existe, o servidor devolve 200 com o cliente existente, e a tela avisa "já estava cadastrado e foi escolhido".

### R3-A-09 — Cadastro de cliente sem campo de telefone (P2-D-10) — MÉDIA — CORRIGIDO
- `ClienteFormPage`: no cadastro novo há um campo Telefone em Dados Gerais, enviado como `telefones[0]` na mesma gravação.

### R3-A-10 — Seleção por teclado (P2-D-15) — MÉDIA — CORRIGIDO no `AsyncSelect`
- Enter no campo de busca escolhe o 1º resultado; ↓/↑ andam pelas opções (Enter/Espaço é o clique nativo); ↑ na primeira opção volta ao campo. A mudança é aditiva (os botões e os nomes acessíveis continuam os mesmos, então os testes existentes que usam `getByRole('button')` seguem valendo).

### R3-A-11 — Revenda sem condição de pagamento não explica o porquê (P2-D-13) — MÉDIA — PARCIAL
- Prop aditiva `vazio` no `AsyncSelect`. No Novo pedido, a lista vazia explica e leva a Financeiro (ou diz para pedir ao administrador, sem `financeiro.edit`). **Continua pendente:** semear condições padrão na criação da revenda (onboarding é do C). O `pie_r3_base` tem 0 condições: no banco-base o operador **não consegue** criar pedido pela tela.

## 4. Correções e provas

**Arquivos alterados (todos da área A, mais um aditivo em `@/components/ui`):**
- novos: `app/Domain/Cliente/BuscaCliente.php`, `app/Domain/Identidade/DocumentoFiscal.php`, `app/Domain/Pedido/QuantidadeDoItem.php`, `database/migrations/2026_10_31_1a0100_busca_de_cliente_sem_acento.php`, `tests/Feature/PieR3AtendimentoTest.php`
- alterados: `app/Domain/Identidade/NormalizadorTexto.php` (só `tabelaAcentos()`, pública), `Api/Admin/LookupController.php`, `Api/Admin/ClienteController.php`, `Http/Requests/ClienteRequest.php`, `Http/Requests/PedidoRequest.php`, `frontend/src/components/ui/async-select.tsx` (aditivo), `features/pedidos/NovoPedidoDialog.tsx` + `.test.tsx`, `features/clientes/ClienteFormPage.tsx`, `features/clientes/api.ts`, `tests/Feature/ClienteTest.php` (fixture: o CPF `12345678900` é inválido e passou a ser recusado, como se quer; foi trocado por um válido).

**Desenho da busca (por quê):** expressão indexada `app_busca_cliente(nome, fantasia)` (IMMUTABLE, gerada da **mesma** tabela de acentos do PHP, com as maiúsculas acentuadas, porque o banco de teste tem `LC_CTYPE=C` e `lower('Á')` devolve `'Á'`). Optei por ela, e não por uma coluna mantida pelo model, porque o ETL grava `clientes` por `DB::table` e a coluna nasceria vazia. Não usa `unaccent`, que não é IMMUTABLE e exige extensão. No Postgres, os ids candidatos vêm de `app_clientes_candidatos()` (SECURITY DEFINER, `app_tenant_can_read` explícito, EXECUTE só para `erp_app`). A consulta de fora roda **sob RLS** pelo runtime, então um id alheio que escapasse seria descartado pela policy. No sqlite, a mesma função é registrada no PDO apontando para o PHP: o SQL da busca é idêntico nos dois bancos.

**Com a correção** — `php artisan test` (sqlite), lote do módulo:
`PieR3AtendimentoTest` 13/13 verde. Lote `PieR3Atendimento + Lookup + LookupAutorizacao + Cliente + Pedido + NovoPedidoPratico + IdentidadeCliente + AlcadaCrud + AlcadaDesconto + ClienteRevisao + AppVendaCampo + PonteNfweb`: **113 passaram, 1 falha**, e a falha era do próprio teste novo (fixture duplicada), corrigida e reexecutada: 13/13.

**Sem a correção** (cópia no scratchpad, `git show HEAD:` nos 4 arquivos de servidor, teste, restauração conferida com `git diff --stat`):
```
⨯ lookup acha cliente digitado sem acento
⨯ listagem acha sem acento pela fantasia e pelo telefone
⨯ termo nao atravessa nome e fantasia
⨯ termo so de pontuacao nao devolve a base
⨯ lookup de produto acha sem acento
⨯ cpf e cnpj sem digito verificador sao recusados
⨯ documento valido e gravado so com digitos
⨯ edicao nao trava por documento legado que nao mudou
⨯ produto por unidade nao aceita fracao
⨯ teto por item vem da configuracao da revenda
Tests: 10 failed, 3 passed
```
Os 3 que passam sem a correção testam funções puras (tradução, dígito verificador) ou o caso que já era aceito (kg com fração). São esperados.

**SPA:** `npx vitest run src/features/pedidos src/features/clientes src/components/ui` → 27/27 depois do ajuste. `NovoPedidoDialog.test.tsx` 8/8 (3 antigos + 5 novos: R2-09, P2-D-12, R2-10, P2-D-15, P2-A-13). `npx tsc --noEmit`: nenhum erro nos meus arquivos (a saída foi filtrada por `features/(pedidos|clientes)|async-select`; não conferi o total do projeto).
**Prova "sem a correção" da SPA: NÃO COMPROVADA.** Na execução com `HEAD` os 2 testes antigos também falharam, por timeout de 15–18 s (máquina saturada pelos 7 agentes). Com isso a execução não distingue a regressão do ruído. Precisa ser refeita com a máquina livre.

## 5. Para outros donos e decisões

**Para C (Plataforma/config):**
1. `EmpresaConfigController.php:46` (`DADOS_EDITAVEIS`): acrescentar `pedido_quantidade_maxima_item` e o campo numérico na tela de configuração da empresa. Sem isso, o teto só se configura direto no banco.
2. Onboarding: semear condições de pagamento padrão na revenda nova (P2-D-13). O `pie_r3_base` tem 0 condições e o Novo pedido não fecha.
3. `config/app.php:92` `locale='en'` sem `lang/pt_BR`: as mensagens padrão continuam saindo como `validation.*`. Contornei nas minhas requests com `messages()`.

**Para E (app do consumidor):** `ClienteAuthService.php:94` grava `cpf` como chega, sem dígito verificador nem normalização. Pode usar `App\Domain\Identidade\DocumentoFiscal`. A regra de quantidade (`QuantidadeDoItem`) só vale no `PedidoRequest` do painel; `PedidoMobileService` não a aplica.

**Para F (demais módulos) e coordenador:** R3-A-02 é sistêmico. Qualquer `LIKE/ILIKE '%…%'` em tabela grande sob RLS varre a tabela (pedidos, ruas, títulos). O padrão "candidatos por função SECURITY DEFINER + leitura final sob RLS" está em `BuscaCliente` e na migration `1a0100`.

**Decisões pendentes (§9):**
- **D-A1 — o que é "unidade contável".** Implementei a lista `UN, UND, UNID, UNIDADE, PC, PÇ, CX, BJ`. Recomendo, em vez disso, uma flag `fracionavel` em `unidadesmedida`, editável pela revenda.
- **D-A2 — teto padrão de quantidade.** Hoje não há teto sem configuração. Recomendo deixar sem teto e expor o campo (item C-1); para revenda doméstica, sugerir 50 no onboarding.

## 6. Não testado, e por quê

- **Specs E2E em `erp-novo/e2e/atendimento/` não foram escritos.** As duas quedas de sessão consumiram o tempo, e a API/SPA morreram junto. As jornadas no navegador (axe, teclado real, celular 390×844, duas abas, sessão vencida, prova com `consultar()`) ficam pendentes. A verificação desta rodada é API (curl como operador) + feature + vitest.
- **Desconto/alçada (P2-D-20)** e **mudança de situação até entregue/cancelado**: não executados nem alterados.
- **Teste Postgres versionado:** não há classe nova para o portão. O plano e a função foram provados à mão no `pie_r3_a` (§3, R3-A-02). Recomendo `BuscaClienteSemAcentoPgsqlTest` (como `erp_app`: `joao` acha `João`; a função não devolve id de outro tenant; `EXPLAIN` usa `clientes_busca_sem_acento_trgm_idx`).
- **Triagem de `pie-costura.mjs`** (rotas sem consumidor de `admin/pedidos`, `admin/clientes`, `admin/central*`, `admin/lookups`): não feita.
- **Gerente** não foi exercitado separadamente: as rotas usadas exigem as mesmas permissões (`cliente.*`, `pedido.*`) que o operador tem no banco-base.
- **CPF com máscara no legado de demonstração:** a busca por dígitos não acha `023.674.920-78` digitando `02367492078`. Dado de ETL real é só dígitos e o cadastro novo também; não tratei a base de demonstração.
