# AUDITORIA — MULTI-TENANCY

> A plataforma é multi-tenant **por linha** (shared schema), com dois níveis: `grupo_id` (rede) e `empresa_id` (revenda/tenant operacional). Esta auditoria avalia o estágio de isolamento no código.

## 1. Estágio atual — OPERACIONAL com defense-in-depth

O isolamento tem **três barreiras independentes**:

### Barreira 1 — Global scope na aplicação
[BelongsToTenant](../../../erp-novo/app/Domain/Tenant/BelongsToTenant.php) / [BelongsToGrupo](../../../erp-novo/app/Domain/Tenant/BelongsToGrupo.php): aplicam `where empresa_id = <tenant>` automaticamente e preenchem `empresa_id/grupo_id` na criação a partir do [TenantContext](../../../erp-novo/app/Domain/Tenant/TenantContext.php). Verificado: **96 models usam BelongsToTenant, 22 usam BelongsToGrupo**. O contexto é resolvido por requisição no [ResolveTenant](../../../erp-novo/app/Http/Middleware/ResolveTenant.php) a partir do usuário autenticado (troca de empresa via header `X-Empresa-Id`, validada contra as empresas permitidas).

### Barreira 2 — RLS no PostgreSQL
[rls_tenant_completa](../../../erp-novo/database/migrations/2026_06_26_000300_rls_tenant_completa.php): descobre em runtime toda tabela com `empresa_id`/`grupo_id` e cria policy `tenant_isolation` com `FORCE ROW LEVEL SECURITY`. A policy lê `current_setting('app.empresa_id')`, setado pelo `ResolveTenant` via `set_config` (e re-setado em jobs via [TenantAwareJob](../../../erp-novo/app/Domain/Tenant/TenantAwareJob.php)). **Fecha o furo crítico**: [rls_role_app_sem_bypass](../../../erp-novo/database/migrations/2026_06_26_000400_rls_role_app_sem_bypass.php) cria a role `erp_app` (LOGIN, **NOSUPERUSER, NOBYPASSRLS**) que o runtime usa; migrations rodam como owner (`--database=pgsql_owner`). Sem a role restrita, o PG ignoraria RLS silenciosamente (o problema real que a auditoria original do projeto encontrou).

### Barreira 3 — Canais de broadcast por posse
[channels.php](../../../erp-novo/routes/channels.php): canais privados `empresa.{id}.pedidos/central`, `pedido.{id}(.entregador)` autorizados por `podeAcessarEmpresa` e posse do pedido (cliente dono / entregador / atendente), com `withoutTenant()` + filtro explícito de empresa (defense-in-depth também no tempo real).

Complementos: [TenantCache](../../../erp-novo/app/Domain/Shared/TenantCache.php) prefixa toda chave de cache por `grupo:empresa` (dado cacheado de um tenant nunca vaza para outro). SuperAdmin ([SuperAdminService](../../../erp-novo/app/Domain/Saas/SuperAdminService.php)) é a **única** superfície cross-tenant, com guard `platform` separado (não resolve tenant) e toda mutação auditada.

## 2. Pontos preparados (verificados)

- Preenchimento automático de tenant na criação, inclusive herança do pai em contexto sem tenant (ETL/job) via `$tenantParent`.
- Jobs propagam o tenant capturado no dispatch e re-setam a RLS ([AtribuirPedidoJob](../../../erp-novo/app/Domain/Logistica/Jobs/AtribuirPedidoJob.php) chama `$tenant->set`).
- IDOR endereçado onde a filha não tem `empresa_id` próprio no request: `CaixaService::baixarParcela` revalida o título-pai; app deriva cliente/endereço do token.
- Transferências de estoque/caixa recusam cruzar empresas ([EstoqueService::transferir](../../../erp-novo/app/Domain/Estoque/EstoqueService.php)).

## 3. Riscos de isolamento

