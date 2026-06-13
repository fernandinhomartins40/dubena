# Análise Técnica Completa — Aplicação "api-app-gc"

> **Documento gerado por auditoria de código-fonte.**
> Base da análise: **exclusivamente o código-fonte real** do workspace `api-app-gc`.
> Documentação auxiliar (`instruções api-siav.txt`, comentários) foi **ignorada como fonte de verdade** e usada apenas como pista a confirmar no código — **exceto** quando o próprio documento expõe segredos versionados, fato registrado como evidência de segurança.
> Data da análise: 2026-06-12.
> Cada conclusão referencia o arquivo/linha que a fundamenta. Itens sem evidência conclusiva estão marcados como **[Necessita Validação]**.

> **Nota sobre a premissa de "25 anos":** o código **não sustenta** essa idade. As migrations começam em **2018-07-11** (`database/migrations/2018_07_11_124554_create_users_table.php`) e o sistema usa Laravel 5.6 / Passport / Vue / Firebase — stack de **~2018**. Trata-se de uma aplicação **relativamente recente (~7-8 anos)**, satélite do ERP legado `ctrl+`. A premissa de 25 anos aplica-se ao ecossistema/negócio, não a este componente.

---

## 1. Visão Geral da Aplicação

### Objetivo principal
O `api-app-gc` é a **API REST backend do aplicativo móvel "Gás em Casa" (GC)** — o canal digital de **venda de gás/GLP ao consumidor final**. Funciona como **camada de integração** entre o aplicativo (cliente final) e o ERP legado `ctrl+`, espelhando cadastros do ERP e orquestrando pedidos, pagamentos PIX, rastreamento de entrega e notificações push.

A natureza fica evidente em: rotas `order/`, `reseller/` (revenda), `coupons/`, `client/`; integração FCM/Firebase (`ApiResources`); rastreamento (`PedidoController@track`, `VehiclePosition`); e o padrão de tabelas **espelho** `*_importacao`/`*_importacoes` que vinculam (`erp_id`) entidades da API às do ERP.

### Principais funcionalidades identificadas (por evidência de código — `routes/api.php`)
| Funcionalidade | Evidência |
| --- | --- |
| Geração de token de acesso para o app | `SecretController@getToken`, `web/testTokenFromApi` |
| Cadastro/gestão de clientes e endereços | `ClienteController`, `EnderecoController` (`client/*`, `address/*`) |
| Catálogo: produtos, categorias, preços, condições de pagamento | `ProdutoController`, `CondicaoPagamentoController` |
| Pedidos: criar, atualizar, histórico, avaliar, cancelar | `PedidoController` (`order/*`) |
| Rastreamento de entrega em tempo real | `PedidoController@track`, `@tracking`, `@getLastestStatus`, `VehiclePosition`, `UserPolylines` |
| Pagamento PIX (confirmar/checar/expirar) | `order/pixpaid`, `order/ispaid/{id}`, `order/expired` |
| Cupons de desconto | `CouponsController` (`coupons/verify`, `coupon`) |
| Revendas (empresa) e "Gás do Povo" (programa social) | `EmpresaController`, `User@updateGasPovo`, migrations `*_add_gasdopovo` |
| Notificações push (recompra, cupom, entrega) | `NotificacaoController@sendNotification*`, FCM |
| Vínculo (linking) API↔ERP de todos os cadastros | rotas `*/getToLink`, `*/link`; models `*Importacao` |
| Feriados/recessos (disponibilidade da revenda) | `FeriadoController` |
| Vídeos (conteúdo do app) | `VideoController` |
| Logs de acesso/erros (banco separado) | `ApiController@reportLog`, conexão `sgcm_logs` |

### Fluxo geral de funcionamento
1. App obtém token: `getToken` valida `app_key == sha1(APP_KEY)` e emite token Passport de um **usuário fixo** (`DEFAULT_USER_ID`).
2. App abre: `app/init` (`SecretController@onOpenApp`) agrega cliente, revendas próximas, cupom, endereços e pedido em rastreamento numa única transação.
3. Cliente faz pedido (`order/create`) → API repassa ao ERP via `ApiResources` (HTTP) e/ou persiste localmente.
4. Pagamento PIX confirmado por chamada (`order/pixpaid` / `ispaid`).
5. Entrega rastreada (`order/track`); status e posição do veículo retornados ao app.
6. Notificações push (FCM) disparadas em eventos.

