# A1 — Provisionamento de revenda (P2-A-01, P2-A-07, P2-D-13, R3-A-11, R3-G-22)

Data: 2026-11-20. Agente A1. Nada foi commitado.

## Resumo

A revenda nº 2 agora nasce pelo produto. Antes, o único caminho era
`saas:tenant:importar <json>`. Agora o superadmin usa **Empresas → Nova revenda**
(`POST /api/superadmin/revendas`). Numa transação só, pela conexão de owner, essa
chamada cria:

- grupo, empresa, dono, TenantAccount, vínculo da empresa, membership OWNER, grant
  de leitura e operação, e a ponte do grupo;
- os papéis padrão, com o dono como Administrador;
- o plano de contas;
- o catálogo inicial;
- a assinatura.

O dono não recebe senha: recebe um **convite de uso único** e define a própria
senha em `/definir-senha`. O Início da revenda ganhou o checklist **"Pronto para
vender?"**, com 6 itens. Cada pendência diz o porquê e leva à tela que a resolve.

O teste de aceite no Postgres percorre o caminho inteiro sob RLS:
provisionar → convite → login → catálogo → pedido concretizado com baixa de
estoque → isolamento A×B nos dois sentidos. Ele está verde, e **falha** quando se
remove a correção principal (o envelope do tenant na gravação do catálogo).

## Arquivo por arquivo

### Backend (novos)

**`erp-novo/app/Domain/Saas/TenantProvisioningService.php`**
- `provisionar()` roda em `ConexaoDeOwner::executar(DB::transaction(...))`.
- Ordem das etapas:
  1. Grupo, Empresa e User. O dono nasce com hash de um aleatório de 64 caracteres, que é descartado.
  2. `TenantMappingImporter::apply()`, reaproveitado com as proteções dele. A evidência é `superadmin:revenda:<admin>:<uuid>`.
  3. `grupos.tenant_account_id`.
  4. `RbacSeeder::papeisPadraoDoGrupo()` e o papel Administrador do dono.
  5. `PlanoContaModeloService::copiarParaGrupo()`.
  6. `CatalogoInicialRevenda` **dentro do `TenantEnvelopeRuntime::run()`** do tenant novo.
  7. `SuperAdminService::definirAssinatura()`.
  8. Token do broker `convites`.
  9. `AuditoriaPlataforma::registrar('revenda.provisionada')`, **sem o token**.
- E-mail existente (sem diferenciar maiúsculas) → 422. Não há reuso nem `firstOrCreate`.
- Plano inexistente ou inativo → 422 (fail-closed).

**`erp-novo/app/Domain/Saas/CatalogoInicialRevenda.php`** — extraído do demo. Cria:
- tipos de pessoa (PF/PJ) e de telefone (Celular, Residencial, Comercial);
- operações Venda e Disk;
- 5 situações: Em Aberto (padrão da tela), Em Separação, **Em Rota com `PapelSituacao::EM_ROTA`**, Concluído e Cancelado;
- **condições À vista e A prazo 30 dias**, sem PIX online (o PIX exige a credencial da própria revenda);
- setores Depósito (DEPOSITO), Loja (LOJA) e Veículo 01 (VEICULO);
- classes GLP, Água e Acessórios, e a unidade UN;
- produtos Gás GLP P13/P20/P45 e Água mineral 20 L, com **preço 0**.

É idempotente pela descrição. Não usa os literais Dubena/Guarapuava nem a palavra "Botijão".

**`erp-novo/app/Domain/Saas/ProntoParaVenderService.php`** — o checklist. Só lê o que já existe:
- **fiscal:** `ProntidaoFiscalService` (cita até 3 faltas);
- **preço:** produto ativo com `preco_venda > 0`;
- **estoque:** `estoque_saldos.quantidade > 0`;
- **entregador:** colaborador ativo, marcado como entregador, com `user_id` (a mesma exigência do login do app);
- **área de entrega:** cerca ativa, OU lat/lng com `raio_entrega_km > 0` (a mesma regra do `MarketplaceService`);
- **condição de pagamento:** pelo menos uma ativa no grupo.

Cada item tem `porque` e `link`.

**`erp-novo/app/Http/Controllers/Api/SuperAdmin/RevendaController.php`** — `store()` → 201 `{data}`.

**`erp-novo/app/Http/Requests/SuperAdmin/ProvisionarRevendaRequest.php`** — validação completa:
- `razao_social`, `dono_nome` e `dono_email` são obrigatórios; o e-mail precisa ser `email:rfc` e `unique:users,email`, e é normalizado para minúsculas;
- o CNPJ é canonizado e o dígito verificador é conferido;
- `plano_id` e `trial_dias` (0–365) são opcionais.

### Backend (alterados)

- **`erp-novo/routes/api.php`** — três rotas:
  - `POST superadmin/revendas`, no grupo `auth:platform` + `plataforma.sessao` + `plataforma.owner`;
  - `POST /definir-senha`, pública, com `throttle:login`;
  - `GET admin/dashboard/pronto-para-vender`.
