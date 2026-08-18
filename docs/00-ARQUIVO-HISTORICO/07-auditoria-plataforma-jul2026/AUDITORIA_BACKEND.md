# AUDITORIA — BACKEND (Laravel)

> Base: `app/Domain` (28 domínios, ~12.3k linhas), `app/Http` (~9.3k), `app/Models` (~5.3k), `app/Etl` (~3.5k). Laravel 12, PHP ≥ 8.2.

## 1. Controllers

- **HTTP fino**: validam, autorizam (`autorizar()`), delegam ao service, serializam. Ex.: [PedidoController](../../../erp-novo/app/Http/Controllers/Api/Admin/PedidoController.php) — CRUD + kanban + transição, toda regra de estoque/financeiro no [PedidoService](../../../erp-novo/app/Domain/Pedido/PedidoService.php).
- **Padrão registry**: cadastros de apoio e geográficos usam um controller genérico parametrizado por `tipo`/`entidade` ([CadastroApoioController](../../../erp-novo/app/Http/Controllers/Api/Admin/CadastroApoioController.php) + [CadastroApoioRegistry](../../../erp-novo/app/Domain/Apoio/CadastroApoioRegistry.php)) — adicionar cadastro é 1 linha.
- **Achado B-1 (P3)**: controllers mobile grandes ([AppClienteController](../../../erp-novo/app/Http/Controllers/Api/Mobile/AppClienteController.php) 572 linhas) misturam ~20 endpoints; extrair sub-controllers (Perfil/Endereço/Pedido) melhora manutenção.

## 2. Services (o coração)

Excelente disciplina transacional e de invariantes:
- **[EstoqueService](../../../erp-novo/app/Domain/Estoque/EstoqueService.php)**: toda mutação por `movimentar()` com `lockForUpdate`, saldo assinado + `saldo_resultante`, custo médio ponderado; invariante `Σ histórico = saldo`.
- **[CaixaService](../../../erp-novo/app/Domain/Caixa/CaixaService.php)** (risco máximo): lock por conta, valor líquido sensível a centavo, estorno gera movimento inverso (não deleta), lançamento em caixa fechado só via flag explícita; invariante `Σ movimentos = saldo_atual`.
- **[FinanceiroService](../../../erp-novo/app/Domain/Financeiro/FinanceiroService.php)**: único gerador de financeiro; idempotência por `(origem, origem_id)`; agrupar/desagrupar/reparcelar via enum de status; `Σ parcelas = valor`.
- **[FiscalService](../../../erp-novo/app/Domain/Fiscal/FiscalService.php)**: numeração sob lock ([NumeroSequencialService](../../../erp-novo/app/Domain/Shared/NumeroSequencialService.php)), SEFAZ isolada por driver, cálculo tributário porta fiel do legado ([CalculoImpostoService](../../../erp-novo/app/Domain/Fiscal/CalculoImpostoService.php): ICMS/ST/FCP/DIFAL/PIS/COFINS/IPI).
- **[PedidoService](../../../erp-novo/app/Domain/Pedido/PedidoService.php)**: máquina de estados que decide baixa/devolução de estoque + geração/estorno de financeiro na transição, idempotente por `estoque_movimentado`.

**Achado B-2 (P4)**: alguns services fazem trabalho O(N) em PHP sobre coleções carregadas (ver Performance): `clientePorGeoloc`, `clientePorTelefone`, `proximaCasa` carregam todos os clientes da empresa e filtram em memória. Correto para praças pequenas; revisar para tenants grandes.

## 3. Models

- Traits transversais bem desenhados: `BelongsToTenant`, `BelongsToGrupo`, `Auditavel` (auditoria automática, exclui campos `encrypted`), `HasApiTokens`.
- Casts nativos (decimal/date/boolean) consistentes — sem string-BR no runtime.
- **Achado B-3 (P3)**: `Auditavel` aplicado a 8 models; incluir os financeiros críticos.

## 4. Policies

