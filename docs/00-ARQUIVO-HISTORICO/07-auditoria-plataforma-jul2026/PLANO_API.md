# PLANO DE EVOLUÇÃO — API

> Corresponde a [AUDITORIA_API.md](AUDITORIA_API.md).

## Contexto
Semântica e segregação de superfícies boas; débito em consistência de contrato (shapes, aliases, OpenAPI).

## Objetivo
Contrato uniforme e versionável, congelado antes da homologação.

## Benefícios
Menos regressão silenciosa; evolução previsível; documentação viva.

## Riscos
Migrar shapes exige atualizar a SPA/apps em sincronia — fazer atrás de testes de contrato.

## Estratégia e fases

**Fase 1 — Envelope uniforme (API-1)**
- Padronizar toda resposta em `{data, meta?}`; migrar listas para Resources.

**Fase 2 — OpenAPI vivo (API-3)**
- Gerar OpenAPI do código; teste de CI compara rotas registradas × spec.

**Fase 3 — Aliases e versão (API-2, API-4, API-6)**
- Canonizar aliases, `@deprecated`, remover após migração da SPA; introduzir `/api/admin/v1`; quebrar `routes/api.php` por domínio.

**Fase 4 — Refino (API-5, API-7, API-8)**
- `?per_page` com teto; limiters nomeados; negociação de versão de app (`app_versao` → 426 Upgrade Required em breaking).

## Dependências
- Fase 1 antes da Fase 3 (SPA usa canônicos). Coordena com PLANO_ARQUITETURA.

## Checklist técnico
- [ ] Envelope `{data, meta}` uniforme
- [ ] OpenAPI gerado + teste de CI
- [ ] Aliases canonizados/depreciados
- [ ] `/api/admin/v1`
- [ ] `routes/api.php` por domínio
- [ ] `?per_page`, limiters nomeados, checagem de versão de app

## Critérios de aceite
- Teste de contrato passa (rotas ↔ OpenAPI).
- SPA/apps consomem só o envelope canônico.
- App desatualizado recebe 426 quando exigido.

## Estratégia de testes
- Teste que enumera rotas e valida shape + presença no OpenAPI; testes de paginação parametrizada; teste da negociação de versão.
