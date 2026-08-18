# PLANO DE EVOLUÇÃO — MULTI-TENANCY

> Corresponde a [AUDITORIA_MULTI_TENANT.md](AUDITORIA_MULTI_TENANT.md).

## Contexto
Isolamento à prova de vazamento no desenho (3 barreiras). Risco residual é operacional (garantir a role restrita) e de dados legados (empresa_id nullable).

## Objetivo
Garantir que a Barreira 2 (RLS) esteja ativa em produção e higienizar linhas sem tenant.

## Benefícios
Isolamento efetivo (não só de aplicação) em runtime; sem linhas visíveis a todos os tenants.

## Riscos
Se a role `erp_app` não existir/estiver mal configurada, o app não conecta — testar em staging. Médio.

## Estratégia e fases

**Fase 1 — Blindagem do go-live (MT-1, MT-2)** ⚠️ bloqueante
- `GoliveCheck` (comando) que ABORTA se: `current_user` for superuser/BYPASSRLS, ou a role `erp_app` não existir, ou `RLS_APP_DB_PASSWORD` vazio.
- Deploy: garantir `DB_USERNAME=erp_app` no runtime e `--database=pgsql_owner` só nas migrations.

**Fase 2 — Higiene de dados (MT-3)**
- `SET NOT NULL` nas filhas após backfill (coordenado com PLANO_BANCO Fase 3), removendo o caminho `empresa_id NULL → visível a todos`.

**Fase 3 — Testes de isolamento em Postgres (MT-4, MT-5)**
- Rodar `FaseF02CrossTenantTest` + novos casos **em Postgres** (RLS é no-op no sqlite). Casos: query crua de outra empresa retorna vazio; papel/usuário de outro grupo não aparece; cache não vaza entre tenants.

## Dependências
- Depende de PLANO_BANCO (NOT NULL) e PLANO_QUALIDADE (CI Postgres).

## Checklist técnico
- [ ] `GoliveCheck` valida role restrita + senha
- [ ] Runtime conecta como `erp_app`
- [ ] NOT NULL nas filhas
- [ ] Suíte cross-tenant em Postgres

## Critérios de aceite
- Deploy aborta se o runtime tiver BYPASSRLS/superuser.
- Query crua (bypass do scope) só enxerga o tenant setado (teste PG).
- Nenhuma linha operacional com empresa_id NULL.

## Estratégia de testes
- Testes de RLS reais em Postgres: setar `app.empresa_id`, inserir/ler cross-tenant, confirmar isolamento; validar que sem variável o comportamento é o esperado (CLI/ETL).