- **Não há classes Policy** no diretório `app/Policies` (vazio). A autorização é centralizada no **Gate por chave** ([AuthServiceProvider](../../../erp-novo/app/Providers/AuthServiceProvider.php)) + [PolicyEvaluator](../../../erp-novo/app/Domain/Acesso/PolicyEvaluator.php). É uma decisão arquitetural válida (um ponto de verdade), mas o nome do diretório engana — **Achado B-4 (P4)**: documentar/renomear para evitar a expectativa de Policies Eloquent.

## 5. Middlewares

- [ResolveTenant](../../../erp-novo/app/Http/Middleware/ResolveTenant.php) (tenant + RLS), [Permissao](../../../erp-novo/app/Http/Middleware/Permissao.php) (RBAC de borda → 403), [Recurso](../../../erp-novo/app/Http/Middleware/Recurso.php) (licença → 402). Bem separados; delegam ao mesmo Gate. `statefulApi` + JSON-sempre no bootstrap.

## 6. Jobs, Events, Listeners

- **Jobs**: [AtribuirPedidoJob](../../../erp-novo/app/Domain/Logistica/Jobs/AtribuirPedidoJob.php), `GeocodificarClienteJob`, `EnviarPushJob` (push assíncrono via FCM v1), `NotificarEstoqueBaixoJob`. Todos com re-set de tenant onde aplicável.
- **Events**: `PedidoStatusAtualizado`, `PedidoEntrouNaFila`, `PedidoAtribuido`, `EntregadorPosicaoAtualizada`, `PixConfirmado` — disparados **após commit** (não emitem em rollback). Bom.
- **Achado B-5 (P3)**: `QUEUE_CONNECTION=database` e `BROADCAST_CONNECTION=log` nos defaults; sem worker/Reverb no compose de homolog. Os jobs/eventos existem mas **não são processados** sem worker → push/tempo real/auto-atribuição ficam inertes. Ver Performance/Ops.

## 7. Commands (scheduler)

11 commands agendados ([console.php](../../../erp-novo/routes/console.php)): alertas de estoque, sync GPS, expirar PIX, vencidos financeiros, venda diária, inconsistências, IBPT, **gerar missões (a cada 10 min)**. Todos `withoutOverlapping`. **Achado B-6 (P2)**: dependem de `schedule:run` no cron — não há evidência de container/cron no deploy de homolog; confirmar antes do go-live.

## 8. ETL

`app/Etl` com Readers/Transformers/Loaders/Migrators (20 migrators por domínio) + Invariantes (Count/Sum/Balance/Integrity) e runner [EtlRun](../../../erp-novo/app/Console/Commands/EtlRun.php) com `--dry-run` e `--check`. É o portão de cutover para o dump real — bem estruturado.

## 9. Achados

| ID | Prio | Achado | Recomendação |
|---|---|---|---|
| B-6 | **P2** | Scheduler/worker não evidenciados no deploy | Adicionar `schedule:run` (cron) + `queue:work` (supervisor/container) |
| B-5 | **P3** | Fila/broadcast em modo dev nos defaults | Redis + Reverb em produção; validar no health |
| B-1 | **P3** | Controllers mobile grandes | Extrair sub-controllers |
| B-3 | **P3** | Auditoria seletiva | Incluir models financeiros |
| B-2 | **P4** | Filtros O(N) em PHP | Indexar/PostGIS para tenants grandes |
| B-4 | **P4** | Diretório Policies vazio confunde | Documentar decisão do Gate central |

## 10. Conclusão

Backend **exemplar na camada de domínio**: services com transações, locks, idempotência e invariantes explícitas — o núcleo financeiro/estoque/fiscal é robusto. As lacunas são de **borda/ops** (worker+scheduler+Reverb) e de **higiene** (FormRequests/Resources, tamanho dos controllers mobile). Nenhuma regra de negócio crítica está frágil.

→ Plano: [PLANO_BACKEND.md](PLANO_BACKEND.md)