- **`erp-novo/database/api-manifest.json`** — regenerado (`php artisan api:manifest`, 622 endpoints).
- **`erp-novo/app/Http/Controllers/Api/AuthController.php`** — `definirSenha()`. Usa `Password::broker('convites')->reset()`, com a regra do `PasswordPolicyService` e `confirmed`. Convite inválido e e-mail desconhecido dão a mesma resposta 422, para não permitir enumerar e-mails.
- **`erp-novo/app/Http/Controllers/Api/Admin/RelatorioController.php`** — `prontoParaVender()`, com `empresa.view`.
- **`erp-novo/database/seeders/RbacSeeder.php`** — novo método público `papeisPadraoDoGrupo()`, que sincroniza as permissões e cria os papéis de um grupo. Assim há uma só fonte da regra de papéis.
- **`erp-novo/config/auth.php`** — novo broker `convites`: mesma tabela `password_reset_tokens`, validade `AUTH_CONVITE_EXPIRA_MIN` (padrão 4320 min, 3 dias).
- **`erp-novo/config/superadmin.php`** — novo bloco `provisionamento.plano_padrao` (`SAAS_PROVISIONAMENTO_PLANO`, padrão `essencial`) e `trial_dias` (`SAAS_PROVISIONAMENTO_TRIAL_DIAS`, padrão 14). São só os defaults do formulário.
- **`erp-novo/composer.json`** — `ProvisionamentoRevendaSobRlsTest` entrou no `test:pgsql-rls`.

### SPA

- **`frontend/src/features/superadmin/api.ts`** — `useSaProvisionarRevenda`, os tipos `SaNovaRevenda`/`SaRevendaCriada` e `linkDoConvite()` (origem + `BASE_URL` + `definir-senha?email&token`).
- **`frontend/src/features/superadmin/SaEmpresasPage.tsx`**
  - Botão "Nova revenda" na `action` do `ResourceList`.
  - `NovaRevendaDialog`, montado só com componentes de `@/components/ui`. O plano vem só da lista de vendáveis; vazio significa o plano padrão.
  - Depois de criar, o diálogo mostra o link uma vez, com aviso (`role="alert"`) e botão Copiar.
- **`frontend/src/features/auth/DefinirSenhaPage.tsx`** (novo) e a rota pública **`/definir-senha`** em `frontend/src/routes.tsx`.
- **`frontend/src/features/dashboard/ProntoParaVender.tsx`** (novo) — renderizado no Início para quem tem `empresa.view`. Some quando tudo está pronto; se a consulta falha, mostra o erro em vez de dizer "pronto".
- **`frontend/src/features/dashboard/DashboardPage.tsx`** — monta o checklist.

### Testes (novos)

- `erp-novo/tests/Feature/ProvisionamentoRevendaTest.php` (sqlite, 8 testes)
- `erp-novo/tests/Feature/ProvisionamentoRevendaSobRlsTest.php` (portão Postgres, 1 teste de aceite, 29 asserções)
- `frontend/src/features/superadmin/NovaRevendaDialog.test.tsx` (3)
- `frontend/src/features/dashboard/ProntoParaVender.test.tsx` (3)
- `frontend/src/features/auth/DefinirSenhaPage.test.tsx` (4)

## Decisões e por quê

1. **Convite de uso único, não senha temporária.**
   - O código não tem troca obrigatória de senha no primeiro login (procurei `must_change`, `senha_temporaria` e `password_changed_at`: nada). Também não há rota para o usuário trocar a própria senha no painel.
   - Sem esse mecanismo, uma senha temporária viraria a definitiva, e o operador da plataforma a conheceria.
   - O que já existia era o broker de reset do Laravel, com a tabela `password_reset_tokens` (sem RLS, classificada) e o token guardado só como hash. Usei esse broker com um perfil próprio (`convites`, 3 dias).
   - O dono nasce com senha impossível de adivinhar e só a define pelo link. O token volta **só** na resposta do POST e nunca vai para a trilha.
2. **O catálogo é gravado dentro do envelope do tenant novo.** Os models preenchem `tenant_account_id` pelo envelope. Gravar como owner sem envelope reproduz o incidente de 2026-10-11 (dado existe, dono não vê). A prova está abaixo.
3. **Reaproveitar o `TenantMappingImporter`, não duplicá-lo.** As proteções dele (empresa já vinculada, grant operacional sem leitura, ponte de grupo de outro tenant) continuam valendo. O `DB::transaction` interno dele vira savepoint dentro da transação do provisionamento.
4. **A assinatura sai do `SuperAdminService::definirAssinatura`.** Não toquei no model nem na tabela `Assinatura` (escopo da B1). Com isso, a assinatura ganha evento, trilha, invalidação de licença e a regra "plano de transição não se atribui pelo painel" de graça. Essa regra também prova o rollback (ver testes).
5. **Freeze de criação de empresa não se aplica aqui.** O achado P2-A-01 recomenda que o freeze valha só para a criação livre pelo tenant. A criação pela plataforma é auditada e passa pelo importador.
6. **Defaults em config.** Plano e trial vêm do pedido, e só o default fica em `config/superadmin.php`. Plano padrão ausente → 422; nunca cai num plano qualquer.
7. **Checklist com `empresa.view`.** Ele aponta configuração da revenda (fiscal, cercas, condições), coisa de administrador, não de atendente. O link da condição vai a `/configuracoes?tab=financeiro`, para onde `/financeiro/configuracoes` já redireciona.

