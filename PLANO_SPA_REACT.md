# PLANO DE MODERNIZAÇÃO — SPA React/Vite + Laravel API (vigente)

> **Substitui** `PLANO_MODERNIZACAO_UX.md` na camada de FRONTEND (a partir de agora a UI
> nova é um SPA React, não Filament). O backend Laravel 12 / PHP 8.3, o banco, o RBAC
> (spatie) e as regras de negócio são **preservados e reusados**.
>
> **Decisão do cliente (norteadora):** a UI precisa ter a cara de um app moderno
> React + Vite (SPA), mantendo PHP/Laravel no backend para não jogar fora o que já
> conhecemos (banco, regras, fiscal). O ERP original tem 26 anos; modernizamos a
> APRESENTAÇÃO e o FLUXO sem reescrever a lógica.

---

## DECISÃO DE ARQUITETURA (ADR)

| Tema | Decisão | Por quê |
|---|---|---|
| Frontend | **SPA React + Vite + TypeScript + Tailwind + shadcn/ui** | Cara moderna 2026; componentes reaproveitáveis; ícones (lucide-react) |
| Backend | **Laravel 12 como API** (mantém banco/regras/RBAC) | Reaproveita 100% do domínio já dominado |
| Auth | **Laravel Sanctum (SPA cookie-based, httpOnly + CSRF)** | Token não fica em JS (mais seguro que Bearer); same-domain sem CORS |
| Onde mora | **`ctrl-web/frontend/`** no mesmo repo; Vite build → servido pelo Nginx em **`/app`** | Mesmo domínio (gasemcasa.com/app), sem CORS, 1 deploy |
| Coexistência | **Strangler**: legado AdminLTE no ar; SPA cresce módulo a módulo; **Filament congelado** | Não quebra produção; migração incremental |
| Filament | **Descartado gradualmente** (foi aprendizado) | RBAC/regras/banco ficam; só a UI Filament sai |

> **O que NÃO muda:** Laravel 12 + PHP 8.3, Postgres, RBAC (spatie acl_roles), Services de
> domínio, Passport (app mobile — contrato intocado), deploy via GitHub Actions + paramiko.
> **O que muda:** a UI nova deixa de ser Filament e passa a ser SPA React em `/app`.

---

## O QUE JÁ EXISTE E REUSAMOS (auditado)

- **App\Api** já em camadas (Http/Models/Repository/Resources/Services), Passport 13, 76 rotas
  em `routes/api.php` — porém **orientadas ao APP MOBILE** (rastreamento, pedidos pendentes,
  push). NÃO é uma API administrativa CRUD do ERP. ⇒ precisamos de uma **API admin nova**,
  separada, sem tocar a do app (contrato publicado).
- **RBAC (spatie)**: 6 papéis, 476 permissões `<modulo>.<view|create|edit|delete>`, ponte
  `User::podeRecurso()`. O SPA autoriza por essas permissões (a API expõe as do usuário).
- **Regras de negócio**: Processors (estoque/financeiro/caixa/sped) caracterizados (F1) — viram
  a camada de domínio chamada pelos controllers de API.
- **Banco**: Postgres, 210 tabelas, dominado (PRDs fiéis + MODERN_*).

---

## ESTRUTURA-ALVO DO PROJETO

```
ctrl-web/
├── app/                      # Laravel (backend)
│   ├── Api/                  # API do APP MOBILE (intocada)
│   └── ApiAdmin/             # API ADMIN nova (CRUD do ERP p/ o SPA)  ← novo
├── routes/
│   ├── api.php               # app mobile (intocado)
│   └── api_admin.php         # endpoints do SPA (Sanctum)             ← novo
├── frontend/                 # SPA React (Vite + TS)                  ← novo
│   ├── src/
│   │   ├── app/              # router, providers
│   │   ├── components/ui/    # shadcn/ui (componentes reaproveitáveis)
│   │   ├── features/<modulo>/# cliente, pedido, financeiro...
│   │   ├── lib/              # api client (axios), auth, hooks
│   │   └── layouts/          # AppShell (sidebar+header marca Dubena)
│   └── vite.config.ts        # build → ../public/app
└── public/app/               # build do Vite (servido pelo Nginx)    ← gerado
```

> **Build:** o Vite gera `public/app` no CI/host (Node não precisa no container de runtime,
> igual aos assets do Filament hoje). Nginx serve `/app/*` (SPA fallback p/ index.html).

---

