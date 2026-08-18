# Auditoria Técnica da Plataforma — Índice

> Engenharia reversa e auditoria técnica do ERP-NOVO (backend + SPA), apps mobile (consumidor + entregador), Monitora e demais módulos. **Fonte da verdade: o código-fonte.** Julho/2026.
> Legado (`ctrl-web`) fora do escopo. Divergências documentação × implementação registradas em cada auditoria.

## Como foi feita
Leitura do código (393 PHP em `app/`, 74 migrations, 167 TS/TSX na SPA, ~115 nos apps), execução da suíte backend (**568 testes / 1859 assertions verdes**, sqlite in-memory) e inspeção do deploy (compose/CI). Cada conclusão tem evidência (arquivo/classe/método).

## Status de implementação (atualizado após a auditoria)

Os achados **P1 e parte dos P2 já foram implementados** (commits na `main`). Resumo:

| Achado | Status | O que foi feito |
|---|---|---|
| Q-6 (testar em Postgres) | ✅ Feito | Suíte roda em PG com role NÃO-superuser + job de CI `test-postgres`. Revelou e corrigiu bugs latentes abaixo. Suíte: **573 verdes em sqlite E em Postgres**. |
| DB-1 (PK role_user nullable) | ✅ Feito | PK própria (`id`) + índices únicos parciais; migration de conversão p/ bancos existentes; teste `PapelGlobalTest`. |
| MT-1/MT-2 (RLS/role restrita) | ✅ Feito | `golive:check` FALHA se runtime é SUPERUSER/BYPASSRLS; policy RLS cast-safe; `ResolveTenant::terminate` limpa GUCs; tabelas de auditoria na allowlist; recobertura de tabelas novas. |
| PF-5/6/8 (worker/cron/Reverb) | ✅ Feito | Containers `queue`, `scheduler`, `reverb` no compose; `laravel/reverb` instalado; `ext-sodium` no Dockerfile; `golive:check` cobre fila/broadcast/cache. |
| S-1 (webhook PIX HMAC) | ✅ Feito | Assinatura HMAC-SHA256 sobre o corpo cru (flag `PIX_WEBHOOK_HMAC_SECRET`) + testes. |
| S-2 (download por tenant) | ✅ Feito | Evidência de missão passa a usar o tenant ativo (global scope), corrigindo multi-empresa. |
| S-3 (MIME de upload) | ✅ Feito (parcial) | Certificado restrito a `pfx/p12`; fotos já usavam `image`. |
| S-4 (auditoria financeira) | ✅ Feito | `Auditavel` em `ContaMovimento` e `FinanceiroParcela`. |
| PF-2 (Kanban N+1) | ✅ Feito | `withExists('notasVivas')` + agregado por query (totais reais, não só os 50). |
| API-5 (`?per_page`) | ✅ Feito | Paginação parametrizável com teto em pedidos. |
| Q-2 (dedupe 2FA) | ✅ Feito | `VerificadorDoisFatores` — ponto único (web + app). |
| FE-1 (ErrorBoundary) | ✅ Feito | Boundary por rota na SPA (sem mais "tela branca"). |
| PF-1/B-2 (geoloc O(N)) | ✅ Feito | Bounding box indexado no SQL + refino Haversine; `proximaCasa` por anéis expansíveis; índice lat/lng + trigram (PF-4). |
| Q-4 (Haversine duplicado) | ✅ Feito | `Domain/Shared/Geo` — ponto único (6 services) + `GeoTest`. |
| DB-6 (FK financeiro) | ✅ Feito | `pedidos.financeiro_id → financeiros` (nullOnDelete). |
| S-7 (bypass support) | ✅ Feito | Login de `support` vai para `security_events`. |
| S-8 (CORS) | ✅ Feito | `config/cors.php` explícito (origens restritas, sem `*`). |
| FE-3 (testes SPA) | ✅ Feito | Vitest + job de CI `frontend` (RBAC, formatadores, `Can`, `ErrorBoundary`) — 16 testes. |
| M-5 (testes app) | 🟡 Parcial | Vitest no app do consumidor (validadores puros + polyline, 11 testes); corrigido bug de `validateCpf`. |
| DB-5 (pix índices) | ✅ Já existia | `txid` unique + `pedido_id` indexado desde a migration de cobrança. |

### 3ª onda (P2/P3 restantes)