### Público usuário
- **Consumidor final** (app mobile "Gás em Casa") — principal consumidor da API.
- **ERP `ctrl+`** — consumidor server-to-server (rotas de linking/importação).
- **Painel web administrativo mínimo** (`routes/web.php`, `LoginController`, Vue) — gestão interna.

### Módulos existentes
Auth/Token, Cliente, Endereço, Produto/Categoria/Preço, Condição de Pagamento, Pedido, Cupom, Revenda/Empresa, Feriado, Notificação, Vídeo, Polígonos/Rastreamento, Config, Logs.

---

## 2. Stack Tecnológica

> Versões **efetivamente instaladas** lidas de `composer.lock`.

| Tecnologia | Versão | Utilização | Status |
| --- | --- | --- | --- |
| PHP | `^7.1.3` (constraint) | Linguagem backend | 🔴 **Obsoleto** — PHP 7.1 EOL desde dez/2019 |
| Laravel Framework | **5.6.40** (`composer.lock`) | Framework MVC/API | 🔴 **Obsoleto** — Laravel 5.6 EOL desde 2019; sem patches de segurança |
| MySQL | — (utf8mb4) | Banco principal + `sgcm_logs` | Ativo (`config/database.php`) |
| Laravel Passport | ^6.0 | OAuth2 / tokens da API mobile | ⚠️ Antigo (atual 11+) |
| lcobucci/jwt | 3.3.3 | JWT (dep. Passport) | Antigo |
| guzzlehttp/guzzle | 6.x | HTTP client (ERP, FCM, Google) | ⚠️ Guzzle 6 (atual 7) |
| google/auth | 1.20 | Autenticação FCM v1 (Firebase) | OK |
| Firebase Cloud Messaging | API HTTP | Notificações push | Serviço externo |
| doctrine/dbal | ^2.8 | Schema/migrations | Antigo |
| intervention/image | ^2.4 | Processamento de imagens | OK |
| cboden/ratchet + ratchet/pawl | ^0.4 / ^0.3.4 | WebSocket (rastreamento? `WsConnect`) | Uso pontual |
| laravel/tinker | ^1.0 | REPL dev | Dev |
| barryvdh/laravel-ide-helper | ^2.4 | Helper de IDE | Dev |
| Vue.js | ^2.5 | Frontend do painel web | ⚠️ Vue 2 EOL desde dez/2023 |
| Bootstrap / bootstrap-vue | ^4.0 / 2.0-rc | UI do painel | Antigo (rc) |
| Firebase / firebase-admin / firebaseui (JS) | ^5.x / ^6.x / ^3.4 | Auth/integração front | Antigo |
| Laravel Mix (webpack) | ^2.0 | Build de assets | ⚠️ Mix 2 antigo |
| jQuery | ^3.2 | Frontend | Legado |
| PHPUnit | ^7.0 | Testes | Antigo |

### Serviços externos / APIs
- **Consumidas:** ERP `ctrl+` (via `ApiResources`), **Google Maps Geocode** (`GMAPS_URL`), **Google Roads API** (`PedidoController:207`), **Firebase Cloud Messaging** (push), Slack (log webhook — `.env`).
- **Expostas:** API REST `routes/api.php` (Passport `auth:api`).

### Ferramentas de build / deploy
- Build: **Laravel Mix 2 / Webpack** (`webpack.mix.js`, `package.json`), npm **e** yarn (ambos lockfiles presentes — `package-lock.json` + `yarn.lock`, redundância).
- Deploy: não há pipeline CI/CD versionado **[Necessita Validação]**; `.gitignore` referencia ambiente IIS (`web.config`).

### Obsolescência (resumo)
**PHP 7.1 + Laravel 5.6 + Passport 6 + Vue 2** — toda a stack está fora de suporte de segurança. É o ponto mais crítico de modernização.

