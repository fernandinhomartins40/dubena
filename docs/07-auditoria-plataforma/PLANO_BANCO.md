# PLANO DE EVOLUÇÃO — BANCO DE DADOS

> Corresponde a [AUDITORIA_BANCO.md](AUDITORIA_BANCO.md).

## Contexto
Modelagem coesa; um achado de falha dura no Postgres (DB-1) e riscos de portabilidade sqlite→PG.

## Objetivo
Tornar o schema 100% correto e testado em Postgres antes do dump real.

## Benefícios
Evita falha ao usar papéis globais; busca/RLS validadas de verdade; integridade referencial completa.

## Riscos
DB-1 muda a PK de `role_user` (migration de alteração + backfill); exige janela e teste. Médio.

## Estratégia e fases

**Fase 1 — Correção de PK do role_user (DB-1)** ⚠️ bloqueante para papéis globais
- Nova migration: trocar PK composta por `id` autoincrement + `unique(user_id, role_id, empresa_id)`; decidir semântica do papel global (sentinela `empresa_id=0` vs unicidade parcial). Ajustar [User](../../erp-novo/app/Models/User.php) se usar sentinela.

**Fase 2 — Portabilidade validada em Postgres (DB-2, DB-3)**
- Job de CI com serviço Postgres rodando a suíte + `migrate:fresh --seed`.
- Abstrair busca case-insensitive por driver (ou testar `ilike` só no PG).

**Fase 3 — Integridade (DB-4, DB-5, DB-6)**
- `SET NOT NULL` nas filhas após validar backfill.
- `unique(txid)` + `index(pedido_id)` em `pix_cobrancas`.
- FK formal `pedidos.financeiro_id → financeiros`.

## Dependências
- Fase 1 antes de habilitar papéis globais. Fase 3 (NOT NULL) após confirmar backfill no dump.

## Checklist técnico
- [ ] Migration de PK do `role_user`
- [ ] CI com Postgres (suíte + seed)
- [ ] Busca case-insensitive portável
- [ ] NOT NULL nas filhas com empresa_id
- [ ] Índices/unique em pix_cobrancas
- [ ] FK pedidos.financeiro_id

## Critérios de aceite
- Atribuir papel global (empresa_id NULL/sentinela) funciona no Postgres.
- Suíte verde **em Postgres** (não só sqlite).
- `migrate:fresh --seed` roda no Postgres sem erro de coluna.
- Nenhuma linha tenant-scoped com `empresa_id NULL`.

## Estratégia de testes
- Reexecutar toda a suíte no PG; teste específico de papel global; teste cross-tenant confirmando que linhas sem empresa_id não vazam (após NOT NULL).
