# Estado atual — transformação SaaS

**Objetivo durável:** ativo  
**Estado:** IMPLEMENTANDO  
**Fase:** F1 — **CONCLUÍDA** (gate aprovado em homologação; ver `F1_16_GATE_APROVADO.md`)  
**Último microlote concluído:** F1-16 — gate F1 aprovado com role de runtime sobre dados reais  
**Próxima fase:** F2 — em andamento (F2-06 concluída)  
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

## Atualizacao de retomada - 2026-08-30 (F2-02 cobertura COMPLETA de exists:)

- Fecha a pendencia deixada no microlote anterior: aplicar `ExisteNoTenant` em
  TODA a superficie, nao so em pedido e cliente.
- Levantamento por REFLEXAO das 124 ocorrencias de `exists:` em `app/`:
  60 escopo empresa + 48 escopo grupo = **108 convertidas**; 15 legitimamente
  sem escopo (mantidas); 1 citacao em comentario (ignorada). 40 arquivos.
- ERRO DE CLASSIFICACAO PEGO A TEMPO: a primeira tentativa mapeou escopo por
  regex e classificou `pedidos` e `colaboradores` como SEM escopo — ambos usam
  `use Auditavel, BelongsToTenant;` agrupado numa linha, forma que o padrao nao
  casava. Refeito com reflexao (traits reais da classe e da hierarquia). Com o
  mapa errado, 11 ocorrencias desses dois teriam ficado sem protecao.
- As 15 mantidas sao deliberadas: `users`, `empresas`, `roles`, `permissions`,
  `planos`, `municipios_ibge`, `logradouros_oficiais`, `cidades_plataforma` nao
  tem escopo de tenant — forcar a regra ali seria trocar por escopo ERRADO.
- Dois casos que a automacao nao resolvia: `GeoController` e
  `CadastroApoioRegistry` guardam regras numa `const`, onde PHP nao aceita `new`.
  Nos dois, a conversao passou para o ponto unico de uso (`cfg()` e `validar()`).
- GUARDIAO: `test_nenhum_exists_aponta_para_tabela_escopada` varre `app/Http` e
  reprova `exists:` novo apontando para tabela escopada. A lista de tabelas vem
  da reflexao, entao model que ganhar escopo entra na varredura sozinho.
- Validacao: sintaxe conferida nos 40 arquivos; `FkTenantAwareTest` 6 testes;
  suite integral **1.363 passes / 4.318 assertions / zero falhas nos DOIS
  modos**; Pint aprovado. Ver `F2_02_FK_TENANT_AWARE.md`.

## Atualizacao de retomada - 2026-08-30 (F2-02A herda_filhos)

- A previsao da auditoria NAO se confirmou. Esperava-se um campo gravado e nunca
  lido ("promessa sem enforcement"); na verdade `PolicyEvaluator::escopoCobre()`
  implementa a semantica: unidade alcanca departamentos e setores dela,
  departamento alcanca seus setores, e as tres consultas de descida filtram por
  `empresa_id` — a heranca nao atravessa empresa.
- DECISAO: implementar integralmente (o campo fica). Setor ignora `herda_filhos`
  porque e a FOLHA da hierarquia — assimetria intencional, agora fixada em teste
  para nao parecer esquecimento.
- A lacuna real era o TESTE, nao o codigo: o teste de unidade so comparava
  `unidade_id` direto, entao o ramo que desce dois niveis nunca era exercitado,
  nem o `herda_filhos = false` desse nivel. Regressao ali passaria calada.
  Dois testes novos fecham isso.
- A outra metade da F2-02 ("autorizacao em cada porta de mutacao") foi medida:
  das rotas de mutacao autenticadas sem permissao, as 5 em `api/admin` sao as
  excecoes ja justificadas; as demais sao `app/v1`, cuja fronteira e o papel do
  token. Verifiquei que essa fronteira existe: so 8 rotas `app/v1` nao tem
  `approle`, e todas sao legitimas (login, cadastro, logout, refresh, device e
  marketplace publico) — nenhuma pode exigir papel de token ainda nao emitido.
- Validacao: `AbacPolicyEvaluatorTest` 12 testes/30 assertions (eram 10); suite
  integral **1.365 passes / 4.329 assertions / zero falhas nos DOIS modos**;
  Pint aprovado. Ver `F2_02A_HERDA_FILHOS.md`.
- F2-02 e F2-02A CONCLUIDAS. Restam da F2: F2-03 (licenca), F2-04 (Legacy Full),
  F2-06 (auditoria), o restante de F2-07 e de F2-08.

## Atualizacao de retomada - 2026-08-30 (F2-03 licenca)

- ACHADO: `LicencaService`, catalogo de 10 recursos e middleware `recurso:` ja
  existiam e estavam CORRETOS — mas ZERO das 604 rotas usavam o middleware e
  havia ZERO assinaturas no banco. A licenca existia e nao decidia nada.
- Documentacao que dizia o contrario do codigo: o docblock do `PlanosSeeder`
  afirmava "empresas sem assinatura tem tudo liberado (fail-open)". Verifiquei
  em teste: e FAIL-CLOSED, `recursosEfetivos()` devolve `[]`. Corrigido.
- GRADE: dois planos, ambos pagos (sem free), desenhados a partir do uso REAL
  medido na copia — 241.021 notas fiscais, 21.135 boletos + 4.961 PIX,
  16.153.938 posicoes GPS, 3.097 pos-vendas. Essencial R$349,90
  (app consumidor/entregador, cobranca, nfce) e Completo R$749,90 (catalogo
  inteiro, declarado por `RecursoCatalogo::chaves()` — recurso novo entra nele
  sozinho). Os 3 planos antigos foram DESATIVADOS, nao excluidos: assinatura
  apontando para plano apagado ficaria orfa e o tenant perderia tudo.
- ENFORCEMENT por PREFIXO (`RecursoPorRota`), nao rota a rota: o middleware
  antigo exigia ser escrito em cada rota, e foi por isso que nenhuma o usava.
  Assim rota nova de um dominio ja nasce coberta. Nucleo do ERP (cliente,
  produto, pedido, estoque, financeiro) fica FORA do mapa: e o que a revenda
  contrata por definicao, nao add-on.
- `saas:assinatura:criar <empresa|tenant> <plano>`: aceita todas as empresas de
  um tenant (so as com TenantCompany APROVADO), recusa duplicar sem `--force`,
  CANCELA a anterior em vez de apagar (a trilha sobrevive a troca), invalida o
  cache por empresa e registra na trilha de plataforma.
- Flag `SAAS_ENFORCE_LICENCA` nasce `false`. A ORDEM importa e nao e sugestao:
  semear planos -> criar assinaturas -> conferir `/api/me` -> so entao ligar.
  Inverter tira os modulos do ar, porque o servico e fail-closed.
- Validacao: `LicencaEnforcementTest` 8 testes/24 assertions; comando conferido
  (assinatura criada, 10 recursos, duplicada recusada); suite integral
  **1.373 passes / 4.353 assertions / zero falhas nos DOIS modos**; Pint
  aprovado. Ver `F2_03_LICENCA.md`.
- PENDENTE do F2-03: limites/add-ons numericos por assinatura (teto de usuarios,
  de veiculos) e porta para operar `RecursoOverride`. Hoje recurso e booleano —
  limite numerico nao existe no catalogo.

## Atualizacao de retomada - 2026-08-30 (pendencias de F2-01 e F2-03 fechadas)

- LIMITES NUMERICOS (F2-03): recurso respondia "tem ou nao tem"; faltava "ate
  quanto". `RecursoCatalogo::LIMITES` declara `empresas`, `usuarios` e
  `veiculos_monitorados`; `plano_limites` guarda o teto por plano e
  `limite_overrides` a excecao por empresa. Essencial: 2 empresas / 15 usuarios
  / 0 veiculos GPS. Completo: ilimitado, declarado como `null` EXPLICITO (omitir
  tambem libera, mas por acidente). Sem assinatura o teto e ZERO, nao ilimitado.
- OVERRIDE TEMPORARIO: `motivo` obrigatorio e `expira_em` opcional nas duas
  tabelas; o `LicencaService` ignora override expirado. Cortesia sem validade
  vira permanente por esquecimento — e assim que um piloto de 30 dias custa dois
  anos.