---

## 3. Arquitetura da Aplicação

### Padrão arquitetural
- **Monólito Laravel orientado a API REST** (MVC sem a camada V relevante — frontend é Vue/app externo).
- **Boa adesão ao padrão Repository**: **21 repositórios** em `app/Repository/` para ~27 models — separação de acesso a dados **superior** à do ERP legado.
- Camadas auxiliares: `app/Http/Resources/ApiResources.php` (cliente HTTP/integração ERP+FCM), `app/Services/CarbonCustom.php`, `app/Helpers/` (Util + customHelpers, 33 funções).

### Monólito ou microsserviços
**Monólito**, porém parte de uma **arquitetura distribuída de fato**: a documentação interna e o `.env` revelam um **"Serviço de Integração" (SIAV)** separado e múltiplos bancos. Esta API conecta a:
- `mysql` (principal — `sgcm_api`)
- `sgcm_logs` (logs — `Util.php:167`, `ApiController:45`)
- e consome o ERP `ctrl+` por HTTP.

### Camadas existentes
```
App Mobile (Vue/Firebase) ──HTTP──► API REST (routes/api.php)
                                        │
                                  middleware: auth:api (Passport) + access(log) + DebugMode
                                        │
                                     Controllers (25)
                                        │
                            ┌───────────┼───────────────┐
                            ▼           ▼               ▼
                       Repository(21)  ApiResources    Helpers/Util
                            │          (HTTP→ERP/FCM)
                            ▼
                       Models Eloquent (27) ──► MySQL (sgcm_api) / MySQL (sgcm_logs)
                            │
                            └──► ERP ctrl+ (server-to-server)
```

### Acoplamentos excessivos
- **Controllers instanciam outros controllers diretamente** (anti-padrão): `SecretController@onOpenApp` faz `new ClienteController()`, `new EmpresaController()`, `new PedidoController()`, `new EnderecoController()` (linhas 80, 99, 118, 121). Isso acopla controllers entre si e burla o ciclo de request/DI.
- **`ApiResources`** concentra integração ERP **e** FCM **e** Google — God-class de integração.
- Dependência forte de **estado/usuário fixo** (`DEFAULT_USER_ID`, `DEFAULT_USER_SYSTEM`) para a autenticação do app.

### Dependências circulares
Não detectada circularidade formal de namespaces; há **acoplamento cruzado entre controllers** (acima) que produz dependência implícita. **[Necessita Validação por análise estática]**

### Fluxo de requisições
`Request → auth:api (Passport) → access (apenas loga) → DebugMode (loga request/response) → Controller → Repository/ApiResources → MySQL/ERP/FCM`.

### Fluxo de dados
Input chega via `Request`/`Input::` (33 usos de `Input::`/`$_GET`). Dados de cadastro são **espelhados do ERP** (`*Importacao`, campo `erp_id`). Resposta padronizada por helpers `responseSuccess/responseError/responseReject`.

---

## 4. Modelagem de Dados

> Mapeada a partir de **70 migrations** (2018→2026) e **27 models**.

### Tabelas (≈31 criadas — migrations `create_*`)
`users`, `menus`, `pedido_situacoes` (+`_importacoes`), `produto_categorias` (+`_importacoes`), `produtos` (+`_importacoes`), `condicao_pagamentos` (+`_importacoes`), `cliente_importacao`, `cliente_telefones`, `cliente_enderecos`, `pedidos`, `pedido_itens`, `general_configs`, `produto_condicao_pagamentos`, `sessions`, `userpolylines`, `feriados`, `pedido_avaliacaos` (sic), `vehiclespositions`, `ordersqueue`, `logs`, `reported_logs`, `cupons`, `accesses`, `jobs`, `failed_jobs`, `videos`, + índices (`indexusersativos`, `indexpedidos…`).

> **Nota:** não há migration `create_clientes_table` explícita no recorte inicial — clientes parecem residir na tabela `users`/`cliente_importacao` **[Necessita Validação]**. O model `User` representa tanto **revenda/usuário-sistema** quanto possivelmente cliente — ver normalização.

