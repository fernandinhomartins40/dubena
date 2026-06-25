# Auditoria do Frontend (SPA) + Proposta de Reorganização

> Diagnóstico do `erp-novo/frontend/src` atual (arquitetura, navegação/IA, componentes,
> padrões, estilo). Baseado em **leitura do código real**. Objetivo: decidir o escopo de
> uma fase de reorganização de frontend (F17). Data: 2026-06-25.

## 0. Números reais

- **17 features**, **76 `.tsx`** + 21 `.ts`, **~9.000 LOC**, **23 componentes UI**, **46 rotas**.
- Data layer: **15 `api.ts`** (1 por feature); só `dashboard` chama `api` direto (legítimo).
- Maiores telas: EstoquePage (378), FiscalPage (316), FinanceiroPage (290), ProdutoForm (285),
  Geografico (283), Colaboradores (279).

## 1. O que JÁ está bom (não mexer)

- **Design tokens**: 0 cores hardcoded; 213 usos de tokens semânticos (`primary`/`muted`/
  `destructive`/`card`…). Tema dark/light via tokens. ✅
- **Data layer consistente**: cada feature tem `api.ts` com hooks TanStack Query; páginas
  quase nunca chamam `api.*` direto. ✅
- **Reuso de componentes razoável**: `PageHeader` (22), `EmptyState` (22), `DataTable` (17),
  `ResourceList` (14), `FormDialog` (13), `RowActions` (7). Biblioteca UI própria
  (shadcn/Radix) coesa. ✅
- **Navegação declarativa** com RBAC (`can(permission)`) e 8 grupos lógicos no `AppShell`. ✅
- `<table>` manual quase eliminado (só 2 telas) — DataTable é o padrão. ✅

## 2. Problemas encontrados (com evidência)

### 2.1 Organização de pastas — `features/satelites/` é um grab-bag 🔴
`features/satelites/` contém **7 telas de domínios diferentes**: `ColaboradoresPage`,
`VeiculosPage` (RH e Frota!), `ValeGasPage`, `ComodatoPage`, `ConvenioPage`, `MonitoraPage`,
`SatelitesPage`. Colaboradores e Veículos **não são satélites** — deviam estar em `rh/` e
`frota/`. A pasta virou um depósito.

### 2.2 Cadastros de config duplicados/legados 🟡
`features/cadastros/` ainda tem `ClienteConfigPage`, `FinanceiroConfigPage`,
`ColaboradorConfigPage` (telas contextuais por módulo) **além** do hub `features/configuracoes/`
(F01) que já agrega tudo. Há rotas para ambos (`/clientes/configuracoes` etc. **e**
`/configuracoes`). Redundância de manutenção — o hub e as 3 telas repetem os mesmos
`CadastroApoioTab`.

### 2.3 Roteamento: sem code-splitting + ordem desorganizada 🟡
`main.tsx`: **46 rotas, 40+ imports eager** (tudo no bundle inicial — sem `lazy()`). Além
disso a ordem das rotas é caótica (produtos no fim, satélites espalhado, comentários
"C10/C11" do plano antigo). Um único arquivo `App()` de 55 linhas de `<Route>`.

### 2.4 Padrão de diálogo inconsistente 🟡
**15 telas usam `<Dialog>` cru** e **13 usam o wrapper `FormDialog`** — dois jeitos de fazer
a mesma coisa (criar/editar em modal). Quem mexe numa tela precisa adivinhar qual padrão
seguir. Ex.: FiscalPage/FinanceiroExtraTabs montam Dialog na mão; outras usam FormDialog.

### 2.5 Estados de loading/erro não padronizados 🟡
**40 telas têm `isLoading`**, mas só **5 usam `Skeleton`** e **8 usam "Carregando…"** em
texto — o resto trata ad-hoc ou não trata erro. Não há um componente/contrato único de
"estado de carregamento/erro/vazio" aplicado de forma uniforme (EmptyState existe, mas
loading/erro não).

### 2.6 Telas-monólito (hotspots) 🟡
EstoquePage (378), FiscalPage (316), FinanceiroPage (290) concentram várias abas + dialogs
+ tabelas num arquivo só. Difícil de manter/testar. Cada uma poderia quebrar em
sub-componentes por aba (como FinanceiroExtraTabs já tenta).

