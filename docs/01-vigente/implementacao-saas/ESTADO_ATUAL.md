# Estado atual — transformação SaaS

**Objetivo durável:** ativo  
**Estado:** IMPLEMENTANDO  
**Fase:** F1 — **CONCLUÍDA** (gate aprovado em homologação; ver `F1_16_GATE_APROVADO.md`)  
**Último microlote concluído:** F1-16 — gate F1 aprovado com role de runtime sobre dados reais  
**Próxima fase:** F2 — ainda não iniciada  
**Pendência externa herdada:** F0-03 — rotação/revogação externa de segredos  
**Última atualização:** 2026-08-29 (America/Sao_Paulo)

> ⚠️ `tenant.saas` continua **fora das rotas** e `SAAS_ENFORCE_TENANT_ENVELOPE`
> segue `false`. F1 entrega a fronteira **provada**; ligar o enforcement é
> decisão de cutover, não consequência automática do gate.

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

## Atualizacao de retomada - 2026-08-27 (financeiro RH frota group-scoped)

- O protetor documental tambem cobre `contamovimentotipos`, `cargos` e
  `veiculo_tipos`. Os dois modelos que escrevem `cargos` e os modelos de
  movimento/frota preenchem a chave somente pelo envelope ativo.
- Nenhuma linha foi convertida por `grupo_id`; sem bridge documental aprovada o
  comando recusa o backfill/policy.
- Validacao local: 17 testes/86 assertions em `CadastroApoioRhTest`, gates de
  frota, `F15MigratorsTest` e o contrato da migration. Proximo passo: commit,
  CI/deploy e continuar pelos demais COMPANY sem `empresa_id`.

## Atualizacao de retomada - 2026-08-27 (CRM Monitora group-scoped)

- `promocoes` e `monitora_veiculo_tipos` foram incluidos no protetor
  documental; suas escritas passam a receber a chave apenas do envelope ativo.
- `config_globais` permanece fora da ponte F1 por decisao explicita: seus
  segredos e fallback de provedores pertencem a `IntegrationAccount` na F6.
- Validacao local: 41 testes/129 assertions em CRM, Monitora e migradores.
  Proximo passo: commit/CI/deploy deste recorte; nenhuma configuracao existente
  sera reatribuida sem JSON documental.

## Atualizacao de retomada - 2026-08-27 (grafos CRM pai-filho)

- `checklists`/`checklist_perguntas` e `sorteios`/`sorteio_numeros` receberam
  chave tenant no pai e propagacao verificavel aos filhos. O protetor recusa
  filho sem pai, tenant ausente ou tenant divergente antes de instalar RLS.
- As policies dos filhos consultam o pai e exigem a mesma permissao canonica de
  grupo; criacao de filho obtem a chave exclusivamente do pai ja protegido.
- Validacao local: `CrmTest` e contrato da migration, 7 testes/41 assertions.
  Proximo passo: commit/CI/deploy; `perda.sql` segue preexistente e intocado.

## Atualizacao de retomada - 2026-08-27 (condicoes de pagamento pai-filho)

- `condicaopagamentos` e `condicaopagamento_parcelas` passaram a usar a ponte
  documental com propagacao/checagem de tenant pelo pai; divergencia bloqueia a
  ativacao RLS.
- Validacao local: 8 testes/38 assertions em condicoes e FKs de migracao.
  Proximo passo: CI/deploy deste microlote; `perda.sql` permanece intocado.

## Atualizacao de retomada - 2026-08-27 (hierarquia financeira)

- `centros_custo` e `planos_conta` entram na ponte documental; a protecao
  recusa qualquer `pai_id` cuja chave tenant divirja da chave do filho.
- Validacao local: 10 testes/64 assertions em financeiro, relatorios e contrato
  da migration. Proximo passo: CI/deploy; nenhum dado da copia foi usado como
  regra de titularidade.

## Atualizacao de retomada - 2026-08-27 (constraint estrutural da hierarquia financeira)

- CI e deploy de homologacao do SHA `f6fc337` foram aprovados. A protecao de
  `centros_custo` e `planos_conta` continua condicionada a ponte documental;
  nenhum backfill remoto foi executado.
