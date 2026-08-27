# Estado atual — transformação SaaS

**Objetivo durável:** ativo  
**Estado:** IMPLEMENTANDO  
**Fase:** F0 — Contenção, inventário vivo e decisões  
**Último microlote concluído:** F0-05.07/08 — PostgreSQL/RLS e reprodutibilidade  
**Microlote parcial:** F0-03 — segredos removidos do código; rotação externa pendente  
**Microlote em andamento:** F0-05A — anel externo de build/deploy  
**Última atualização:** 2026-08-26 10:15 (America/Sao_Paulo)

## Referências obrigatórias

- `docs/01-vigente/PLANO_TRANSFORMACAO_SAAS.md`
- `docs/01-vigente/PROCEDIMENTO_EXECUCAO_CONTINUA_SAAS.md`
- método, Volume 15 e achados listados em F0

## Estado do workspace antes da implementação

Alterações preexistentes que não podem ser incorporadas, sobrescritas ou revertidas sem prova de autoria:

- cinco arquivos modificados de comodato em `erp-novo/app/`;
- migration nova `erp-novo/database/migrations/2026_08_27_000100_comodato_sentido.php`;
- teste novo `erp-novo/tests/Feature/ComodatoSentidoTest.php`;
- `erp-novo/perda.sql`;
- documentos da auditoria e plano ainda não rastreados.

`ctrl-web/` permanece fora do escopo de alteração.

## Atualização de retomada — 2026-08-26 10:45 (America/Sao_Paulo)

- F0-05A foi concluído localmente: ver `F0_05A_ANELEXTERNO_BUILD_DEPLOY.md`.
- O próximo microlote é F0-06, catálogo vivo reexecutável.
- A promoção/rollback remotos continuam pendentes de uma release e ambiente
  autorizados; não são considerados aprovados por inferência.

## Atualização de retomada — 2026-08-26 11:05 (America/Sao_Paulo)

- F0-06 concluído: ver `F0_06_CATALOGO_VIVO.md` e `CATALOGO_VIVO.json`.
- Próximo microlote: F0-07, baseline particionado e classificação explícita de
  falhas/skips antes do gate F0.

## Atualização de retomada — 2026-08-26 11:25 (America/Sao_Paulo)

- F0-07 concluído: suíte integral com 1.287 passes, 3.883 assertions, 8 skips
  e zero falhas; ver `F0_07_BASELINE.md`.
- Gate F0 ainda não aprovado: F0-03 requer rotação/revogação externa real e
  F0-01 requer decisão formal de titularidade/controlador. O ensaio remoto de
  promoção/rollback também permanece pendente.
- Não iniciar F1 até essas pendências serem resolvidas e registradas.

## Atualização de retomada — 2026-08-26 11:35 (America/Sao_Paulo)

- F0-01 ganhou marcação técnica `OWNERSHIP_UNRESOLVED`; ver
  `F0_01_OWNERSHIP_UNRESOLVED.md`. Não houve inferência de titularidade.
- O único bloqueio material do gate F0 é agora F0-03: rotação/revogação externa
  de segredos conhecidos. O ensaio remoto de promoção/rollback também segue
  pendente, sem ser declarado aprovado.
- Enquanto não existir prova externa de rotação, preservar o freeze e não
  iniciar alterações fundacionais de F1.

## Atualizacao de retomada - 2026-08-26 12:05 (America/Sao_Paulo)

- F1-01 concluido como schema sombra; ver `F1_01_SCHEMA_RAIZ.md`.
- Foram criadas contas, memberships, vinculo tenant-empresa, grants e vinculo
  comercial separado, todos vazios e sem backfill legado.
- Proximo microlote: F1-02, classificacao verificavel de 100% do catalogo.
- F0-03 (rotacao externa) e o ensaio remoto continuam pendencias externas;
  eles nao autorizam inferencia, cutover ou declaracao de gate aprovado.

## Atualizacao de retomada - 2026-08-26 12:15 (America/Sao_Paulo)

- F1-02 ganhou o validador fail-closed e o manifesto inicialmente vazio; ver
  `F1_02_CLASSIFICACAO_TABELAS.md`.
- A classificacao de 100% ainda nao foi declarada: o proximo lote deve decidir
  tabelas por dominio a partir do catalogo PostgreSQL e do codigo consumidor,
  preenchendo owner e justificativa verificaveis.

## Atualizacao de retomada - 2026-08-26 12:45 (America/Sao_Paulo)

