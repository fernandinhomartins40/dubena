# PRD FIDEDIGNO (linha-a-linha) — Acesso / Permissões / Menu · D11

> **Diferente do PRD estratégico** (`PRD/11_*.md`): este foi escrito após LER 100%
> das linhas de TODOS os arquivos do módulo. Documenta comportamento real,
> bugs e segurança verificados no código, com `arquivo:linha`.
>
> Arquivos lidos integralmente (1.617 linhas):
> AuthController (187), UsersController (479), RoleController (196),
> MenuController (86), Menu (168), User (151), Role (29),
> Authenticate (63), AuthorizeCustom (254).

---

## 1. Mapa de rotas → ação (routes/web.php)
- `GET /login` → `AuthController@login` → view `auth.login`.
- `POST /handleLogin` → `AuthController@handleLogin`.
- `GET /logout` → `AuthController@logout`.
- `GET /home` (auth) → `UsersController@home` → view `users.home`.
- `GET /changepassword` (auth) → `UsersController@indexchangepassword`.
- `PATCH /updatepassword/{id}` (auth) → `UsersController@updatepassword`.
- `resource user` (auth) → `UsersController` (index/create/store/show/edit/update/destroy).
- `resource roles` (auth) → `RoleController`.
- `GET definirtipos` / `POST definirstore` → `RoleController@definicao/@definicaoStore`.
- `resource menu` → `MenuController` (**controller 100% vazio — rota inócua**).

## 2. Fluxo de LOGIN (AuthController@handleLogin, linhas 27-68) — verificado
1. Valida `email` (exists:users) + `password` (min:8) + `ativo` (`User::$login_validation_rules`, User.php:88-92).
2. `Auth::attempt(email,password,ativo)` (linha 32). **Inclui `ativo` no attempt** → usuário inativo não loga.
3. Em sucesso, monta a sessão:
   - `clearBrowserCache()` (101) — no-op em CLI (guard adicionado).
   - `empresa_padrao` = `$user->empresa` + descrições de bairro/cidade/rua (36-42).
   - `empresa_config` = `Empresaconfig::getForSession` (44-46).
   - `menu` = `Menu::menus()` → **HTML pré-renderizado** (48-49).
   - `permissoes` = `Menuuser join menus where user+empresa` (51-54).
   - `empresas_user` / `empresas_permitidas` (56-60).
   - `organizeNotifications` (62).
   - redirect → `home` (64).
4. Falha → volta com erro "Email e/ou senha inválidos." (66-67).
- `loginFromApi($data)` (70-93): variante para API (sem menu/notificações).

## 3. Permissionamento (modelo de dados)
- `menuusers(user_id, empresa_id, menu_id, visualizar, criar, editar, deletar, baixar, alerta)`.
- `menus(id, parent_id, titulo, descricao, ordem)` — árvore; `descricao` = nome da rota
  (ex.: `cliente.index`); pais têm `descricao` vazia/null.
- Permissão é por **usuário × empresa × menu** (multi-empresa real).

## 4. Autorização em runtime (AuthorizeCustom, 254 linhas) — verificado
- `handle` (18): se `authorization()` false → lança AuthException.
- `authorization` (30-43):
  - **`if strpos(route,'ajax.') return true`** (36) — rotas ajax.* sempre liberadas.
  - **`if ($request->ajax()) return true`** (39) — ⚠️ **QUALQUER request AJAX passa
    sem checar permissão**.
- `validar` (45-73): exceções → especiais → report → demais (deriva `<rota>.index` e
  checa ability via `validacoesGerais`).
- `validacoesGerais` (120-181): switch ability → combina visualizar/criar/editar/deletar.
- `getPermissoes` (250-253): `Session::get('permissoes')->where('descricao',$route)->first()`.

## 5. Montagem do MENU (Menu::menus / menusinner) — verificado
- `menus()` (84-107): pega `menu_id` de `menuusers` (user+empresa); carrega árvore com
  `with(implode('.', array_fill(0,100,'children')))` (91); **monta string HTML** `<li><ul>`.
- `menusinner()` (56-82): recursivo; idem HTML.

---

## 6. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA (o que a amostragem não pegou)

### 🔴 Segurança
- **AuthorizeCustom:39 — `if ($request->ajax()) return true`**: o ERP faz quase tudo
  via AJAX → autorização **amplamente bypassável** mandando header
  `X-Requested-With: XMLHttpRequest`. Falha grave (autorização efetiva só nas telas
  "cheias"). **Corrigir: não liberar por ser AJAX; aplicar a mesma regra de permissão.**
- **UsersController:412 e :423 — `hash_hmac('sha256',$senha,'secret',true)`**: o literal
  **`'secret'`** como chave HMAC do oauth client. A Fase 1 (S7) corrigiu isso no
  monitora/api, mas **AQUI no ERP permanece**. Secret previsível → tokens forjáveis.
  **Corrigir: usar SECRET_HMAC_KEY/APP_KEY (como já feito no monitora).**
