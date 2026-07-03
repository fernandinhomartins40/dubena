# AUDITORIA — FRONTEND (SPA React)

> Base: [erp-novo/frontend/](../../erp-novo/frontend/) — React 18, Vite 5, TypeScript 5.6, TanStack Query 5, React Router 6, Radix/shadcn, Tailwind. 167 arquivos TS/TSX, 30 features.

## 1. Organização

- **Feature-based**: [src/features/](../../erp-novo/frontend/src/features/) espelha os domínios do backend (clientes, pedidos, financeiro, fiscal, central, missoes, superadmin…). Cada feature tende a ter sua página + um `api.ts` co-localizado (hooks de query/mutation).
- **Kit de UI próprio**: [src/components/ui/](../../erp-novo/frontend/src/components/ui/) com 27 componentes (DataTable, FormDialog, ResourceList, AsyncSelect, ConfirmDialog, StatCard, RowActions, Can…) — base shadcn/Radix, reutilização alta.
- **Libs transversais**: [api.ts](../../erp-novo/frontend/src/lib/api.ts) (axios Sanctum cookie+Bearer), [auth.tsx](../../erp-novo/frontend/src/lib/auth.tsx) (contexto + `can`/`canField`), [useResourceForm.ts](../../erp-novo/frontend/src/lib/useResourceForm.ts) (boilerplate de form + 422), `useBusca`, `format`, `googleMaps`.

## 2. Navegação e RBAC

- Navegação **declarativa** ([AppShell.tsx](../../erp-novo/frontend/src/layouts/AppShell.tsx)): array `NAV` com `permission` por item; a sidebar filtra por `can()`. Sem menu-no-banco (objetivo da modernização atingido).
- Rotas com **code-splitting** (47 chunks lazy) + duas guardas: `Protegido` (auth) e `RequirePermission` (mostra "Sem acesso" 403 em vez de redirecionar) — [routes.tsx](../../erp-novo/frontend/src/routes.tsx).
- RBAC do cliente casa 1:1 com o backend: `/me` devolve `roles`+`permissions`+`features`; `can`/`canField` espelham `temPermissao`/`CamposPermitidos`.

## 3. Estado

- **Estado de servidor** via TanStack Query (cache, `staleTime`, invalidação). Sem Redux — decisão adequada para app CRUD-heavy.
- **Auth** em contexto React + Query (`['me']`), com login robusto a CSRF (tenta cookie, cai para Bearer).
- **Troca de tenant** via `EmpresaSwitcher` + header `X-Empresa-Id`.

## 4. UX/UI

- Design system centralizado (tokens em `index.css`, tema claro/escuro), componentes de padrão (FormDialog, ResourceList) → telas consistentes.
- Kanban de pedidos com drag-and-drop e **confirmação antes de transições que mexem em estoque/financeiro** ([PedidosPage](../../erp-novo/frontend/src/features/pedidos/PedidosPage.tsx)) — bom cuidado de UX/segurança.

## 5. Achados classificados

| ID | Prio | Achado | Evidência | Recomendação |
|---|---|---|---|---|
| FE-1 | **P2** | Sem Error Boundary global | nenhum `componentDidCatch`/ErrorBoundary encontrado | Um erro de render em qualquer página quebra a SPA inteira (tela branca). Adicionar ErrorBoundary por rota + fallback |
| FE-2 | **P2** | RBAC do cliente é só UX; a segurança real está no backend, mas `permissions` são cacheadas 5 min (`staleTime`) | [auth.tsx](../../erp-novo/frontend/src/lib/auth.tsx) | Ao revogar papel, o usuário mantém a UI liberada até refetch; forçar refetch de `/me` em ações sensíveis / reduzir stale |
| FE-3 | **P2** | Sem testes de frontend (script `lint` = só `tsc --noEmit`) | [package.json](../../erp-novo/frontend/package.json) | Adicionar Vitest + Testing Library ao menos para os componentes de UI e o fluxo de auth |
| FE-4 | **P3** | Páginas grandes concentram lógica (PedidosPage 455, MissoesPage 290) | [PedidosPage](../../erp-novo/frontend/src/features/pedidos/PedidosPage.tsx) | Extrair sub-componentes (Kanban/Lista/Ficha) e hooks |
| FE-5 | **P3** | Build do front é **commitado** em `erp-novo/public/app` (Dockerfile não builda) | memória do projeto | Risco de esquecer `npm run build` a cada mudança de UI; mover build para o pipeline de deploy |
| FE-6 | **P3** | Token Bearer em `localStorage` (persistência "manter conectado") | [api.ts](../../erp-novo/frontend/src/lib/api.ts) | Preferir só o fluxo cookie httpOnly para a SPA; localStorage é exposto a XSS |
| FE-7 | **P4** | Sem lazy de libs pesadas (Google Maps) por rota | `lib/googleMaps.ts` | Carregar sob demanda nas telas de mapa |

## 6. Performance do frontend

- Code-splitting por rota já reduz o bundle inicial. TanStack Query evita refetch desnecessário. Sem virtualização em listas grandes (DataTable) — revisar para telas com milhares de linhas após o dump real (paginação server-side já existe, ajuda).

## 7. Conclusão

Frontend **moderno, consistente e bem fatorado** (feature-based, design system, RBAC declarativo, code-splitting). Os débitos são de **resiliência e teste** (Error Boundary, Vitest) e **higiene de build/segurança de token**. Nenhum problema estrutural; FE-1 (Error Boundary) e FE-3 (testes) são os de maior retorno antes da homologação.

→ Plano: [PLANO_FRONTEND.md](PLANO_FRONTEND.md)