- ARMADILHA EVITADA: o primeiro rascunho da policy de `limite_overrides` usou
  `WITH CHECK (false)` por parecer "mais seguro"; isso quebraria a porta do
  SuperAdmin, que grava pela conexao de runtime. A policy final espelha a de
  `recurso_overrides`, a tabela irma.
- METADE FRONTEND DE F2-01: o menu da SPA filtrava por PERMISSAO mas nao por
  FEATURE — com a licenca ligada o usuario veria "Monitoramento" e tomaria 402
  ao clicar. `hasFeature()` fecha isso. Duas decisoes: `support` NAO fura
  licenca (bypass e acesso, licenca e contrato), e ausencia de `features` no
  payload libera, para a SPA nao quebrar durante o deploy.
- ACHADO: `break_glass_grants` e `otp_consumidos` (criadas em F2-05) nunca
  entraram no manifesto de classificacao — o gate F1 as reprovaria em
  homologacao. Registradas como TENANT e PLATFORM. O guardiao de RLS tambem
  pegou `limite_overrides` sem policy antes de eu perceber.
- Validacao: `LicencaEnforcementTest` 14 testes/39 assertions; frontend 11 testes
  e `tsc --noEmit` limpo; 145 migrations do zero em PostgreSQL 16 com
  `RlsCoberturaTest` 6/358 sem skip; manifesto de API 594 -> 596; suite integral
  **1.379 passes / 4.369 assertions / zero falhas nos DOIS modos**; Pint
  aprovado. Ver `F2_03_LICENCA.md`.
- PENDENTE: o ENFORCEMENT dos limites — quem cria empresa/usuario/veiculo ainda
  nao chama `dentroDoLimite()`. A decisao existe e esta testada; falta chama-la
  nas portas de criacao, trabalho que toca os mesmos controllers de F2-08.

## Atualizacao de retomada - 2026-08-30 (enforcement de limites e grade editavel)

- CORRECAO DE RUMO pedida pelo dono: eu estava adaptando o produto a copia. Os
  tetos do Essencial (2 empresas / 15 usuarios) tinham sido derivados do uso da
  Dubena — uma revenda virando definicao de produto, exatamente o erro que a
  transformacao SaaS existe para desfazer. Os numeros SAIRAM do codigo.
- A grade agora se edita em SuperAdmin -> Planos: preco, recursos e limites. O
  seeder cria so dois planos de partida e NAO declara teto nenhum (sem teto =
  ilimitado). Ele tambem deixou de sincronizar limites: semeia o que falta e
  nunca apaga, senao desfaria a decisao comercial a cada deploy.
- Editar plano invalida o cache de licenca de quem o assina — sem isso a edicao
  so teria efeito apos o TTL e pareceria nao funcionar.
- ENFORCEMENT: `LimiteContratado` e a porta unica que recusa com 402, ligada em
  empresa, usuario e veiculo monitorado. Cada contagem declara seu recorte
  (empresas = unidades ativas do MESMO TENANT; usuarios = so ATIVOS, senao
  desligar alguem nao liberaria vaga).
- FURO ENCONTRADO ao ligar: `EmpresaController::store()` criava a Empresa mas
  nao o `TenantCompany` — a filial nascia FORA da fronteira, nao contava no teto
  e o resolver a negava. Agora entra no tenant de quem criou, com evidencia
  `api:empresa.store:user:N`. Nao e inferencia: o tenant vem do ator.
- Um bug meu foi pego pelo teste antes de existir em runtime: coluna ambigua no
  JOIN de `empresasDoTenant`, que so apareceria com tenant aprovado.
- Validacao: `LicencaEnforcementTest` 18 testes/45 assertions; frontend 11 testes
  e tsc limpo; suite integral **1.383 passes / 4.375 assertions / zero falhas nos
  DOIS modos**; Pint aprovado. Ver `F2_03_LICENCA.md`.
- REGRA DE TRABALHO registrada na memoria: medir a copia serve para ACHAR
  problema, nunca para definir regra de negocio. Valor comercial e do dono,
  editavel no painel.
- PENDENTE: tela de override POR EMPRESA (cortesia/piloto) so existe como API; o
  painel ainda nao a expoe.

## Atualizacao de retomada - 2026-08-30 (override exposto no painel)

- Fecha a pendencia: a tela de override por empresa so existia como API.
- ACHADO ao abrir a tela: `RecursosDialog` ja tinha switches de override, mas
  chamava a API SEM `motivo` — que passou a ser obrigatorio em F2-03. A tela
  estava QUEBRADA (422) desde aquele microlote e ninguem tinha notado, porque
  nao havia teste de painel cobrindo o caminho.
- O dialogo foi refeito: o switch (ou o campo de limite) agora abre um pedido de
  MOTIVO antes de aplicar, com prazo opcional em destaque. Aplicar exceção sem
  registrar por que e exatamente o que F2-03 existe para impedir.
- Limites entraram na mesma tela, mostrando o teto EFETIVO (plano + override) —
  novo endpoint `GET /superadmin/empresas/{id}/limites`. Mostrar o teto do plano
  faria quem concedeu a cortesia achar que ela nao pegou.
- Validacao: 31 testes focais; `tsc --noEmit` limpo; manifesto 596 -> 597;
  suite integral **1.384 passes / 4.377 assertions / zero falhas nos DOIS
  modos**; Pint aprovado.

## Atualizacao de retomada - 2026-08-31 (F2-06 auditoria unificada)

- F2-06 CONCLUIDO. Restam da F2: F2-01 (schema de request/response por rota),
  F2-04 (Legacy Full), o restante de F2-07 e de F2-08.
- ACHADO que muda a leitura da tarefa: `audit_logs`, `login_logs` e
  `security_events` **ja tinham** a coluna `tenant_account_id` desde a migration
  000300 — e **nada a preenchia**. Nao era schema faltando; era coluna vazia
  esperando codigo. Coluna vazia e pior que ausente: parece resolvida.
  Descoberto rodando as migrations contra PostgreSQL real e lendo o `\d` — a
  suite em sqlite nao teria mostrado.
- O que faltava de fato: `correlation_id` nas quatro, `tenant_account_id` em
  `platform_audit_logs`, `motivo` como coluna consultavel (estava dentro do JSON
  `depois`) e, sobretudo, alguem preenchendo tudo isso.
- `ContextoAuditoria` e o ponto unico: envelope > header `X-Request-Id` > gerado.
  A trilha de plataforma deriva o tenant da empresa ALVO, porque o SuperAdmin
  opera sem tenant resolvido por desenho.
- BUG MEU pego por teste: memorizar a correlacao num campo de servico `scoped`
  NAO da uma por requisicao. O container resolve no boot e sob Octane a instancia
  atende requisicoes seguidas — confirmei com `spl_object_id` dentro e fora da
  requisicao: o mesmo objeto. A trilha passaria a afirmar que acoes de clientes
  diferentes vieram do mesmo clique. Corrigido com `WeakMap` por Request; o teste
  `requisicoes_distintas_nao_compartilham_o_fio` trava a regressao.
- BUG MEU no `down()`: dropava `tenant_account_id` das quatro, destruindo em tres
  delas a coluna da migration 000300. Agora so `platform_audit_logs` a perde.
  Verificado up -> down -> up em PostgreSQL real.
- O fio ficou navegavel: filtro `correlacao` em `ConsultaTrilha` (DENTRO do
  `where empresa_id`, com teste adversarial de linha de outra empresa usando o
  mesmo fio) e botao "Ver tudo que veio deste clique" na tela.
- CORRECAO DE REGISTRO: as atualizacoes anteriores dizem "zero falhas nos DOIS
  modos". Medi o modo enforcement e ha **103 falhas**, que sao ANTERIORES a este
  trabalho — confirmei rodando a suite com as minhas mudancas em stash: as mesmas
  103. Sao fixtures sem plano batendo em 402 depois que F2-03 ligou o
  `LimiteContratado`. Nao e regressao, mas tambem nao e "zero": e backlog de
  F2-08 (teste de ausencia de assinatura).
