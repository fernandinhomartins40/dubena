# PLANO DE EVOLUÇÃO — BACKEND

> Corresponde a [AUDITORIA_BACKEND.md](AUDITORIA_BACKEND.md).

## Contexto
Camada de domínio exemplar; lacunas em borda/ops (worker, scheduler, broadcast) e higiene.

## Objetivo
Ativar o processamento assíncrono/agendado em produção e reduzir dívida de borda.

## Benefícios
Auto-atribuição, push, geocodificação, PIX-expira e missões passam a funcionar; controllers mais enxutos.

## Riscos
Subir worker/Reverb é operacional (infra), baixo risco de código.

## Estratégia e fases

**Fase 1 — Ops de execução (B-6, B-5)** ⚠️ pré-go-live (ver PLANO_PERFORMANCE)
- Adicionar container/serviço `queue:work` (supervisor) e `schedule:run` (cron) ao deploy.
- Trocar defaults de produção: `QUEUE_CONNECTION=redis`, `BROADCAST_CONNECTION=reverb`, `CACHE_STORE=redis`.

**Fase 2 — Auditoria e higiene (B-3, B-4)**
- `Auditavel` nos models financeiros (coordenar com PLANO_SEGURANCA S-4).
- Documentar a decisão do Gate central (diretório `app/Policies` vazio).

**Fase 3 — Refino de controllers (B-1)**
- Extrair sub-controllers de [AppClienteController](../../erp-novo/app/Http/Controllers/Api/Mobile/AppClienteController.php) (Perfil/Endereço/Pedido/Pagamento).

**Fase 4 — Escala de consultas (B-2)** (coordenar com PLANO_PERFORMANCE)
- Substituir filtros O(N) em PHP (geoloc/telefone) por consulta indexada/PostGIS.

## Dependências
- Fase 1 depende de infra (Redis/Reverb/cron) — ver PLANO_PERFORMANCE.

## Checklist técnico
- [ ] Worker de fila no deploy
- [ ] Scheduler (cron) no deploy
- [ ] Redis/Reverb como defaults de produção
- [ ] `Auditavel` financeiro
- [ ] Sub-controllers mobile
- [ ] Consultas de proximidade indexadas

## Critérios de aceite
- `/health` reporta queue=redis, broadcast=reverb.
- Um pedido novo em modo `auto` é atribuído sem intervenção (job processado).
- PIX vencido expira via cron.

## Estratégia de testes
- Testes de job (assert dispatched + handle). Teste de integração do agendamento (comandos executam). Health check no smoke test de deploy.
