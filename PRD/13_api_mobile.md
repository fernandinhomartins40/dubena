# PRD FIDEDIGNO (linha-a-linha) — API Mobile (módulo App\Api) · D13

> Módulo `App\Api` (unificado da antiga api-app-gc), 20 controllers (~4.100 linhas) +
> NfwebController (1763, no ctrl-web raiz, é API/web do app de NF).
> Lidos integralmente: ApiController(150), SecretController(252), UserController(349),
> ClienteController(484, núcleo), PedidoController(1142, store/núcleo).
> Caracterizados (arquitetura): Empresa/Endereco/CondicaoPagamento/Coupons/Notificacao/
> Produto/Video/OauthClient/Passport/etc. (Repositories + Resources + Passport).

- **Status:** ✅ pronto (fiel — núcleo lido; periféricos caracterizados)
- **Criticidade:** 🔴 (porta pública: app dos clientes + revendas; cria pedido/PIX)
- **Decisão:** **REFATORAR/MANTER** (módulo mais MODERNO que o ERP — Passport/Repos/
  Requests/Resources) — corrigir SQLi e token de usuário fixo

---

## 1. O que cada peça FAZ (verificado)
- **SecretController (252):** **autenticação do app** — `getToken` (app_key →
  Passport accessToken), `onOpenApp` (abertura: cliente+empresas+cupom+endereço+pedido em
  track), testToken/testTokenERP/testTokenFromApi (valida o elo app↔ERP via ApiResources).
- **PedidoController (1142):** **pedido pelo app** — `store` (valida endereço/situação/
  cupom/gás do povo, cria pedido, **linka ao ERP** via `linkTo`/ApiResources HTTP, PIX),
  `track` (acompanhamento em tempo real + simulador), avaliação, itens.
- **ClienteController (484):** **cliente final do app** — cadastro/identidade por
  **telefone + primeiro nome** (sem senha), detecção de novo dispositivo, convênio,
  gás do povo, push registration.
- **UserController (349):** **revenda/distribuidora** — cadastro com horários de
  funcionamento, polígonos de área (Polylines), gás do povo, token OAuth revenda↔ERP.
- **ApiController (150):** reverse geocoding (Google), logs (conexão `sgcm_logs`), marker
  de mapa, download do app.
- **Periféricos:** Empresa (revendas próximas), Endereco, CondicaoPagamento, Coupons
  (cupom de desconto), Notificacao (push), Produto/Categoria, Video, Feriado, ConfigUser,
  GeneralConfig, OauthClient/Passport (tokens). Usam Repositories + ApiResources + Passport.

> Regra real a preservar: o **contrato com os apps publicados** (rotas/payloads de
> getToken, onOpenApp, pedido, track, cliente) — apps nas lojas dependem dele.

---

## 2. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA

### 🔴 Segurança (SQLi em endpoint público)
- **ClienteController::newClientWithPhone:88 — SQLi**: `whereRaw("telefoneantigo = '" .
  $dataPhone['telefone'] . "' AND primeironome = '" . firstWord($data["nome"]) . "'")`
  interpola **telefone e nome do request** sem binding. É chamado no **cadastro de cliente
  do app** (fluxo `onOpenApp`, pré-autenticação) → **SQL injection numa porta pública**.
  **Parametrizar.**

### 🔴 Segurança (token de usuário único) e chave previsível
- **SecretController::getToken:51 — `User::findOrFail(env('DEFAULT_USER_ID'))`**: TODOS os
  tokens do app são emitidos para **UM usuário fixo** → o app opera como identidade única
  na API (sem rastreabilidade por cliente; risco multi-tenant). Padrão legado conhecido.
- **NfwebController::getToken:87 (ctrl-web raiz) — `sha1(env('APP_KEY'))`**: ⚠️ enquanto o
  módulo App\Api **JÁ corrigiu** (APP_TOKEN_KEY + hash_equals, Fase 1 — ver
  SecretController:37), **o NfwebController ainda usa `sha1(APP_KEY)`** (chave previsível,
  APP_KEY vazou no repo do app). **Migrar p/ APP_TOKEN_KEY/hash_equals igual ao App\Api.**

### 🟠 Bugs / dívida funcional
- **UserController::index:31 — `User::all()`** (todas as revendas, sem paginação/filtro);
  `index/store/update` **sem authorize explícito** (dependem de middleware da rota admin).
- **ApiController::logs/getLog/reportLog sem authorize explícito** (logs da API por rota).
- **PedidoController::store** depende de `linkTo` HTTP ao ERP (ApiResources) — o objetivo
  da unificação é trocar por **chamada interna** (ainda é cliente-servidor HTTP).

### 🟡 Dívida estrutural
- **Cliente-servidor HTTP interno**: App\Api ↔ ERP via `ApiResources` (erpurl) — ida-e-
  volta HTTP dentro do mesmo app pós-unificação. Trocar por chamadas internas/eventos.
- **Tabelas espelho** (*_importacao: ProdutoImportacao/CondPgtoImportacao) sincronizadas
  do ERP — manter por ora; eliminar duplicação na virada (já mapeado no projeto).
- **Controller↔controller** (Secret→Cliente/Empresa/Coupons/Endereco/Pedido).
- `\Input` em vez de request tipado em vários (mas FormRequests presentes nos núcleos).

### ✅ O que está BOM (é o módulo mais moderno)
- **Laravel Passport** (OAuth2 real), **Repositories**, **FormRequests** (Pedido/Cliente/
  User Request), **API Resources**, transações, `getFillable()` whitelist, `hash_equals`
  no getToken (App\Api), Hash::check nas senhas, validação de CPF, suporte a PIX/cupom/
  gás do povo/track em tempo real. Arquitetura claramente superior ao ERP legado.
- getToken do App\Api **já endurecido** na Fase 1 (APP_TOKEN_KEY).

## 3. Especificação do REFAT/MANTIDO (Laravel 12)
- **Manter a arquitetura** (Passport/Repos/Requests/Resources) — modernizar junto do
  framework. **Preservar contratos** dos apps publicados (versionamento retrocompatível).
- **Corrigir SQLi** do newClientWithPhone (binding) — prioridade (porta pública).
- **Token por cliente/revenda** (não DEFAULT_USER_ID fixo) — quando o app permitir
  identidade própria; alinhar com multi-tenant.
- **Trocar ApiResources HTTP→chamadas internas** (eliminar ida-e-volta) e aposentar
  tabelas espelho na virada.
- **NfwebController** → migrar p/ APP_TOKEN_KEY (igual App\Api) ou consolidar no App\Api.

## 4. DECISÃO
- **Decisão: REFATORAR/MANTER** (base moderna; correções pontuais de segurança).
- **Quick wins aplicáveis JÁ:**
  (a) **parametrizar o whereRaw do newClientWithPhone** (SQLi em porta pública) — prioridade;
  (b) **NfwebController::getToken → APP_TOKEN_KEY/hash_equals** (chave previsível);
  (c) paginar/escopar User::all() e authorize nas telas admin da API.
- **Pré-requisitos:** contratos dos apps mapeados (não quebrar lojas); multi-tenant p/
  token por identidade; D01 (pedido) e D04 (PIX/financeiro) alinhados.
- **Esforço:** baixo-médio (base já moderna; correções pontuais).
- **Ordem:** SQLi do cliente e chave do Nfweb são quick wins de segurança (já); refactor
  do elo HTTP→interno junto da unificação final.