- F1-02 concluido e confirmado no PostgreSQL descartavel: o manifesto cobre
  100% das tabelas do catalogo efetivo; ver `F1_02_CLASSIFICACAO_TABELAS.md`.
- A prova corrigiu migrations que deixavam transacao PostgreSQL abortada ao
  tentar GRANT para `erp_app` inexistente. A role agora e verificada antes do
  GRANT, preservando o comportamento de producao e a reproducibilidade local.
- Proximo microlote: F1-03, expansao aditiva de chaves por agregados, sem
  backfill inferido e sem cutover de runtime.

## Atualizacao de retomada - 2026-08-26 13:15 (America/Sao_Paulo)

- F1-03 iniciou a expansao aditiva nos grants; F1-04/F1-05 receberam
  `TenantEnvelope` e resolver fail-closed. Ver `F1_03_05_CHAVES_ENVELOPE_FAIL_CLOSED.md`.
- O middleware legado permanece sem switch por decisao explicita: nao existem
  ainda vinculos de titularidade aprovados para as empresas atuais. O proximo
  passo seguro e expandir chaves por agregados, nao simular o mapeamento.

## Atualizacao de retomada - 2026-08-26 13:35 (America/Sao_Paulo)

- F1-03 expandiu `tenant_account_id` para todas as 151 tabelas COMPANY, com FK
  e indice, ainda nullable e sem backfill. O teste do manifesto conferiu todas
  as colunas (155 assertions no arquivo de fronteira).
- O proximo microlote desbloqueado e F1-06: funcoes SQL canonicas e policies
  sombra baseadas no TenantEnvelope; elas nao substituirao as policies legadas
  antes da conversao aprovada de F1-10.

## Atualizacao de retomada - 2026-08-26 14:00 (America/Sao_Paulo)

- F1-06 criou e provou as funcoes RLS canonicas em PostgreSQL descartavel; sem
  contexto, leitura e operacao retornaram negadas. Ver `F1_06_RLS_CANONICA.md`.
- F1-07 e o proximo microlote: jobs precisam carregar/validar/limpar o novo
  envelope, substituindo o no-op legado de `TenantAwareJob`.

## Atualizacao de retomada - 2026-08-26 14:25 (America/Sao_Paulo)

- F1-07 recebeu runtime/trait de envelope com limpeza em finally; F1-08 recebeu
  trigger PostgreSQL que recusou grant cruzado em prova real; F1-09 recebeu
  staging catalogado com TTL e purge. Ver `F1_07_09_JOBS_STAGING_INTEGRIDADE.md`.
- Proximo microlote: F1-10, importador de mapeamento aprovado. Nenhuma empresa
  existente sera vinculada antes de existir evidencia documental externa.

## Atualizacao de retomada - 2026-08-26 14:45 (America/Sao_Paulo)

- F1-10 ganhou `saas:tenant:importar`: preview por padrão e apply somente para
  JSON documental completo. Ver `F1_10_IMPORTACAO_TITULARIDADE.md`.
- A dependencia externa restante para o switch é a decisao jurídica/arquivo de
  titularidade das empresas. Até recebê-la, o workspace segue com schema,
  envelope, RLS sombra, integridade, staging e importador preparados, sem
  promoção fraudulenta de `grupo_id` a tenant.

## Atualizacao de retomada - 2026-08-26 15:00 (America/Sao_Paulo)

- O middleware de cutover HTTP foi preparado como alias `tenant.saas` e passou
  no teste; ele não foi anexado às rotas antes de F1-10 aplicar mapping aprovado.
- O próximo passo externo exato continua sendo fornecer o JSON documental para
  `saas:tenant:importar` e revisar o dry-run antes de `--apply`.

## Checkpoint bloqueado - 2026-08-26 15:10 (America/Sao_Paulo)

- Todas as frentes locais seguras de F1 foram preparadas e testadas: schema,
  classificação, chaves, envelope, resolver, funções RLS, integridade, staging,
  importador e middleware de cutover.
- O bloqueio repetido é externo: falta o mapeamento jurídico/documental que
  vincula cada empresa Dubena a seu controlador. Não existe fonte local lícita
  para substituir essa decisão por `grupo_id`, CNPJ, usuário padrão ou maioria
  de dados.
- Retomada exata: fornecer o JSON documentado para
  `php artisan saas:tenant:importar <arquivo>`, executar o dry-run, revisar os
  totais, autorizar `--apply`, habilitar `tenant.saas`, converter os jobs que
  ainda usam IDs legados e recertificar o gate F1 em PostgreSQL/runtime role.