### Relacionamentos, PKs, FKs, índices
- PKs: padrão `id` (auto-increment MySQL).
- FKs/índices: definidos nas migrations (há migrations dedicadas a índices — `indexusersativos`, etc.), indicando **preocupação com performance** — melhor que o ERP legado. Cobertura completa **[Necessita Validação]**.
- Relacionamentos Eloquent presentes nos models (`Pedido`, `PedidoItem`, `ClienteEndereco`, etc.).

### Procedures / Triggers / Views
- **Não há** procedures/triggers/views versionadas nas migrations (busca não retornou DDL desse tipo). MySQL puro com lógica na aplicação. ✅ (melhor rastreabilidade que o ERP Oracle).

### Padrão de modelagem "espelho" (Importação)
Tabelas `*_importacao(es)` duplicam entidades do ERP com `erp_id`. É uma **redundância arquitetural intencional** (sincronização/integração), mas gera:
- **Duplicação de dados** entre API e ERP (risco de divergência).
- Models duplicados (`Produto` vs `ProdutoImportacao`, `CondicaoPagamento` vs `CondicaoPagamentoImportacao`, etc.) — manutenção em dobro.

### Problemas de modelagem / observações
| Item | Evidência / Risco |
| --- | --- |
| **`User` polissêmico** | Representa revenda, usuário-sistema e talvez cliente; campos `gasdopovo`, `isgpenabled`, `erp_authorization`, `erpurl` misturam responsabilidades |
| **Redundância API↔ERP** | Tabelas espelho `*_importacao` |
| **Token/URL do ERP no registro de usuário** | `erp_authorization`, `erpurl` em `users` (ver Segurança) |
| **`pedido_avaliacaos`** | Nome de tabela com pluralização incorreta — inconsistência |
| **Tabelas órfãs/campos sem uso** | **[Necessita Validação]** — `ordersqueue`, `vehiclespositions` precisam confirmação de uso |

### Mapa conceitual (núcleo)
```
        ┌────────────────┐
        │  User (revenda/ │◄── erp_authorization / erpurl (integração ERP)
        │  usuário-sistema)│
        └───────┬────────┘
                │ 1:N (configs, feriados, polylines, gasdopovo)
                ▼
   ┌─────────┐      ┌──────────────┐      ┌──────────┐
   │ Produto │      │  Cliente*     │──1:N─►│ Endereco │
   │+Importac│      │ (User/import) │      └──────────┘
   └────┬────┘      └──────┬───────┘
        │ N:M               │ 1:N
        ▼                   ▼
┌────────────────┐    ┌──────────┐  1:N  ┌────────────┐
│CondicaoPagamento│   │  Pedido  ├──────►│ PedidoItem │
│   +Importacao   │   └────┬─────┘       └────────────┘
└────────────────┘        │
                  ┌────────┼─────────┐
                  ▼        ▼         ▼
            PedidoAvaliacao  Cupom   VehiclePosition/UserPolylines
                                         (rastreamento)
        logs / reported_logs ──► conexão sgcm_logs (banco separado)
```

---

## 5. Fluxos de Negócio

### Principais processos
1. **Abertura do app (`onOpenApp`)** — agrega cliente + revendas + cupom + endereços + rastreamento numa transação (`SecretController:71-130`). Fluxo crítico de UX.
2. **Ciclo do pedido** — criação (`order/create`) → integração ERP (`ApiResources`) → pagamento PIX (`pixpaid`/`ispaid`) → rastreamento (`track`) → avaliação (`evaluate`).
3. **Rastreamento de entrega** — interpolação de posições + **Google Roads API** para "snap to roads" (`PedidoController:200-219`).
4. **Sincronização API↔ERP (linking)** — todas as rotas `*/getToLink` + `*/link` vinculam cadastros via `erp_id`.
5. **Notificações push** — recompra, cupom, entrega (FCM).
6. **Cupons** — validação de disponibilidade e limite de uso por cliente (`CouponsController::available`).

### Fluxos críticos
- **Confirmação de pagamento PIX** (`order/pixpaid` → `setPaidOrder`) — financeiro.
- **Integração com ERP** — ponto único de falha (se ERP cai, pedidos não fluem).
- **Geração de token** — `getToken` baseada em `APP_KEY`.