- A migration seguinte completa F1-08: adiciona de forma aditiva a chave que
  faltava em `planos_conta` e instala trigger PostgreSQL que recusa `pai_id`
  entre tenants distintos em ambas as arvores. Ela nao atribui tenant, nao muda
  `grupo_id` e nao ativa RLS antes do comando documental.
- Prova PostgreSQL 15 descartavel: cadeia de migrations aplicada; dois tenants
  criados; os dois cruzamentos foram recusados pelo trigger. A unica
  compatibilidade permitida e pai/filho ambos sem chave SaaS antes da conversao.
  A ponte documental tambem ganhou guarda idempotente para retomada apos
  interrupcao parcial. Testes focais: 10 testes/69 assertions aprovados.
- Proximo microlote: mapear as FKs de configuracao financeira que saem de
  `financeiros`/`financeirorateios` para `planos_conta` e `centros_custo`,
  escolhendo constraint composta ou validacao estrutural sem usar dados da copia
  como regra. `erp-novo/perda.sql` segue preexistente e intocado.

## Atualizacao de retomada - 2026-08-27 (propagacao de chave financeira)

- `BelongsToTenant` passou a preencher `tenant_account_id` somente do envelope
  ativo ou, em ETL/seed sem envelope, do pai explicitamente declarado. Nunca
  aceita a chave vinda do payload.
- `financeiros`, `financeiroparcelas` e `financeirorateios` declararam a chave
  para participar dessa propagacao. Parcelas e rateios so podem herdar do
  lancamento pai; a proxima constraint de FK podera confiar nessa chave.
- Validacao local: `FinanceiroTest`, `TenantEnvelopeRuntimeTest` e
  `ResolveTenantEnvelopeMiddlewareTest`: 9 testes/28 assertions aprovados.
- Proximo microlote: instalar e provar em PostgreSQL a validacao estrutural das
  FKs financeiras para plano/centro de custo, sem executar backfill remoto.

## Atualizacao de retomada - 2026-08-27 (FKs financeiras estruturais)

- Trigger PostgreSQL passou a validar `planoconta_id` e `centrocusto_id` de
  `financeiros` e `financeirorateios`. Quando a configuracao ja possui chave
  SaaS, a linha financeira precisa ter a mesma chave; referencia cruzada e
  recusada no banco.
- Prova PostgreSQL 15 descartavel: cadeia completa aplicada, insercao cruzada
  recusada tanto para lancamento quanto para rateio. Nenhum dado remoto foi
  preenchido ou reatribuido.
- Proximo microlote: revisar os demais consumidores de configuracao financeira
  armazenados em `empresa_configs.dados`, que exigem conversao tipada posterior
  e nao podem ser inferidos a partir da copia.

## Atualizacao de retomada - 2026-08-28 (recertificacao da cobertura RLS)

- O item 2 do gate F1 (recertificar todas as tabelas classificadas) foi
  executado e **reprovou**: `saas:f1:pre-cutover-check` conferia apenas a
  existencia da coluna `tenant_account_id`, nao a policy. Em PostgreSQL real,
  150 tabelas tinham a chave e so 117 tinham policy canonica.
- Cinco tabelas COMPANY nao eram alcancadas por nenhuma conversao:
  `sequencias`, `produto_operacao_fiscal` e `convenio_fechamento_pedidos`
  estavam com `rls=false` (sem policy alguma); `transportadoras` e
  `malha_fiscal` seguiam na policy legada por `grupo_id`. O portao aprovava
  esse banco com exit 0.
- `sequencias` guarda a numeracao fiscal. Prova com a role `erp_app` sem
  contexto: leu e sobrescreveu o contador de outra empresa. Corrigido com
  `empresa_id` real derivado dos dois formatos de chave existentes + policy
  canonica; a mesma prova depois retorna 0 linhas e `UPDATE 0`.
- O portao passou a exigir policy canonica ativa com RLS forcada. A ponte
  documental cobre 18 tabelas (eram 16) e ganhou protecao de filho por pai
  escopado por empresa. `NumeroSequencialService` deixou de falhar em silencio
  sob RLS. Ver `F1_11_COBERTURA_RLS_RECERTIFICADA.md`.