## Atualizacao de retomada - 2026-08-27 (America/Sao_Paulo)

- O schema de fronteira F1 e a protecao RLS de `tenant_companies` e
  `tenant_company_grants` foram versionados e aplicados na copia de homologacao.
- O mapping documental Dubena foi validado em dry-run e aplicado uma unica vez:
  1 tenant, 11 empresas, 1 membership OWNER para Vilso (user 3) e 11 grants.
  A empresa de teste ficou fora do mapping. Nao reaplicar o importador.
- `tenant.saas` permanece somente como alias: nao foi associado a rotas, pois os
  jobs legados e a recertificacao do gate F1 ainda nao foram concluidos.
- Duas tabelas observadas na copia (`_bkp_autocadastro_20260820` e
  `tenant_staging_artifacts`) foram classificadas como STAGING. O catalogo vivo
  foi corrigido no commit `3f1dcc8`, enviado para `main`; o teste focal do
  manifesto passou (3 testes, 4 assertions). A CI desse commit deve concluir
  antes de qualquer verificacao remota dependente dele.
- Proximo microlote: confirmar a CI/deploy de `3f1dcc8`, executar
  `saas:f1:pre-cutover-check` com `pgsql_owner` na copia e, em seguida,
  inventariar e converter os jobs legados que ainda carregam IDs sem
  TenantEnvelope. O gate F1 continua aberto.

## Atualizacao de retomada - 2026-08-27 (pre-check remoto)

- O commit `88b45fd` foi implantado em homologacao. O comando somente-leitura
  `saas:f1:pre-cutover-check --connection=pgsql_owner` retornou exit 0.
- A verificacao comprovou schema e funcoes estruturais da fronteira aprovada.
  Uma empresa legada sem `TenantCompany APPROVED` foi apenas reportada como
  fora da fronteira SaaS; ela nao foi classificada, vinculada ou tratada por
  regra especifica do produto.
- O proximo microlote de F1 e converter os jobs legados que ainda recebem IDs
  de empresa sem serializar e restaurar `TenantEnvelope`.

## Atualizacao de retomada - 2026-08-27 (memberships da copia)

- Na copia de homologacao, o mapping conservador baseado em `empresa_user` foi
  validado em dry-run (80 memberships e 141 grants) e aplicado pelo comando
  versionado `saas:tenant:importar-memberships`.
- A verificacao posterior encontrou 81 memberships e 152 grants no total,
  incluindo Vilso como OWNER; nenhum grant aponta para TenantCompany fora de
  status APPROVED. O pre-check remoto voltou a retornar exit 0.
- Esse mapping e adaptador da copia Dubena, nao regra do kernel SaaS. A empresa
  legada nao vinculada permanece fora da fronteira e o resolver deve nega-la.

## Limite/janela

- Em 2026-08-25 16:39 (America/Sao_Paulo), três subagentes de implementação
  retornaram o limite real `try again at 9:26 PM`. Não reenviar tarefas a
  subagentes antes de **2026-08-25 21:26 America/Sao_Paulo**.
- A causa operacional observada foi duplicação de contexto e releitura ampla.
  O procedimento agora impõe modo serial econômico, sem força-tarefa por padrão.
- Última consulta do objetivo: 1.982.225 tokens acumulados e 6.971 segundos de execução; saldo restante não exposto (`remainingTokens: null`).
- Em 2026-08-25 12:48 (America/Sao_Paulo), dois subagentes receberam do serviço o limite real: `try again at 4:17 PM`.
- `resume_after` verificável para capacidade de subagentes: **2026-08-25 16:17 America/Sao_Paulo**.
- A sessão principal permaneceu operacional e continua o trabalho localmente; não há razão para esperar ocioso.
- Se a execução principal também for interrompida, retomar após 16:17 lendo este arquivo. Não criar outro temporizador sem nova informação real.

## Próximo passo exato

1. não repetir os cross-scans: seus relatórios finais já confirmaram os riscos e são a fonte de entrada do microlote;
2. INF-01–09 foram aprovados; executar F0-05A sobre workflow, TLS/proxy, migrations, health e vulnerabilidades sem reconstruir os artefatos já validados;
3. usar somente leitura integral dos arquivos diretamente alterados e testes focais; não executar nova suíte integral antes do gate F0-04;
4. manter como baseline formal da suíte integral: 1.258 passes, 7 skips e 5 falhas, sendo quatro de comodato e uma expectativa antiga de ETL; esta última foi corrigida e passou isoladamente (1 teste/2 assertions);
5. iniciar F0-05 (infraestrutura fail-closed) somente depois do gate documental do F0-04;
6. manter T-02.05 pendente até uma execução PostgreSQL real com role restrita.