| Achado | Status | O que foi feito |
|---|---|---|
| MT-3/DB-4 (empresa_id NOT NULL nas filhas) | ✅ Feito | Migration `..._000600` fixa NOT NULL nas 27 filhas backfilladas (pula com aviso se houver linha órfã — não aborta o deploy). Fecha o "empresa_id NULL visível a todos" na RLS. |
| PF-3 (índice de relatório) | ✅ Feito | `financeiroparcelas(empresa_id, datahora_baixa)` para o DRE (os demais relatórios já tinham índice de data). |
| M-6 (localização fixa no app) | ✅ Feito | O mapa de endereço centraliza na REVENDA (endpoint `reseller`), caindo no default só até carregar; recentra se o usuário não moveu. |
| FE-2 (RBAC stale) | ✅ Feito | `staleTime` do `/me` de 5 min → 60s + `refetchOnWindowFocus` — revogação de papel reflete rápido na UX. |
| API-7 (limiters ad-hoc) | ✅ Feito | Limiters NOMEADOS (`marketplace`, `gps-ping`, `missao-visita`) no lugar dos `throttle:60,1`/`120,1`/`30,1` inline. |
| Q-8 (seeder obsoleto) | ✅ Feito | Removido `GuarapuavaMapaSeeder` (órfão, sem teste/referência). `HomologSeeder` mantido (tem teste). |

### 4ª onda (P2/P3 restantes)

| Achado | Status | O que foi feito |
|---|---|---|
| API-2 (aliases) | ✅ Feito (parcial) | Removidos os aliases de ESCRITA `cheques/recebidos` (SPA migrada p/ `/cheques`). Os "aliases" `cobranca/*` e `conciliacao-contabil` são mantidos: o `F14RastreabilidadeTest` os exige como contrato de paridade; `fiscal/nfe/transmitir` é único (não dup). |
| API-3 (contrato vivo) | ✅ Feito | `database/api-manifest.json` (443 endpoints) + comando `api:manifest [--check]` + `ApiContratoDriftTest` — endpoint removido do contrato quebra o CI; novo pede regenerar. |
| B-1 (controller mobile 572L) | ✅ Feito | `AppClienteController` dividido em `AppLojaController` / `AppPerfilController` / `AppPedidoController` + trait `ResolveClienteDoApp`; rotas/contrato idênticos (manifest íntegro). |
| FE-4 (página 455L) | ✅ Feito | `PedidosPage` dividida em `KanbanView` / `ListaView` / `PedidoDialogs` + `shared` (shell de 24 linhas). |
| FE-5 (build commitado) | ✅ Feito | Deploy builda a SPA do fonte (Docker node:20) e `public/app` saiu do versionamento (gitignore) — fim do "esquecer de rodar o build". |

| M-2/Q-1 (infra duplicada dos apps) | ✅ Feito | `mobile-shared/` com núcleo HTTP store-agnóstico (`createHttp`) + validadores; os dois apps viraram adaptadores finos. tsc dos dois apps sem erro novo. |
| API-1 (envelope) | ✅ Feito | Único outlier (`GET clientes/{id}/convenio` devolvia campos no topo) padronizado para `{data}` — backend + SPA + teste. Demais respostas já eram `{data,meta}`. |

Pendências finais: **S-1** requer o segredo real do PSP no `.env` (código pronto). **M-3** (tempo real dos apps) e **PostGIS** dependem de infra/ops de produção (Reverb, extensão no PG da VPS). `realtime`/`storage` dos apps podem migrar ao `mobile-shared` numa próxima iteração (mesmo padrão do HTTP).

## Documentos

| Área | Auditoria | Plano |
|---|---|---|
| Arquitetura | [AUDITORIA_ARQUITETURA](AUDITORIA_ARQUITETURA.md) | [PLANO_ARQUITETURA](PLANO_ARQUITETURA.md) |
| Segurança | [AUDITORIA_SEGURANCA](AUDITORIA_SEGURANCA.md) | [PLANO_SEGURANCA](PLANO_SEGURANCA.md) |
| Banco de Dados | [AUDITORIA_BANCO](AUDITORIA_BANCO.md) | [PLANO_BANCO](PLANO_BANCO.md) |
| Multi-Tenancy | [AUDITORIA_MULTI_TENANT](AUDITORIA_MULTI_TENANT.md) | [PLANO_MULTI_TENANT](PLANO_MULTI_TENANT.md) |
| Backend | [AUDITORIA_BACKEND](AUDITORIA_BACKEND.md) | [PLANO_BACKEND](PLANO_BACKEND.md) |
| API | [AUDITORIA_API](AUDITORIA_API.md) | [PLANO_API](PLANO_API.md) |
| Frontend SPA | [AUDITORIA_FRONTEND](AUDITORIA_FRONTEND.md) | [PLANO_FRONTEND](PLANO_FRONTEND.md) |
| Apps Mobile | [AUDITORIA_MOBILE](AUDITORIA_MOBILE.md) | [PLANO_MOBILE](PLANO_MOBILE.md) |
| Performance | [AUDITORIA_PERFORMANCE](AUDITORIA_PERFORMANCE.md) | [PLANO_PERFORMANCE](PLANO_PERFORMANCE.md) |
| Qualidade | [AUDITORIA_QUALIDADE](AUDITORIA_QUALIDADE.md) | [PLANO_QUALIDADE](PLANO_QUALIDADE.md) |