- Validacao: suite integral **1.328 passes, 4.176 assertions, 8 skips, zero
  falhas**; `RlsCoberturaTest` com role runtime e `--fail-on-skipped` em
  6 testes/354 assertions sem skip; Pint e `git diff --check` aprovados.
- Achado registrado sem corrigir: `operacoes_fiscais` esta classificada
  PLATFORM mas possui `grupo_id`. Mudar classe e decisao de desenho.
- Continuam abertos no gate F1: execucao em homologacao com role de runtime,
  jobs/eventos/WebSockets sem envelope, demais grafos pai-filho e o registro de
  rollback/snapshot de grants. `erp-novo/perda.sql` segue intocado.

## Atualizacao de retomada - 2026-08-28 (jobs, eventos e WebSockets)

- Item 3 do gate F1 executado. Dos 6 jobs, 4 ja estavam convertidos; o
  `ExecutarMigracaoJob` esta correto como job de plataforma declarado; faltava o
  `NotificarEstoqueBaixoJob`, que lia dados de negocio sem fronteira alguma.
- O `empresaId` que viaja no payload do job nao e credencial: quem enfileira
  escolhe o numero. O job passou a conferi-lo com `requireOperation` antes de
  ler estoque e usuarios.
- Achado do tempo real: `routes/channels.php` autorizava so pelo modelo legado
  (`podeAcessarEmpresa`, que aceita `empresa_user` e devolve `true` para
  `support`). Sem uma referencia sequer ao envelope. Com enforcement ligado, um
  usuario sem grant continuaria entrando no canal ao vivo de outro tenant — o
  dado nao vazaria pela RLS, vazaria pelo WebSocket.
- Os canais de pedido tambem filtravam por `$user->empresa_id`, a empresa padrao
  do usuario, ignorando o multi-empresa. A empresa passou a vir do PEDIDO e a
  ser validada contra o grant.
- Validacao: suite integral **1.331 passes, 4.196 assertions, 8 skips, zero
  falhas**; `TempoRealTest` 6 testes/19 assertions (o caso novo recusa ate
  usuario `support`); `JobsTratamentoFalhaTest` 9 testes/48 assertions, com
  varredura que reprova job novo sem envelope. Ver
  `F1_12_JOBS_EVENTOS_WEBSOCKETS.md`.
- Continuam abertos: itens 4 (demais grafos pai-filho), 5 (rollback/snapshot de
  grants) e 1 (execucao em homologacao com role de runtime).

## Atualizacao de retomada - 2026-08-28 (grafos pai-filho)

- Item 4 do gate F1 medido por `pg_constraint`: 175 FKs ligam duas tabelas com
  chave SaaS, mas 168 tem `empresa_id` no proprio filho — a policy canonica ja
  valida a linha na escrita, entao apontar para pai de outro tenant nao ajuda.
  Instalar trigger nessas 168 seria custo e risco sem ganho de isolamento.
- Dos 7 filhos sem `empresa_id`, 6 sao grafos ja cobertos pelo protetor. Sobrava
  `sorteio_numeros.cliente_id`, provado cruzando tenants em PostgreSQL: numero
  do tenant 901 amarrado a cliente do tenant 902.
- Corrigido com trigger no padrao do guarda de FK financeira. Depois dela:
  cruzamento recusado em INSERT e UPDATE, mesmo tenant aceito e numero sem dono
  (cliente_id nulo, caso legitimo) aceito.
- Validacao: 138 migrations do zero em PostgreSQL 16; `RlsCoberturaTest` com
  role runtime 6 testes/354 assertions sem skip; suite integral **1.333 passes,
  4.207 assertions, 8 skips, zero falhas**; Pint aprovado. Ver
  `F1_13_GRAFOS_PAI_FILHO.md`.
- Itens 2, 3 e 4 do gate estao fechados. Restam o item 5 (rollback/snapshot de
  grants) e o item 1 (execucao em homologacao com role de runtime, que depende
  de deploy).

## Atualizacao de retomada - 2026-08-28 (snapshot/rollback de grants)

