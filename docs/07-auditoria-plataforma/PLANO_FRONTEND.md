# PLANO DE EVOLUÇÃO — FRONTEND (SPA)

> Corresponde a [AUDITORIA_FRONTEND.md](AUDITORIA_FRONTEND.md).

## Contexto
SPA moderna e consistente; débito em resiliência, testes e higiene de build/token.

## Objetivo
Tornar a SPA resiliente a erros de render, testável e mais segura no armazenamento de sessão.

## Benefícios
Fim de "tela branca" por erro isolado; regressão pega no CI; menor superfície de XSS.

## Riscos
Baixos; mudanças aditivas.

## Estratégia e fases

**Fase 1 — Resiliência (FE-1)**
- ErrorBoundary por rota (dentro do `Suspense`) com fallback amigável e reporte.

**Fase 2 — Testes (FE-3)**
- Vitest + Testing Library: cobrir componentes de UI, `useAuth` (login/csrf/bearer), guardas de rota.

**Fase 3 — Segurança de sessão (FE-6, FE-2)**
- Preferir fluxo cookie httpOnly para a SPA; se manter Bearer, reduzir superfície (não `localStorage` para sessão longa).
- Forçar refetch de `/me` após ações sensíveis / reduzir `staleTime` do RBAC.

**Fase 4 — Higiene (FE-4, FE-5, FE-7)**
- Extrair sub-componentes das páginas grandes; mover build do front para o pipeline (não commitar `public/app`); lazy de Google Maps por rota.

## Dependências
- FE-3 antes de refatorações grandes (rede de segurança).

## Checklist técnico
- [ ] ErrorBoundary por rota
- [ ] Vitest + testes de UI/auth/guardas
- [ ] Sessão SPA via cookie httpOnly
- [ ] Refetch de `/me` em ações sensíveis
- [ ] Build no pipeline (parar de commitar public/app)
- [ ] Split de páginas grandes

## Critérios de aceite
- Erro de render em uma página não derruba a SPA.
- CI do front roda testes (não só `tsc`).
- Deploy builda o front (sem depender de commit manual).

## Estratégia de testes
- Vitest para componentes/hook de auth; teste de ErrorBoundary (throw controlado); smoke E2E opcional (Playwright) do login + navegação por permissão.