## Veredito geral

Plataforma **madura e bem construída**: monólito modular Laravel 12 com fronteiras de domínio claras, núcleo financeiro/estoque/fiscal robusto (transações, locks, idempotência, invariantes), multi-tenancy à prova de vazamento no desenho (3 barreiras), RBAC+ABAC com field-level, SPA e apps modernos e seguros. Débito técnico concentrado em **formalização de contrato**, **duplicação** e, sobretudo, **infra de execução em produção**.

## Achados críticos consolidados (ordenados por prioridade)

### P1 — resolver antes do go-live
| ID | Achado | Documento |
|---|---|---|
| PF-5/PF-6/PF-8 | Sem **worker de fila**, **scheduler (cron)** e **Reverb** no deploy → jobs, tarefas agendadas e tempo real inertes | [Performance](AUDITORIA_PERFORMANCE.md) |
| MT-1/MT-2 | RLS só protege se o runtime conectar como `erp_app` (sem BYPASSRLS); risco de configuração silenciosa | [Multi-Tenant](AUDITORIA_MULTI_TENANT.md) |
| DB-1 | PK de `role_user` com `empresa_id` nullable → papel global falha no Postgres | [Banco](AUDITORIA_BANCO.md) |
| Q-6 | Suíte roda em **sqlite**; RLS e `ilike` (produção Postgres) não são testados de verdade | [Qualidade](AUDITORIA_QUALIDADE.md) |
| S-1 | Webhook PIX sem assinatura HMAC/mTLS do PSP (bloqueante só para go-live transacional real) | [Segurança](AUDITORIA_SEGURANCA.md) |

### P2 — antes ou logo após a homologação
- **API-1/API-2**: shape de resposta inconsistente + aliases duplicados ([API](AUDITORIA_API.md)).
- **PF-1/PF-2**: matching geoloc/telefone e Kanban O(N) em PHP ([Performance](AUDITORIA_PERFORMANCE.md)).
- **S-2/S-3/S-4**: reescopo de tenant em downloads, validação de MIME, auditoria financeira ([Segurança](AUDITORIA_SEGURANCA.md)).
- **FE-1/FE-3**: Error Boundary + testes de frontend ([Frontend](AUDITORIA_FRONTEND.md)).
- **M-2/Q-1**: duplicação de infra entre os apps mobile ([Mobile](AUDITORIA_MOBILE.md)).
- **MT-3/DB-4**: `NOT NULL` nas filhas com `empresa_id` ([Multi-Tenant](AUDITORIA_MULTI_TENANT.md)/[Banco](AUDITORIA_BANCO.md)).

## Sequência recomendada para a homologação

1. **CI/testes em Postgres** (Q-6) — pré-requisito de tudo (revela DB-1, DB-2, RLS).
2. **Infra de execução** (PF-5/6/8) — worker + cron + Reverb + Redis.
3. **Blindar RLS no go-live** (MT-1/2) via `GoliveCheck`.
4. **Corrigir PK do role_user** (DB-1) + `NOT NULL` nas filhas (MT-3/DB-4).
5. **Assinatura do webhook PIX** (S-1) antes de transacionar com PSP real.
6. Carregar o **dump** via ETL (`etl:run --check`) validando invariantes.
7. Contrato/API/Frontend/Mobile (P2) em seguida.

## Pontos fortes a preservar
- Máquina de estados explícita do pedido; invariantes de estoque/caixa/financeiro.
- Ports & adapters para todo externo (SEFAZ, PSP, Google, Firebase, SGCasa) com gate.
- 3 barreiras de tenant + role restrita + cache namespaced + broadcast por posse.
- ETL com invariantes como portão de cutover.
- Cobertura de testes backend ampla e verde.