- Item 5 do gate F1 fechado. O importador escreve cinco tabelas de fronteira e
  promove `empresas.ownership_status` como efeito colateral; nada disso era
  reversivel: `migrate:rollback` nao desfaz dados e `tenant_staging_artifacts`
  exige tenant_account_id e tem TTL/purge, que apagaria a evidencia.
- Criado `saas:tenant:snapshot-grants <arquivo> [--restore]`, somente leitura
  por padrao, gravando fora do banco que restaura. Cobre as cinco tabelas MAIS
  o `ownership_status` — sem esse campo o rollback deixaria empresas aprovadas
  sem vinculo, estado que nenhum gate detecta.
- Prova em PostgreSQL 16: decisao errada aplicada (2 vinculos/2 aprovadas),
  `--restore` devolveu 1 vinculo/1 aprovada e a empresa 802 voltou a
  `OWNERSHIP_UNRESOLVED`. Restore com snapshot de outro banco foi recusado.
- Validacao: `SaasSnapshotGrantsTest` 3 testes/13 assertions; suite integral
  **1.336 passes, 4.220 assertions, 8 skips, zero falhas**; Pint aprovado. Ver
  `F1_14_SNAPSHOT_ROLLBACK_GRANTS.md`.
- Itens 2, 3, 4 e 5 do gate F1 estao fechados. Resta o item 1 (execucao em
  homologacao com a role de runtime), que depende de CI/deploy destes commits, e
  so entao o item 6 (declarar F1 concluida).

## Atualizacao de retomada - 2026-08-28 (verificacao em homologacao)

- Acesso SSH autorizado; verificacao somente leitura. Runtime confirmado como
  `erp_app` com `rolsuper=false` e `rolbypassrls=false`: a RLS e exercida de
  verdade na copia.
- As migrations `001600` e `001700` rodaram. A correcao de `sequencias` funcionou
  em 100% dos dados reais: das 14 sequencias (numeracao fiscal da Dubena),
  ZERO ficaram sem `empresa_id` ou sem tenant. Os 20 numeros de sorteio reais
  nao cruzam tenant.
- O gate reprovou (exit 1) por 4 tabelas sem policy canonica; `sequencias` NAO
  esta entre elas. O preview do comando documental na copia esta `ready:true`
  (18 tabelas, 387 linhas, zero pendencia).
- Achado que virou codigo: `produto_operacao_fiscal` e
  `convenio_fechamento_pedidos` estavam com `rls=false` e 0 linhas. Eles recebem
  a coluna numa migration mas a policy so pela conversao documental — entao num
  tenant NOVO nasceriam permanentemente sem RLS. A migration `001800` protege o
  pivot enquanto ele esta vazio e se recusa a tocar tabela com dados.
- Prova local: 139 migrations do zero; pivots com `rls=true`; apos o
  `--apply` do comando documental o `pre-cutover-check` retorna **exit 0**.
  Suite integral **1.337 passes, 4.226 assertions, zero falhas**;
  `RlsCoberturaTest` 6/354 sem skip. Ver `F1_15_VERIFICACAO_HOMOLOGACAO.md`.
- Snapshot de rollback da fronteira real gravado em
  `/opt/dubena-snapshots/f1-pre-apply-20260828-234328.json` (1 tenant, 11
  empresas, 81 memberships, 152 grants).
- PENDENTE: o `--apply` em homologacao nao foi executado (escrita em dados reais
  bloqueada pelo classificador). Com preview `ready:true` e snapshot no lugar, a
  operacao e segura e reversivel; falta a decisao de executa-la, e so entao o
  item 6 (declarar F1 concluida).

## Atualizacao de retomada - 2026-08-28 (deploy 001800 verificado)

- Verificado na homologacao apos o deploy: `produto_operacao_fiscal` e
  `convenio_fechamento_pedidos` passaram a `rls=true force=true` com policy
  CANONICA. `sequencias` idem. O gate caiu de 4 para 2 tabelas pendentes.
