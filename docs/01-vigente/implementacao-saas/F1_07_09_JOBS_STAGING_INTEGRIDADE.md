# F1-07 a F1-09 - worker, integridade e staging

**Estado:** bases locais implementadas; cutover depende de F1-10.
**Data:** 2026-08-26 (America/Sao_Paulo)

## Worker

`TenantEnvelopeRuntime` executa um envelope por vez, aplica as GUCs canônicas e
as limpa em `finally`; `TenantEnvelopeJob` exige payload serializado. A prova
unitária executou dois envelopes consecutivos e também uma falha, sem resíduo.
Os jobs legados ainda não usam o trait porque eles não possuem mapping aprovado
para capturar o envelope; essa conversão é parte do switch posterior a F1-10.

## Integridade estrutural

O trigger PostgreSQL `tenant_company_grant_consistency` verifica que membership,
conta, tenant-company e empresa são da mesma fronteira. Em banco descartável,
um grant coerente foi aceito e o cruzado foi recusado com erro explícito.

## Staging

`tenant_staging_artifacts` é o mecanismo temporário catalogado: tenant, owner,
finalidade, payload, expiração e momento de purge. `saas:staging:purgar` remove
somente payload expirado e preserva a trilha. O teste focal passou.
