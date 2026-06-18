# MODERNIZAÇÃO (auditoria de código) — Acesso / Permissões / Menu · D11

> Auditoria do CÓDIGO REAL (pós F0–F4A) vs. PRD fiel
> [`11_acesso_permissoes.md`](11_acesso_permissoes.md). É a FUNDAÇÃO da modernização
> (menu-no-banco + níveis de acesso). Inclui a comparação **RBAC-roles × manter menuusers**
> que decide o resto do projeto.

---

## 1. ANTES × AGORA (verificado no código)

| Item (PRD fiel §6) | Original | HOJE (auditado) | Ref. |
|---|---|---|---|
| Bypass `if ($request->ajax()) return true` | 🔴 autorização contornável | 🟡 **agora atrás de flag** (kill-switch) | `AuthorizeCustom.php:52` |
| Bypass `ajax.*` sempre liberado | 🔴 | ⚠️ **ainda liberado** (auxiliares de leitura) | `AuthorizeCustom.php:40` |
| `password` fora de `$hidden` | 🔴 expõe hash | ✅ **corrigido** (F0/F1) | `User.php` `$hidden` |
| HMAC `'secret'` no oauth | 🔴 previsível | ✅ **corrigido** → `config('integracoes.oauth_client_hmac_key')` | `UsersController.php:412,423` |
| `User::all()` no index (IDOR) | 🔴 lista todas empresas | ✅ **substituído** por UserResource escopado por empresa | `UserResource.php` (F4A) |
| `Menu::menuspermissoes*` morto (fatal) | 🟠 | ✅ **removido** | `Menu.php` (F4A) |
| Menu HTML montado no Model | 🟡 dívida | ❌ **intacto** (string `<li><ul>` cacheada na Session no login) | `Menu.php::menus()` |
| `canAccessPanel`/`podeNoMenu` | (não existia) | ✅ **novo** (Filament lê menuusers via banco) | `User.php:77,89` (F4A) |

> A F4A já entregou: kill-switch do bypass, UserResource (IDOR), RelationManager de
> permissões (menuusers editável na UI Filament), limpeza do Menu. Falta: allowlist das
> `ajax.*`, **menu declarativo** e a **decisão do modelo de permissões**.

---

## 2. O MODELO ATUAL (verificado) — por que é complexo

- **Permissão = `menuusers(user_id, empresa_id, menu_id, visualizar, criar, editar, deletar,
  baixar, alerta)`**: granularidade por **usuário × empresa × menu × 6 flags**. Não há
  conceito de PAPEL (role) — cada usuário recebe a matriz inteira individualmente.
- **Menu = `menus(id, parent_id, titulo, descricao, ordem)`**: árvore (5 raízes + 212
  filhos, auditado). `descricao` do nó-folha = nome da rota (ex.: `cliente.index`); nós-pai
  têm `descricao` nula. **HTML do menu é montado no Model** (`Menu::menus()` gera string
  `<li><ul>`) e **cacheado na Session no login** → mudança de permissão só reflete no próximo
  login.
- **Autorização runtime** = `AuthorizeCustom` (middleware `pode`): switch/listas hardcoded
  (`especiais` ~28 entradas, `excecoes`), deriva `<rota>.index` e checa flags. Frágil: rota
  nova esquecida → 302/exception silenciosa.
- **Criação de permissões** = `UsersController::criarPermissoes` (479 linhas no controller,
  God): grava menuusers por menu e **sobe ancestrais** via WITH RECURSIVE (concede
  visualizar=1 aos pais). `update` faz `menuuser()->delete()` + recria do zero.

**Resumo da complexidade:** atribuir acesso a um usuário = marcar dezenas/centenas de menus
× 6 flags × por empresa, manualmente, sem reaproveitamento entre usuários iguais.

---

## 3. DECISÃO-CHAVE (definida pelo cliente): ABANDONAR menu-no-banco + RBAC por papéis