- As 2 restantes, `transportadoras` e `malha_fiscal`, estao VAZIAS na copia e
  seguiam na policy legada por `grupo_id`. E a mesma lacuna que a `001800`
  fechou para os pivots: a troca para a policy canonica so viria pela conversao
  documental, entao num tenant NOVO elas ficariam indefinidamente confiando em
  `app.grupo_id` — a barreira fail-open que F1 substitui.
- Criada a migration `001900`, que troca a policy enquanto a tabela esta vazia e
  NAO toca tabela com dados. Provado nos dois sentidos: banco novo -> canonica;
  com 1 linha preexistente -> intocada, decisao segue documental.
- Validacao: 140 migrations do zero; suite integral **1.338 passes, 4.232
  assertions, 8 skips, zero falhas**; `RlsCoberturaTest` 6/354 sem skip; Pint
  aprovado.
- Apos o deploy desta migration, a lista de pendencias do gate em homologacao
  deve ficar vazia, restando apenas o `--apply` do comando documental (preview
  `ready:true`, snapshot ja gravado) para as tabelas group-scoped com dados.

## GATE F1 APROVADO - 2026-08-29 (America/Sao_Paulo)

- `saas:f1:pre-cutover-check --connection=pgsql_owner` retornou **exit 0** na
  homologacao. A unica empresa fora da fronteira e a de teste ("Grupo Padrao"),
  que DEVE ficar fora e e negada pelo resolver.
- O exit 0 agora vale: o portao verifica policy canonica ativa com RLS forcada,
  nao so a coluna. Ele reprovou tres vezes nesta sequencia (4 tabelas -> 2 -> 0).
- Cobertura: 150 tabelas com `tenant_account_id`, **141 com policy canonica +
  FORCE**. As 9 restantes sao excecoes declaradas.
- Prova do item 1 sobre DADOS REAIS, com role `erp_app`
  (`rolsuper=false`, `rolbypassrls=false`):
  - sem contexto -> 0 clientes visiveis (existem 55.453);
  - tenant alheio -> 0 em clientes, pedidos, sequencias e financeiros;
  - UPDATE cruzado -> 0 linhas, conferido pelo owner.
  Com envelope legitimo, a visibilidade bate exatamente com os 11 grants.
- Efeito das migrations: `sequencias` com 14/14 sequencias fiscais preenchidas
  (zero orfa); trigger de sorteio ativo com 20 numeros reais sem cruzamento; os
  2 pivots e as 2 configuracoes vazias com policy canonica.
- **F1 CONCLUIDA.** Itens 1 a 6 fechados. Ver `F1_16_GATE_APROVADO.md`.
- Abertos, sem bloquear F1: 19 tabelas PLATFORM ainda com policy por `grupo_id`
  (catalogos compartilhados — inconsistencia de classificacao, nao de
  isolamento); `operacoes_fiscais` PLATFORM com `grupo_id`; e a decisao de
  ligar `tenant.saas`/enforcement, que e cutover.

## Atualizacao de retomada - 2026-08-29 (credenciais e reclassificacao)

- Motivado pela observacao do operador: cada revenda tem chaves proprias e duas
  concorrentes nao podem ver a da outra.
- FURO PROVADO em `config_globais` (CSRT, senha SMTP, assinatura SAT, chave do
  Maps): a policy ainda era a legada por `app.grupo_id`. Sem envelope nenhum, a
  linha vinha COM a `google_maps_key`, enquanto `clientes` na mesma condicao ja
  retornava zero. E `google_maps_key` era a unica credencial da tabela sem
  `encrypted`/`hidden` — vazou em claro. Corrigido: policy canonica, coluna
  `varchar(120)` -> `text` antes de cifrar, migration idempotente, e
  `googleMapsKey()` exige que o grupo pertenca ao tenant do envelope.
- RECLASSIFICACAO: as 19 tabelas PLATFORM viraram COMPANY group-scoped. Todas
  tem `grupo_id`, unicidade `(grupo_id, descricao)`, model `BelongsToGrupo` e
  sao EDITAVEIS pela revenda; `tipos_documento_veiculo` ja estava duplicado 7+7.
  O PLATFORM de verdade e `municipios_ibge`/`logradouros_oficiais` (publico,
  imutavel, sem `grupo_id`, sem tela). A ponte documental passa a 37 tabelas.
