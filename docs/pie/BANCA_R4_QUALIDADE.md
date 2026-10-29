# Banca R4 — Engenheiro de qualidade / Funcionalidades e regressões

**Revisor:** R4 · **Data:** 2026-10-28/29 · **Commit:** `48b29f72` (main, árvore com alterações só em docs/legado-nfweb — nada em `erp-novo`, `app-*`, `mobile-shared`)
**Ambiente:** Windows 11, PHP 8.3 portátil, Node local, sqlite `:memory:` (phpunit.xml) e **PostgreSQL 15 portátil local** (`127.0.0.1:55432`, banco `erp_teste`, 171 migrations já aplicadas) para os testes de RLS. VPS de homologação lida em modo somente leitura (uma consulta de variáveis `SAAS_ENFORCE*`/`APP_ENV`).
**Modo:** AUDITORIA — nenhum arquivo do projeto foi alterado; nenhum teste criado no repositório.

Legenda de prova: **[EXEC]** executado por mim, com saída real · **[ESTÁTICA]** leitura de código · **[PROPOSTO]** teste descrito, não criado · **[BLOQUEADO]** depende de ambiente/credencial ausente.

---

## 1. Comandos executados (saída real resumida)

| # | Comando | Resultado |
|---|---|---|
| C1 | `cd erp-novo/frontend; npx vitest run` | **46 arquivos, 169 testes, 169 verdes** (51,5 s). Stderr com `Error: boom` é o `ErrorBoundary.test.tsx` provocando o erro de propósito. |
| C2 | `cd erp-novo/frontend; npx tsc --noEmit` | **exit 0**, sem erros. |
| C3 | `cd app-gas-em-casa; npx vitest run` | **3 arquivos, 17 testes verdes** (`polyline`, `dataNascimento` 6, `validators` 9). |
| C4 | `cd app-gas-em-casa; npx tsc --noEmit` | **falha de configuração**: `tsconfig.json(3,5): error TS5098: Option 'customConditions' can only be used when 'moduleResolution' is set to 'node16', 'nodenext', or 'bundler'.` |
| C5 | `cd app-entregador; npx tsc --noEmit` (= `npm run typecheck`) | **exit 2**, mesmo TS5098. Nenhum erro de tipo é avaliado (ver R4-01). |
| C6 | `npx tsc --noEmit -p tsconfig.json --moduleResolution bundler --module preserve` (override só na linha de comando, sem tocar arquivo) | app-entregador: **0 erros**. app-gas-em-casa: **2 erros TS2551** reais (ver R4-02). |
| C7 | `php artisan test tests/Feature/MobileTest.php tests/Feature/AppLoginSobRlsTest.php` (sqlite) | **40 verdes, 4 pulados** (os 4 do `AppLoginSobRlsTest`: "RLS é específico do PostgreSQL; pulado em sqlite"). |
| C8 | Mesmo backend contra Postgres local como `erp_app` (variáveis do job `test-postgres`): `php artisan test tests/Feature/AppLoginSobRlsTest.php tests/Migration/DedupClientesFksTest.php` | **4 verdes** (AppLoginSobRlsTest, todos) e **2 FALHAS** (DedupClientesFksTest: `SQLSTATE[42501] must be owner of table agencias` no `drop table` do `RefreshDatabase`). Ver R4-03/R4-04. Nada foi apagado: o `DROP ... CASCADE` é uma instrução só e foi recusada inteira. |
| C9 | Lotes por jornada (sqlite), script em scratchpad, `php artisan test <arquivos>` | 1 pedido/entrega: **66 verdes** · 2 financeiro/caixa: **79 verdes** · 3 cobrança PIX/boleto: **80 verdes (985 asserções)** · 4 fiscal: **61 verdes** (o `EmpresaCertificadoTest` NÃO pulou — openssl gerou o PKCS#12) · 5 estoque/comodato: **85 verdes** · 6 login admin/app/superadmin: **78 verdes** · 7 licença SaaS/isolamento: **46 verdes** · 8 ETL/cutover: **90 verdes, 2 pulados** (`DedupClientesFksTest`, 276 s) · 9 guardiões: **26 verdes**. **Total dos lotes: 611 verdes, 2 pulados, 0 falhas.** |
| C10 | `ssh dubena-vps` → `docker exec <c> printenv \| grep -E '^SAAS_ENFORCE\|^APP_ENV='` (somente leitura) | `APP_ENV=homologation`, `SAAS_ENFORCE_TENANT_ENVELOPE=true` em app/queue/scheduler/reverb; **`SAAS_ENFORCE_LICENCA` ausente** (default `false`, `config/saas_transformation.php:21`). |

Arquivos de cada lote (C9):
1. `PedidoTest, PedidoSituacaoTest, Domain/PedidoServiceTest, EntregaP7Test, PortaUnicaDeBaixaTest, NovoPedidoPraticoTest, JornadaEntregadorTest, PrecoRevalidadoNaConclusaoTest, CentralVendasTest, AppPedidoCoberturaTest, AppPedidoEscopoClienteTest`
2. `CaixaTest, Domain/CaixaServiceTest, Domain/CaixaRegrasC4Test, FinanceiroTest, Domain/FinanceiroServiceTest, F07FinanceiroCaixaChequeTest, ReconciliacaoFinanceiraTest, ConferenciaDeSaldoTest, LedgerIdempotenteTest, MaloteTest`
3. `CobrancaTest, Domain/CobrancaServiceTest, Domain/CnabBoletoTest, Domain/CnabVetoresOficiaisTest, PixDriverTest, PixWebhookFailClosedTest, PagamentoTest, BoletoPdfTest, IdempotenciaTest, IdempotenciaSobConcorrenciaTest`
4. `FiscalTest, FiscalFailClosedTest, PedidoNfceTest, FaseF09FiscalTest, EmpresaCertificadoTest, ProntidaoFiscalTest, ContratoNotaFiscalApiTest, DanfePdfTest, Domain/CenariosFiscaisTest, Unit/NfceConfiguracaoDoToolsTest, CertificadoVigilanciaTest`
5. `EstoqueOperacoesTest, Domain/EstoqueServiceTest, ConferenciaDeCustodiaTest, ComodatoSentidoTest, ComodatoDevolucaoParcialTest, ComodatoVinculoApiTest, ComodatoContratoVersaoTest, EstoquePeriodoFechadoTest, TransferenciaEstoqueApiTest, Domain/EstoqueMultiTenantTest`
6. `AuthTenantTest, AppAuthHardeningTest, SuperAdminTest, FaseF1SegurancaTest, SegurancaAvancadaTest, LockoutEntreTenantsTest, AppRoleTest, PoliticaSenhaDonoTest, BreakGlassTest`
7. `LicencaEnforcementTest, LicenciamentoSaasTest, MultiTenantIsolamentoTest, FaseF02CrossTenantTest, TrocaAdversarialDeIdsTest`
8. `tests/Migration/*` (15 arquivos) + `EtlTravaPosCutoverTest, EtlNaoDizConcluidoAToaTest, PosCheckDoCutoverTest, GoliveCheckTest, EtlEnxergaODestinoTest, MaquinaDeEstadosDaConversaoTest`
9. `FkTenantAwareTest, RbacContratoTest, EnforcementCentralTest, ApiContratoDriftTest, ContratoSpaTest, RotasSpaProtegidasTest, ApiSchemaContratoTest`

Observação: a execução coincidiu com a virada do dia (23:47–00:1x BRT, 02:47+ UTC). Nenhum teste de data falhou nessa janela — indício (não prova) de que a suíte não é sensível à fronteira de dia com `APP_TIMEZONE=America/Sao_Paulo`. Só 2 usos de `travelTo/setTestNow/freezeTime` em 91 arquivos que chamam `now()`.

---

## 2. Inventário de jornadas críticas

Todos os números de testes "verdes" vêm de C7–C9. Sqlite não exerce RLS, grants, tamanho de `varchar`, nem truncamento de `date` (CLAUDE.md). Por isso nenhuma jornada é classificada VERIFICADA "em produção" — a classificação vale para o comportamento da aplicação no sqlite; o que depende do Postgres está separado.

| ID | Jornada | Testes (arquivo) | Positivo | Negativo | Efeito colateral asserido | Estado |
|---|---|---|---|---|---|---|
| J1 | Pedido telefone → central → entrega → baixa | `TelefoniaTest` (identificação por máscara/9º dígito/sem DDD, não vaza outra empresa, webhook sem segredo recusa), `CentralVendasTest`, `PedidoTest`, `Domain/PedidoServiceTest` (concluir baixa estoque, não baixa em dobro, sem saldo bloqueia, cancelar devolve), `EntregaP7Test` (comprovação exigida, pedido de outro entregador 404), `MaloteTest`, `PortaUnicaDeBaixaTest`, `Domain/PedidoCondicaoPagamentoTest` | sim | sim | estoque e parcelas sim; **não há teste encadeado** central→app entregador→malote→caixa pela HTTP | **PARCIAL** — cada elo VERIFICADO isoladamente [EXEC lote 1/2]; a cadeia inteira não tem teste (R4-08). `TelefoniaTest` não rodei. |
| J2 | Financeiro / caixa | `CaixaTest`, `CaixaServiceTest`, `CaixaRegrasC4Test`, `FinanceiroTest`, `FinanceiroServiceTest`, `F07FinanceiroCaixaChequeTest`, `ReconciliacaoFinanceiraTest`, `ConferenciaDeSaldoTest`, `LedgerIdempotenteTest`, `MaloteTest` | sim | sim (outro tenant, sem permissão, título cancelado, conta de outra empresa) | sim (crédito em conta, parcelas baixadas) | **VERIFICADA (sqlite)** [EXEC lote 2: 79 verdes]. `financeiro:conferir` sob RLS real: BLOQUEADA (não rodei contra base com dados). |
| J3 | Cobrança PIX / boleto | `CobrancaTest`, `CobrancaServiceTest`, `CnabBoletoTest`, `CnabVetoresOficiaisTest`, `PixDriverTest`, `PixWebhookFailClosedTest` (7 cenários fail-closed), `PagamentoTest`, `BoletoPdfTest`, `IdempotenciaTest`, `IdempotenciaSobConcorrenciaTest`, `MobileTest::test_pix_*` | sim | sim (webhook sem segredo, HMAC errado, txid desconhecido, valor divergente) | sim | **VERIFICADA com drivers Fake** [EXEC lote 3: 80 verdes]. PSP real e leitura física de boleto: **BLOQUEADA** (credencial PIX/banco; CLAUDE.md). |
| J4 | Fiscal NF-e / NFC-e | `FiscalTest`, `FiscalFailClosedTest`, `PedidoNfceTest`, `FaseF09FiscalTest`, `EmpresaCertificadoTest`, `ProntidaoFiscalTest`, `ContratoNotaFiscalApiTest`, `DanfePdfTest`, `CenariosFiscaisTest`, `NfceConfiguracaoDoToolsTest`, `CertificadoVigilanciaTest` | sim | sim (fail-closed sem certificado) | sim (situação da nota, numeração) | **VERIFICADA com `FakeSefazDriver`** [EXEC lote 4: 61 verdes]. Autorização real na SEFAZ: **BLOQUEADA** (certificado A1). SPA sem teste de emissão (só `MalhaTab`, `FiscalDaEmpresa`). |
| J5 | Estoque / comodato | `EstoqueOperacoesTest`, `EstoqueServiceTest`, `ConferenciaDeCustodiaTest`, `Comodato*Test` (4 rodados), `EstoquePeriodoFechadoTest`, `TransferenciaEstoqueApiTest`, `EstoqueMultiTenantTest` | sim | sim | sim (ledger, custódia por movimentos) | **VERIFICADA (sqlite)** [EXEC lote 5: 85 verdes]. `estoque:conferir` contra dado real: BLOQUEADA aqui. |
| J6a | Login admin (SPA) | `AuthTenantTest`, `FaseF1SegurancaTest`, `SegurancaAvancadaTest`, `LockoutEntreTenantsTest`, `PoliticaSenhaDonoTest`, `BreakGlassTest`; SPA `lib/loginError.test.ts` | sim | sim (lockout, senha) | sim | **VERIFICADA (sqlite)** [EXEC lote 6]. Sob RLS: sem teste dedicado no gate. |
| J6b | Login app cliente (Firebase) / entregador | `MobileTest` (login por telefone com máscara, outra empresa falha, token inválido 401, cadastro, data de nascimento), `AppAuthHardeningTest`, `AppRoleTest`, **`AppLoginSobRlsTest`** | sim | sim | sim (user/cliente vinculados, `tenant_account_id`, grant) | **VERIFICADA** — sqlite [EXEC C7] **e Postgres/RLS local** [EXEC C8: 4 verdes]. Mas o teste RLS **não roda em nenhum portão automatizado** (R4-03). Firebase real: BLOQUEADA (chave). |
| J6c | Login superadmin | `SuperAdminTest` | sim | sim | sim | **VERIFICADA (sqlite)** [EXEC lote 6]. SPA `features/superadmin` (104 nós no grafo) **sem nenhum teste vitest**. |
| J7 | Licença SaaS (plano/módulo/teto) | `LicencaEnforcementTest` (19), `LicenciamentoSaasTest`, `MultiTenantIsolamentoTest`, `FaseF02CrossTenantTest`, `TrocaAdversarialDeIdsTest` | sim | sim (402 módulo não contratado, teto de empresa/usuário, override expira) | sim | **VERIFICADA no código com a flag ligada no teste** [EXEC lote 7: 46 verdes]; **no ambiente a flag está desligada** (C10) → em homologação o caminho de bloqueio não é exercido (R4-07). |
| J8 | ETL / cutover | `tests/Migration/*` (15), `EtlTravaPosCutoverTest`, `EtlNaoDizConcluidoAToaTest`, `PosCheckDoCutoverTest`, `GoliveCheckTest`, `EtlEnxergaODestinoTest`, `MaquinaDeEstadosDaConversaoTest`, `DedupClientesFksTest` | sim | sim | sim (invariantes, idempotência de recarga) | **VERIFICADA contra legado simulado em sqlite** [EXEC lote 8: 90 verdes, 2 pulados]. Execução contra legado MySQL real e `cutover:check` (71/0 é de agosto): **BLOQUEADA**. `DedupClientesFksTest` **FALHA REPRODUZIDA** no Postgres (R4-04). |

---

## 3. Achados

### R4-01 — Typecheck dos dois apps móveis não roda: erro de configuração esconde todo erro de tipo
- **Categoria:** regressão / ferramenta de qualidade · **Severidade:** ALTA · **Estado:** CONFIRMADO [EXEC C4, C5, C6]
- **Arquivo:** `app-entregador/tsconfig.json:3-5` e `app-gas-em-casa/tsconfig.json:3-5` (`extends: expo/tsconfig.base` + `"moduleResolution": "Node"`); o base do Expo (`node_modules/expo/tsconfig.base.json`) define `customConditions: ["react-native"]`, incompatível com `Node`.
- **Esperado × observado:** `npm run typecheck` (app-entregador) deveria reportar erros de tipo; sai com `exit 2` e **só** o TS5098. Quando há erro de opções, o `tsc` não chega ao diagnóstico semântico — nenhum erro de tipo é avaliado. A prova: com o override na linha de comando (C6) aparecem 2 erros reais no app do cliente (R4-02) que o typecheck "oficial" nunca mostrou.
- **Agravante:** nenhum dos apps (nem `mobile-shared`) está no CI — `.github/workflows/erp-novo.yml:24-37` dispara só em `erp-novo/**`, `deploy/**`; os jobs são `test`, `frontend` (SPA), `test-postgres`, `build`, `deploy-*`. Os 17 testes vitest do app do cliente também nunca rodam no CI; o app do entregador não tem teste nenhum.
- **Impacto:** a fronteira app↔API (a mais movimentada, e sem ligação sintática — CLAUDE.md) não tem rede de tipos; o defeito R4-02 é exemplo concreto.
- **Correção proposta:** nos dois `tsconfig.json`, `"moduleResolution": "bundler"` e `"module": "preserve"` (ou remover os dois e herdar do base do Expo); adicionar job de CI com `npx tsc --noEmit` nos dois apps + `vitest run` no app do cliente, com `paths` incluindo `app-*/**` e `mobile-shared/**`.
- **Teste de aceite:** `npm run typecheck` em app-entregador sai 0; em app-gas-em-casa, depois de corrigir R4-02, sai 0; plantar `const x: number = "a"` em um arquivo de cada app e ver o typecheck falhar.

### R4-02 — Perfil do app do cliente lê `perfil.telefone`, a API devolve `telefones` (lista): o telefone nunca aparece
- **Categoria:** funcionalidade / contrato app↔API · **Severidade:** BAIXA (cosmético, sem perda de dado) · **Estado:** CONFIRMADO [EXEC C6 + ESTÁTICA]
- **Arquivo:** `app-gas-em-casa/src/app/(auth)/(tabs)/perfil/index.tsx:82-83`; tipo `PerfilCliente` em `app-gas-em-casa/src/types/types.ts:337-344` (`telefones: string[]`); API `erp-novo/app/Http/Controllers/Api/Mobile/AppPerfilController.php:39` (`'telefones' => $c->telefones->pluck('telefone')`).
- **Rota:** `GET /api/app/v1/perfil`.
- **Esperado × observado:** o cartão do usuário deveria mostrar o telefone; `perfil?.telefone` é sempre `undefined`, o bloco não renderiza. `tsc` acusa `TS2551: Property 'telefone' does not exist on type 'PerfilCliente'. Did you mean 'telefones'?`.
- **Correção:** `perfil?.telefones?.[0]`. **Aceite:** tsc limpo (depende de R4-01) e teste de componente mostrando o primeiro telefone.

### R4-03 — Seis testes só-Postgres não rodam em portão nenhum; entre eles o de regressão do login dos apps (b2f151bb)
- **Categoria:** cobertura de regressão · **Severidade:** ALTA · **Estado:** CONFIRMADO [ESTÁTICA + EXEC C7/C8]
- **Arquivo:** `erp-novo/composer.json` script `test:pgsql-rls` — `--filter='RlsCoberturaTest|GrantsSomenteLeituraTest|EscritaDePlataformaSobRlsTest|AutomacaoSobRlsTest|ChaveDeTenantOperacionalTest' --fail-on-skipped` (job `test-postgres`, `.github/workflows/erp-novo.yml:153-216`). O job `test` roda a suíte em sqlite, onde esses testes são pulados.
- **Contagem:** testes que pulam fora do Postgres = `AppLoginSobRlsTest` 4 + `AutomacaoSobRlsTest` 4 + `ChaveDeTenantOperacionalTest` 2 + `EscritaDePlataformaSobRlsTest` 2 + `GrantsSomenteLeituraTest` 1 + `RlsCoberturaTest` 6 + `DedupClientesFksTest` 2 = **21**. O gate cobre 15. **Ficam 6 sem execução em lugar nenhum**: `AppLoginSobRlsTest` (4) e `DedupClientesFksTest` (2). O CLAUDE.md diz "20 pulados (os de PostgreSQL/RLS, que só rodam no CI)" — a segunda metade é falsa para esses 6.
- **Impacto:** o defeito que derrubou todos os logins da homologação em 13/10 (só visível sob RLS) não tem guarda automatizada; um retorno dele passa verde no CI. Hoje o comportamento está correto: rodei o arquivo no Postgres local e os 4 passaram (C8).
- **Correção:** trocar a lista fixa por um critério que não esquece arquivo novo — p.ex. `--group=pgsql` com `#[Group('pgsql')]` nos testes que pulam fora do Postgres, ou um guardião que varre `tests/` por `getDriverName() !== 'pgsql'` + `markTestSkipped` e exige que cada classe esteja no filtro (com `assertGreaterThan(5, $varridos)`).
- **Aceite:** `composer test:pgsql-rls` executa 21 testes, zero pulados.

### R4-04 — `DedupClientesFksTest` falha no Postgres: usa `RefreshDatabase` com o runtime sem posse das tabelas
- **Categoria:** teste quebrado / falso verde · **Severidade:** MÉDIA · **Estado:** FALHA REPRODUZIDA [EXEC C8]
- **Arquivo:** `erp-novo/tests/Migration/DedupClientesFksTest.php:6,28` (`use RefreshDatabase;`).
- **Observado:** `SQLSTATE[42501]: Insufficient privilege: 7 ERROR: must be owner of table agencias` nos 2 testes. É exatamente o antipadrão listado no CLAUDE.md ("Teste que toca RLS não usa `RefreshDatabase`"). O teste protege a descoberta de FKs por `pg_constraint` — a guarda do quase-acidente de ~40 mil órfãos em produção — e **nunca executou de verdade**: no sqlite pula, no gate não está no filtro (R4-03), e se estivesse falharia.
- **Correção:** trocar por `DatabaseTransactions` (ou nada — o teste só lê catálogo) e incluir no gate. **Aceite:** 2 verdes no Postgres como `erp_app`; plantar a versão com `information_schema` em `referenciasAClientes` e ver o teste falhar.

### R4-05 — Guardiões que varrem arquivos sem piso de contagem (dois já falharam assim)
- **Categoria:** qualidade de teste · **Severidade:** MÉDIA · **Estado:** CONFIRMADO [ESTÁTICA]
- **Arquivos:**
  - `erp-novo/tests/Feature/FkTenantAwareTest.php:150-207` (`test_nenhum_exists_aponta_para_tabela_escopada`) — o próprio comentário em `:228-235` relata que no Windows `modelsComEscopo()` voltava **vazio** e o guardião "nunca acusou nada". A correção normalizou o caminho mas **não adicionou** `assertGreaterThan` sobre `$escopadas` nem sobre os arquivos varridos. O mesmo bug pode voltar e o teste passa.
  - `erp-novo/tests/Feature/EnforcementCentralTest.php:119-146` — varre `app/Http/Controllers` sem contar arquivos.
  - `erp-novo/tests/Feature/RbacContratoTest.php:95-130,159-180` — se a regex de `chavesUsadasNosControllers()` ou a de `permission:` em `AppShell.tsx` extrair zero itens, `array_diff` é vazio e passa. Hoje extrai 35 do `AppShell.tsx` (contei com grep), mas **outras 17 ocorrências de `permission:` vivem fora dele** (`layouts/BotaoNovo.tsx`, `features/dashboard/ParaHoje.tsx`, `DashboardPage.tsx`, `lib/*`) e não são varridas; conferi à mão que os 38 módulos distintos existem no `PermissaoCatalogo`, então não há órfã hoje. A lista `VERBOS` (`:150`) também não inclui `alcada`/`faturar`, que existem no catálogo (`app/Domain/Shared/PermissaoCatalogo.php:47`).
- **Regra violada:** CLAUDE.md, "Todo teste que varre arquivo precisa de `assertGreaterThan(N, $varridos)`". Os outros 13 guardiões de varredura do repositório têm piso (grep por `assertGreaterThan|assertNotEmpty|assertCount` nos arquivos com `RecursiveDirectoryIterator|glob|File::allFiles`).
- **Correção/aceite:** `assertGreaterThan(50, count($escopadas))`, contagem de arquivos varridos em cada um; `RbacContratoTest` varrer `frontend/src/**` por `permission:`. Aceite: esvaziar artificialmente o diretório/regex (localmente) faz o teste falhar.

### R4-06 — Correções recentes: cobertura de regressão parcial
- **Categoria:** cobertura de regressão · **Severidade:** MÉDIA · **Estado:** CONFIRMADO [EXEC C3, C7, C8 + ESTÁTICA]

| Commit | Teste de regressão | Executado | Lacuna |
|---|---|---|---|
| `b2f151bb` login dos apps sob RLS | `tests/Feature/AppLoginSobRlsTest.php` (4) | sqlite: pulado; **Postgres local: 4 verdes** | não está no gate (R4-03) |
| `1c5a0e54` pré-filtro `like '%d%d%d%d%'` | nenhum teste novo; coberto indiretamente por `MobileTest::test_login_cliente_por_telefone_firebase_*` (telefone com máscara `(42) 99888-7766`) e `test_login_cliente_telefone_de_outra_empresa_falha` | verdes (C7) | não há caso com **dois clientes que compartilham os 4 últimos dígitos** (o pré-filtro devolve ambos; a comparação exata dos 8 tem de escolher o certo) nem com telefone cadastrado com `+55` e o token sem — o que garante que o pré-filtro "nunca perde" quem a comparação exata aceitaria |
| `d1b2878b` candidatos pela conexão de owner | só exercido no Postgres (`ConexaoDeOwner::executar` cai para o runtime em sqlite, `app/Domain/Tenant/ConexaoDeOwner.php:37-39`) — coberto pelo `AppLoginSobRlsTest` | Postgres local: verde | o fallback silencioso (`:41-45`: sem credencial de owner executa pelo runtime e o login volta a recusar todo mundo, sem erro) não tem teste nem log; ver R4-09 |
| `48b29f72` data de nascimento | `MobileTest::test_cadastro_aceita_nascimento_iso_e_recusa_a_mascara_ambigua` + `app-gas-em-casa/src/helpers/dataNascimento.test.ts` (6) | verdes (C3, C7) | o commit também mudou `PUT /api/app/v1/perfil` (`AppPerfilController.php:50-52`, `date_format:Y-m-d`), **sem teste**: `test_perfil_get_update_delete` só manda `nome`. As telas (`perfil-dados.tsx:50,57`, `newuser.tsx:90`) não têm teste. |

### R4-07 — Licença SaaS testada só com a flag ligada no teste; desligada no ambiente
- **Categoria:** funcionalidade / receita · **Severidade:** MÉDIA (o plano é virar SaaS para N revendas) · **Estado:** CONFIRMADO [EXEC C10 + lote 7]
- **Arquivo:** `erp-novo/config/saas_transformation.php:21` (`env('SAAS_ENFORCE_LICENCA', false)`); homologação sem a variável (C10).
- **Observado:** os 19 testes de `LicencaEnforcementTest` passam, inclusive `test_flag_desligada_e_passagem_livre`. Em homologação o 402 de módulo não contratado e o teto de plano não são aplicados. O comentário explica que nasce desligado de propósito. Para a banca: o caminho de bloqueio **nunca foi exercido num ambiente real**.
- **Proposta:** ensaio em homologação com a flag ligada e assinatura semeada; `golive:check --strict` acusar a flag desligada quando o alvo for SaaS. Decisão de produto, fora do R4.

### R4-08 — Não existe teste encadeado da jornada principal (pedido → entrega → malote → caixa)
- **Categoria:** cobertura E2E · **Severidade:** MÉDIA · **Estado:** CONFIRMADO [ESTÁTICA]
- Cada elo tem teste verde (lote 1/2), mas nenhum teste percorre a sequência pela HTTP com os efeitos somados (estoque baixado uma vez, parcela gerada pela condição, baixa no malote credita a conta certa, pedido reaberto estorna). O antipadrão já documentado no `IdempotenciaSobConcorrenciaTest` (testes que faziam `refresh()` antes de repetir e por isso nunca acusavam) mostra que elos testados isoladamente escondem defeitos de integração.
- **[PROPOSTO]** `JornadaPedidoCompletaTest`: `POST /api/admin/central-vendas` (pedido por telefone, condição a prazo 2x) → atribuir entregador → `PUT /api/app/v1/entregador/pedidos/{id}` aceitar → concluir com comprovação → `POST malote/fechar` → asserir: `estoquehistorico` com 1 saída assinada, 2 `financeiroparcelas`, 1 baixa (a da 1ª parcela à vista ou a do malote), `contamovimentos` na conta configurada da empresa, e repetir a conclusão (duplo envio do app) sem nova saída. Variante negativa: entregador de outra empresa recebe 404 em cada passo.

### R4-09 — `ConexaoDeOwner` cai para o runtime em silêncio; no login isso volta a recusar todos
- **Categoria:** robustez / observabilidade · **Severidade:** MÉDIA · **Estado:** FORTE INDÍCIO [ESTÁTICA]
- **Arquivo:** `erp-novo/app/Domain/Tenant/ConexaoDeOwner.php:41-45`; uso em `erp-novo/app/Domain/Mobile/ClienteAuthService.php:139-145`.
- Se `pgsql_owner` não conecta (senha trocada, variável ausente), o pré-filtro roda como `erp_app` sem envelope de tenant, lê zero `clientetelefones`, `clientePorTelefone` devolve `null` e o login responde como "telefone não cadastrado" — o mesmo sintoma do incidente de 13/10, agora sem log. Não reproduzi (exigiria derrubar a credencial de owner do Postgres compartilhado).
- **[PROPOSTO]** teste Postgres que configura `pgsql_owner` com senha inválida e espera login 503/erro explícito (ou ao menos `Log::warning` asserido), em vez de 422 de "não encontrado".

### R4-10 — Lacunas de teste na SPA e nos apps
- **Severidade:** BAIXA/MÉDIA · **Estado:** CONFIRMADO [ESTÁTICA + EXEC C1]
- SPA: 46 arquivos de teste. **Sem nenhum teste:** `features/superadmin` (licença, planos, overrides), `features/pagamentos`/cobrança (PIX/boleto), caixa, emissão fiscal (NF-e/NFC-e: só `MalhaTab` e `FiscalDaEmpresa`), login (só o helper `lib/loginError.test.ts`).
- app-entregador: **zero** testes. app-gas-em-casa: 3 arquivos de helper, nenhuma tela.
- Documentação desatualizada: CLAUDE.md fala em "119 testes da SPA"; medi **169** (C1).

### R4-11 — Expectativa de `QueryException` genérica em teste de RLS (pode passar pela verificação errada)
- **Severidade:** BAIXA · **Estado:** HIPÓTESE [ESTÁTICA]
- **Arquivo:** `erp-novo/tests/Feature/RlsCoberturaTest.php:127-128` (`expectException(QueryException::class)` no insert sem contexto) e `:100-112`. Qualquer `QueryException` (NOT NULL, FK, varchar curto) satisfaz o teste. O CLAUDE.md já registrou um teste que passava pela verificação seguinte. Correção: asserir `SQLSTATE 42501` / mensagem `row-level security`. Não rodei no Postgres.

### Antipadrões procurados e NÃO encontrados (registro de busca negativa)
- `Event::fake()` global: 0 ocorrências (4 comentários explicando o fake seletivo).
- `assertSame(f($x), f($x))`: só a citação histórica em `tests/Domain/CnabVetoresOficiaisTest.php:18` (docblock).
- Testes presos à serialização ISO: 0 (só o comentário em `EscritaRestauradaTest.php:95`).
- `return;` precoce em teste (passa sem asserção): 0.
- `assertTrue(true)`: só `tests/Unit/ExampleTest.php:14` (o exemplo do Laravel; ruído, sem risco).
- Lembrete do CLAUDE.md: `grep` confirma presença, não ausência — estas buscas são por padrão textual.

---

## 4. Testes pulados — o que fica sem prova no sqlite

| Arquivo | Testes | Motivo | Roda em algum portão? |
|---|---|---|---|
| `RlsCoberturaTest` | 6 | Postgres | sim (gate) |
| `AutomacaoSobRlsTest` | 4 | Postgres | sim |
| `ChaveDeTenantOperacionalTest` | 2 | Postgres | sim |
| `EscritaDePlataformaSobRlsTest` | 2 | Postgres | sim |
| `GrantsSomenteLeituraTest` | 1 | Postgres | sim |
| `AppLoginSobRlsTest` | 4 | Postgres | **não** (R4-03) — rodei: 4 verdes |
| `DedupClientesFksTest` | 2 | Postgres (`pg_constraint`) | **não** — rodei: 2 FALHAS (R4-04) |
| `EmpresaCertificadoTest` | condicional | openssl sem PKCS#12 | não pulou aqui |
| `ContratoSpaTest`, `RbacContratoTest`, `RotasSpaProtegidasTest` | condicional | frontend ausente | não pulam no monorepo |
| `ApiSchemaContratoTest` | condicional | `API_SCHEMA_CAPTURA=1` | não pula por padrão |

Sem prova em sqlite (por natureza): RLS/policies e `WITH CHECK`, grants por role, tamanho de `varchar`/`char(1)`, truncamento do cast `date`, `whereDate` × índice, `lockForUpdate` real, `pg_constraint`, conexão de owner (`ConexaoDeOwner`), desempenho sob RLS.

---

## 5. Lacunas de regressão de maior risco — testes propostos (não criados)

1. **[PROPOSTO] Gate Postgres sem lista fixa** (R4-03): guardião que falha se houver classe com `markTestSkipped` por driver fora do filtro de `test:pgsql-rls`.
2. **[PROPOSTO] Login por telefone — colisão de sufixo:** dois clientes na mesma empresa com telefones `42 99999-1234` e `42 98888-1234`; token `+5542988881234` deve entrar como o segundo; token `+5511988881234` (outro DDD, mesmos 8) — documentar o comportamento escolhido (hoje casa pelos 8 últimos dígitos, `ClienteAuthService.php:121-125`).
3. **[PROPOSTO] `PUT /api/app/v1/perfil` com `datanascimento: '05/06/1990'` → 422; com `'1990-06-05'` grava 5 de junho; com `null` limpa.**
4. **[PROPOSTO] Jornada encadeada** pedido→entrega→malote→caixa (R4-08).
5. **[PROPOSTO] Typecheck + vitest dos apps no CI** (R4-01), com uma prova plantada.
6. **[PROPOSTO] `ConexaoDeOwner` sem credencial** no login (R4-09).
7. **[PROPOSTO] Licença ligada em homologação:** smoke `GET` de um módulo opcional para tenant sem assinatura → 402 (R4-07).
8. **[PROPOSTO] Pisos de contagem** nos três guardiões de R4-05.

---

## 6. Inspecionado / testado / não testado / limitações

- **Inspecionado:** `CLAUDE.md`, briefing, protocolo §2.2/2.3/4/9; diffs dos commits `b2f151bb`, `1c5a0e54`, `d1b2878b`, `48b29f72`; `ClienteAuthService`, `ConexaoDeOwner`, `AppPerfilController`; `AppLoginSobRlsTest`, `DedupClientesFksTest`, `MobileTest`, `FkTenantAwareTest`, `EnforcementCentralTest`, `RbacContratoTest`, `RlsCoberturaTest`; workflow de CI, `composer.json`, `package.json`/`tsconfig.json` dos apps; telas de perfil/cadastro do app do cliente.
- **Testado [EXEC]:** SPA (vitest + tsc), app do cliente (vitest + tsc), app do entregador (tsc), 9 lotes de backend em sqlite, 2 arquivos só-Postgres no Postgres local como `erp_app`, leitura de flags na VPS.
- **Não testado:** suíte backend integral (baseline do Auditor-Chefe); `TelefoniaTest` e demais arquivos fora dos lotes; o gate `test:pgsql-rls` completo (só rodei os 2 arquivos fora dele); integrações reais (SEFAZ, PSP PIX, banco/CNAB, Firebase, push, Traccar); E2E de UI (sem Playwright no repositório da SPA; a SPA não foi executada no navegador); apps em aparelho/emulador.
- **Limitações:** sqlite não reproduz RLS/grants/tipos do Postgres; o Postgres local é o compartilhado de testes (não rodei migrations nem alterei senha); a contagem de testes "pulados" é estática (grep) e deve ser conferida com o relatório do baseline.

## 7. Parecer R4

**NÃO COMPROVADO.** As jornadas centrais passam nos testes existentes (611 testes rodados nos lotes + 40 do `MobileTest`, zero falhas em sqlite; login dos apps verde também sob RLS local), mas: (a) o typecheck dos dois apps está inoperante e esconde ao menos um defeito real (R4-01/R4-02, CONFIRMADOS); (b) a regressão mais grave recente (login sob RLS) não tem guarda automatizada, e um guardião de integridade de produção está quebrado e nunca executou (R4-03/R4-04); (c) fiscal, cobrança e Firebase só estão provados com drivers Fake, e a licença SaaS só com a flag ligada dentro do teste. Nenhum desses é bloqueador de dado ou segurança por si; são lacunas de prova que impedem declarar as jornadas verificadas no ambiente-alvo.
