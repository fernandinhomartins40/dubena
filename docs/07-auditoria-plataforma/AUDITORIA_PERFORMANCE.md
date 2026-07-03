# AUDITORIA — PERFORMANCE & ESCALABILIDADE

> Base: services, controllers, migrations de índice, config de fila/cache/broadcast, deploy.

## 1. Consultas

**Boas práticas presentes:**
- Índices compostos por padrão de acesso (ver AUDITORIA_BANCO §3).
- Eager loading seletivo com colunas explícitas (`with(['cliente:id,nome', 'situacao:id,descricao,efeito'])`) em Pedido/Central/Mobile — evita N+1 e over-fetch.
- Paginação server-side (`paginate(20)`) nas listas grandes.

**Gargalos identificados:**

| ID | Prio | Gargalo | Evidência | Impacto |
|---|---|---|---|---|
| PF-1 | **P2** | Matching de cliente por **geoloc/telefone carrega TODOS os clientes da empresa e filtra em PHP** | [PedidoMobileService::clientePorGeoloc](../../erp-novo/app/Domain/Mobile/PedidoMobileService.php), [ClienteAuthService::clientePorTelefone](../../erp-novo/app/Domain/Mobile/ClienteAuthService.php), [MissaoService::proximaCasa](../../erp-novo/app/Domain/Missao/MissaoService.php) | O(N) por request; num tenant com 50k clientes cada pedido/login varre tudo. OK para praça pequena, quebra na escala |
| PF-2 | **P2** | Kanban carrega até 50 pedidos **por coluna** com contagem/soma em PHP, e ainda faz um `exists()` de NF por linha na serialização | [PedidoController::kanban](../../erp-novo/app/Http/Controllers/Api/Admin/PedidoController.php), `tem_nf` em [PedidoResource](../../erp-novo/app/Http/Resources/PedidoResource.php) | N colunas × 50 + N queries de NF; pesa com muitas situações/pedidos |
| PF-3 | **P3** | `dashboardResumo` e relatórios agregam por query — verificar índices de suporte (datas/empresa) sob volume real | [RelatorioController](../../erp-novo/app/Http/Controllers/Api/Admin/RelatorioController.php) | Latência de dashboard |
| PF-4 | **P3** | Busca `ilike '%q%'` sem índice trigram | Cliente/Pedido/Usuario | Full scan em busca textual |

## 2. Processamento assíncrono / filas

- **PF-5 (P1 ops)**: `QUEUE_CONNECTION=database` no default e **sem worker no [docker-compose.homolog.yml](../../erp-novo/docker-compose.homolog.yml)** (só app/web/db/redis). Jobs (`AtribuirPedidoJob`, `EnviarPushJob`, `GeocodificarClienteJob`) **não são processados** → auto-atribuição, push e geocodificação ficam pendentes indefinidamente. Redis está no compose mas a fila não o usa por padrão.
- **PF-6 (P1 ops)**: Scheduler (`schedule:run`) não evidenciado no deploy — 11 tarefas agendadas (expirar PIX, gerar missões, vencidos) não rodam sem cron.

## 3. Cache

- `CACHE_STORE=database` default; **Redis disponível** mas não é o store padrão. [TenantCache](../../erp-novo/app/Domain/Shared/TenantCache.php) namespaced por tenant e [LicencaService](../../erp-novo/app/Domain/Saas/LicencaService.php) cacheia recursos (TTL 5 min). Google Routes tem cache in-memory + cache **persistente** por célula ([TracadorRotaCacheado](../../erp-novo/app/Domain/Logistica/Drivers/TracadorRotaCacheado.php)) — excelente para custo/latência.
- **PF-7 (P2)**: mover cache/fila/sessão para **Redis** em produção; `database` não escala em concorrência.

## 4. Tempo real

- **PF-8 (P1 ops)**: `BROADCAST_CONNECTION=log` default e **sem serviço Reverb** no compose → eventos (`pedido.status`, `entregador.posicao`, `PixConfirmado`) não trafegam; apps caem para polling. O código está pronto; falta a infra.

## 5. Roteirização / logística (bem resolvido)

- Nearest-neighbor usa **sempre Haversine local (zero rede)** na ordenação O(N²); a Google Routes entra só no traçado final dos trechos escolhidos, com **circuit breaker** (uma falha corta 5 min) + cache persistente ([RoteirizadorService](../../erp-novo/app/Domain/Logistica/RoteirizadorService.php), [GoogleRoutesDriver](../../erp-novo/app/Domain/Logistica/Drivers/GoogleRoutesDriver.php)). Isso corrigiu um timeout de 22s documentado — desenho de performance maduro.

## 6. Escalabilidade

- **Stateless** (tenant por token, não por sessão preso) → escala horizontal de app viável.
- **RLS por conexão** exige que cada worker/instância conecte como `erp_app` — validar no autoscaling.
- **PostGIS ausente**: geofencing/geoloc em PHP (Haversine/ray-casting) limita a escala de matching (PF-1). Para tenants grandes, migrar consultas de proximidade para PostGIS (`ST_DWithin` com índice GiST).

## 7. Achados priorizados (resumo)

| ID | Prio | Tema |
|---|---|---|
| PF-5 | **P1** | Sem worker de fila em produção |
| PF-6 | **P1** | Sem scheduler (cron) em produção |
| PF-8 | **P1** | Sem Reverb (tempo real inerte) |
| PF-1 | **P2** | Matching geoloc/telefone O(N) em PHP |
| PF-2 | **P2** | Kanban O(N) + exists por linha |
| PF-7 | **P2** | Redis para cache/fila/sessão |
| PF-3/4 | **P3** | Índices de relatório + trigram para busca |

## 8. Conclusão

O **código** trata performance com cuidado (eager loading, índices, cache persistente, circuit breaker, Haversine local). O risco real está na **infraestrutura de execução**: sem worker, cron e Reverb, funcionalidades assíncronas/tempo-real ficam inertes em produção — os três P1 são **pré-requisito de go-live**, não otimização. Em seguida, PostGIS/Redis destravam a escala por tenant grande.

→ Plano: [PLANO_PERFORMANCE.md](PLANO_PERFORMANCE.md)
