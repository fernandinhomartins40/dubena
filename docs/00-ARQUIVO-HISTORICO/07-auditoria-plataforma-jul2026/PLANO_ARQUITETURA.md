# PLANO DE EVOLUÇÃO — ARQUITETURA

> Corresponde a [AUDITORIA_ARQUITETURA.md](AUDITORIA_ARQUITETURA.md).

## Contexto
Monólito modular sólido; débito concentrado em **formalização de contrato** e **duplicação**.

## Objetivo
Congelar a fronteira backend↔superfícies e reduzir duplicação sem mudança estrutural.

## Benefícios
Menos regressão silenciosa entre backend/SPA/apps; evolução de API previsível; correção única de bugs de infra dos apps.

## Riscos
Baixos; mudanças são incrementais e cobertas por testes.

## Estratégia e fases

**Fase 1 — Contrato formal (A2, A3, A6)**
- Migrar respostas de lista para **API Resources** (padrão `{data, meta}`).
- Extrair **FormRequests** dos controllers com validação inline (começar por Estoque/Financeiro/Caixa).
- Gerar **OpenAPI a partir do código** (ou testes de contrato) cobrindo admin+app+superadmin; versionar no CI.

**Fase 2 — Rotas (A4, A5)**
- Canonizar aliases, marcar duplicados `@deprecated`, quebrar [routes/api.php](../../../erp-novo/routes/api.php) por domínio.

**Fase 3 — Infra compartilhada dos apps (A7)**
- Monorepo/workspace com pacote comum (`http`, `realtime`, `storage`, tipos) consumido pelos dois apps.

## Dependências
- Fase 1 antes de depreciar aliases (a SPA precisa migrar para os canônicos).
- Fase 3 é independente (pode paralelizar).

## Checklist técnico
- [ ] Resources para todas as listas
- [ ] FormRequests nos módulos financeiros/estoque
- [ ] OpenAPI gerado + no CI
- [ ] Aliases canonizados + depreciados
- [ ] `routes/api.php` por domínio
- [ ] Pacote compartilhado dos apps

## Critérios de aceite
- Todo endpoint responde `{data, meta?}` uniforme (teste de contrato).
- OpenAPI bate com as rotas registradas (teste automatizado que compara).
- Um bug de HTTP corrigido em um único lugar reflete nos dois apps.

## Estratégia de testes
- Teste que enumera `Route::getRoutes()` e valida contra o OpenAPI. Testes de Resource (shape). CI dos apps compilando o pacote compartilhado.