- Validacao: 9 testes focais; suite integral **1393 passes / 4402 assertions** no
  modo padrao; `RlsCoberturaTest` 6/6 em PostgreSQL real; tsc limpo; Vitest 39;
  manifesto 597; Pint aprovado. Ver `F2_06_AUDITORIA.md`.
- PENDENTE registrado (nao bloqueia): FK `ON DELETE RESTRICT` de
  `tenant_account_id` nas tres trilhas antigas bloquearia excluir um tenant com
  trilha. Hoje e inocuo — nao existe exclusao de tenant no sistema. Quando o
  fluxo existir, e decisao de retencao (anonimizar ou arquivar), nao de
  correlacao.

## Atualizacao de retomada - 2026-08-31 (F2-04 Legacy Full)

- F2-04 CONCLUIDO. Restam da F2: F2-01 (schema de request/response por rota),
  o restante de F2-07 e de F2-08.
- O PORTAO DA F2 FOI ATINGIDO NESTE LOTE: a suite passa com
  `SAAS_ENFORCE_LICENCA=true` **sem nenhuma falha**. Era 103 falhas antes.
- Plano `legacy-full` criado pelo seeder, marcado `transitorio` (coluna nova).
  `ativo` nao servia para separar oferta de transicao: o plano transitorio
  PRECISA estar ativo, senao as assinaturas nele deixam de valer.
- Quatro travas, cada uma fechando uma porta: nao aparece em `vendaveis()`; o
  painel recusa atribui-lo; o slug e imutavel (e por ele que o comando e o
  relatorio acham o plano — renomear deixaria os dois cegos SEM erro visivel); e
  `saas:licenca:status` conta quem ainda esta nele.
- Comando e nao seeder: seeder roda a cada deploy e nao pergunta nada. Assinar
  empresa e ato comercial — tem `--dry-run`, conferencia do alvo e trilha.
  Assinatura nasce sem `fim`: prazo aqui desligaria a operacao numa data que
  ninguem lembraria de renovar.
- LACUNA DO F2-06 fechada aqui: `AuditoriaPlataforma::registrar()` nao aceitava
  `motivo` — eu criara a coluna sem expor o parametro, e ela ficaria sempre nula
  justamente na trilha onde o porque mais importa.
- A causa real das 103 falhas nao era o Legacy Full: as fixtures criam empresas
  por factory, sem passar por comando nenhum. `FronteiraTenant` ganhou
  `licencaDeTransicao()` (empresa dentro da fronteira TEM assinatura — e o estado
  que F2-04 estabelece) e `semLicenca()` para os testes que exercitam a NEGACAO.
  Mesmo padrao que ja existia para o envelope, pela mesma razao.
- A fixture assina no plano de TRANSICAO de proposito: assinar `essencial` faria
  a suite exercitar uma grade comercial que o dono ainda vai desenhar, e o dia em
  que ele a mudasse a suite quebraria sem nada ter quebrado.
- Validacao: 12 testes focais; suite integral **1405 passes / 4430 assertions /
  zero falhas nos DOIS modos**; RlsCobertura 6/6 em PostgreSQL real; migration
  up->down->up; tsc limpo; Vitest 39; Pint aprovado. Ver `F2_04_LEGACY_FULL.md`.
- OPERACIONAL (nao e codigo): rodar `saas:legacy-full --dry-run` contra o banco
  real, conferir a lista, executar, e so entao virar `SAAS_ENFORCE_LICENCA`.

## Atualizacao de retomada - 2026-08-31 (F2-07 seguranca)

- F2-07 CONCLUIDO (o TOTP anti-replay ja tinha sido fechado antes). Restam da
  F2: F2-01 (schema de request/response por rota) e o restante de F2-08.
- Os tres itens tem a MESMA forma: codigo correto para uma revenda, defeituoso
  para muitas.
- LOCKOUT: o contador por IP era compartilhado entre tenants. Duas revendas
  atras do mesmo CGNAT de operadora — comum em cidade pequena, que e o publico
  de um ERP de GLP — e a primeira a errar 5 vezes TIRAVA A SEGUNDA DO AR. Pior:
  5 tentativas com e-mails inventados derrubavam o IP de um escritorio inteiro.
  Agora o eixo do IP e escopado ao tenant do e-mail alvo; as duas defesas
  (varredura no tenant, ataque a uma conta de varios IPs) seguem intactas.
- ACHADO no caminho: falha por senha errada — a mais comum — nao gravava
  `empresa_id` no `login_logs`. O contador por IP nunca enxergaria justamente as
  tentativas que deveria contar.
- POLITICA DE SENHA: declarada por EMPRESA, mas a senha e do USUARIO. Um gerente
  de duas filiais trocava a senha na filial frouxa (min 8) e enfraquecia a
  credencial que abre a rigida (min 12 + complexidade). A regra aplicada passa a
  ser a MAIS RIGIDA entre as empresas alcancadas. Empresa sem politica nao baixa
  a regua (o default e piso, nao teto) e `expira_dias = 0` e "nunca", nao "menor
  que tudo".
- CAMPOS SENSIVEIS: a lista vivia em DOIS lugares — no catalogo e numa constante
  `CAMPOS_SENSIVEIS` dentro de cada resource. Declarar uma chave no catalogo NAO
  protegia nada ate alguem lembrar de editar a segunda lista: uma permissao que
  existe, aparece na tela de papeis, pode ser negada, e nao esconde o campo. Isso
  e pior que nao ter a permissao, porque afirma uma protecao que nao acontece.
  Agora `camposControlados($modulo)` deriva do catalogo; zero ocorrencias de
  `CAMPOS_SENSIVEIS` no app/. Um teste varre o catalogo inteiro e exige que cada
  chave vire controle real.
- Sobre "default-deny" do enunciado: trocar a convencao globalmente quebraria
  todas as respostas da API sem ganho proporcional. O que estava aberto nao era o
  default — era a segunda lista, que fazia a declaracao nao valer. Foi isso que
  se fechou.
- Validacao: 18 testes focais; suite integral **1423 passes / 4490 assertions /
  zero falhas nos DOIS modos**; RlsCobertura 6/6 em PostgreSQL real; Pint
  aprovado. Ver `F2_07_SEGURANCA.md`.

## Atualizacao de retomada - 2026-08-31 (F2-08 testes reais)

- F2-08 CONCLUIDO. Da F2 resta apenas **F2-01** (schema de request/response por
  rota; a parte de action/resource/capability e catalogo ja foi entregue).
- VAZAMENTO REAL ENCONTRADO pela varredura adversarial:
  `GET /api/admin/produtos/{id}/estoque` devolvia **200 para produto de outro
  tenant**. Os saldos nao vazavam (a RLS os protege), mas o 200 confirma que
  aquele id existe em algum lugar — metade do que um atacante quer ao varrer
  numeros. `EstoqueController::porProduto` nao verificava o dono do produto.
  Corrigido com 404 (nao 403: "existe, mas voce nao pode" ja e a informacao).
- O teste adversarial DESCOBRE as rotas pelo roteador, entao alcanca o que
  ninguem pensou em cobrir. Duas salvaguardas contra virar teatro: contraprova
  positiva (as mesmas rotas respondem para o id proprio — senao passaria com a
  app inteira fora do ar) e guarda do mapa (recurso novo com `{id}` FALHA ate
  alguem declarar a que grupo pertence).
- MATRIZ DE PAPEIS REAIS: os 4 do RbacSeeder (Administrador/Gerente/Operador/
  Entregador), sem `support`. Papeis sob medida provam que o MECANISMO funciona;
  nao que os papeis ENTREGUES estao desenhados certo. 13 testes passaram de
  primeira — o valor nao foi achar defeito, foi passar a detectar quando a
  hierarquia deixar de valer. Inclui "a escada nao se inverte": cada papel e
  subconjunto ESTRITO do anterior.
- AUSENCIA DE ASSINATURA nas PORTAS (o servico ja tinha teste): 402 e nao 403 —
  a distincao permite a tela dizer "seu plano nao inclui isto" em vez de mandar o
  cliente pedir acesso a quem nao pode conceder. O nucleo do ERP continua
  respondendo: pendencia comercial nao pode virar perda de dado operacional.
