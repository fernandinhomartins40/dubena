# Matriz de verificação — PIE V2

**Commit:** `48b29f72` + correções desta auditoria · **Data:** 2026-10-29

Ambientes de prova:
- **S** = suíte em sqlite `:memory:` (sem RLS);
- **P** = PostgreSQL 15 local, runtime `erp_app` (RLS real);
- **V** = VPS de homologação, só leitura;
- **E** = análise estática.

"Verificada" exige teste executado **nesta auditoria** cobrindo o efeito colateral
(estoque, título, nota). Resultado antigo não conta.

| ID | Jornada | Estado | Método e prova | Limite |
|---|---|---|---|---|
| INV-01 | Login admin / troca de empresa | VERIFICADA (S) | `AuthTenantTest`, `LockoutEntreTenantsTest`, `PieSegurancaRegressaoTest` | — |
| INV-02 | RBAC/ABAC | VERIFICADA (S) | `RbacContratoTest` (agora com piso), `Abac*`, `MatrizPapeisReaisTest` | — |
| INV-03 | Isolamento de tenant / RLS | VERIFICADA (P) | Portão RLS: 26/26, 0 pulados (`RlsCoberturaTest`, `GrantsSomenteLeituraTest`, `AppLoginSobRlsTest`…) | Banco local, não a homologação |
| INV-03b | Suspensão de empresa | VERIFICADA (S) | `test_r1_03_*` (bloqueia e reativa) | Em sqlite; a leitura de `empresas` sob RLS no Pg não foi exercida por este teste |
| INV-03c | Licença por módulo | IMPLEMENTADA, NÃO VERIFICADA em ambiente | `LicencaEnforcementTest` liga a flag dentro do teste; a homologação roda com a flag **desligada** (V) | PIE-21 |
| INV-05 | Cliente: cadastro, busca, exportação | VERIFICADA (S) | `ClienteTest`, `ClienteExportacaoTest`, `LookupTest` (telefone) | A listagem não busca por telefone |
| INV-06 | Pedido: criar, mudar situação, cancelar | VERIFICADA (S) | `PedidoTest`, `PedidoSituacaoTest`, `PieDinheiroRegressaoTest` (7 cenários) | Corrida real só provável em Pg: o lock foi conferido por leitura, não por concorrência |
| INV-06b | Pedido ponta a ponta (telefone → entrega → malote → caixa) | PARCIAL | Cada elo tem teste verde; não existe teste encadeado (R4-08) | — |
| INV-07 | Financeiro: baixa, agrupar, reparcelar | VERIFICADA (S) | `FinanceiroTest`, `PortaUnicaDeBaixaTest`, `PieDinheiroRegressaoTest` | — |
| INV-08 | Caixa / cheque / malote | VERIFICADA (S) | `CaixaTest`, `CaixaServiceTest`, `MaloteTest` | Datas locais: `format.test.ts` |
| INV-09 | PIX / boleto | VERIFICADA com driver Fake (S) | `PixDriverTest`, `PixWebhookFailClosedTest`, `CobrancaTest` | PSP real BLOQUEADO (sem credencial); PIE-11 e PIE-24 pendentes |
| INV-09b | Pagamento online do app ↔ título | FALHA REPRODUZIDA (E) | R3-08: nenhum listener baixa o título do pedido | PIE-11 |
| INV-10 | Fiscal NF-e/NFC-e | VERIFICADA com driver Fake (S) | `FiscalTest`, `PedidoNfceTest`, `test_r3_05_e_r3_07_*` | SEFAZ real BLOQUEADA (sem A1); **driver real com defeito** (PIE-02, FORTE INDÍCIO) |
| INV-11 | Estoque | VERIFICADA (S) | `EstoqueOperacoesTest`, `TransferenciaEstoqueApiTest`, `test_r3_01_*` (devolução ao setor de origem) | Divergências herdadas na carga real (32) não reexaminadas |
| INV-12 | Comodato | VERIFICADA (S) | `Comodato*Test` (lote do R4: verdes) | — |
| INV-13..22 | Produto, logística, monitora, geo, RH, CRM, relatórios, auditoria, telefonia, satélites | VERIFICADA (S) no nível dos testes existentes | Baseline 1951 verdes; lotes do R4: 611 verdes | Sem revisão adversarial específica nesta execução |
| INV-23 | App do cliente | PARCIAL | `MobileTest`, `MarketplaceTest`, `AppLoginSobRlsTest` (P), vitest 17/17, `tsc` exit 0 | PIE-11, PIE-33 pendentes; sem aparelho real |
| INV-24 | App do entregador | IMPLEMENTADA, NÃO VERIFICADA (app) | Backend: `JornadaEntregadorTest`, `EntregaP7Test`. App: só `tsc` exit 0; **zero testes** | Fila offline corrigida sem teste automatizado |
| INV-25 | Pontes legadas | VERIFICADA (S) | `PonteLegadoTest`, `PonteNfwebTest` | A ponte MovelApp continua sem restrição de origem na mudança de situação |
| INV-26 | ETL / cutover | PARCIAL | `tests/Migration/*` (S); `DedupClientesFksTest` (P) | Legado real e ensaio de cutover BLOQUEADOS nesta execução |
| INV-27 | Agendador | VERIFICADA (V) por observação | R5: 0 FAIL em 24 h; tarefas de 0,3 a 2 s | 135 `failed_jobs` antigos (PIE-29) |
| INV-28 | Tempo real | VERIFICADA (S) | `TempoRealTest`, `TempoRealCruzadoTest` | Reverb com clientes conectados não testado |
| INV-30 | Backup / restore | FALHA REPRODUZIDA (V) | Sem cron e sem dump; script de produção apontava para a homologação | PIE-12 corrigido no script; PIE-13 depende do dono |
| INV-31 | Deploy / rollback | IMPLEMENTADA, NÃO VERIFICADA | Leitura de `remote-deploy.sh` e `rollback.sh` | Nenhum deploy executado nesta auditoria |