## Atualizacao de retomada - 2026-08-26 15:25 (America/Sao_Paulo)

- Foi criado o portao somente-leitura `saas:f1:pre-cutover-check`; ele exige
  PostgreSQL efetivo e falha fechado quando faltam funcoes RLS, chaves COMPANY,
  TenantCompany APPROVED ou ownership aprovado. Ele nao habilita rotas nem
  certifica o gate F1 completo.
- As pendencias, a sequencia documental e a lista de conversoes posteriores
  estao consolidadas em `MEMORIA_RETOMADA_F1.md`, para que a retomada nao
  dependa da memoria da conversa.
- Proximo trabalho seguro: testar o novo portao e manter a preparacao local;
  o switch e a conversao dos jobs de negocio continuam dependentes do mapping
  juridico/documental aprovado.

### Validacao do microlote

- Testes focais `SaasF1PreCutoverCheckTest`, `TenantMappingImporterTest` e
  `TenantBoundarySchemaTest`: 8 testes/168 assertions aprovados.
- Pint e `git diff --check` dos arquivos do microlote aprovados.

## Atualizacao de retomada - 2026-08-26 16:05 (America/Sao_Paulo)

- Analise somente-leitura da VPS confirmou a topologia `erpnovo` em
  homologacao e que a migration raiz de tenant ainda nao esta no banco remoto.
  Nenhum deploy, dado ou configuracao remota foi alterado.
- A decisao operacional fornecida pelo usuario foi registrada como proposta de
  dry-run: Grupo Dubena (11 empresas) vira um unico TenantAccount; Grupo Padrao
  (empresa 139) continua fora por ser teste; Vilso (user 3) recebe papel OWNER
  e grants explicitos. Ver `ANALISE_VPS_DUBENA_2026-08-26.md` e
  `mapeamentos/DUBENA_VPS_2026-08-26_PROPOSTA.json`.
- JSON validado: 1 tenant, 11 empresas, 1 membership e 11 grants. O proximo
  passo de escrita continua condicionado ao deploy versionado das migrations
  para `main`, seguido de dry-run e revisao do importador no banco alvo.

## Testes executados neste checkpoint

F0-01/F0-02: 32 testes aprovados, 94 assertions, zero falha. Ver `F0_01_02_DECISOES_E_FREEZE.md`.

F0-03: AST Python aprovado; 1 teste/11 assertions aprovado. Ver `F0_03_SEGREDOS.md`.

F0-04A: 85 testes passaram na execução conjunta; a única falha era o nome incorreto de um enum no novo assert. Após correção, o arquivo afetado passou com 7 testes/12 assertions. Ver `F0_04A_PIX_E_PAGAMENTO_FAIL_CLOSED.md`.

F0-04B: 36 testes/99 assertions aprovados. Ver `F0_04B_CONFIG_E_PABX.md`.

F0-04C: 20 testes/56 assertions aprovados. Ver `F0_04C_LOGINS_E_CAIXA.md`.

F0-04D: 42 testes/91 assertions aprovados. Ver `F0_04D_PORTA_CAIXA_OWNER.md`.

F0-04E: 31 testes/84 assertions aprovados. Ver `F0_04E_FINANCEIRO_E_CNAB_OWNER.md`.

F0-04F: 140 testes/333 assertions aprovados. Ver `F0_04F_ESTOQUE_LOGISTICA_CHEQUE_EXTRATO.md`.

F0-04G: 30 testes/110 assertions aprovados após correção dos consumidores atingidos. A suíte integral registrou 1.240 passes, 5 skips PostgreSQL e 4 falhas finais, todas no baseline conhecido de comodato. Ver `F0_04G_FISCAL_FAIL_CLOSED.md`.

F0-04H: 39 testes/106 assertions na validação dirigida e 83 testes/237 assertions na validação ampliada, sem falhas. Ver `F0_04H_LICENCA_ABAC_CUSTO.md`.

F0-04I: 64 testes/152 assertions na validação dirigida e 135 testes/329 assertions na validação ampliada, sem falhas. Ver `F0_04I_COBRANCA_E_BAIXA_UNICA.md`.

