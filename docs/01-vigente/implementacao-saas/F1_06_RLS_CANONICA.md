# F1-06 - RLS canônica em modo sombra

**Estado:** conversão canônica iniciada para tabelas COMPANY com `empresa_id`; F1 ainda não está fechada.
**Data:** 2026-08-27 (America/Sao_Paulo)

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

## Conversão após o mapping aprovado

A migration `2026_08_29_000800_backfill_and_protect_company_tenant_keys.php`
faz o primeiro switch efetivo, limitado às tabelas `COMPANY` que possuem tanto
`empresa_id` quanto `tenant_account_id`:

- preenche a chave somente por `TenantCompany` com status `APPROVED`;
- recusa uma chave preexistente que divirja desse vínculo documental;
- deixa linhas sem vínculo aprovado inacessíveis ao runtime;
- aplica `USING app_tenant_can_read(...)` e `WITH CHECK
  app_tenant_can_operate(...)` com `FORCE ROW LEVEL SECURITY`.

Não usa `grupo_id`, usuário padrão, CNPJ ou maioria de dados como fonte de
titularidade. Tabelas COMPANY que não possuem `empresa_id` continuam no
recorte seguinte: elas exigem uma chave operacional explícita antes de uma
policy canônica poder ser aplicada sem ampliar permissões.

## Prova PostgreSQL com role de runtime

Em PostgreSQL 16 descartável, a migration foi aplicada com `pgsql_owner` e a
suíte `RlsCoberturaTest` foi executada usando `erp_app` (NOSUPERUSER,
NOBYPASSRLS), sem skips. Resultado: 6 testes, 350 assertions aprovados,
incluindo leitura, update e insert cruzados negados e leitura/escrita sem
contexto negadas. Um ensaio adicional inseriu uma linha sem chave para uma
empresa com `TenantCompany APPROVED`, reaplicou a migration e confirmou que a
chave recebeu exatamente o `tenant_account_id` aprovado.

## Limite atual

A conversão não fecha F1 por si só: faltam a modelagem/policy canônica das
classes COMPANY sem empresa operacional, a conversão dos jobs cron/ETL sem
ator tenant e a recertificação no banco de homologação após deploy.