- CORRECAO DE REGISTRO: `tenant.saas` NUNCA esteve fora das rotas — ja estava em
  todas as rotas `auth:sanctum`; o docblock e que estava desatualizado. E
  `SAAS_ENFORCE_TENANT_ENVELOPE=true` JA ESTAVA ativo na homologacao (config
  efetiva `true`). A app responde HTTP 200 nesse modo.
- Validacao: 142 migrations do zero; protetor cobre 37 tabelas;
  `pre-cutover-check` exit 0; suite integral **1.339 passes, 4.254 assertions,
  zero falhas**. Ver `F1_17_CREDENCIAIS_E_RECLASSIFICACAO.md`.
- PENDENCIA ASSUMIDA: com enforcement ligado a suite vai a 566 falhas de 1.346,
  porque as factories criam empresa sem TenantCompany/membership/grant. E divida
  do ambiente de teste, nao da aplicacao — em homologacao 81 dos 82 usuarios tem
  membership, e o unico sem e o admin de teste da empresa 139 (fora da fronteira
  de proposito, `support` deixando de ser bypass). Adaptar as factories e o
  proximo trabalho antes de considerar o modo novo coberto por testes.

## Atualizacao de retomada - 2026-08-29 (suite verde com enforcement)

- Fechada a pendencia de F1-17: com `SAAS_ENFORCE_TENANT_ENVELOPE=true` a suite
  ia a 566 falhas de 1.346. Agora sao **1.339 passes / 4.254 assertions / zero
  falhas nos DOIS modos** (ligado e desligado).
