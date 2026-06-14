# PRD — Monitoramento / GPS (App\Monitora)  ·  D14

- **Status:** ✅ pronto
- **Criticidade:** 🟡 (operacional — rastreio de entrega; não fiscal)
- **Decisão:** **REESCREVER** (módulo isolado, candidato a UX moderna de mapa)

---

## 1. Escopo
- Módulo `App\Monitora` (ex-app monitoramento-veiculos), schema `monitora`. Já
  unificado (esta sessão). `app/Monitora/{Models,Http/Controllers,Repository,
  Console/Commands}`.
- Rotas `/monitora` (guard próprio `monitora`). Views em `views/monitora`.
- Jobs: `SyncPosicoesSGCasa`, `UpdateClientsLocation`.

## 2. O que o módulo FAZ
- Rastreamento GPS de veículos/entregas: ingestão de posições (fonte externa
  SGCasa), última posição, cercas eletrônicas, rotas, eventos.
- Mapa de entregas (integra com pedidos do ERP — lê schema public).
- Painel de monitoramento em tempo real.

## 3. Como FAZ hoje
- Módulo isolado (schema monitora), guard `monitora`, lê o ERP no mesmo Postgres
  (oracle3 eliminado nesta sessão). Fontes externas GPS via conexões `sgcasa`/`mysql2`.
- Views AdminLTE + JS de mapa (Google Maps) reaproveitando assets do ERP.

## 4. Gambiarras / dívida técnica
- [ ] HTML/JS de mapa legado (funcoes_maps.js etc.) — funcional mas datado.
- [ ] Jobs dependem de fontes externas (sgcasa/mysql2) — credenciais via .env.
- [ ] Models reaproveitados do legado (já ajustados p/ MonitoraModel/conexão).

## 5. Riscos de tocar
- **Baixo** (não fiscal/financeiro). Cuidado com: vínculo `veiculoerp_id` (ERP↔GPS)
  e a ingestão de posições (não perder dados de GPS).

## 6. Estado de compatibilidade Postgres
- ✅ Unificado e validado em produção (login /monitora 200). oracle3 eliminado;
  MySQL-isms (date_format/IF/IFNULL) traduzidos.

## 7. Visão REESCRITA (Laravel 12)
- Reescrever com **mapa moderno** (Leaflet/Mapbox/Google novo) + tempo real
  (WebSocket/broadcasting) em vez de polling.
- Ingestão de posição como pipeline/queue robusto; cercas como geofencing testável.
- UI de painel/rotas moderna; integração limpa com pedidos (mapa de entregas).
- Manter vínculo com frota (D09) e pedidos (D01).

## 8. DECISÃO e justificativa
- **Decisão: REESCREVER.**
- **Por quê:** baixo risco (não fiscal), módulo já isolado, e é onde UX moderna de
  mapa/tempo-real agrega muito. Os jobs de ingestão podem ser modernizados sem
  risco fiscal.
- **Pré-requisitos:** D11 (auth/navegação — embora tenha guard próprio); definir
  stack de mapa/realtime.
- **Esforço:** médio.
- **Ordem:** independente dos fiscais; pode ir após cadastros/frota.
