# PLANO DE EVOLUÇÃO — PERFORMANCE & ESCALABILIDADE

> Corresponde a [AUDITORIA_PERFORMANCE.md](AUDITORIA_PERFORMANCE.md).

## Contexto
Código cuida bem de performance; o risco está na infra de execução (worker, cron, Reverb) e em consultas O(N) em PHP para tenants grandes.

## Objetivo
Ativar a infra assíncrona/tempo-real (pré-go-live) e preparar a escala de proximidade/relatório.

## Benefícios
Funcionalidades assíncronas/tempo-real operantes; latência estável sob volume; escala por tenant grande.

## Riscos
Infra (Redis/Reverb/PostGIS) exige provisionamento e validação em staging. Médio.

## Estratégia e fases

**Fase 1 — Infra de execução (PF-5, PF-6, PF-8)** ⚠️ **pré-go-live**
- Adicionar ao deploy: `queue:work` (supervisor/container), `schedule:run` (cron), serviço **Reverb**.
- Defaults de produção: `QUEUE_CONNECTION=redis`, `BROADCAST_CONNECTION=reverb`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`.

**Fase 2 — Redis (PF-7)**
- Mover cache/fila/sessão para o Redis já presente no compose.

**Fase 3 — Consultas de escala (PF-1, PF-2, PF-3, PF-4)**
- PostGIS: migrar matching de cliente/geofence/proxima-casa para `ST_DWithin` + índice GiST; parar de carregar todos os clientes em PHP.
- Kanban: agregar totais/soma por query (`selectRaw`) e resolver `tem_nf` em batch (não por linha).
- Índice trigram (`pg_trgm`) para busca `ilike`; índices de suporte para relatórios/dashboard.

## Dependências
- Fase 1 é pré-requisito de go-live e destrava PLANO_BACKEND Fase 1 e PLANO_MOBILE Fase 2.
- Fase 3 (PostGIS) coordena com PLANO_BACKEND B-2.

## Checklist técnico
- [ ] Worker + cron + Reverb no deploy
- [ ] Redis para cache/fila/sessão
- [ ] PostGIS + índices GiST para proximidade
- [ ] Kanban agregado por query + batch de NF
- [ ] pg_trgm para busca textual
- [ ] Índices de relatório/dashboard

## Critérios de aceite
- Jobs processados; cron dispara as 11 tarefas; eventos chegam por WebSocket.
- Matching de cliente por geoloc em tempo constante (não O(N)).
- Kanban carrega em tempo estável com muitas situações/pedidos.

## Estratégia de testes
- Health check no smoke de deploy (queue/broadcast). Benchmark de matching antes/depois do PostGIS. Teste de carga no Kanban/relatórios com massa do dump.