- A adaptacao nao era so divida de teste. Caminhar pelas falhas revelou TRES
  defeitos reais que iriam para producao com o enforcement ligado:
  1. Fila SINCRONA derrubava criacao de pedido pelo app com HTTP 403 ("nao e
     permitido sobrepor TenantEnvelope no mesmo worker"): o job roda dentro do
     request, onde o envelope ja esta ativo. Agora reusa o envelope se for o
     MESMO tenant; tenant diferente segue recusado.
  2. Cliente do app tomava 403 depois de logar: `ClienteAuthService` cria o User
     do cliente sem membership. Corrigido na origem, com grant restrito a
     empresa do proprio cadastro.
  3. Job despachado fora de request (console/seed/ETL) estourava por exigir ator
     autenticado. Criado `captureOrNull()`; a barreira real (`requireOperation`
     no handle) continua intacta.
- Ajuste em `IntegracaoTenant`: a guarda de F1-17 negava a chave do Maps sem
  envelope, o que nao protege de outro tenant (nao ha tenant a comparar) e so
  quebrava trabalho legitimo. Sem envelope agora permite; a protecao desse
  caminho e a RLS canonica instalada em `config_globais`.
- `Database\Factories\Support\FronteiraTenant` monta tenant/vinculo/membership/
  grant via `afterCreating`, entao nenhuma chamada de teste mudou no caso comum.
  Fica em `database/factories` porque `Tests\` so existe em `autoload-dev`.
  Estados `semFronteiraSaas()` atendem os testes que exercitam a AUSENCIA.
- Validacao: 142 migrations do zero; `RlsCoberturaTest` 6/354 sem skip; Pint
  aprovado. Ver `F1_18_SUITE_COM_ENFORCEMENT.md`.

## Atualizacao de retomada - 2026-08-29 (F2-05 break-glass)

- Primeira tarefa da F2. `support` era bypass total de RBAC em QUATRO camadas
  (`Gate::before`, `PolicyEvaluator`, `podeAcessarEmpresa`, `empresasVisiveis`).
  Medido, nao deduzido: a mesma rota devolvia 403 sem o flag e 200 com ele. Na
  copia real ha 12 usuarios ativos assim e `platform_audit_logs` esta ZERADO.
- O acesso virou EVENTO: `break_glass_grants` com alvo, motivo, prazo e autor,
  decidido em ponto unico (`App\Domain\Acesso\BreakGlass`) e concedido por
  `saas:break-glass:conceder`, que exige motivo. Expira sozinho.
- Provado: sem concessao 403; vigente 200; expirada 403; revogada 403; concessao
  de OUTRA empresa 403; uso deixa 1 entrada `break_glass.usado`; modo legado
  preserva o bypass.
- Defeito encontrado no proprio desenho: a primeira versao memorizava a decisao
  por request, o que faria uma revogacao so valer no request seguinte. Agora a
  consulta nao e memorizada; memoriza-se apenas o registro na trilha.
- F2-08 parcial: 96 usos de `'support' => true` em 87 arquivos foram removidos.
  A `UserFactory` passou a conceder PAPEL REAL (`papelAdministrador`) e o estado
  `semPapel()` atende os testes que provam o 403. Progresso das falhas:
  290 -> 69 -> 36 -> 23 -> 15 -> 4 -> 0.
- Validacao: suite integral **1.347 passes / 4.274 assertions / zero falhas nos
  DOIS modos**; Pint aprovado. Ver `F2_05_BREAK_GLASS.md`.
- PENDENTE do F2-05: 2FA e aprovacao para acao critica exigem decisao sobre quem
  aprova e o que e critico; `platform_admins.twofa_confirmado_em` ja existe e
  precisa entrar no desenho.

## Atualizacao de retomada - 2026-08-30 (break-glass fechado: 2FA, aprovacao, anti-replay)

- Fecha a pendencia de F2-05.
- 2FA NO ATO: `--otp` passou a ser obrigatorio e e conferido contra o TOTP do
  proprio usuario elevado. A concessao grava `twofa_verificado_em`, e
  `vigente()` exige o carimbo — linha sem ele nao autoriza, mesmo no prazo.
- ESCOPO: `LEITURA` (default) exige so 2FA; `OPERACAO` exige tambem aprovacao de
  um PlatformAdmin. A concessao OPERACAO nasce inerte e o comando avisa; o
  `saas:break-glass:aprovar` RECUSA o mesmo admin que concedeu — sem isso a
  "segunda assinatura" seria decoracao.
- ANTI-REPLAY (F2-07): achado independente durante a implementacao.
  `Totp::verificar` aceita janela de +-1 passo, entao o MESMO codigo valia ~90
  segundos e nada registrava consumo — era reapresentavel a vontade. A tabela
  `otp_consumidos` fecha isso, e a barreira e a UNICIDADE no banco, nao um
  SELECT antes do INSERT (duas requisicoes simultaneas passariam as duas por uma
  checagem previa). Corrige o login inteiro, nao so o break-glass, porque o
  `VerificadorDoisFatores` ja era ponto unico de web/app/SuperAdmin.
- Cuidado de consistencia: `BreakGlassGrant::vigente()` (PHP) e a consulta em
  `BreakGlass` (SQL) fazem a mesma pergunta e foram atualizadas juntas —
  divergir faria a decisao depender de por onde se pergunta.
- Validacao: `BreakGlassTest` 12 testes/40 assertions; login/2FA/SuperAdmin
  62 testes/197 assertions sem regressao; **144 migrations do zero** em
  PostgreSQL 16 com `RlsCoberturaTest` 6/356 sem skip; suite integral
  **1.351 passes / 4.294 assertions / zero falhas nos DOIS modos**; Pint
  aprovado. Ver `F2_05_BREAK_GLASS.md`.
- F2-05 CONCLUIDO. Restam da F2: F2-01 (manifesto), F2-02/02A, F2-03 (licenca),
  F2-04, F2-06, o restante de F2-07 e de F2-08.

## Atualizacao de retomada - 2026-08-30 (F2-01 permissao declarada por rota)

- O manifesto ja pegava rota removida/nova, mas so sabia QUAIS rotas existem,
  nao O QUE cada uma exige. A diferenca tinha custo real.
- FURO PROVADO: `LookupController` era o unico controller Admin sem `autorizar()`
  em metodo nenhum, e serve 33 lookups — entre eles clientes, produtos, contas,
  colaboradores e usuarios. Com o mesmo usuario sem papel:
  `/api/admin/clientes` -> 403 e `/api/admin/lookups/clientes` -> 200 com os
  clientes. A mesma informacao, pela porta de tras.
- Corrigido: cada lookup declara a permissao de leitura do modulo DONO do dado
  (quem enxerga cliente nao passa a enxergar conta bancaria), autorizando depois
  de normalizar o alias e antes de qualquer leitura. Slug conhecido sem permissao
  declarada e negado; slug inexistente mantem lista vazia com 200 — contrato que
  a SPA ja consome (minha 1a versao trocou por 404 e derrubou um teste).
- GUARDIAO: `ApiContratoDriftTest` passou a reprovar rota `api/admin/*`
  autenticada sem permissao declarada. Verificado nos dois sentidos: removi a
  autorizacao do Lookup e o teste reprovou apontando a rota exata.
- Falso positivo corrigido antes de virar regra: a 1a versao do detector olhava
  so o corpo do metodo e acusou o `GeoController`, que autoriza corretamente
  dentro do helper `cfg()`. Detector que grita errado passa a ser ignorado —
  agora ele segue helpers privados da propria classe. Isso levou o recorte admin
  de 17 para 11 rotas, todas legitimas (2FA/sessoes do proprio usuario,
  assinatura da propria empresa, dashboard, troca de empresa), cada uma
  justificada em `ADMIN_SEM_PERMISSAO_APROVADAS`.
- Validacao: `LookupAutorizacaoTest` 5 testes; `ApiContratoDriftTest` 2; suite
  integral **1.357 passes / 4.304 assertions / zero falhas nos DOIS modos**;
  Pint aprovado. Ver `F2_01_MANIFESTO_PERMISSAO.md`.
- PENDENTE do F2-01: schema de request/response por rota e catalogo consumido
  pelo frontend. Ficaram fora por escolha — o valor imediato estava na permissao,
  que e o que F2-02 consome e o que fechava um vazamento real.

## Atualizacao de retomada - 2026-08-30 (F2-02 FK tenant-aware)

- Medi as tres exigencias da tarefa antes de mexer. Duas JA ESTAVAM certas:
  condicao ABAC desconhecida ja e negada (`default => false` no
  `PolicyEvaluator`), e a transferencia entre contas ja tinha contencao no
  `CaixaService`, com comentario nomeando o achado F0-04/A-12.2. Nao mexi.
- FURO PROVADO: `exists:clientes,id` valida contra a tabela INTEIRA. Um POST
  /pedidos da empresa A com `cliente_id` da empresa B era aceito com 201 — e
  continuava 201 entre TENANTS distintos com o enforcement ligado. A RLS protege
  a leitura; a validacao corria por fora e a FK nascia cruzada.
- Corrigido com `App\Rules\ExisteNoTenant`, que valida pelo MODEL em vez da
  tabela: o escopo vem de quem ja o declara (`BelongsToTenant` por empresa,
  `BelongsToGrupo` por grupo, model sem escopo continua valendo na tabela toda,
  correto para catalogo de plataforma). Evita reescrever 135 regras a mao.
- Aplicado em `PedidoRequest` (cliente, situacao, operacao, setor, produto dos
  itens) e `ClienteRequest` (7 campos, incluindo `convenio_id`, que aponta para
  outro cliente e amarrava convenio alheio).
- `atendente_user_id`/`entregador_user_id` seguem com `exists` nativo de
  proposito: `User` nao tem escopo de tenant e a validacao certa ali e outra
  (pertencer a empresa via `empresa_user`).
- Validacao: `FkTenantAwareTest` 5 testes (cross-empresa, cross-tenant, produto,
  convenio, e o caminho legitimo seguindo em 201); suite integral **1.362 passes
  / 4.317 assertions / zero falhas nos DOIS modos**; Pint aprovado. Ver
  `F2_02_FK_TENANT_AWARE.md`.
- PENDENTE: as demais ocorrencias de `exists:` (colaborador, comodato, veiculo,
  alcada, logradouro, pagamento e app mobile). A ferramenta esta pronta e
  provada, mas cada arquivo precisa de leitura propria para nao trocar por
  escopo errado — `exists:users,id` e justamente o caso onde a troca automatica
  estaria errada.