F0-04J: 42 testes/116 assertions na validação ampliada, 5 skips exclusivamente PostgreSQL/RLS, além da prova isolada de falha parcial com 1 teste/4 assertions. Ver `F0_04J_ETL_CUTOVER_FAIL_CLOSED.md`.

F0-04K: 21 testes/48 assertions aprovados; Pint e `git diff --check` aprovados. Ver `F0_04K_PIX_FAKE_PRODUCAO.md`.

F0-04L: 33 testes/123 assertions aprovados; Pint e `git diff --check` aprovados. Ver `F0_04L_EMPRESA_ALVO_IDOR.md`.

F0-04M: 29 testes/93 assertions aprovados; Pint e `git diff --check` aprovados. Ver `F0_04M_CUSTO_ESTOQUE.md`.

F0-04N: 26 testes/107 assertions aprovados; Pint e `git diff --check` aprovados. Ver `F0_04N_CUSTO_AUDITORIA.md`.

F0-04O1: 23 testes/64 assertions aprovados; Pint e `git diff --check` aprovados. Ver `F0_04O1_CUSTO_COMODATO.md`.

F0-04O2: 12 testes/192 assertions aprovados; Pint e `git diff --check` aprovados. Ver `F0_04O2_CUSTO_SPED.md`.

F0-04O3: 11 testes/50 assertions aprovados; Pint e `git diff --check` aprovados. Ver `F0_04O3_CUSTO_NF_ENTRADA.md`.

F0-04P: 22 testes/112 assertions no conjunto focal final; `RelatorioTest` completo também aprovado; Pint e `git diff --check` aprovados. Ver `F0_04P_EMPRESA_ATIVA.md` e `F0_04_GATE_CONTENCOES.md`.

F0-05.01/02: targets frontend/runtime/web construídos; conteúdo obrigatório e `php artisan about` aprovados; Nginx válido; manifesto de `public/` idêntico entre app/web. Ver `F0_05_01_02_IMAGEM_PUBLIC.md`.

F0-05.03/04/05: entrypoint e Compose fail-closed aprovados; Redis real negou anônimo e aceitou autenticado; segredo não apareceu no render. Ver `F0_05_03_05_AMBIENTE_APPKEY_REDIS.md`.

F0-05.06/09: Reverb incompleto bloqueado; contrato completo aprovado; OPcache `Off` em produção e `On` em homologação. Ver `F0_05_06_09_REVERB_OPCACHE.md`.

F0-05.07/08: PostgreSQL real com role runtime aprovou 6 testes/346 assertions e zero skip; contexto ausente passou a negar; bases/imagens/promoção/rollback usam digest e SBOM foi gerado. Ver `F0_05_07_08_POSTGRESQL_RLS_E_REPRODUTIBILIDADE.md`.

## Atualizacao de retomada - 2026-08-27 (RLS canonica COMPANY)

- O microlote F1-06/F1-10 iniciou a conversao efetiva das policies somente nas
  tabelas COMPANY que possuem `empresa_id`. A migration
  `2026_08_29_000800_backfill_and_protect_company_tenant_keys.php` preenche
  `tenant_account_id` exclusivamente pelo `TenantCompany APPROVED`, recusa
  divergencia preexistente e instala policy canonica de leitura/escrita.
- Prova em PostgreSQL 16 descartavel: migration aplicada com `pgsql_owner`;
  `RlsCoberturaTest` com a role `erp_app` aprovou 6 testes/350 assertions, sem
  skip. O ensaio de backfill confirmou que uma linha sem chave recebeu o tenant
  exatamente do vinculo aprovado.
- A role runtime da homologacao foi conferida somente-leitura: `erp_app`,
  `rolsuper=false`, `rolbypassrls=false`. App e worker estao no release
  `15e247a`; o pre-cutover remoto continua aprovado, mas ele nao substitui o
  gate F1 completo.
- Arquivo preexistente intocavel: `erp-novo/perda.sql`.
- Proximo microlote: tratar as tabelas COMPANY sem `empresa_id` sem inferir
  tenant por `grupo_id`, e converter os jobs cron/ETL que hoje nao tem ator
  tenant declarado. Nao declarar F1 concluida antes dessa recertificacao.

## Atualizacao de retomada - 2026-08-27 (jobs sem envelope)

- O `PushService` deixou de promover chamadas de console a job de plataforma
  automaticamente. Opt-out de plataforma agora precisa ser declarado pelo
  chamador, e nunca e inferido pelo ambiente de execucao.