### 2.7 Form pages com boilerplate repetido 🟡
ProdutoFormPage/ClienteFormPage/EmpresaFormPage/Colaborador/Veiculo repetem o mesmo
esqueleto (estado local `form`, `set(k)`, validação manual `req.some`, toast de erro
`e?.response?.data?.message`). Não há um hook/abstração de formulário (`useResourceForm`)
nem validação declarativa.

## 3. Proposta de reorganização (escopo da F17)

Ordenada por impacto/risco. Tudo incremental e commitável, com typecheck a cada passo.

| # | Ação | Tipo | Risco |
|---|---|---|---|
| R1 | ✅ **Mover** `colaboradores`/`veiculos` de `satelites/` → `rh/` e `frota/`; manter só vale-gás/comodato/convênio/monitora em `satelites/` | refactor pastas | baixo |
| R2 | ✅ **Code-splitting**: trocar imports eager por `React.lazy` + `Suspense` no roteador; extrair as rotas para `routes.tsx` agrupado por domínio | perf + org | baixo |
| R3 | ✅ **Unificar diálogos**: criado `ConfirmDialog` (confirmação destrutiva) + `widthClass` no `FormDialog`; migradas 9 telas (clientes/produtos/veículos/colaboradores/empresas/geográfico/cadastros-apoio/financeiro-cheques/produto-config). Mantidos como `<Dialog>` cru os casos legítimos: multi-diálogo com fluxo/largura próprios (estoque/fiscal/financeiro/pedidos) e os com `DialogTrigger` embutido (ConfigTab/ValeGás) | consistência | médio |
| R4 | ✅ **Contrato de estado**: criado `AsyncState` (loading→Skeleton / erro→EmptyState / vazio→EmptyState) para blocos não-tabela. Eliminado o `Carregando…` ad-hoc em interações/telefones/convênio (clientes), satélites, pedidos, config global e familiares (RH). Tabelas (DataTable/ResourceList) já tratavam loading/empty internamente; mantidos os Skeletons já existentes com wrapper próprio (ConfigTab/Certificado/ProdutoForm) | consistência/UX | médio |
| R5 | ✅ **Hook de formulário** `useResourceForm` (estado + `campo()` + hidratação 1/0→bool + `erros` 422 + `submit` + `dirty`) em `lib/`. Adotado nas 5 form pages: cliente, produto (via `hidratar` p/ pGNi/origens), empresa (via `hidratar` c/ whitelist CAMPOS), colaborador e veículo | DRY | médio |
| R6 | ✅ **Resolver config duplicada**: removidas as 3 `*ConfigPage` (cliente/financeiro/colaborador); o hub `/configuracoes` agora aceita `?tab=` (geral/clientes/financeiro/colaboradores) e as rotas antigas redirecionam (`Navigate`) para a aba certa. Botões "Configurações" das telas e o item de menu duplicado ("Config. Financeira") apontam para o hub | dedupe | baixo |
| R7 | ✅ **Quebrar monólitos**: cada página virou shell de abas (Estoque 378→35, Fiscal 316→26, Financeiro 290→35 linhas); cada aba foi extraída para `tabs/` (estoque: 8 incl. ItensEditor; fiscal: 4; financeiro: 4 + extras já em FinanceiroExtraTabs). Diálogos das abas migrados p/ FormDialog/ConfirmDialog de quebra | manutenibilidade | médio |
| R8 | Revisar a **ordem/labels do menu** (`AppShell`) e a ordem das rotas para refletir a IA dos 8 grupos | UX | baixo |

## 4. Recomendação de execução

Fazer como as fases do backend: **uma sub-fase por commit**, na ordem R1→R8 (baixo risco
primeiro), rodando `tsc --noEmit` (e a app) a cada passo. R3/R4/R5 são os que mais elevam a
consistência de UX e reduzem boilerplate — o "sentir organizado" que faltou.

> Observação honesta: o frontend **não está bagunçado no estilo nem no data layer** (esses
> estão bons). O que falta é **organização estrutural** (pastas/rotas), **consistência de
> padrões** (diálogo/loading/formulário) e **quebra dos monólitos** — exatamente o que não
> teve fase própria no plano de paridade. Nada aqui é reescrita; é refactor incremental.