- A varredura pergunta a `RecursoPorRota::recursoDaRota()`, nao aos middlewares
  declarados na rota — ele resolve por PREFIXO de caminho, entao procurar
  `recurso:` nas rotas nao acharia nada (foi o meu primeiro erro aqui).
- Validacao: 22 testes focais; suite integral **1445 passes / 4547 assertions /
  zero falhas nos DOIS modos**; Pint aprovado. Ver `F2_08_TESTES_REAIS.md`.

## Atualizacao de retomada - 2026-08-31 (F2-01 parte 2: schema de contrato)

- **A FASE F2 ESTA FECHADA.** Todas as oito tarefas: F2-01, F2-02, F2-02A,
  F2-03, F2-04, F2-05, F2-06, F2-07, F2-08.
- O que faltava da F2-01 era o schema de request/response por rota. O manifesto
  existente e uma lista de "METODO uri": pega rota removida, e nada mais. O que
  quebra a SPA e os apps com muito mais frequencia NAO altera essa lista — campo
  que some da resposta, obrigatorio que aparece, tipo que muda.
- DECISAO: capturar em RUNTIME, nao ler o codigo. Medido antes: so **5 rotas**
  usam FormRequest; as outras **217** validam inline, muitas com regras montadas
  em tempo de execucao. Um extrator estatico leria bem as 5 e mal as 217, e um
  contrato que descreve mal metade do sistema e pior que nenhum — da confianca
  onde nao deve.
- PRECO, dito na cara: a cobertura do contrato e a cobertura da suite. Rota nao
  exercitada nao entra, e o comando REPORTA quantas ficaram de fora — que e a
  lista de rotas que nenhum teste toca.
- Ganchos: `Validator::resolver()` (unico ponto por onde passam FormRequest E os
  217 validate inline) e um middleware no grupo `api` (unico ponto por onde passa
  toda resposta, seja Resource, array ou JsonResponse). Ambos saem na primeira
  linha com a captura desligada.
- O que NAO entra: regras completas (gravar `min:8` faria o arquivo mudar a cada
  ajuste de validacao sem o contrato ter mudado), corpo de erro (o 422 e a forma
  da FALHA; misturar faria o contrato prometer campos que so existem quando algo
  deu errado) e profundidade > 3 (viraria ruido).
- Lista vira a forma do ITEM (`data[].nome`): o contrato e o formato, nao a
  quantidade.
- ARMADILHA DO WINDOWS anotada: a env do `Process` SUBSTITUI o ambiente herdado.
  Sem mesclar com `getenv()`, o subprocesso perde PATH/SystemRoot e o PHP nem
  inicia — o sintoma e saida VAZIA, que nao parece erro de ambiente. Custou uma
  execucao inteira da suite.
- `api:schema --check` classifica o diff em vez de so acusar mudanca; campo que
  SUMIU vem primeiro, porque e o que quebra o consumidor silenciosamente.
- Ver `F2_01B_SCHEMA_DE_CONTRATO.md`.

### F2-01 parte 2 — resultado medido

- Contrato gerado: **368 rotas** (179 com request, 292 com response), **62%**
  das 597 rotas do manifesto. As 229 sem contrato sao as que nenhum teste
  exercita — nao e meta frustrada, e lista acionavel.
- Amostra de qualidade: `POST api/admin/clientes` identificou exatamente os DOIS
  campos realmente obrigatorios (`nome` e `telefones.*.telefone`) entre ~30
  chaves aceitas, inclusive o obrigatorio aninhado.
- `--check` verificado com campo fantasma: acusou
  "campo `data.campo_fantasma` SUMIU da resposta".
- Suite: **1451 passes / 4614 assertions / zero falhas nos DOIS modos**.
- O comando NAO dispara mais a suite (a versao com `Process` aninhado falhava
  MUDO no Windows). Sao duas etapas:
  `API_SCHEMA_CAPTURA=1 php artisan test` e depois `php artisan api:schema`.

## Atualizacao de retomada - 2026-08-31 (F3-04A papel da situacao)

- Primeira tarefa da **F3**. Escolhida por ser a mais concreta e por ter um
  defeito ATIVO, nao so um risco de desenho.
- `EntregaService::iniciarRota()` — a acao que o entregador dispara ao sair com
  a carga — encontrava a situacao de deslocamento por
  `LIKE '%saiu%' OR '%rota%' OR '%caminho%'`, desempatava por `orderBy('id')` e,
  nao achando, **CRIAVA** "Saiu para entrega" para continuar.
- O item 3 e o pior tipo de defeito: silencioso e cumulativo. O cliente
  configurou o Kanban dele e o sistema acrescenta uma coluna que ele nao pediu.
  A partir dai existem dois nomes para o mesmo momento, o relatorio soma errado,
  e nada liga o sintoma a causa.
- Falso positivo do outro lado: "Saiu do estoque para conferencia" casa com
  `%saiu%` e viraria destino de entrega. Ha teste para isso.
- CORRECAO: `papel` declarado na situacao (`PapelSituacao::EM_ROTA`). O
  `EfeitoPedido` continua com 3 valores e governa a maquina de estados — o papel
  distingue momentos DENTRO de um mesmo efeito.
- Quatro decisoes: default NENHUM (papel e afirmacao, nao inferencia);
  exclusivo por grupo (dois alvos devolveriam o desempate arbitrario);
  sem papel a acao FALHA com mensagem acionavel (erro custa um minuto, situacao
  duplicada em silencio contamina o relatorio para sempre); exposto na UI do
  Kanban (senao a config existiria so na API).
- CONVERSAO: a heuristica antiga roda UMA vez, na migration, e so onde e
  inequivoca — um unico candidato no grupo. Grupo com dois candidatos fica SEM
  papel de proposito. Escolher "a de menor id" resolveria a migration e deixaria
  uma decisao errada gravada num banco que ninguem revisaria.
- Testes em ESPANHOL de proposito (`En reparto`, `Camino al cliente`): e o
  cenario que o gate da F3 exige e que a heuristica antiga nao sobrevive.
- O contrato de schema (F2-01) capturou a mudanca SOZINHO: `papel` apareceu no
  diff de `api-schema.json` sem ninguem editar o arquivo. Foi para isso que ele
  foi construido.
- Validacao: 14 testes focais; suite **1460 passes / 4627 assertions / zero
  falhas nos DOIS modos**; RlsCobertura 6/6 e migration up->down->up em
  PostgreSQL real; tsc limpo; Vitest 39; Pint aprovado.
  Ver `F3_04A_PAPEL_DA_SITUACAO.md`.

## Atualizacao de retomada - 2026-08-31 (F3-02 tipo do produto)

- `VinculoVasilhame` decidia o que um produto E lendo a descricao: `VASILHA`,
  `CASCO`, `BOTIJAO`, `BOTIJAO` para recipiente; `GLP`/`RECARGA` para conteudo,
  com `GRANEL` excluindo. Toda a vigilancia de comodato — que esta EM PRODUCAO e
  revelou 145 contratos vencidos — depende disso. E NAO ha tabela de vinculo: a
  inferencia era recalculada a cada execucao.
- Uma revenda que cadastre "Cilindro 13kg", "P13 cheio" ou opere em espanhol
  some da vigilancia inteira. E o modo de falhar e o pior possivel: **a tela nao
  fica vazia, fica com MENOS linhas**. Um erro visivel seria reportado no
  primeiro dia; uma lista curta parece uma lista.
- CORRECAO: `tipo` no produto (RECIPIENTE/CONTEUDO/MERCADORIA/INDEFINIDO),
  ortogonal a `natureza` — um recipiente e um conteudo sao ambos `produto`; o
  que os separa e serem o casco ou o gas.
- A regex NAO foi jogada fora, mudou de lugar: `sugerirTipo()` a mantem como
  sugestao na tela de conferencia, devolvendo a EVIDENCIA junto — um palpite sem
  o motivo nao e conferivel.
- Tres colunas: `tipo`, `tipo_origem` (heuristica|humano) e `tipo_evidencia`. A
  origem e o que impede a conversao de virar verdade absoluta: sem ela, palpite
  e decisao humana ficariam indistinguiveis no dia seguinte e a divida sumiria
  de vista sem ser paga.