- `notify:alertas` falha fechado quando o enforcement SaaS esta ativo: o cron
  nao pode escolher um usuario/membership em nome da empresa nem ler dados de
  negocio sem envelope. A identidade de automacao e seus grants continuam uma
  decisao explicita do proximo recorte.
- Testes focais aprovados: `RelatorioTest` (2 testes/3 assertions) e
  `PushAssincronoTest` (4 testes/7 assertions). `erp-novo/perda.sql` segue
  preexistente e intocado.

## Atualizacao de retomada - 2026-08-27 (configuracao group-scoped)

- `pedidosituacoes`, `pedidooperacoes` e `pedido_motivos_atraso` receberam
  ponte documental `tenant_legacy_group_scopes`: ela e unica por grupo, exige
  evidencia e uma empresa ja aprovada naquele tenant. Nenhum grupo da copia foi
  promovido automaticamente a tenant.
- A migration de deploy cria somente a ponte/funcoes. O comando explicito
  `saas:tenant:proteger-configuracao-grupo --apply` preenche chaves e instala
  RLS apenas se nao restar nenhuma linha sem ponte documental. Novas escritas
  leem a chave somente do `TenantEnvelopeRuntime` ativo. Ver
  `F1_10_CONFIGURACAO_GRUPO_EXPLICITA.md`.
- Validacao local: 18 testes/193 assertions aprovados; `git diff --check`
  aprovado. A prova PostgreSQL/runtime role e o preview documental em
  homologacao permanecem pendentes antes de declarar este recorte concluido.
- Proximo microlote: repetir esse padrao para os demais COMPANY sem
  `empresa_id`, agrupados por agregado e somente apos leitura de seus modelos,
  migrations e consumidores diretos. `erp-novo/perda.sql` continua preexistente
  e intocado.

## Atualizacao de retomada - 2026-08-27 (gate PostgreSQL recuperado)

- A CI `test-postgres` falhava antes do recorte novo porque a migration
  `2026_06_27_000200_f1_monitora_tipos_e_campos` tentava recriar
  `monitora_veiculo_tipos`, ja presente no schema inicial. A migration agora
  reconhece a tabela existente; teste de regressao adicionado.
- `tenant_legacy_group_scopes` recebeu policy propria com `ENABLE + FORCE`:
  runtime le apenas sua conta e `WITH CHECK (false)` nega escrita. O importador
  documental continua usando `pgsql_owner`.
- Prova em PostgreSQL 15 descartavel, banco criado do zero: todas as migrations
  aplicadas; `RlsCoberturaTest --fail-on-skipped` aprovou 6 testes/352
  assertions. Role `erp_app`: `rolsuper=false`, `rolbypassrls=false`.
- Proximo passo: push do reparo, acompanhar CI e somente apos deploy executar
  qualquer preview documental em homologacao. `erp-novo/perda.sql` segue fora
  dos commits.

## Atualizacao de retomada - 2026-08-27 (deploy F1 group-scoped)

- CI e deploy de homologacao aprovados no SHA `4f4390c`
  (`fix(ci): make postgres migration chain idempotent`).
- A release criou somente a estrutura, funcoes e policy da ponte. Ela nao criou
  `tenant_legacy_group_scopes`, nao fez backfill e nao substituiu as policies
  das configuracoes legadas: essas acoes continuam condicionadas ao JSON
  documental e ao comando explicito de protecao.
- Proximo microlote seguro: inventariar o agregado `CadastroApoio` restante e
  separar cadastros de plataforma daqueles que realmente precisam da ponte
  documental, sem promover `grupo_id` ou dados Dubena a regra SaaS.

## Atualizacao de retomada - 2026-08-27 (apoio operacional por grupo)

- O protetor documental agora tambem cobre `motivos_nao_venda`,
  `clientecontatotipos` e `clientecontatosituacoes`. Os modelos gravam
  `tenant_account_id` somente a partir do envelope ativo.
- Estes tres cadastros configuram pedido/atendimento e possuem FKs diretas de
  negocio; continuam sem conversao automatica. O comando explicito recusa a
  protecao se qualquer linha nao possuir ponte documental aprovada.
- Validacao local: `LegacyGroupConfigurationMigrationTest`,
  `TenantMappingImporterTest` e `MotivosPedidoTest`: 12 testes/39 assertions
  aprovados. Proximo passo: CI/deploy desta extensao e depois o proximo grupo
  de cadastros COMPANY sem `empresa_id`.