## IDENTIDADE VISUAL (paleta Dubena — extraída dos logos)

- **Azul** `#2a54ad` (primary), `#137bc9` (azul claro), `#2a3b85` (azul escuro)
- **Amarelo** `#e7eb13` (destaque/marca)
- **Roxo** `#672290` / `#605ca8` (header da identidade; accent)
- Tema claro/escuro; tipografia legível; **lucide-react** para ícones; componentes shadcn/ui.
- AppShell: **sidebar + header + área de conteúdo**; logo Dubena; navegação declarativa em JS.

---

## FASES (Strangler — cada módulo: API admin + tela React + permissão + validar + flag)

### S1 — FUNDAÇÃO DO SPA · risco BAIXO
- [ ] Sanctum: instalar/configurar (stateful domains, CSRF, guard web p/ SPA).
- [ ] API admin: `app/ApiAdmin/` + `routes/api_admin.php` (prefixo `/api/admin`, middleware
      `auth:sanctum`); endpoint `me` (usuário + permissões RBAC) + login/logout/CSRF.
- [ ] Scaffold `frontend/`: Vite + React + TS + Tailwind + shadcn/ui + react-router + TanStack
      Query (server state) + axios (cliente com XSRF). Build → `public/app`.
- [ ] **AppShell**: sidebar + header com marca Dubena (paleta acima), tema claro/escuro,
      navegação declarativa, guarda de rota por permissão. Tela de **login** (Sanctum).
- [ ] Nginx (deploy): servir `/app` (SPA) + `/api/admin`; CI faz `npm ci && vite build`.

**Portão S1:** logar no SPA (`/app`), ver o AppShell com a marca, `me` retornando permissões.

### S2 — CADASTROS (vitrine do padrão) · risco BAIXO
- [ ] **Cliente (página completa)** — 1º alvo: lista (paginada/filtrada, server-side via API) +
      ficha em abas (Dados/Endereço/Fiscal/Convênio) + sub-recursos (Telefones/Contatos/Pedidos/
      Financeiro) na mesma página. API admin de cliente reusa ClienteRequest/regras.
- [ ] Produto, Geográfico (Cidade/Bairro/Rua), Empresa/Config — mesmos padrões React.
- [ ] Componentes reaproveitáveis: DataTable, FormField (com máscara moeda/decimal BR),
      Select assíncrono (cidade→bairro→rua), uploader, tabs, modais.

**Portão S2 (por entidade):** tela React validada dev→prod; permissão por RBAC; legado da
entidade aposentado por flag (link do legado passa a apontar p/ `/app/<modulo>`).

### S3 — MOTORES (Estoque + Financeiro) · risco MÉDIO
- [ ] API admin expõe os Services (estoque/financeiro/caixa) com transação; telas React com
      feedback em tempo real (saldo, preview de baixa). Corrigir SQLi de filtro do Financeiro.

### S4 — FISCAL (NF-e/NFC-e/SPED) · risco ALTO
- [ ] (BLOQUEANTE) validar emissão SEFAZ homologação. Telas fiscais em React; malha coesa.

### S5 — VENDAS/PEDIDOS · risco MÁXIMO (por último)
- [ ] Pedido como Domain/Actions + testes; UI de venda React (jornada em etapas); preservar
      contrato do app mobile.

### S6 — SATÉLITES (paralelo) · RH, Frota, Vale-Gás, Relatórios, Monitoramento, Integrações.

### S7 — LIMPEZA & HARDENING
- [ ] Remover AdminLTE + Filament + menus/menuusers; consolidar RBAC; testes E2E (Playwright);
      auditoria de segurança; OpenAPI da API admin.

### S8 — MULTI-TENANT (adiada).

---

## REGRAS DE EXECUÇÃO
1. Backend: branch + CI verde (PHPUnit) + deploy + verificação VPS.
2. Frontend: build Vite no CI; testes de componente (Vitest) + E2E (Playwright) por feature.
3. Não quebrar o app mobile (api.php intocado) nem o legado (coexistência).
4. Permissão por RBAC (a API admin checa `can('<modulo>.<acao>')`).
5. Cada entidade migrada: validar dev→prod → apontar legado p/ `/app/<modulo>`.

## Dependências
```
S1 Fundação(Sanctum+SPA shell) ─► S2 Cadastros ─► S3 Motores ─► S4 Fiscal ─► S5 Vendas
                                          └► S6 Satélites (paralelo) ─► S7 Limpeza ─► S8 Tenant
```
