# F1-07 - Compatibilidade group-scoped no TenantEnvelopeRuntime

**Estado:** implementado localmente; aguarda deploy e recertificação PostgreSQL.
**Data:** 2026-08-27 (America/Sao_Paulo)

## Decisão

Durante a transição existem tabelas legadas ainda escopadas por `grupo_id`.
`TenantEnvelopeRuntime` passou a derivar as GUCs legadas da empresa ativa já
autorizada no envelope. Não recebe grupo de payload, sessão, nem o deduz por
maioria de dados.

As GUCs `app.empresa_id`, `app.grupo_id` e `app.empresas_visiveis` são criadas
após as GUCs canônicas tenant/membership e apagadas no mesmo `finally`.

## Evidência

- `TenantEnvelopeRuntimeTest` e `ResolveTenantEnvelopeMiddlewareTest`: 5 testes,
  15 assertions aprovados.
- PostgreSQL 16 descartável recebeu todas as migrations até o recorte F1.
- A tentativa de inspeção interativa das GUCs não gerou saída útil no terminal;
  ela não é considerada prova de gate e deve ser repetida após deploy.

## Próximo passo

Converter `ImportarLogradourosJob` com esse runtime e declarar explicitamente
os jobs de plataforma de ETL antes de modelar as tabelas COMPANY sem
`empresa_id`.