### Regras duplicadas / múltiplos locais
- **Modelo espelho** duplica regras de produto/preço/condição entre API e ERP.
- **Validação de senha**: `min:4` em `LoginController:37` e `SecretController` vs `min:6` em `RegisterController:54` — **regra inconsistente** entre pontos de autenticação.
- **Lógica de pedido/rastreamento** dividida entre `PedidoController` e `PedidoRepository`.

---

## 6. Estrutura do Código

### Organização
- Estrutura Laravel padrão, **bem mais limpa e enxuta** que o ERP: ~**10.346 linhas** em `app/`, 25 controllers, 27 models, 21 repositories.
- Separação de responsabilidades **razoável** graças aos repositories.

### Qualidade / complexidade
- **Boa**: uso de bindings parametrizados em queries sensíveis (cupom com `:clienteId`; IP via `getPdo()->quote()`), repositories, Resources de resposta padronizados.
- **Pontos fracos**: controllers instanciando controllers (`onOpenApp`); `ApiResources` como God-class de integração; `Input::` em vez de `Request` validado (33 ocorrências); rota com **`dd($request)`** em produção (`api.php:27`).

### Duplicação
Padrão espelho `*Importacao` (intencional) + repositories quase idênticos (`CondPgtoRepository`/`CondPgtoImportacaoRepository`, `ProdutoRepository`/`ProdutoImportacaoRepository`).

### Código morto / comentado / debug
- **Baixo volume** de `dd/dump/die` (1 efetivo no app) — bem melhor que o ERP.
- `dd($request)` numa rota ativa (`api.php:27`) e blocos comentados em `api.php:35-37`, `ClienteController:79,94`.
- `DebugMode` middleware loga **request e response inteiros** — risco de vazamento de dados sensíveis em log (ver Segurança).

### Classificação de qualidade por área
| Área | Classificação |
| --- | --- |
| Estrutura/organização | **Bom** |
| Camada de dados (Repository) | **Bom** |
| Tratamento de input | **Regular** (Input:: direto, validação inconsistente) |
| Integração (ApiResources) | **Regular** (God-class) |
| Acoplamento entre controllers | **Ruim** (instanciação direta) |
| Segredos/config | **Ruim** (key hardcoded — ver §7) |
| Testes | **Crítico** (~4 testes) |
| **Geral** | **Regular → Bom** (com pontos críticos isolados de segurança) |

---

## 7. Segurança

### 🔴 CRÍTICA

| # | Vulnerabilidade | Evidência | Impacto |
| --- | --- | --- | --- |
| S1 | **Segredos versionados / hardcoded** | `instruções api-siav.txt` contém **APP_KEY**, **GMAPS_KEY** (`AIzaSy...QWg`) e **FCM_SERVER_KEY** (`AIzaSy...Vf5M`) em texto puro, versionados no git; `PedidoController.php:207` tem **Google Roads API key hardcoded** (`AIzaSyBlaYqOGBuXKdrRrB8KkyqbpvOG2AlRXxs`) | Chaves Google/FCM expostas → uso indevido/custos; **APP_KEY exposta compromete a geração de tokens (S2)** |
| S2 | **Geração de token frágil baseada em APP_KEY** | `SecretController@getToken`: `if Input::get('app_key') !== sha1(env('APP_KEY'))` → emite token Passport do **usuário fixo** `DEFAULT_USER_ID`. Como a APP_KEY está exposta (S1), qualquer um calcula `sha1(APP_KEY)` e **obtém um token válido da API** | **Bypass de autenticação** — acesso total às rotas `auth:api` |

### 🟠 ALTA