- **User.php:83-86 — `$hidden` só tem `remember_token`**: `password` NÃO está em
  `$hidden`; qualquer serialização do model User (->toArray/->toJson) expõe o hash.
  **Corrigir: adicionar `'password'` ao $hidden.**
- **UsersController:40 — `User::all()` no index**: lista TODOS os usuários (todas as
  empresas), sem paginação nem filtro de empresa/tenant. IDOR + performance.
  **Corrigir: filtrar por empresa do usuário + paginar.**

### 🟠 Bugs funcionais
- **Menu.php:143-167 — `menuspermissoes`/`menuspermissoesAll` chamam `Menu::menuscheck()`
  que está COMENTADO (109-141)** e usam `$menu->users` (relação inexistente no Menu).
  São **código morto que quebraria se chamado**. Ninguém chama `Menu::*` (as chamadas
  reais são `Centrocusto::`/`Planoconta::menuspermissoesAll`, que têm seu próprio
  menuscheck). **Remover do Menu.**
- **Menu.php:73-77 — if/else do `report` idêntico nos dois ramos** (`link_to_route`
  igual): condição inútil. **Simplificar.**
- **Authenticate.php:60 — exceção `'cliente.createFromPedidos/{id}'`**: comparada com
  `route()->getName()` (que é `cliente.createFromPedidos`, sem `/{id}`) → **nunca casa**;
  exceção morta.
- **MenuController (86 linhas) 100% vazio** (métodos só com `//`): scaffold morto;
  `resource('menu')` nem está nas rotas. **Deletar controller + rota.**

### 🟡 Dívida estrutural
- **HTML do menu montado no Model** (Menu.php:60-107): apresentação no backend.
- **Eager loading de 100 níveis** (Menu.php:91 e tree:53): `array_fill(0,100,'children')`
  para árvore de ~3 níveis — desperdício/risco de performance.
- **Menu pré-renderizado na sessão** (AuthController:48-49): muda permissão → só no
  próximo login.
- **AuthorizeCustom**: `switch`/listas hardcoded (`especiais` 28 entradas, `excecoes`),
  fácil esquecer rota nova → 302/exception silenciosa. `validacoesEspeciais:110`
  `strpos(route,"logs")` casa qualquer rota contendo "logs".
- **UsersController God methods**: `dadosExtras` + `store`/`update` + `oauthClient` +
  `compareCall` (lógica de call-center misturada) num só controller.

### ✅ O que está BOM (não mexer sem motivo)
- `store`/`update`/`updatepassword`/`destroy` usam **DB::transaction** com rollback.
- `criarPermissoes` (245-304): robusto; sobe ancestrais (WITH RECURSIVE) para dar
  visualização aos menus-pai. Bem feito.
- `updatepassword` confere senha antiga com `Hash::check` (161).
- RoleController: limpo, transacional, filtra por empresa em `definicao`.
- Login inclui `ativo` no attempt (bom).

## 7. Compatibilidade Postgres (verificado)
- `criarPermissoes`/`getFinanceiros` já com WITH RECURSIVE (traduzido da Fase Postgres).
- `Menu::whereRaw('descricao is not null')` (UsersController:57,205) — sem interpolação,
  seguro.
- `getQuery` de notificações (AuthController:171) usa `||` e `like` — Postgres-OK.

## 8. ESPECIFICAÇÃO do módulo REESCRITO (Laravel 12) — baseada no código real
- **Auth**: login por email+senha+ativo, multi-empresa; manter `Auth::attempt` com
  `ativo`. Trocar oauth `'secret'` por chave de env.
- **Permissões**: manter modelo menuusers (user×empresa×menu×abilities) OU migrar p/
  spatie/laravel-permission. Autorização via **Policy/Gate por rota** (não switch);
  **remover o bypass de AJAX** — AJAX checa permissão igual.
- **Menu**: árvore declarativa (config/DB de dados, SEM HTML); componente de view
  filtra por permissão em tempo de render (reflete na hora, sem relogin); sem eager 100.
- **Usuários**: CRUD com index paginado e filtrado por empresa; password em $hidden;
  separar lógica de call-center/oauth em Services.
- **Limpeza**: deletar MenuController vazio + rota; remover `Menu::menuspermissoes*`
  mortos; corrigir/remover exceção morta do Authenticate.
- **Migração de dados**: menuusers → novo modelo de permissão (mapear abilities).

## 9. DECISÃO (confirmada pela leitura): **REESCREVER**
- Baixo risco fiscal; alta dívida + **3 falhas de segurança reais** (AJAX bypass,
  'secret' HMAC, password fora do $hidden) que por si só justificam reescrever a base.
- É a **fundação** dos módulos novos. Primeiro a migrar entre os REESCREVER, **após**
  as frentes A (app TLS) e C (SQLi) do PLANO_FECHAMENTO_PENDENCIAS.
- **Quick wins aplicáveis JÁ (mesmo antes da reescrita)**, por serem segurança:
  (a) `password` no $hidden; (b) `'secret'`→env no oauth; (c) reavaliar o
  `return true` de AJAX no AuthorizeCustom.
- **Esforço:** médio (1–2 semanas) + migração de permissões.