- O que nao casou com nada NAO virou MERCADORIA. "Nao bateu com nenhuma palavra"
  e "e mercadoria comum" sao afirmacoes diferentes, e so a primeira e verdade —
  marcar tudo esconderia os cascos que a heuristica nao reconhece, que sao a
  razao da migration existir.
- `GET /comodatos/vinculos` passou a devolver `nao_classificados` com sugestao e
  evidencia: troca "a tela mostra menos linhas" por "estes N precisam decisao".
- Detalhe pego no teste: o default da COLUNA so vale na linha gravada; um
  `new Produto` vinha com `tipo = null`, e `null === RECIPIENTE` da false por
  acidente. `protected $attributes` no model resolve.
- Validacao: 10 testes focais + 22 de comodato atualizados; suite **1470 passes
  / 4644 assertions / zero falhas nos DOIS modos**; tsc limpo; Vitest 39; Pint.
- PostgreSQL real: 149 migrations aplicadas, RlsCobertura 6/6, rollback ->
  reaplicacao OK. O Docker Desktop caiu no meio do lote, entao a validacao foi
  feita contra o PostgreSQL LOCAL, num banco descartavel (`erp_f3_check`, criado
  e removido; o `erp_teste` preexistente nao foi tocado).
- A CONVERSAO foi verificada com massa real em Postgres. Os tres casos que
  provam as decisoes: "Botijao P13 - RECARGA" virou CONTEUDO (nao recipiente,
  apesar da palavra); "GLP a GRANEL" ficou INDEFINIDO (excluido); e "Agua
  mineral 20L" ficou INDEFINIDO — NAO virou MERCADORIA por presuncao.
  Ver `F3_02_TIPO_DO_PRODUTO.md`.
- ANOTACAO DE AMBIENTE: ha PostgreSQL local escutando em 127.0.0.1:5432
  (postgres/postgres). Serve para validar migration quando o Docker falhar —
  criar banco descartavel, nunca usar o `erp_teste`.

### F3-02 segunda peca — a capacidade

- Mesmo defeito, um nivel abaixo: `capacidade()` extraia a grade da descricao
  (`/\bP\s?(13|20|45|90)\b/` e `/\b(13|20|45|90)\s?KG\b/`). **A grade brasileira
  de GLP estava escrita no codigo.** Outra grade nao pareia casco com gas — e o
  pareamento e o que sustenta a vigilancia inteira.
- `capacidade` e varchar e nao decimal DE PROPOSITO: e um ROTULO de grade
  comercial, nao uma medida. Dois recipientes de 13 kg de grades diferentes nao
  sao intercambiaveis, e um numero faria parecer que sao. O par e por igualdade
  exata do rotulo.
- Precedencia: coluna -> `tipo_glp` -> texto. O campo fiscal vence o texto porque
  e preenchido para valer; a coluna vence os dois porque e declaracao.
- Conversao verificada em PostgreSQL com massa real. Os dois casos que provam o
  cuidado: "Produto P130 especial" NAO virou P13 (o `\b` evita o falso positivo)
  e "Botellon 15 kg" ficou NULO em vez de receber palpite — e exatamente o caso
  que a coluna existe para resolver.
- 150 migrations em PostgreSQL, RlsCobertura 6/6, rollback -> reaplicacao OK
  (banco descartavel `erp_f3b_check`, criado e removido).

## Atualizacao de retomada - 2026-08-31 (F3-11 escrita canonica)

- Guardiao executavel contra o RETORNO das inferencias por texto. A F3 tirou
  decisoes de dentro de palavras em portugues (F3-04A, F3-02); nada disso se
  sustenta sozinho.
- O raciocinio: a proxima pessoa que precisar distinguir dois conceitos que o
  modelo nao separa vai escrever `str_contains($p->descricao, '...')`. E vai
  FUNCIONAR — passa no teste, resolve o chamado, e o custo so aparece na segunda
  revenda, meses depois, como uma tela com menos linhas do que devia. Uma regra
  que depende de alguem lembrar nao e uma regra.
- Varre `app/Domain`, `app/Http/Controllers` e `app/Jobs` procurando termos do
  dominio (VASILHA, CASCO, BOTIJAO, GRANEL, RECARGA, SAIU, ROTA DE, CAMINHO) e
  falha quando um deles GOVERNA decisao.
- Tres cuidados para nao virar ruido: presenca nao basta (exige `str_contains`,
  `stripos`, `preg_match`, `LIKE`… na mesma linha — senao todo rotulo de tela
  acusaria); comentario nao conta (documentacao que cita o padrao antigo e
  documentacao, inclusive a que explica por que ele saiu); e a licenca e nominal,
  com o motivo escrito (so `VinculoVasilhame.php`, onde a regex vive como
  sugestao conferida por humano).
- Segundo teste impede a lista de PERMITIDOS de envelhecer: arquivo permitido que
  nao existe mais quebra a suite.
- A mensagem de falha aponta a SAIDA (declarar no cadastro, ou virar `sugerir*`
  com evidencia) — um guardiao que so diz "nao" empurra o proximo a contornar.
- VERIFICADO QUE DETECTA: inseri de proposito um `str_contains(..., 'BOTIJAO')`
  no EntregaService e ele apontou `app/Domain/Mobile/EntregaService.php:124`.
  Um guardiao que nunca acusa e decorativo.
- LIMITACAO ASSUMIDA: a lista e do dominio de GLP, nao exaustiva do portugues.
  Conceito novo com vocabulario novo nao e pego — o valor esta em travar o
  caminho ja trilhado, que e por onde a regressao volta.
- Validacao: 2 testes focais; suite **1475 passes / 4650 assertions**; Pint.
  Ver `F3_11_ESCRITA_CANONICA.md`.

## Atualizacao de retomada - 2026-08-31 (F3-06 tipo do local de estoque)

- `setores` tinha `descricao` e `ativo`, e mais nada. Deposito da revenda,
  estoque "Em poder de Fulano" (criado automaticamente pelo
  `CargaFranqueadoService` na primeira carga) e carga de veiculo conviviam na
  MESMA lista, indistinguiveis.
- No seletor de "onde lancar a entrada", o operador ve "Em poder de Joao" ao lado
  de "Deposito central" e pode escolher qualquer um. O lancamento errado NAO da
  erro — da um saldo que nao bate, descoberto no inventario, quando ninguem mais
  liga uma coisa a outra.
- CORRECAO: `tipo` (DEPOSITO/LOJA/CUSTODIA_PESSOA/VEICULO) com dois predicados
  que o codigo consulta em vez de repetir a regra: `aceitaEntradaDireta()` e
  `eCustodia()`.
- A restricao fica na PORTA HTTP, nao no `EstoqueService`: o servico e usado pela
  transferencia e pela carga do franqueado, que sao justamente os caminhos
  legitimos de por mercadoria nesses locais. Ha teste dos dois lados.
- A CONVERSAO e a mais segura desta fase. Nas outras (F3-02, F3-04A) a heuristica
  lia texto digitado por humano; aqui o prefixo "Em poder de " e escrito pelo
  PROPRIO CODIGO, e existe evidencia melhor: `colaboradores.setor_estoque_id`.
  Por isso a ordem e vinculo primeiro, prefixo so para os orfaos.
- Verificado em Postgres com massa: "Deposito 2" virou CUSTODIA_PESSOA pelo
  VINCULO, apesar de o nome nao ter a assinatura. Uma conversao so por texto
  teria errado esse caso — e e o que prova o desenho.
- Validacao: 8 testes focais; suite **1483 passes / 4663 assertions**; 151
  migrations em PostgreSQL real; RlsCobertura 6/6; rollback -> reaplicacao OK;
  Pint. Ver `F3_06_TIPO_DO_LOCAL_DE_ESTOQUE.md`.
- NAO FEITO (registrado): `/lookups/setores` ainda devolve todos. Ele e generico
  por tabela e serve tanto seletor de lancamento quanto transferencia (onde
  custodia E destino valido). A tela de entrada manual ainda NAO existe na SPA —
  quando existir, o seletor deve usar `Setor::armazens()`, que ja esta pronto.

## Atualizacao de retomada - 2026-08-31 (F3-08 PARCIAL: codigo IBGE conferido)