| # | Vulnerabilidade | Evidência | Impacto |
| --- | --- | --- | --- |
| S3 | **Middleware `access` não autoriza — só registra log** | `app/Http/Middleware/Access.php` apenas chama `Util::logAccess` e segue. O nome sugere controle de acesso, mas **não há verificação de autorização** (multi-tenant/ownership). A autorização depende **só** de `auth:api` | **Falha de autorização / IDOR**: um token válido pode acessar dados de **qualquer cliente** se os controllers não filtrarem por dono — **[Necessita Validação caso a caso por controller]** |
| S4 | **`DebugMode` loga request e response completos** | `app/Http/Middleware/DebugMode.php` registra `$request->all()` e `$response->getContent()` em log | Vazamento de **dados pessoais (LGPD)**, tokens e payloads de pagamento nos logs |
| S5 | **`dd($request)` em rota de produção** | `routes/api.php:27` (rota `/users`) | Exposição de dump completo do request/ambiente se a rota for atingida |
| S6 | **Política de senha fraca e inconsistente** | `min:4` (`LoginController:37`) vs `min:6` (`RegisterController:54`) | Senhas fracas em painel administrativo |
| S7 | **Token e URL do ERP armazenados em texto no banco** | `users.erp_authorization`, `users.erpurl` (`SecretController:201-203`) | Vazamento do banco expõe credenciais de acesso ao ERP |

### 🟡 MÉDIA

| # | Item | Evidência |
| --- | --- | --- |
| S8 | `APP_DEBUG=true` no `.env.example` e checagens `env("APP_DEBUG")` no fluxo de integração (`ApiResources:138`) | Risco de stack traces em produção se replicado |
| S9 | Rota pública `web/testTokenFromApi` e `video/get` fora de `auth:api` | `api.php:20,23` — autentica por email/senha no corpo; expõe endpoint de login não rate-limited explicitamente |
| S10 | CSRF desativado para `api/*` | `VerifyCsrfToken::$except` — **aceitável** em API stateless com Bearer token, mas registrar |
| S11 | Dois gerenciadores de pacote JS (npm + yarn lockfiles) | Risco de divergência de dependências |

### 🟢 BAIXA / Pontos positivos
- ✅ **SQL Injection mitigado nos pontos checados**: cupom usa **binding nomeado** (`CouponsController:91-93`); IP é sanitizado com `getPdo()->quote()` antes de `INET_ATON` (`ClienteController:397,411`). Os demais `whereRaw` usam **IDs internos** (de `findOrFail`/relacionamentos), risco baixo — porém recomenda-se parametrizar `PedidoRepository` (`implode` de IDs, linhas 87/188) **[Necessita Validação da origem dos IDs]**.
- ✅ Senhas de usuário com `Hash::make` (Bcrypt) — `RegisterController:69`.
- ✅ FCM/Google credentials lidas via `env()` no `ApiResources` (exceto a key hardcoded de Roads, S1).
- ✅ `.env` no `.gitignore`.

---

## 8. Performance

| Item | Evidência | Risco |
| --- | --- | --- |
| **`onOpenApp` agrega muitas consultas numa request** | `SecretController:71-130` instancia 4+ controllers e executa cliente+empresas+cupom+endereços+rastreamento em transação | Latência alta na abertura do app; transação longa |
| **Chamada externa síncrona no fluxo de request** | `file_get_contents` à Google Roads API dentro de `PedidoController` (linha 208), bloqueante | Lentidão/timeout se a API do Google demorar |
| **Log de request+response inteiros** | `DebugMode` middleware | I/O de log elevado; crescimento de tabela `logs` |
| **Redundância de dados (espelho)** | tabelas `*_importacao` | Sincronização custosa; risco de inconsistência |
| **Risco de N+1** | controllers/repositories com relações Eloquent | **[Necessita Validação]** — menor que o ERP; há índices dedicados |
| Pontos positivos | migrations de **índice dedicadas** (`indexusersativos`, etc.); banco de **logs separado** (`sgcm_logs`) alivia o principal | ✅ |

---

## 9. Débito Técnico

