# Inventário da aplicação — PIE V2

**Commit:** `48b29f72` · **Data:** 2026-10-28 · **Método:** leitura de `routes/api.php`,
`database/api-manifest.json` (617 rotas), `app/Domain/*`, `app/Http/Controllers/Api/*`,
`frontend/src/features/*`, `tests/*`, `routes/console.php`.

O **estado** de cada linha está em [`MATRIZ_VERIFICACAO.md`](MATRIZ_VERIFICACAO.md) — aqui
fica só a rastreabilidade. "não identificado" = não encontrei a peça, não que ela não exista.

## Stack e topologia

| Camada | Tecnologia | Onde |
|---|---|---|
| API | Laravel 12, PHP 8.3, Sanctum (`auth:sanctum`), guard `auth:platform` (superadmin) | `erp-novo/app`, `routes/api.php` |
| Banco | PostgreSQL 15 + RLS (runtime `erp_app`, migrations `pgsql_owner`); suíte em sqlite `:memory:` | `database/migrations` (171) |
| SPA admin | React + TypeScript + React Query + Vite | `erp-novo/frontend/src/features` (34 módulos) |
| Apps | Expo/React Native: cliente (`app-gas-em-casa`), entregador (`app-entregador`), `mobile-shared` | raiz |
| Tempo real | Laravel Reverb (broadcast) | `routes/channels.php` |
| Agendador | `schedule:run` (11 comandos) | `routes/console.php` |
| CI/CD | GitHub Actions → GHCR → SSH na VPS (homologação; produção por disparo manual) | `.github/workflows/erp-novo.yml`, `deploy/` |
| Legado | Laravel 5.8 (`ctrl-web/`) + pontes `api/legado/*` para o app Android antigo e o NFweb | fora do escopo de alteração |

## Cadeia de middleware (rotas autenticadas)

`auth:sanctum` → `tenant` (resolve envelope de tenant) → `tenant.saas` → `licenca.rota`
(módulo contratado) → `throttle:api` → no controller `$this->autorizar($request, 'modulo.acao')`
(RBAC/ABAC) → RLS no Postgres. Apps: `approle:cliente|entregador|industrial` + `idempotente`
no entregador. Pontes legadas: `dialeto.legado` + `revenda.legado`. Públicas:
`/health`, `/login`, login/cadastro dos apps, marketplace, `/pix/webhook`, `/pabx/chamada`.

## Rastreabilidade por domínio

