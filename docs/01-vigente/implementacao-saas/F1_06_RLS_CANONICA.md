# F1-06 - RLS canônica em modo sombra

**Estado:** funções SQL concluídas; switch das policies depende de F1-10.
**Data:** 2026-08-26 (America/Sao_Paulo)

## Entrega

A migration `2026_08_29_000400_create_canonical_tenant_rls_functions.php`
cria quatro funções PostgreSQL:

- `app_current_tenant_account_id()`;
- `app_current_tenant_membership_id()`;
- `app_tenant_can_read(tenant, empresa)`;
- `app_tenant_can_operate(tenant, empresa)`.

As duas funções de autorização exigem GUCs explícitas e um grant aprovado na
mesma conta, membership e empresa. Elas usam `SECURITY DEFINER` com
`search_path` fechado, para que uma policy consiga consultar grants sem abrir
bypass genérico de runtime.

## Prova PostgreSQL

Em PostgreSQL 16 descartável, a migration foi aplicada contra uma tabela mínima
de grants. Sem GUCs, a consulta retornou `t|f|f`: conta atual nula, leitura
negada e operação negada. Isso prova ausência de contexto como negação, não
resultado vazio ou acesso amplo.

## Switch adiado intencionalmente

As policies `tenant_isolation` legadas ainda não foram removidas. As chaves
novas estão nulas até F1-10; trocar a policy agora bloquearia todo dado legado
sem provar a conversão. O switch exigirá tenants, links e grants aprovados,
recertificação PostgreSQL/RLS e rollback dual-read.
