# F1-10 - importação documental de titularidade

**Estado:** mecanismo local concluído; execução real depende do mapeamento jurídico.
**Data:** 2026-08-26 (America/Sao_Paulo)

## Comando

`php artisan saas:tenant:importar <arquivo.json>` executa somente preview.
`--apply` é necessário para persistir. O JSON precisa enumerar cada tenant,
empresa, membership e grant, sempre com sua referência de evidência.

O importador recusa empresa ausente, duplicada ou já vinculada, tenant sem
empresa, membership sem evidência, grant fora da empresa do mesmo tenant e
operação sem leitura. Ao aplicar, cria `TenantAccount`, `TenantCompany`,
`TenantMembership` e `TenantCompanyGrant` aprovados em uma transação e marca a
empresa como `OWNERSHIP_APPROVED`.

## Evidência

`TenantMappingImporterTest`: dry-run não persistiu; apply criou apenas os
vínculos documentados; planos inválidos foram recusados. Validação focal
conjunta: 8 testes, 165 assertions, zero falhas.

## Dependência externa real

Ainda não há arquivo documental aprovado que determine o controlador de cada
empresa Dubena. Sem ele, não é permitido executar `--apply`, ligar o resolver
ao middleware, converter jobs existentes, endurecer NOT NULL ou trocar as
policies legadas. Essa pendência é intencionalmente fail-closed, não um estado
de sucesso.

## Cutover HTTP preparado

O alias `tenant.saas` aponta para `ResolveTenantEnvelope`. Ele roda depois de
`tenant`, resolve a conta/membership/grant aprovados e mantém o envelope apenas
durante a resposta, limpando-o em `finally`. O teste HTTP confirmou a conta no
request e ausência de resíduo após ele. O alias não foi adicionado às rotas
atuais antes da aplicação documental.