## Testes: com e sem a correção

| Teste | Com | Sem (correção removida) |
|---|---|---|
| `ProvisionamentoRevendaSobRlsTest` (Pg, aceite) | **1/1 verde**, 29 asserções | Catálogo gravado **sem** o envelope: `GET /api/admin/produtos` do dono volta **vazio** (`Failed asserting that two arrays are equal`, linha 110). É o sintoma da carga de 10/11 |
| `ProvisionamentoRevendaTest` (sqlite) | **8/8**, 77 asserções | 4 remoções feitas ao mesmo tempo, cada uma derrubando o teste dela (5 falhas, 3 verdes) — detalhe abaixo |
| Portão Pg completo (`test:pgsql-rls` + o novo, banco `pie_a1_rls`) | **35 passed**, 557 asserções, 0 skipped | — |
| Lote sqlite relacionado (SuperAdmin, ApiContratoDrift, EscritaCanonica, RbacSeeder, Auth/Login, Dashboard, PlanoContaModelo, TenantMapping) | 29/29 | — |
| Vitest dos 3 arquivos novos | 10/10 | Duas remoções: (a) o checklist não some quando está pronto → `some quando a revenda está pronta` falha; (b) sem o aviso "só uma vez" → `cria a revenda e mostra o link…` falha |
| Vitest SPA inteira | **191/191** (50 arquivos) | — |
| `npx tsc --noEmit` | sem erros | — |
| `node scripts/pie-costura.mjs` | 0 chamadas sem rota; as rotas novas têm consumidor | — |

As 4 remoções do lote sqlite, e o teste que cada uma derrubou:

- **Condições removidas do catálogo:** `revenda nasce completa…` falhou (`0 is identical to 2`), e `checklist…` também (condição `false`).
- **Conferência de e-mail do serviço removida:** `email existente…` deu 201 com o e-mail em outra caixa (a regra `unique` do sqlite diferencia maiúsculas). Só a conferência do serviço segura esse caso.
- **`DB::transaction` removido:** `falha numa etapa desfaz…` deixou linhas órfãs (`two arrays are identical` falhou).
- **Fail-closed de plano removido:** `plano padrao ausente…` deu 500 em vez de 422.

Tudo foi restaurado e reexecutado: 8/8.

Suíte PHP completa (sqlite), rodada no fim e com a árvore já contendo as
alterações da A2: **2123 passed, 0 falhas, 35 skipped** (os testes de
Pg/RLS), 7885 asserções.

## Pendente / anotado para outros

- **Reemitir convite.** Se o link se perder ou vencer, não há rota para gerar outro. Hoje seria preciso um operador da revenda com `usuario.reset`, que não existe numa revenda nova. Sugestão: `POST /superadmin/revendas/{empresa}/convite`, que só gera token novo para o OWNER e é auditado. Fica para a próxima onda (rota nova; nesta onda só eu mexo em rota).
- **Troca de senha pelo próprio usuário no painel** não existe (achado lateral). Quem esquece a senha depende do administrador.
- **B1:** quando a assinatura for para o tenant, trocar a chamada `definirAssinatura($empresa->id, …)` no `TenantProvisioningService` pela versão por tenant. É uma linha só, comentada.
- **Papéis Atendente/Caixa/Logística** (proposta de P2-A-14 na Frente A): não criados. A revenda nasce com os 4 papéis do `RbacSeeder` (Administrador, Gerente, Operador, Entregador), que hoje são a regra do produto.
- **Item "app" do checklist** (citado em P2-A-07): fora do escopo pedido (6 itens). A área de entrega cobre a parte "o app acha a revenda".
- **Papéis da plataforma** (decisão 9): qualquer admin da plataforma autenticado com 2FA pode criar revenda, como já acontece com suspender ou trocar plano.
- Os produtos iniciais nascem com `tipo` INDEFINIDO, como no demo. Classificar recipiente ou conteúdo (F3-02) fica com o dono.
- O A2 usou o `scratchpad/pgenv.sh` compartilhado, e a migration
  `2026_11_20_000100_rls_nas_filhas_de_configuracao_por_grupo` rodou também no
  `pie_a1_rls` (batch 6; ela só acrescenta RLS e policy a `checklist_perguntas` e
  `sorteio_numeros`). Depois disso, reexecutei com um arquivo de ambiente só meu
  (`scratchpad/a1/pgenv_a1.sh`): `ProvisionamentoRevendaSobRlsTest`,
  `SuperAdminSobRlsTest`, `UsuarioNovoSobRlsTest` e `RlsCoberturaTest` deram
  10/10 (421 asserções). O resultado não mudou.
- O banco `pie_a1_rls` foi criado a partir de `erp_teste` e ficou com os dados dos testes, como nos outros testes Pg (as linhas do owner commitam). Pode ser descartado.