- ESCOPO, dito antes de tudo: este lote faz o PRIMEIRO PEDACO da F3-08, nao a
  tarefa inteira. Os tres catalogos continuam existindo (`municipios_ibge`
  nacional, `cidades` por grupo, `cidades_plataforma`). Unifica-los alcanca
  `Cidade` em 27 arquivos e mexe em dado fiscal — nao e trabalho para fazer sem
  validacao.
- O vinculo `cidades.municipio_ibge -> municipios_ibge` JA EXISTIA (migration de
  2026-08-23). O que faltava era a PORTA DE ESCRITA usa-lo: `POST /geo/cidades`
  aceitava `cod_ibge` como `nullable|integer` — numero livre, digitado a mao, sem
  conferencia. Codigo errado nao da erro no cadastro; da rejeicao da SEFAZ na
  primeira nota, quando ninguem lembra de onde veio o numero.
- CORRECAO DE LEITURA MINHA: achei que
  `'cod_municipio' => (int) ($municipio?->cod_ibge ?? 0)` no NFePHPSefazDriver
  mandaria `cMun = 0` para a SEFAZ. NAO MANDA — logo abaixo ha validacao que
  exige `>= 1000000`, `cuf > 0` e UF batendo, com erro claro. Registro porque a
  conclusao apressada teria produzido "correcao" para problema inexistente.
- `normalizarCidade()`: se veio `municipio_ibge`, codigo e UF sao DERIVADOS dele
  (nao se confia em dois campos que podem discordar); se veio so `cod_ibge`, ele
  e conferido e vira o vinculo; UF divergente do proprio codigo e recusada; e
  vale tambem na EDICAO, senao bastaria criar certo e editar errado.
- NAO se exige codigo: cidade sem `cod_ibge` continua podendo ser criada. Exigir
  travaria quem ainda nao migrou, e a emissao fiscal ja barra o que falta. A
  garantia e sobre codigo ERRADO, nao sobre codigo AUSENTE — so o primeiro e
  silencioso.
- Validacao: 6 testes focais; suite **1489 passes / 4676 assertions**; Pint.
  Ver `F3_08_MUNICIPIO_IBGE.md`.
- ABERTO da F3-08: unificar os tres catalogos; `cidades_plataforma` tem a mesma
  porta sem conferencia; e decidir se `cidades` deve seguir por grupo, ja que
  municipio e fato nacional.

## Atualizacao de retomada - 2026-08-31 (F3-09 PARCIAL: vinculo entre as frotas)

- O mesmo caminhao existia DUAS vezes: `veiculos` (km, troca de oleo, documentos)
  e `monitora_veiculos` (rastreador, posicoes, cercas). Nada as ligava — cada uma
  com seu proprio `veiculo_id`, e a placa como unica coisa em comum, sem ninguem
  conferir se batia.
- Duas consequencias: "onde esta o caminhao que precisa trocar o oleo?" nao tinha
  resposta (uma frota sabe o km, a outra a posicao); e a placa podia divergir por
  erro de digitacao SEM NADA ACUSAR — o veiculo sumia de um dos lados.
- VINCULO e nao FUSAO. O alvo final e uma tabela so, mas fundi-las agora alcanca
  `Veiculo` em 23 arquivos e as tabelas de posicao (milhoes de linhas). O vinculo
  entrega a resposta operacional hoje e deixa a fusao para um passo separado,
  feito com os dois lados JA CONCILIADOS — posicao bem melhor para faze-la.
- `nullOnDelete` e nao cascade: apagar o cadastro de frota nao pode apagar o
  historico de posicoes, que e a prova de onde o veiculo esteve (usada para
  conferir entrega e jornada). Ha teste.
- A chave mora do lado do monitora: a frota e o cadastro PRINCIPAL, o
  rastreamento e algo que se acopla a ele. E o rastreador e um VINCULO, nao a
  identidade — trocar de aparelho nao cria caminhao novo (com teste).
- CONCILIACAO verificada em Postgres, 4 casos: `abc-1d23` casou com `ABC1D23` (a
  normalizacao e o ponto); placa duplicada na frota ficou NULO; sem par ficou
  nulo; e mesma placa em outra empresa casou com O VEICULO DELA. Placa duplicada
  sem vinculo e deliberado — o palpite errado ligaria a manutencao de um caminhao
  a posicao de outro, que e pior que nao ligar nada.
- Validacao: 5 testes focais; suite **1494 passes / 4685 assertions**; 152
  migrations em PostgreSQL real; RlsCobertura 6/6; rollback -> reaplicacao OK;
  Pint. Ver `F3_09_VINCULO_ENTRE_FROTAS.md`.
- ABERTO: a fusao das tabelas; uma tela de conciliacao (hoje os nao vinculados e
  os ambiguos ficam nulos CORRETAMENTE, mas ninguem os ve); e criar
  `monitora_veiculos` com placa inexistente na frota ainda nao avisa.

## Atualizacao de retomada - 2026-08-31 (F3-10 PARCIAL: literal da Dubena fora do codigo)

