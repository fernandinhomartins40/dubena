# PLANO DE EVOLUÇÃO — QUALIDADE DO CÓDIGO

> Corresponde a [AUDITORIA_QUALIDADE.md](AUDITORIA_QUALIDADE.md).

## Contexto
Qualidade alta; o maior risco é testar em sqlite o que roda em Postgres (mascara RLS/busca).

## Objetivo
Testar de verdade a camada crítica, reduzir duplicação e cobrir front/mobile.

## Benefícios
Confiança real no isolamento/busca; menos manutenção duplicada; regressão pega cedo.

## Riscos
CI em Postgres é mais lento; mitigar com cache de dependências.

## Estratégia e fases

**Fase 1 — CI em Postgres (Q-6)** ⚠️ pré-dump
- Job de CI com serviço Postgres rodando toda a suíte + `migrate:fresh --seed`; manter sqlite como job rápido paralelo.

**Fase 2 — Deduplicação (Q-1, Q-2, Q-4)**
- Pacote compartilhado dos apps (coordena PLANO_MOBILE); extrair `verificar2fa` para o domínio Seguranca; helper `Geo::haversineKm`.

**Fase 3 — Testes de front/mobile (Q-7)**
- Vitest na SPA (coordena PLANO_FRONTEND); testes de serviço/store nos apps.

**Fase 4 — Higiene (Q-3, Q-5, Q-8, Q-9, Q-10)**
- Canonizar aliases; quebrar arquivos grandes; remover seeders obsoletos; documentar Gate central; FK `pedidos.financeiro_id`.

## Dependências
- Fase 1 destrava PLANO_BANCO e PLANO_MULTI_TENANT (que precisam de PG real).

## Checklist técnico
- [ ] CI com Postgres (suíte + seed)
- [ ] `verificar2fa` unificado
- [ ] Helper de geodistância
- [ ] Vitest na SPA + testes de apps
- [ ] Aliases/arquivos grandes/seeders/FK

## Critérios de aceite
- Suíte verde em Postgres no CI.
- Zero duplicação de `verificar2fa`/Haversine/infra de apps.
- Front e apps com testes rodando no CI.

## Estratégia de testes
- Reexecutar 568 testes em PG; adicionar testes de RLS reais; Vitest + testes de serviço mobile; medir cobertura como baseline.
