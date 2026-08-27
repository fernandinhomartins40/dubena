# F1-01 - schema raiz da fronteira SaaS

**Estado:** concluido localmente (shadow schema, sem cutover).
**Data:** 2026-08-26 (America/Sao_Paulo)

## Entrega

A migration `2026_08_29_000100_create_tenant_boundary_tables.php` cria, vazias:

- `tenant_accounts`, a conta juridica da plataforma;
- `tenant_memberships`, sem reaproveitar `users.empresa_id` ou `empresa_user`;
- `tenant_companies`, com unicidade de `empresa_id` para impedir duas contas
  titulares simultaneas;
- `tenant_company_grants`, com leitura e operacao separadas;
- `tenant_network_links`, separado de grants para que franquia/rede nao produza
  visibilidade implicita.

Os registros entram pendentes e com campos de referencia de evidencia. A
migration nao le `grupo_id`, nem migra empresas, usuarios ou relacoes
comerciais legadas. `TenantAccount` e seus quatro modelos sao deliberadamente
neutros: ainda nao ha scope global, fallback ou mudanca de rota/runtime.

## Validacao executada

- `php -l` da migration e dos cinco models: aprovado.
- `php artisan test --filter=TenantBoundarySchemaTest`: 3 testes, 9 assertions,
  zero falhas.
- O teste prova que empresa/grupo legado nao cria conta ou vinculo; que uma
  empresa nao entra em dois tenants; e que um link comercial nao cria grant.

## Limites para os proximos microlotes

F1-01 nao ativa RLS, nao faz backfill e nao declara titularidade. F1-02 deve
classificar a totalidade do catalogo efetivo; F1-10 so podera criar
`tenant_companies` aprovados a partir da decisao documental externa.