> O cliente foi explícito: menu no banco é arcaico e não se usa mais; o alvo é navegação
> moderna (sidebar declarativo) e telas completas. Portanto, no ALVO:
> **a tabela `menus` deixa de existir** e **`menuusers` é substituída por papéis (roles)**.

### Navegação — ABANDONAR `menus` (tabela)
- O sidebar passa a ser **declarativo**: cada Resource Filament registra-se na navegação
  (`navigationGroup`/`navigationIcon`/`navigationSort`). Sem árvore no banco, sem HTML no
  Model, sem cache na Session. Muda permissão → reflete na hora.
- O legado AdminLTE (e sua `menus`/`Menu::menus()`) é descartado conforme os módulos migram
  para Filament. Não se "moderniza" o menu-no-banco — ele **sai**.

### Permissões — RBAC com Papéis (spatie/laravel-permission), alvo
- **Papéis** (Vendedor, Caixa, Gerente, Admin…) agrupam permissões nomeadas por recurso/ação
  (`cliente.view`, `pedido.create`…). Usuário recebe papel(is) — atribuição por **1 clique**,
  não a matriz user×empresa×menu×6 flags atual.
- Telas/recursos checam via **Policy** (`viewAny/view/create/update/delete`); integra com
  Filament (Shield).
- **Migração segura (sem big-bang):** a F4A já lê `menuusers` via `podeNoMenu`. Introduzir
  papéis por cima (um papel = conjunto de permissões pré-definido), migrar usuários para
  papéis, e então **aposentar `menuusers` e `menus`**. Multi-empresa por escopo/tenant.
- **Trade-off registrado:** manter `menuusers` (opção conservadora) foi DESCARTADO pelo
  cliente — mantém a complexidade que ele quer eliminar.

---

## 4. NAVEGAÇÃO DECLARATIVA (não-no-banco) — ver MODERN_00

- O esboço do sidebar-alvo (grupos: Cadastros, Vendas, Estoque, Financeiro, Fiscal, RH,
  Frota, Relatórios, Admin) está em [`MODERN_00_VISAO_UX.md`](MODERN_00_VISAO_UX.md) §3.
- Cada item do sidebar = um Resource Filament. A hierarquia que hoje vive em `menus.parent_id`
  vira `navigationGroup` no código. `Menu::menus()`/`menusinner()` e o cache na Session
  deixam de ser usados (removidos quando o último módulo migrar).

---

## 5. UX/UI — o que moderniza

- **Gestão de usuário**: hoje um form gigante + matriz de permissões; alvo = UserResource
  Filament (já criado) + atribuição por PAPEL (1 clique) + aba de permissões finas só quando
  necessário.
- **Login**: único (já reusado pelo Filament na F3). Considerar 2FA e política de senha.
- **Auditoria**: hoje não há trilha de quem mudou permissão; alvo = log/auditoria.

---

## 6. PENDÊNCIAS RESIDUAIS (arquivo:linha — auditado)

- `AuthorizeCustom.php:40` — `ajax.*` ainda liberado sem checar permissão (allowlist pendente:
  separar leitura vs. as 17 `ajax.*` de gravação).
- `AuthorizeCustom.php` (`especiais`/`excecoes`) — listas hardcoded; alvo = permissão por rota.
- `Menu.php::menus()` — HTML no Model + cache na Session (menu declarativo pendente).
- `UsersController.php` (479) — God controller (criarPermissoes + compareCall + oauthClient +
  dadosExtras juntos); alvo = Services + UserResource.
- `Authenticate.php:60` — exceção morta `'cliente.createFromPedidos/{id}'` (nunca casa).
- `MenuController` (ERP) — scaffold 100% vazio, sem rota → deletar.

> **Decisão herdada (PRD fiel):** REESCREVER. Esta auditoria mostra que a F4A já avançou
> metade do caminho de forma incremental e segura. Próximo passo de fundação: **escolher A/B/
> híbrido** acima, depois menu declarativo + allowlist `ajax.*`.