| Débito | Impacto | Evidência |
| --- | --- | --- |
| Stack em EOL (PHP 7.1 / Laravel 5.6 / Passport 6 / Vue 2) | **Crítico** | `composer.lock`, `package.json` |
| Segredos hardcoded/versionados (S1) | **Crítico** | `PedidoController:207`, `instruções api-siav.txt` |
| Autenticação por APP_KEY exposta + usuário fixo (S2) | **Crítico** | `SecretController@getToken` |
| Cobertura de testes ~0% (4 testes) | **Alto** | `tests/` |
| Middleware `access` que não autoriza (nome enganoso) | **Alto** | `Access.php` |
| Log de payloads completos (LGPD) | **Alto** | `DebugMode.php` |
| Controllers instanciando controllers | **Médio** | `SecretController@onOpenApp` |
| `ApiResources` God-class (ERP+FCM+Google) | **Médio** | `app/Http/Resources/ApiResources.php` |
| Redundância de modelagem espelho (`*Importacao`) | **Médio** | models/migrations |
| `dd()`/código comentado/`Input::` direto | **Médio** | `api.php:27`, 33 usos `Input::` |
| Validação de senha inconsistente | **Baixo** | `min:4` vs `min:6` |
| npm + yarn duplicados | **Baixo** | lockfiles |

---

## 10. Riscos da Aplicação

### Operacionais
- **Acoplamento ao ERP `ctrl+`**: indisponibilidade do ERP interrompe o fluxo de pedidos.
- **Stack sem suporte**: dificuldade de manter ambiente PHP 7.1/Laravel 5.6 em servidores atualizados.

### De negócio
- **Bypass de autenticação (S2)**: comprometimento da APP_KEY permite emitir pedidos/consultar dados em nome do sistema.
- **Exposição de dados de clientes** via falta de autorização granular (S3) e logs (S4) — risco **LGPD**.

### Tecnológicos
- EOL de framework/linguagem/Vue.
- Dependência de Google Maps/Roads e FCM (chaves expostas).

### De segurança
- Segredos versionados, token frágil, ausência de autorização por dono, logs sensíveis (§7).

### Dependências críticas (SPOF)
- ERP `ctrl+` (HTTP). Banco `sgcm_api`. FCM/Firebase. Google Maps/Roads. Passport (token).

---

## 11. Estratégia de Modernização

> **Premissa: manter a stack principal (PHP/Laravel/MySQL/Passport/Vue).**

### Curto Prazo (0–3 meses) — Estancar risco de segurança
- **Rotacionar TODOS os segredos expostos** (APP_KEY, Google Maps/Roads, FCM) e **removê-los do git** (`PedidoController:207` → `env()`; mover `instruções api-siav.txt` para cofre/secret manager; reescrever histórico do repo).
- **Substituir o esquema de token (S2)**: deixar de derivar acesso de `sha1(APP_KEY)` + usuário fixo; usar fluxo OAuth/Passport apropriado por cliente, com escopos.
- **Remover `dd($request)`** (`api.php:27`) e desativar `DebugMode` em produção (ou anonimizar/limitar payloads — LGPD).
- **Implementar autorização por dono** (policies/escopos) garantindo que um token só acesse dados do seu cliente (mitigar IDOR, S3).
- Unificar política de senha (mín. 8 + complexidade).
- Tornar a chamada à Google Roads **assíncrona/tolerante a falha** (fila/timeout).

### Médio Prazo (3–9 meses) — Estabilizar
- **Suíte de testes** (feature tests das rotas de pedido/pagamento/cupom) — hoje ~0%.
- **Refatorar `onOpenApp`** para um Service dedicado (parar de instanciar controllers); quebrar `ApiResources` em serviços (ErpClient, FcmClient, GeocodeClient).
- Padronizar input via **FormRequest** (eliminar `Input::`).
- Documentar/validar contrato de integração API↔ERP; tratar reconciliação das tabelas espelho.
- Unificar gerenciador de pacotes JS (npm **ou** yarn).

### Longo Prazo (9–18 meses) — Sustentabilidade
- **Upgrade incremental** Laravel 5.6 → 6 (LTS) → 7 → 8/9 com PHP 8.x; atualizar Passport; migrar **Vue 2 → Vue 3** no painel.
- Avaliar **fila/eventos** para notificações e integração ERP (desacoplar HTTP síncrono).
- Centralizar observabilidade (logs estruturados sem dados sensíveis) e métricas.

---

## 12. Estimativa de Complexidade

