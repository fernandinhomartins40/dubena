# PRD — Acesso / Permissões / Menu  ·  D11

- **Status:** ✅ pronto (piloto)
- **Criticidade:** 🟠 (controla acesso a todo o sistema; erro = ninguém entra ou todos veem tudo)
- **Decisão:** **REESCREVER** (ver §8)

> Módulo-piloto. É onde está a "gambiarra do menu" que motivou esta discussão.

---

## 1. Escopo
- **Controllers:** `AuthController` (187), `UsersController` (479), `RoleController` (196), `MenuController` (86).
- **Models/tabelas (public):** `users`, `roles`, `menus` (árvore via `parent_id`),
  `menuusers` (permissão usuário×menu×empresa: visualizar/criar/editar/deletar/baixar/alerta),
  `empresa_user`, `password_resets`.
- **Rotas:** `/login`, `/handleLogin`, `/logout`, `/changepassword`, resource `user`,
  resource `roles`, resource `menu`, `/empresachange/{id}`.
- **Views:** `auth/login`, `users/*`, `layouts/mainmenu.blade.php` (consome o menu).
- **Peça central:** `App\Menu::menus()` + `menusinner()` e o middleware
  `AuthorizeCustom` (`app/Http/Middleware/Authenticate.php` + a lógica `pode`).

## 2. O que o módulo FAZ (regra de negócio)
- **Login** (`AuthController@handleLogin`): autentica; monta na sessão
  `empresa_padrao`, `empresa_config`, `menu` (HTML), `permissoes`, `empresas_user`,
  `empresas_permitidas`; organiza notificações; redireciona p/ `/home`.
- **Permissionamento data-driven**: o que um usuário vê/pode é definido por linhas
  em `menuusers` (por usuário **e** empresa). `AuthorizeCustom` checa, a cada rota,
  se há permissão correspondente (`<rota>.index` + ability create/edit/...).
- **Menu lateral**: montado a partir dos `menus` que o usuário tem permissão de ver,
  respeitando a hierarquia `parent_id` (submenus).
- **Troca de empresa** (`empresachange`): usuário com várias empresas alterna a
  `empresa_padrao` (recarrega menu/permissões/config daquela empresa).
- **Gestão de usuários/papéis**: CRUD de usuários, atribuição de permissões por menu.

> Regra legítima (NÃO é gambiarra): permissão por **empresa** (multi-empresa) é
> requisito real — o mesmo usuário pode ter acessos diferentes por filial.

## 3. Como FAZ hoje (implementação atual)
- `Menu::menus()` busca os `menu_id` permitidos em `menuusers`, carrega a árvore e
  **monta string de HTML** (`<li><ul>...`) no PHP, guardada em `Session('menu')`.
- A view `mainmenu.blade` só faz `@foreach(Session::get('menu'))` e ecoa o HTML.
- `AuthorizeCustom` mapeia rota→permissão com `switch`/arrays de exceções e casos
  especiais (grande, frágil).

## 4. Gambiarras / dívida técnica / práticas amadoras
- [ ] **HTML montado no Model** (`Menu::menus`/`menusinner`, `app/Menu.php:60-100`):
      apresentação no backend, impossível de testar/estilizar; mistura camadas.
- [ ] **Eager loading de 100 níveis**: `with(implode('.', array_fill(0,100,'children')))`
      (`app/Menu.php:91`) — carrega 100 relações `children` aninhadas; absurdo de
      performance para uma árvore de ~3 níveis.
- [ ] **Menu pré-renderizado na sessão**: muda permissão → só reflete no próximo
      login; sessão guarda HTML (acopla view a auth).
- [ ] **"Página só aparece se constar no banco"** (a dor relatada): consequência de
      o menu E a autorização dependerem de `menuusers`. Sem registro → invisível e
      barrado. Sem fallback, sem seed garantido → foi a causa do "só vejo /home".
- [ ] **AuthorizeCustom** com listas de exceção hardcoded e `switch` gigante:
      difícil manter, fácil esquecer rota nova (vira 302 silencioso).
- [ ] `header()` cru em `clearBrowserCache` (já mitigado com guard CLI).

## 5. Riscos de tocar neste módulo
- **Alto alcance**: mexer aqui afeta o acesso a TODOS os módulos.
- **Multi-empresa**: a lógica de permissão por empresa precisa ser preservada
  fielmente (é regra real).
- Sem teste de autorização hoje (só o fluxo de login validado).

## 6. Estado de compatibilidade Postgres
- ✅ Funciona em Postgres (boolean→smallint cobriu os flags de menuusers).
- ✅ Seeders garantem menus + permissões do admin no deploy.
- Sem whereRaw de risco aqui.

## 7. Visão do módulo REESCRITO (Laravel 12 + boas práticas)
- **Permissões**: adotar `spatie/laravel-permission` (roles & permissions maduro)
  OU manter o modelo menuusers, mas com **Policies/Gates** do Laravel (não switch).
- **Menu**: definido em **config/código** (uma árvore declarativa de rotas), e o que
  aparece é filtrado por permissão em tempo de render — **fim do HTML no banco/PHP**.
  Permissão muda → reflete na hora (sem depender de relogin).
- **UI moderna**: layout novo (ex.: Blade + Tailwind/Livewire, ou SPA), menu como
  componente que recebe a árvore + permissões.
- **Autorização**: middleware único baseado em Policy por rota/recurso (declarativo),
  com teste de "usuário sem permissão recebe 403", "com permissão vê", e isolamento
  por empresa.
- **Contrato a preservar**: o conceito permissão×empresa; as tabelas podem ser
  remodeladas (migração de dados de menuusers→novo modelo).

## 8. DECISÃO e justificativa
- **Decisão: REESCREVER.**
- **Por quê:** baixo risco fiscal (não calcula imposto), alta dívida técnica
  (HTML no model, eager 100 níveis, switch de autorização), e é **fundação**: um
  modelo de auth/menu limpo destrava a reescrita moderna dos demais módulos. O
  custo de refatorar a gambiarra do menu ≈ custo de reescrever, mas reescrevendo
  ganhamos a base correta para o resto.
- **Pré-requisitos:** definir stack de frontend do "novo" (decisão de projeto);
  mapear todas as permissões/abilities atuais para migrar `menuusers`.
- **Esforço estimado:** médio (1–2 semanas), inclui migração de dados de permissão.
- **Ordem:** **primeiro a reescrever** entre os candidatos a REESCREVER — é a
  fundação de acesso/navegação que os outros módulos novos vão usar. Mas só depois
  de fechadas as frentes de segurança A/C do PLANO_FECHAMENTO_PENDENCIAS.