| ID | Prio | Risco | Evidência | Mitigação |
|---|---|---|---|---|
| MT-1 | **P1** | RLS só protege se o runtime conectar como `erp_app`. Se o deploy/ambiente configurar `DB_USERNAME` com a role owner (ou superuser), a Barreira 2 **fica inerte** silenciosamente. | [rls_role_app_sem_bypass](../../../erp-novo/database/migrations/2026_06_26_000400_rls_role_app_sem_bypass.php) + [config/database.php](../../../erp-novo/config/database.php) (owner cai no DB_USERNAME se DB_OWNER_* não definido) | Portão de go-live: verificar em runtime que `current_user` **não** tem BYPASSRLS/superuser; comando `golive:check` deve falhar se não. Garantir `RLS_APP_DB_PASSWORD` setado (senão a migration é no-op e a role nem existe). |
| MT-2 | **P2** | `RLS_APP_DB_PASSWORD` vazio → migration da role é NO-OP; o app então conecta com a role que estiver em `DB_USERNAME` (possivelmente owner). Silencioso. | idem | Fazer o `GoliveCheck` bloquear deploy sem a role. |
| MT-3 | **P2** | Filhas com `empresa_id` nullable pós-backfill: uma linha com `empresa_id NULL` é **visível a todos os tenants** pela policy (`nullif(...) IS NULL → true`). | policy em [rls_tenant_completa](../../../erp-novo/database/migrations/2026_06_26_000300_rls_tenant_completa.php) L120-126 | `SET NOT NULL` nas filhas após backfill (ver DB-4). |
| MT-4 | **P3** | Allowlist da RLS inclui `roles`/`role_user`/`empresa_configs` — corretamente (login/RBAC precisam agir antes do tenant), mas exige que a lógica de app filtre esses por grupo/empresa. Verificado que `UsuarioController` filtra por grupo; manter disciplina. | allowlist em [rls_tenant_completa](../../../erp-novo/database/migrations/2026_06_26_000300_rls_tenant_completa.php) | Teste cross-tenant dedicado para papéis/usuários (já existe `FaseF02CrossTenantTest`). |
| MT-5 | **P3** | Cache global (sem tenant) usa prefixo `global` — se algum código cachear em contexto sem tenant e ler em contexto com tenant, poderia servir dado incorreto. | [TenantCache](../../../erp-novo/app/Domain/Shared/TenantCache.php) | Auditar chamadas de cache fora de request; hoje o uso é via `TenantCache` (seguro). |

## 4. Impacto das alterações pendentes

Nenhuma alteração **estrutural** é necessária — o modelo por linha + RLS já é a estratégia recomendada para esta escala (múltiplas revendas por rede). O trabalho restante é de **garantia operacional** (MT-1/MT-2) e **higienização** (MT-3), não de arquitetura.

## 5. Estratégia recomendada de evolução

1. **Blindar a Barreira 2 no go-live** (MT-1/MT-2): `GoliveCheck` que aborta se o runtime não for `erp_app` sem bypass, e se a role não existir.
2. **NOT NULL nas filhas** (MT-3) após validar o backfill no dump real.
3. **Suíte cross-tenant em Postgres** (não sqlite): a RLS é no-op no sqlite, então os testes atuais validam só a Barreira 1. Rodar `FaseF02CrossTenantTest` + novos casos contra PG.
4. Manter SuperAdmin como única fronteira cross-tenant; nunca introduzir query cross-tenant fora dele.

## 6. Conclusão

Multi-tenancy **à prova de vazamento no desenho** (3 barreiras, role restrita, cache namespaced, broadcast por posse). O risco residual é **operacional** (garantir a role certa em produção) e de **dados legados** (empresa_id nullable nas filhas). Endereçados esses dois, a plataforma está pronta para operar múltiplos tenants com o dump real.

→ Plano: [PLANO_MULTI_TENANT.md](PLANO_MULTI_TENANT.md)