| Área | Complexidade |
| --- | --- |
| Arquitetura | **Média** — monólito enxuto, mas integrado a ERP + múltiplos bancos |
| Banco de Dados | **Média** — ~31 tabelas, modelagem espelho redundante, MySQL puro (sem procedures) |
| Backend | **Média** — ~10k linhas, repositories organizados, alguns acoplamentos |
| Frontend | **Média** — painel Vue 2 + app externo (não neste repo) |
| Segurança | **Alta** — segredos expostos, token frágil, autorização granular ausente |
| Modernização | **Média/Alta** — EOL de stack eleva o esforço, porém base é organizada e testável |

---

## 13. Resumo Executivo

### Estado geral
O `api-app-gc` é a **API backend do aplicativo "Gás em Casa"**, uma aplicação **Laravel 5.6 enxuta, relativamente recente (~2018) e razoavelmente bem estruturada** (uso consistente de Repositories, queries parametrizadas nos pontos sensíveis, índices dedicados, banco de logs separado). É **tecnicamente superior em organização** ao ERP legado que integra. Contudo, está assentada em **stack totalmente fora de suporte** (PHP 7.1 / Laravel 5.6 / Vue 2) e apresenta **falhas de segurança críticas e pontuais**, sobretudo na **gestão de segredos e autenticação**.

### Principais problemas encontrados
1. **Segredos hardcoded/versionados** — Google Maps/Roads key no código (`PedidoController:207`) e APP_KEY/FCM/Maps em arquivo versionado.
2. **Autenticação frágil** — token de API derivado de `sha1(APP_KEY)` (exposta) para um usuário fixo → **bypass possível**.
3. **Stack em EOL** — PHP 7.1, Laravel 5.6, Passport 6, Vue 2.
4. **Autorização granular ausente** — middleware `access` só loga; risco de IDOR.
5. **Logs com dados sensíveis** (`DebugMode`) — risco LGPD.
6. **Testes ~0%**.

### Principais riscos
- **Segurança/negócio imediato**: comprometimento da APP_KEY → acesso indevido à API; exposição de dados de clientes (LGPD).
- **Continuidade**: stack sem patches; acoplamento ao ERP como SPOF.

### Potencial de modernização
**Alto.** A base é organizada (Repositories, Resources, helpers), pequena (~10k linhas) e sem amarras a banco proprietário (MySQL puro), o que torna o **upgrade incremental do Laravel viável** e a introdução de testes barata. O maior bloqueio é a **dívida de segurança**, que é corrigível em curto prazo.

### Prioridades recomendadas
1. **(Imediato)** Rotacionar e desversionar segredos; substituir o esquema de token; remover `dd()` e o log de payloads completos.
2. **(Curto)** Autorização por dono (anti-IDOR); unificar política de senha; tornar chamadas externas tolerantes a falha.
3. **(Médio)** Testes de feature; refatorar `onOpenApp`/`ApiResources`; padronizar input com FormRequest.
4. **(Longo)** Upgrade incremental Laravel/PHP/Passport e migração Vue 2→3.

---

### Apêndice — Evidências quantitativas coletadas
- LOC PHP em `app/`: **~10.346** · Arquivos PHP em `app/`: **109**
- Controllers: **25** · Models: **27** · Repositories: **21** · Migrations: **70** (2018→2026)
- Rotas: `api.php` 441 linhas · `web.php` 67 · Tabelas criadas: **~31**
- Conexões DB: **2** (mysql `sgcm_api` / mysql `sgcm_logs`) + integração HTTP com ERP
- `whereRaw/DB::raw/select`: **55** (pontos sensíveis **parametrizados**) · `Input::/$_GET/$_POST`: **33**
- `dd/dump/die` no app: **1** (+1 em rota) · Testes: **~4 métodos**
- Stack: PHP 7.1 · Laravel **5.6.40** · Passport 6 · Vue 2 · Firebase/FCM · Laravel Mix 2

> Itens **[Necessita Validação]** (autorização por dono em cada controller, tabelas/campos órfãos, existência da tabela `clientes`, origem dos IDs em `PedidoRepository`, N+1) requerem execução/instrumentação ou acesso ao banco para confirmação definitiva.