- Varri `app/` e `config/` por "Dubena" e "Guarapuava". Resultado honesto: a
  ESMAGADORA MAIORIA sao COMENTARIOS explicando de onde veio um numero
  ("medido na base real de Guarapuava", "a −25° um grau de longitude vale ~10%
  menos"). Isso e documentacao valiosa e nao foi tocado.
- Em codigo EXECUTAVEL havia **um** caso: o `User-Agent` enviado ao Overpass
  dizia `ERP-Dubena/1.0`. Num SaaS, toda revenda se identificaria como a
  primeira cliente perante um servico externo — e a politica de uso do Overpass
  pede justamente que o User-Agent identifique quem chama. Agora vem de
  `config('app.name')`, com fallback generico (identificar-se de forma neutra e
  melhor do que se passar por outra empresa).
- Verifiquei tambem defaults suspeitos (UF fixa, cidade fixa, codigo IBGE fixo):
  o unico `'41'` encontrado e CST fiscal, legitimo.
- O guardiao `EscritaCanonicaTest` ganhou um terceiro teste: nome de revenda ou
  da cidade dela dentro de STRING em codigo falha a suite. Comentario continua
  permitido — o que se proibe e o literal governar comportamento. VERIFICADO QUE
  DETECTA: reinseri `'ERP-Dubena/1.0'` de proposito e ele apontou arquivo e linha.
- NAO FEITO da F3-10, e registrado: `empresa_configs.dados` continua sendo JSON
  livre, sem schema tipado nem versionado — a tarefa pede isso e e trabalho
  grande. Empresa nova hoje nao recebe configuracao nenhuma (fail-closed, que e
  consistente), entao nao ha "defaults de plataforma copiados no onboarding".

## Atualizacao de retomada - 2026-08-31 (F3-05 canal de venda)

- Quatro caminhos criam pedido — painel admin, app do consumidor, app do
  entregador (venda em campo) e central de vendas — e NO BANCO OS QUATRO FICAVAM
  IDENTICOS. "Quanto do meu faturamento ja vem do app?" nao tinha resposta, e essa
  e exatamente a decisao de investir ou nao no canal digital.
- DIMENSAO e nao booleanos paralelos, como a tarefa pede: booleanos por canal
  permitem estados IMPOSSIVEIS (dois verdadeiros — veio do app E do balcao?) e
  exigem coluna nova a cada canal. Um enum resolve os dois.
- SEM conversao retroativa: os pedidos existentes ficam DESCONHECIDO. Daria para
  adivinhar ("tem entregador e nao tem atendente, entao veio do campo"), mas
  "provavelmente" num dado que vira RELATORIO DE FATURAMENTO POR CANAL e pior que
  "nao sei" — o grafico ficaria bonito e errado. A fatia sem origem aparece,
  encolhe sozinha, e a decisao se baseia no que foi medido de verdade.
- GUARDIAO: `test_toda_porta_que_cria_pedido_declara_o_canal` varre quem injeta
  `PedidoService` e chama `criar`. Precisou de DUAS iteracoes para nao virar
  ruido — a primeira pegava qualquer `$service->criar(...)`, a segunda ainda
  acusava o `FinanceiroService`, que so CITA `PedidoService` num comentario e tem
  o proprio `criar`. Verificado que detecta: removi o canal do
  `CentralVendasService` de proposito e ele apontou o arquivo.
- Validacao: 6 testes focais; suite **1501 passes / 4699 assertions**; 153
  migrations em PostgreSQL real; RlsCobertura 6/6; rollback -> reaplicacao OK;
  Pint. Ver `F3_05_CANAL_DE_VENDA.md`.
- ABERTO: o relatorio por canal NA TELA (o dado existe e e consultavel, mas
  ninguem o ve — e era a pergunta que motivou a tarefa); e `envia_app_nf` no
  produto, que continua sendo um flag booleano de canal.

## Atualizacao de retomada - 2026-08-31 (F3-03 snapshot do item)

- **O achado mais serio desta fase.** `pedidoitens` e `nota_itens` guardavam
  `produto_id`, quantidade e preco. O PRECO estava congelado e sempre esteve
  certo; a DESCRICAO nao — era lida do produto na hora de exibir.
- No pedido isso reescreve o historico (renomear um produto faz o pedido de tres
  meses atras dizer que o cliente comprou algo que nao existia com aquele nome).
  Chato, mas contornavel.
- NA NOTA FISCAL e outra coisa: `XmlNfeBuilder` montava o `xProd` do XML lendo
  `$item->produto?->descricao`. Depois de autorizada, a NF-e e IMUTAVEL na SEFAZ
  — mas uma reimpressao de DANFE passava a mostrar a descricao NOVA. O papel
  deixava de bater com o documento autorizado. Isso e divergencia fiscal, nao
  detalhe de tela. `nota_itens` ja congelava CFOP, CST e aliquotas; faltava
  justamente o que aparece impresso.
- Congelados: `descricao_snapshot` no pedido; descricao + NCM + unidade na nota
  (NCM e unidade entram no XML e definem tributacao). Os tres pontos de leitura
  passaram a preferir o congelado: XmlNfeBuilder, DanfePdfService e
  CupomTextoService.
- `null` significa "nao capturado", NAO "sem nome": colunas nullable, com
  fallback para o cadastro atual. A conversao preenche as linhas antigas com o
  valor de HOJE — que e exatamente o que elas ja usavam ao exibir, entao nao
  piora nada: troca "le o atual toda vez" por "leu uma vez e congelou".
- Conversao com SUBSELECT correlacionado, e nao `UPDATE ... FROM`: Postgres e
  sqlite escrevem o segundo de formas diferentes. E em SQL por tabela, nao linha
  a linha — `nota_itens` numa base real tem centenas de milhares de linhas.
- Validacao: 5 testes focais + 1 fiscal; 75 testes fiscais existentes passando;
  suite **1507 passes / 4708 assertions**; 154 migrations em PostgreSQL real;
  RlsCobertura 6/6; conversao verificada com massa; rollback -> reaplicacao OK.
  Ver `F3_03_SNAPSHOT_DO_ITEM.md`.
- ABERTO: `unidade_snapshot` e gravado mas o XML ainda manda `uCom = 'UN'` fixo
  (trocar sem conferir a tabela de unidades da SEFAZ e risco fiscal); e
  `NfEntradaService` nao recebeu snapshot — e nota de TERCEIRO, onde a descricao
  vem do XML do fornecedor e nao do cadastro, entao o problema nao e o mesmo.

## Atualizacao de retomada - 2026-08-31 (F3-07: medicao, sem trabalho a fazer)

- A tarefa manda "desativar a hierarquia concorrente somente APOS medir
  consumidores". Fiz a medicao. **Nao ha o que desativar.**
- Parti da hipotese de que `regioes` competia com
  `unidades/departamentos/setores_org`. Errado: sao tres coisas com nomes
  parecidos e propositos DIFERENTES — arvore organizacional (RBAC), regiao
  GEOGRAFICA de entrega, e local de ESTOQUE (F3-06).
- DOIS FALSOS POSITIVOS que investiguei e descartei:
  (1) `colaborador_comissoes.setor_id` aponta para `setores` (estoque) e parecia
  "colaborador lotado num deposito" — mas o comentario da migration diz
  `(colaborador x produto x setor x condicao)`: e regra de comissao POR LOCAL DE
  ESTOQUE, legitima;
  (2) `cargos` poderia ter sido duplicada pela A3 — e a migration diz
  explicitamente que NAO foi, so acrescentou `role_id`. Os dois sao o oposto do
  defeito que a tarefa combate: decisoes corretas ja tomadas.
- A arvore serve exclusivamente ao RBAC (`role_user` + `herda_filhos`, fechado em
  F2-02A). E o uso para o qual foi criada.
- NAO FIZ, de proposito: o colaborador nao tem lotacao na arvore
  (`colaboradores.unidade_id` nao existe). Poderia parecer lacuna; nao e —
  acrescentar seria INVENTAR REQUISITO. Nenhum relatorio, tela ou regra pede "em
  qual departamento o colaborador trabalha", e coluna sem consumidor e o peso
  morto que esta transformacao esta removendo. Se vier a ser necessaria (folha
  por centro de custo), o lugar ja esta pronto e a decisao e do dono.
- Ver `F3_07_ORGANIZACAO_MEDICAO.md` — registrei a medicao para a proxima pessoa
  nao refazer a investigacao.

## Atualizacao de retomada - 2026-08-31 (F3-04 medido + duas lacunas fechadas)

- F3-04 ("separar order/fulfillment/payment/fiscal; forma de pagamento nunca e
  status de entrega"): MEDIDO. As situacoes que o seeder entrega sao todas de
  FULFILLMENT (Em Aberto -> Separacao -> Rota -> Concluido). Nao ha forma de
  pagamento disfarcada de status, que e o defeito que a tarefa alerta.
- LACUNA MINHA fechada: criei `PapelSituacao::EM_ROTA` em F3-04A e NAO atualizei
  os seeders. Uma revenda nova recebia "Em Rota" no Kanban e o app do entregador
  falhava ao iniciar rota — comportamento correto (pede configuracao), mas o
  seeder existe justamente para nao exigir configuracao manual do que ja se sabe.
  `DemoGuarapuavaSeeder` e `HomologSeeder` agora declaram o papel, com teste.
- BUG PREEXISTENTE encontrado no caminho: `DemoGuarapuavaSeeder` chamava
  `CaixaService::abrir($conta->id)` com um argumento, mas a assinatura passou a
  exigir `empresaId` (isolamento de tenant) e o seeder ficou para tras —
  `ArgumentCountError`. **O seeder de demonstracao estava quebrado.** Corrigido.
  So apareceu porque o teste novo o executa; nenhum teste o exercitava antes.

## Atualizacao de retomada - 2026-08-31 (F3-01 PARCIAL: papeis com vigencia)

- ESCOPO: a tarefa pede papeis com vigencia + endereco unico normalizado. Este
  lote entrega a PRIMEIRA. O endereco fica aberto: o texto de
  `clientes.endereco` ainda e a fonte em SEIS pontos de leitura, incluindo cupom
  fiscal e contrato de comodato — documento que vai para o cliente.
- Os tres booleanos paralelos (`cliente`/`fornecedor`/`transportador`) respondem
  "e?" e nao "era, quando?". Um fornecedor que deixou de fornecer nao tinha como
  sair da lista sem APAGAR O HISTORICO: desmarcar faz parecer que ele nunca
  forneceu, e as notas de entrada antigas passam a apontar para alguem que "nao
  e fornecedor".
- `cliente_papeis`: um papel por linha com `inicio`/`fim`. Marcar ABRE vigencia,
  desmarcar ENCERRA com a data de hoje — a linha nao e apagada.
- Os booleanos NAO foram removidos, de proposito: `ClienteResource`,
  `ClienteRequest` e o ETL ainda dependem deles, e migration destrutiva nao viaja
  junto com feature. `sincronizarPapeis()` mantem as duas fontes coerentes, e
  `temPapel()` le da tabela com o booleano como FALLBACK — um cadastro criado
  pelo ETL tem so o booleano e nao pode sumir da lista por isso (com teste).
- DOIS ERROS MEUS no caminho, os dois so visiveis porque o teste cobre o
  encerramento NO MESMO DIA (o caso real): (1) `fim >= hoje` mantinha vigente um
  papel encerrado hoje; (2) `where` em vez de `whereDate` — o valor chega como
  "2026-08-31 00:00:00" e comparado como STRING com "2026-08-31" sai maior. E a
  armadilha do `whereBetween` registrada no CLAUDE.md, e eu a repeti mesmo tendo
  lido o arquivo.
- O `TableClassificationManifestTest` acusou a tabela nova: o snapshot
  `CATALOGO_VIVO.json` NAO se reescreve (e evidencia de um estado certificado);
  tabelas posteriores entram numa lista do proprio teste.
- Validacao: 7 testes focais; 155 migrations em PostgreSQL real; RlsCobertura 6/6
  com **360 assertions** (eram 358 — a tabela nova entrou na cobertura sozinha);
  policy canonica `app_tenant_can_read/operate` + FORCE RLS confirmados no banco;
  conversao com 4 casos (inclusive "nenhum papel", que NAO ganhou papel por
  presuncao); rollback -> reaplicacao OK. Ver `F3_01_PAPEIS_DA_PESSOA.md`.
- ABERTO da F3-01: endereco normalizado; os lookups ainda nao filtram por papel
  (mesma limitacao do `LookupController` registrada em F3-06); e remover os
  booleanos quando o consumo migrar.

## Atualizacao de retomada - 2026-08-31 (F3-01: endereco com ponto unico de leitura)

- Fecha a segunda parte da F3-01. `Cliente::endereco_completo` JA EXISTIA, com a
  medicao registrada no proprio codigo: a coluna `endereco` esta NULL em **100%
  da base** (0 de 55.453 medidos em producao) — o logradouro real sempre veio da
  FK `rua_id`.
- Mesmo assim, QUATRO lugares montavam a string a mao a partir da coluna. Como
  ela e nula, todos exibiam SO O NUMERO: cupom fiscal, contrato de comodato (cujo
  proposito e localizar quem esta com o vasilhame), central de vendas e app do
  entregador (que serve para ele CHEGAR no endereco).
- "Endereco: 587" nao trava nada — e um documento entregue ao cliente com a
  informacao errada, e ninguem liga o sintoma a causa.
- ISENCAO com motivo: `IdentidadeCliente` usa o logradouro como TRACO de
  identidade para deduplicacao, separado do numero e da cidade. Ja resolve a FK
  corretamente; forcar o accessor ali juntaria numero e cidade no mesmo traco e
  faria dois clientes distintos casarem.
- O guardiao tem `assertGreaterThan(50, $varridos)` — licao do `FkTenantAwareTest`,
  que varria ZERO arquivos e passava sempre. Verificado que detecta: reinseri a
  montagem manual no CentralController e ele apontou arquivo e linha.
- Validacao: 4 testes focais; suite **1519 passes / 4731 assertions**; Pint.

## Atualizacao de retomada - 2026-08-31 (F3-08 completa + F3-10 medida)

- F3-08: `cidades_plataforma` (a segunda porta) tinha o MESMO `cod_ibge` livre,
  sem conferencia. Aplicada a mesma correcao do `GeoController`: codigo
  inexistente e recusado, UF divergente e recusada, e a UF passa a vir do
  catalogo. Cidade SEM codigo continua permitida — a garantia e sobre codigo
  ERRADO, nao ausente.
- F3-10 MEDIDA, e **corrijo o que eu mesmo anotei errado**: registrei antes que
  `empresa_configs.dados` era "JSON livre, sem schema tipado". NAO E. O endpoint
  generico tem allow-list (~50 chaves declaradas), RECUSA chave inesperada,
  RECUSA estrutura aninhada ("configuracao estruturada deve usar seu endpoint
  especifico") e valida a configuracao financeira a parte. Os blocos
  estruturados tem porta propria e validada campo a campo — `integracoes` valida
  `pix.ambiente` com `in:producao,homologacao`, `cartao.url` com `url`, e cifra
  os segredos por valor preservando o ja salvo.
- Isso e schema tipado por bloco na pratica. O que nao existe e VERSAO declarada
  — e sem consumidor que precise migrar entre versoes, seria peso morto.
- Empresa nova nao recebe configuracao nenhuma: fail-closed, consistente com o
  resto (sem credencial da empresa nao cobra e nao autentica). Semear defaults
  exigiria decidir QUAIS valores sao universais, que e exatamente o que a F3
  existe para nao fazer.
- Validacao: 7 testes focais; suite **1520 passes / 4734 assertions**; Pint.
  Ver `F3_10_MEDICAO_CONFIGURACAO.md`.

## Atualizacao de retomada - 2026-08-31 (F3-09: conciliacao visivel e vinculo declaravel)

- A conversao de F3-09 ligou os pares INEQUIVOCOS e deixou nulo o ambiguo (placa
  repetida na frota) ou sem par. Isso esta certo — o palpite errado ligaria a
  manutencao de um caminhao a posicao de outro. Mas **uma pendencia que so existe
  no banco e uma pendencia que nao sera resolvida**.
- `GET /monitora/conciliacao` lista os rastreados sem vinculo, com os candidatos
  da frota pela placa NORMALIZADA ("ABC-1D23" e "abc1d23" sao a mesma placa
  digitada por pessoas diferentes, e e essa divergencia que faz o veiculo sumir
  de um dos lados). Um candidato = sugestao segura; dois ou mais = ambiguo, e a
  lista vai inteira porque a escolha e humana.
- `veiculo_frota_id` passou a ser declaravel na criacao/edicao — senao a
  conciliacao apontaria o problema sem oferecer saida.
- Validacao: 7 testes focais; suite **1522 passes / 4741 assertions**; manifesto
  597 -> 598; Pint.
- NAO FEITO, registrado: a tela de veiculos rastreados ainda nao existe na SPA,
  entao a conciliacao vive so na API — que e onde a decisao pode ser tomada hoje.
  A FUSAO das duas tabelas continua aberta: 7 tabelas dependem de
  `monitora_veiculos`, incluindo posicoes com milhoes de linhas.

## Atualizacao de retomada - 2026-08-31 (F4-01: ledger idempotente) — INICIO DA F4

- `estoquehistorico` JA ERA um ledger, e um bom: quantidade assinada, tipo,
  evento causal (`origem`/`origem_id`), ator e `saldo_resultante`. Faltavam duas
  coisas.
- `tenant_account_id` EXISTIA, VAZIA — a migration 000300 (F1) a acrescentou e
  nada a preenchia. Mesmo achado do F2-06 nas trilhas: coluna vazia e pior que
  ausente porque PARECE resolvida.
- IDEMPOTENCIA (o gate da F4: "rerun nao duplica"). A protecao existia mas era
  POR CASO DE USO — o pedido tem `estoque_movimentado`; transferencia, acerto e
  carga do franqueado nao tinham nada. Movimento duplicado NAO da erro: da um
  saldo que nao bate, descoberto no inventario.
- Tres decisoes: (1) a chave e OPCIONAL — exigir de todos num lote so quebraria
  quem ainda nao informa; o que se garante e que QUEM INFORMA nunca duplica;
  (2) a garantia e do BANCO, via indice unico PARCIAL (sem o `WHERE`, todas as
  linhas sem chave colidiriam entre si) — verificado em Postgres: o segundo
  insert e recusado e duas linhas sem chave convivem; (3) a chave e escopada por
  EMPRESA, porque o numero do pedido reinicia por empresa e uma unicidade global
  faria a segunda revenda PERDER o movimento.
- Detalhe que so o teste pegou: acrescentei o parametro em `movimentar()` e
  esqueci de repassa-lo nos atalhos `entrada()`/`saida()` — por onde quase todo
  mundo chama. A chave morria ali.
- Validacao: 6 testes focais; suite **1528 passes / 4753 assertions**; 156
  migrations em PostgreSQL real; RlsCobertura 6/6; indice parcial conferido no
  banco; rollback preserva a coluna da 000300. Ver `F4_01_LEDGER_IDEMPOTENTE.md`.
- ABERTO: os chamadores ainda NAO passam a chave — a infraestrutura esta pronta e
  testada, adotar em cada porta e o passo seguinte e cada uma precisa decidir
  qual e a sua chave natural.