| ID | Domínio/módulo | Função/jornada | UI/entrada | API (prefixo · nº rotas) | Controller → serviço | Persistência/integração | Permissão/plano | Testes (evidência) |
|---|---|---|---|---|---|---|---|---|
| INV-01 | Identidade | Login admin, `/me`, logout, troca de empresa | `features/auth` | `api/login`, `api/me`, `api/logout` | `AuthController` → `Domain/Identidade` | `users`, tokens Sanctum | throttle:login | `AuthTenantTest`, `LockoutEntreTenantsTest`, `PoliticaSenhaDonoTest`, `TrocaDeEmpresaRegraTest` |
| INV-02 | Acesso/RBAC/ABAC | Papéis, usuários, alçadas, segurança | `features/acessos`, `seguranca` | `admin/papeis`·9, `usuarios`·5, `seguranca`·9, `alcadas`·4 | `Papel`, `Usuario`, `AlcadaDesconto`, `SegurancaController` → `Domain/Acesso`, `Seguranca` | papéis/permissões | `autorizar()` | `RbacContratoTest`, `Abac*Test`, `MatrizPapeisReaisTest`, `BreakGlassTest`, `FieldLevelTest` |
| INV-03 | Tenant/SaaS | Envelope de tenant, licença, plano, módulos | `features/superadmin` | `superadmin/*`·~33, `admin/assinatura`·1 | `SuperAdmin/*` → `Domain/Saas`, `Tenant` | `tenant_*`, `saas_*` | `auth:platform`, `licenca.rota` | `LicencaEnforcementTest`, `LicenciamentoSaasTest`, `SuperAdminTest`, `MultiTenantIsolamentoTest`, `FaseF02CrossTenantTest`, `RlsCoberturaTest` (Pg) |
| INV-04 | Empresa | Cadastro de empresas/filiais, config fiscal, certificado | `features/empresas`, `configuracoes` | `admin/empresas`·20, `config-global`·2 | `Empresa`, `EmpresaConfig`, `ConfigGlobal` | `empresas` (hub do grafo, 961 arestas) | `autorizar` | `EmpresaTest`, `EmpresaCertificadoTest`, `EmpresaAlvoAutorizacaoTest` |
| INV-05 | Cliente | Cadastro, telefones, convênio, revisão, exportação | `features/clientes` | `admin/clientes`·26 | `Cliente*` → `Domain/Cliente` | `clientes` (92 mil reais na homolog) | `autorizar` | `ClienteTest`, `ClienteExportacaoTest`, `IdentidadeClienteTest`, `SeparacaoClienteColaboradorTest` |
| INV-06 | Pedido/Venda | Pedido por telefone, central de vendas, situação, NFC-e | `features/pedidos`, `central-vendas`, `central` | `admin/pedidos`·18, `central-vendas`·5, `central`·11 | `Pedido`, `CentralVendas`, `Central` → `Domain/Pedido`, `Venda` | `pedidos` (≈400 mil), itens | `autorizar` | `PedidoTest`, `NovoPedidoPraticoTest`, `PedidoSituacaoTest`, `PrecoRevalidadoNaConclusaoTest`, `IdempotenciaTest`, `IdempotenciaSobConcorrenciaTest` |
| INV-07 | Financeiro | Títulos, baixa, conciliação, plano de contas | `features/financeiro` | `admin/financeiro`·25, `conciliacao-contabil`·1 | `Financeiro`, `FinanceiroCadastro` → `Domain/Financeiro` | ≈1,36 mi linhas | `autorizar` | `FinanceiroTest`, `PortaUnicaDeBaixaTest`, `ReconciliacaoFinanceiraTest`, `LedgerIdempotenteTest` |
| INV-08 | Caixa/Cheque/Malote | Abertura/fechamento, cheques, malotes | `features/financeiro` (abas) | `admin/caixa`·11, `cheques`·7, `malotes`·2 | `Caixa`, `Cheque`, `Malote` → `Domain/Caixa` | | `autorizar` | `CaixaTest`, `CaixaServiceTest`, `ChequeServiceTest`, `MaloteTest` |
| INV-09 | Cobrança/Pagamento | Boleto CNAB, PIX, cartões, webhook | `features/pagamentos` | `admin/boletos`·8, `cobranca`·6, `pix`·2, `cartoes`·2, `pix/webhook` | `Boleto`, `Pagamento`, `Pix`, `PixWebhookController` → `Domain/Cobranca`, `Pagamento` | PSP (drivers `Fake*` no CI) | webhook público | `CobrancaTest`, `CnabVetoresOficiaisTest`, `PixDriverTest`, `PixWebhookFailClosedTest` |
| INV-10 | Fiscal | NF-e/NFC-e, DANFE, matriz tributária, SPED, NF entrada, IBPT | `features/fiscal` | `admin/fiscal`·27, `notas`·6, `cupons-fiscais`·3 | `NotaFiscal`, `FiscalConfig`, `NfEntrada`, `InventarioFiscal` → `Domain/Fiscal` | SEFAZ (exige certificado A1) | `autorizar` | `FiscalTest`, `FiscalFailClosedTest`, `DanfePdfTest`, `SpedFiscalTest`, `MatrizTributariaTest` |
| INV-11 | Estoque | Movimento, transferência, inventário, período fechado | `features/estoque` | `admin/estoque`·19 | `Estoque` → `Domain/Estoque` | ledger assinado (507 mil) | `autorizar` | `EstoqueOperacoesTest`, `EstoquePeriodoFechadoTest`, `TransferenciaEstoqueApiTest`, `ConferenciaDeSaldoTest` |
| INV-12 | Comodato | Contratos, devolução parcial, PDF, vigilância | `features/comodatos` | `admin/comodatos`·16 | `Comodato` → `Domain/Contrato` | `comodato_movimentos` | `autorizar` | `Comodato*Test`, `ConferenciaDeCustodiaTest` |
| INV-13 | Produto | Produtos, preços, config, promoções, taxas de entrega | `features/produtos` | `admin/produtos`·6, `produto-config`·8, `produtos-precos`·2, `taxas-entrega`·5 | `Produto*`, `TaxaEntrega` → `Domain/Produto` | | `autorizar` | `ProdutoEstoqueTest`, `ProdutoCadastroFiscalTest`, `NaturezaItemETaxaEntregaTest` |
| INV-14 | Logística/Missões | Roteirização, missões, rotas do entregador | `features/missoes` | `admin/missoes`·9 | `Missao` → `Domain/Logistica`, `Missao` | Google Routes | `autorizar` | `MissaoFluxoTest`, `RoteirizadorServiceTest`, `IniciarRotaTest`, `ViagensRotaTest` |
| INV-15 | Monitora/Frota | Rastreamento (Traccar), posições, veículos, checklists | `features/frota` | `admin/monitora`·20, `veiculos`·18, `checklists`·5 | `Monitora`, `Veiculo` → `Domain/Monitora`, `Frota` | Traccar, posições (expurgo) | `autorizar` | `MonitoraTest`, `TraccarRastreamentoTest`, `MonitoraExpurgoPosicoesTest`, `VeiculoTest` |
| INV-16 | Geográfico | Cidades, logradouros, regiões, cercas, IBGE | `features/geografico` | `geo`·5, `cidades`·4, `logradouros*`·8, `regioes`·4, `municipios-ibge`·4 | `Geo`, `Cidade`, `LogradouroOficial`, `ImportacaoLogradouro` | | `autorizar` | `GeoTest`, `Cerca*Test`, `ImportacaoLogradourosTest` |
| INV-17 | RH | Colaboradores, departamentos, documentos, extrato de remuneração | `features/rh` | `colaboradores`·23, `departamentos`·4, `documentos`·4 | `Colaborador`, `Estrutura` → `Domain/Rh` | PII de funcionários | `autorizar` | `ColaboradorTest`, `ExtratoRemuneracaoTest` |
| INV-18 | CRM/Gestão/Metas | CRM, pós-venda, metas, mala direta, sorteios, gás do povo, vale-gás | `features/crm`, `gestao`, `valegas`, `convenios` | `crm`·1, `pos-vendas`·4, `metas`·4, `sorteios`·6, `gasdopovo`·6, `vale-gas`·6, `convenios`·4 | `Crm`, `Gestao`, `MalaDireta`, `ValeGas`, `Convenio` | | `autorizar` | `CrmTest`, `GestaoTest`, `GasDoPovoProgramaTest`, `ValeGasPdfTest` |
| INV-19 | Relatórios/Dashboard | Relatórios, painel do dia | `features/relatorios`, `dashboard` | `relatorios`·13, `dashboard`·2 | `Relatorio`, `Gestao` → `Domain/Relatorio` | consultas pesadas (RLS por linha) | `autorizar` | `RelatorioTest`, `RelatoriosCentralTest`, `DashboardResumoTest`, `PainelDoDiaTest` |
| INV-20 | Auditoria | Trilha de auditoria | `features/auditoria` | `auditoria`·6, `superadmin/auditoria`·1 | `Auditoria` → `Domain/Auditoria` | | `autorizar` | `TrilhaAuditoriaTest`, `AuditoriaUnificadaTest` |
| INV-21 | Telefonia | PABX: identificação de chamada | `features/central` | `admin/telefonia`·4, `pabx/chamada` (público) | `Telefonia`, `PabxWebhookController` | PABX | webhook | `TelefoniaTest` |
| INV-22 | Satélites | Status de satélites/franqueados, carga de franqueado | `features/satelites` | `satelites`·3, `franqueados`·3 | `SateliteStatus`, `CargaFranqueado` → `Domain/Satelite` | | `autorizar` | `SateliteTest`, `CargaFranqueadoTest` |
| INV-23 | App cliente | Login por telefone, cadastro, pedido, perfil, marketplace | `app-gas-em-casa` | `api/app/v1/*` (73 no total, parte `approle:cliente`) | `Mobile/AppAuth`, `AppPedido`, `AppPerfil`, `Marketplace` → `Domain/Mobile` | push (Firebase — sem chave) | `approle:cliente` | `AppLoginSobRlsTest`, `AppPedidoEscopoClienteTest`, `MarketplaceTest`, `AppAuthHardeningTest` |
| INV-24 | App entregador | Login, rota, entrega, posição GPS, missão, fiscal industrial | `app-entregador` | `api/app/v1/entregador/*` | `AppEntregador`, `AppMissao`, `AppFiscal` | GPS | `approle:entregador` + `idempotente` | `JornadaEntregadorTest`, `RastreamentoEntregadorTest`, `EntregaP7Test` |
| INV-25 | Pontes legadas | API compatível com app Android antigo e NFweb | (apps legados) | `api/legado/*`·11, `legado/nfweb`·18 | `PonteMovelApp`, `PonteNfweb` | | `dialeto.legado`, `revenda.legado` | `PonteLegadoTest`, `PonteNfwebTest`, `UsoDaPonteTest` |
| INV-26 | ETL/Cutover | Migração Oracle→Pg, invariantes, pós-carga, superadmin/migracoes | `features/superadmin` | `superadmin/migracoes`·11 | `MigracaoController`, `ExecutarMigracaoJob` → `app/Etl` | espelho `legado` | `auth:platform` | `tests/Migration/*` (15), `EtlTravaPosCutoverTest`, `PosCheckDoCutoverTest` |
| INV-27 | Agendador | 11 comandos (alertas, posições/30 s, PIX/min, missões/10 min, expurgo, comodato…) | — | — | `app/Console/Commands` | | console sob RLS | `JobsAgendadosTest`, `JobsTratamentoFalhaTest`, `AutomacaoSobRlsTest` (Pg) |
| INV-28 | Tempo real | Broadcast de pedidos/posições | SPA | `routes/channels.php` | Reverb | | autorização de canal | `TempoRealTest`, `TempoRealCruzadoTest` |
| INV-29 | Lookups/Cadastros de apoio | Tabelas auxiliares, inconsistências | `features/cadastros` | `cadastros`·7, `lookups`·1, `unidades`·4, `setores*`·8, `grupos`·4, `bens`·4 | `CadastroApoio`, `Lookup`, `Setor`, `Grupo` | | `autorizar` | `CadastroApoioTest`, `LookupAutorizacaoTest` |
| INV-30 | Saúde/infra | Health check | — | `api/health` | `HealthController` | | throttle 60/min | `GoliveCheckTest`, `BancoProducaoCheckTest` |

**Contagem de testes:** 244 Feature, 31 Domain, 15 Migration, 11 Unit (arquivos) + 119 testes na SPA (último registro, 10/10).
