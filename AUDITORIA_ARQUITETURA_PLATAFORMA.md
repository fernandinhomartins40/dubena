# AUDITORIA DE ARQUITETURA — PLATAFORMA SaaS (Gás em Casa / ERP-NOVO)

> **Método:** engenharia reversa por **leitura integral do código-fonte**, não por amostragem. Foram lidas as **61 migrations** (schema completo), os **52 controllers** (7.044 linhas), os **93 arquivos de Domain** (9.371 linhas), os **130 models**, rotas/middleware/providers/config, a **SPA** (`erp-novo/frontend`, 153 arquivos) e o **app do consumidor** (`app-gas-em-casa`, 79 arquivos). README/docs/markdown foram **ignorados** conforme determinado — cada afirmação abaixo cita arquivo/símbolo do código real.
>
> **Data:** 2026-06-28 · **Pergunta central:** a arquitetura atual suporta a visão de plataforma SaaS multi-cidade / multi-empresa / multi-app com backend único, API única e regras centralizadas? Onde não suporta, o quê exatamente falta?

---

## 0. SUMÁRIO EXECUTIVO

| Capacidade da visão | Veredito | Evidência de código |
|---|---|---|
| Backend único | ✅ Sim | Único Laravel 12 em `erp-novo/`; `ctrl-web/` é legado inativo p/ o novo |
| API única | ✅ Sim | `routes/api.php` (601 linhas): `/login`, `/api/admin/*` (SPA), `/api/app/v1/*` (apps) |
| Regras centralizadas (sem duplicação) | ✅ Sim, comprovado | Lógica em `app/Domain/*Service`; SPA e app **não calculam** (ver §1.4, §5, §6) |
| Banco único multi-tenant | ✅ Sim, robusto | 81/130 models com `BelongsToTenant` + RLS Postgres auto-descoberta dupla-barreira |
| Multi-Empresa | ✅ Sim | `empresas`, `empresa_user`, `TenantContext`, `ResolveTenant`, troca via `X-Empresa-Id` |
| Multi-Tenant (isolamento) | ✅ Sim, forte | `BelongsToTenant` + RLS (`...000300`) + role PG sem BYPASSRLS (`...000400`) |
| **Multi-Cidade (nível de tenancy)** | ❌ Não | Hierarquia real é **Grupo→Empresa**; cidade é só cadastro geográfico por grupo |
| Monorepo | ✅ Sim | `erp-novo` + `app-gas-em-casa` no mesmo repo git |
| App Consumidor | 🟡 Funcional, sem tempo real | `app-gas-em-casa` consome `app/v1`; `track.tsx` = polling 30s, sem mapa do entregador |
| **App Entregador** | 🟠 Embrionário | `AppEntregadorController` = **2 endpoints**; **nenhuma tela** de entregador existe |
| **Rastreamento em tempo real** | ❌ Não | Sem broadcasting/WebSocket; push FCM (API legacy) + polling |
| **Painel SuperAdmin** | ❌ Não existe | Nenhuma rota/model/tela de cidades/planos/assinaturas/licenciamento |

### Veredito de uma frase
A **fundação técnica é sólida e madura** — tenancy à prova de vazamento (scope + RLS), API única real, RBAC+ABAC+hierarquia+field-level, regras 100% centralizadas e já comprovadamente não-duplicadas nos clientes. O que falta para a "plataforma SaaS" são **quatro blocos de funcionalidade ausente** (camada SaaS/billing, app do entregador, infraestrutura de tempo real, e a decisão sobre "Cidade"), todos **aditivos** — nenhum exige reescrever o que existe.

---

## 1. ENGENHARIA REVERSA — O QUE O CÓDIGO REALMENTE É

### 1.1 Topologia (monorepo)
```
Dubena/
├── erp-novo/                 ← NÚCLEO: Laravel 12 / PHP 8.2 + SPA React/Vite
│   ├── app/Domain/<20 subdomínios>   ← regras de negócio (DDD)
│   ├── app/Http/Controllers/Api/{Admin,Mobile}  ← 52 controllers finos
│   ├── app/Models/  ← 130 models (81 BelongsToTenant, 22 BelongsToGrupo)
│   ├── routes/api.php  ← ÚNICA superfície HTTP
│   ├── database/migrations/  ← 61 migrations
│   └── frontend/  ← SPA React (24 features) — painel admin
├── app-gas-em-casa/          ← App do CONSUMIDOR (Expo / React Native)
└── ctrl-web/                 ← LEGADO (não é alvo; em substituição Strangler)
```

### 1.2 Subdomínios de negócio (todos no ERP-NOVO)
`Acesso, Apoio, Caixa, Cliente, Cobranca, Estoque, Financeiro, Fiscal, Frota, Gestao, Mobile, Monitora, Pagamento, Pedido, Produto, Relatorio, Rh, Satelite, Seguranca, Shared, Tenant`. Cada um expõe Services; integrações externas (SEFAZ, boleto CNAB, PIX, eRede, FCM, Firebase, SGCasa GPS) são isoladas atrás de **Contracts + Drivers** com seleção Fake/Real por config (`AppServiceProvider`). Isso comprova o princípio "regra no núcleo, gate externo isolado".

### 1.3 Máquina de estados do pedido (núcleo da operação)
`Pedido/PedidoService.php` + `EfeitoPedido` (PENDENTE/CONCLUIDO/CANCELADO): a transição de efeito decide baixa/devolução de estoque e geração/estorno de financeiro, com idempotência (`estoque_movimentado`). Estoque e caixa têm **saldo auditável** (Σ histórico = saldo). É a regra de venda — e ela é única, server-side.

### 1.4 Prova de centralização de regra (não-duplicação)
- App envia só itens → servidor precifica: `CotacaoMobileService::cotar()` é "a AUTORIDADE de preço do app"; `criarDoApp()` **ignora qualquer `preco_unitario` do cliente** (comentário e código). 
- `app-gas-em-casa/src/store/flashStore.ts` documenta no próprio código: *"sem calculateTotal/applyDiscount no cliente — total/desconto vêm da COTAÇÃO do servidor"*.
- SPA: busca por cálculo de total/imposto/desconto em `frontend/src/features` não achou regra (só rótulos de UI). 

➡️ A regra "toda lógica no ERP-NOVO" **já é cumprida**, não é só uma intenção.

---

## 2. ARQUITETURA ATUAL — RESPOSTAS COM EVIDÊNCIA

### 2.1 Backend único — ✅
Um único Laravel ativo. Não há segundo backend servindo a SPA/apps.

### 2.2 API única — ✅
`routes/api.php`, tudo sob um único grupo `['auth:sanctum','tenant','throttle:api']`:
- Públicas: `/login`, `/pix/webhook`, `/app/v1/login`, `/app/v1/cliente/login`, `/app/v1/cliente/cadastro`, `/app/v1/marketplace/empresas`.
- `Route::prefix('admin')` → SPA (≈460 linhas de rotas, todos os módulos).
- `Route::prefix('app/v1')` → apps (cliente + entregador).
- SPA usa cookie Sanctum stateful (`frontend/src/lib/api.ts` → `baseURL: ${PREFIX}/api/admin`); apps usam Bearer (`app-gas-em-casa/src/helpers/http.ts`). Mesmo guard `sanctum` (`bootstrap/app.php::statefulApi()`).

### 2.3 Sem APIs paralelas / sem regra duplicada — ✅
- Controllers Admin e Mobile **injetam os mesmos Services** (ex.: `AppEntregadorController` usa `PedidoService`, o mesmo do admin; `AppClienteController` usa `PixService`, `PedidoMobileService` que delega a `PedidoService`).
- A diferença entre `/admin` e `/app/v1` é **forma de resposta/escopo**, não regra. Correto.

### 2.4 Multi-Empresa — ✅
`users.empresa_id` (empresa ativa padrão) + `empresa_user` (multi-empresa) + troca stateless por header `X-Empresa-Id` validada em `ResolveTenant` contra `User::podeAcessarEmpresa()`.

### 2.5 Multi-Tenant (isolamento) — ✅ (duas barreiras + endurecimento)
1. **App:** trait `BelongsToTenant` (81 models) e `BelongsToGrupo` (22 models) — global scope automático + preenchimento de `empresa_id`/`grupo_id` na criação (inclusive herdando do pai em ETL/jobs).
2. **Banco:** RLS Postgres por **auto-descoberta de coluna** (`2026_06_26_000300_rls_tenant_completa`): qualquer tabela com `empresa_id`/`grupo_id` é isolada sem editar lista; `ResolveTenant` injeta `app.empresa_id`/`app.grupo_id` via `set_config`. `FORCE ROW LEVEL SECURITY`.
3. **Endurecimento:** `2026_06_26_000400` cria role `erp_app` **NOSUPERUSER/NOBYPASSRLS** — fecha o furo de RLS ser ignorada por superusuário (a auditoria interna registrou que o app conectava como superuser). `TenantCache` (Shared) ainda prefixa o cache por tenant.

### 2.6 Multi-Cidade — ❌ (achado central)
A hierarquia **real** no schema é:
```
Grupo (rede)  →  Empresa (tenant/revenda)  →  [dados operacionais]
```
"Cidade" **não** é nível acima de Empresa. No código:
- `empresas.cidade` é **string** (migration `0000_*`).
- `cidades` (migration `0003_*`) é cadastro geográfico **escopado por GRUPO** (`cidades→bairros→ruas`), usado para endereço de cliente.
- A hierarquia A3 (`unidades→departamentos→setores_org`, migration `...000500`) é **interna à empresa** (escopo de RBAC), portanto *abaixo* da empresa, não acima.

A visão pede `Cidade → Empresa → ...`. Isso **não existe**. Porém, há um caminho já pavimentado: **multi-cidade por geolocalização** (ver §2.8 e §11).

### 2.7 Monorepo — ✅
Repositório único com backend, SPA e app.

### 2.8 Múltiplos aplicativos — 🟡 + descoberta cross-empresa já existe
- App **consumidor**: existe e funciona (catálogo, cotação, pedido, PIX, perfil, endereços, avaliação).
- App **entregador**: `AppEntregadorController` tem **só** `GET entregador/pedidos` e `POST entregador/pedidos/{id}/status`. **Não há projeto/tela de entregador**; `pedidos.entregador_user_id` liga pedido↔entregador.
- **Marketplace (MP1)**: `MarketplaceController` + `MarketplaceService::empresasNoPonto()` já fazem **descoberta pública cross-empresa por geolocalização** (geofence poligonal via `MonitoraService::dentroDaCerca`, ou `raio_entrega_km` da matriz como fallback), lendo cercas com `withoutTenant()`. Migration `2026_06_28_000100` adicionou `app_marketplace_ativo`/`raio_entrega_km` em `empresas`. **Esta é, de fato, a fundação "multi-cidade" por geolocalização — o "iFood do gás".**

---

## 3. BANCO DE DADOS — SUPORTE À HIERARQUIA DESEJADA

| Nível desejado | Existe? | Tabela / observação (código) |
|---|---|---|
| **Cidade (tenancy)** | ❌ | só `cidades` geográfico por grupo; e `empresas.cidade` string |
| **Empresa** | ✅ | `empresas` sob `grupos` (tenant) |
| Usuários | ✅ | `users` + `empresa_user` (multi-empresa) |
| Clientes | ✅ | `clientes` (tenant), `f5_cliente_user_id` liga ao login do app |
| Pedidos | ✅ | `pedidos` (tem `entregador_user_id`, `setor_id`, máquina de estados) |
| Entregadores | 🟡 | são `users`; `colaboradores.entregador` (bool, migration RH); **sem perfil/turno de entregador para app, sem posição em tempo real** |
| Veículos | ✅ | `veiculos` (frota) + `monitora_veiculos` (GPS) |
| Produtos | ✅ | `produtos` + `produto_condicao_precos` (preço por forma de pgto) |
| Configurações | ✅ | `empresa_configs` (1:1, com `dados` JSON), `config_globais` (por grupo) |
| Integrações | ✅ | PIX (`pix_cobrancas`), boleto/CNAB, eRede (`pagamentos_online`), FCM (`app_devices`) |

**Já existe infra de geolocalização reutilizável** (relevante para o entregador em tempo real): `monitora_posicoes` (append-only), `monitora_ultima_posicao` (snapshot 1:1 por veículo), `monitora_cercas` + `monitora_cerca_pontos` (geofence poligonal, tenant-scoped). Mas é **por veículo, ingerida por job a partir de GPS externo (SGCasa)** — não por entregador via app, e **não é publicada em tempo real**.

**Alterações de banco necessárias para a visão (resumo):**
1. Camada SaaS — **inexistente**: `planos`, `plano_recursos`, `assinaturas`, overrides de recurso por empresa, eventos de cobrança.
2. Modelo de "Cidade" — decisão A (geolocalização-first, recomendado) ou B (entidade acima de empresa).
3. Entregador — posição em tempo real (snapshot leve + Redis), perfil de entregador para o app, comprovação de entrega (foto/assinatura/ocorrência).
4. SuperAdmin — `platform_admins` (global), `platform_audit_logs`.

---

## 4. API ÚNICA — DUPLICAÇÃO E UNIFICAÇÃO

| Consumidor | Consome | Regra própria? |
|---|---|---|
| SPA admin | `/api/admin/*` (cookie) | ❌ Não — só apresenta e aplica RBAC do `/me` |
| App consumidor | `/api/app/v1/*` (Bearer) | ❌ Não — preço/pedido server-side |
| App entregador (futuro) | `/api/app/v1/entregador/*` | ❌ — reusa `PedidoService` |
| SuperAdmin (futuro) | `/api/superadmin/*` (a criar) | — não existe |

- **Duplicação de regra:** não encontrada.
- **APIs/bancos paralelos:** não existem.
- **A unificar no futuro:** o app entregador deve crescer sob `app/v1/entregador/*` (reuso de Services), **não** como API separada. O SuperAdmin precisa de **novo prefixo `superadmin/*` com guard próprio** e acesso cross-tenant controlado (hoje o runtime é `NOBYPASSRLS`; cross-tenant exigirá `withoutTenant()` e/ou role dedicada, auditada).

---

## 5. APP DO CONSUMIDOR — ESTADO REAL

`app-gas-em-casa` (Expo Router). Telas: `index, login, sms, newuser, policies, startupvideo, (tabs)/{home,pedidos,perfil,info}, address, pix, track, error`. Já ligado ao `app/v1`:
- Auth telefone (Firebase) + cadastro (`AppAuthController::loginCliente/cadastrarCliente` + `ClienteAuthService`).
- Catálogo/cotação/cupom (`order.service.ts` → `app/v1/init|carrinho/cotacao|cupom`).
- Pedido, histórico, **PIX com polling** (`PixStatus`), cancelar, avaliar.
- Endereços múltiplos (`address.service.ts`), perfil, exclusão de conta.
- **Acompanhar pedido (`track.tsx`)**: React Query `refetchInterval: 30s`, exibe **status textual** ("Seu pedido está a caminho, aguarde a chegada do entregador"). Há `react-native-maps`, mas usado para endereço/revenda — **não** para posição do entregador.

**Lacunas vs. visão:** ❌ acompanhar entregador em tempo real no mapa; 🟡 push recebido/deep-link a validar; depende de tempo real (§7).

---

## 6. APP DO ENTREGADOR — ESTADO REAL

**Praticamente inexistente.** Backend: `AppEntregadorController` (68 linhas) — lista pedidos atribuídos ao entregador e muda situação (com `lat/lng` opcionais), disparando push ao cliente (`notificarStatus`). `grep` por entregador/driver em `app-gas-em-casa/src` **não acha tela alguma** de entregador.

Ausente toda a Etapa 6: app próprio, aceite de corrida, mapa/navegação, **streaming de GPS** (a `lat/lng` do `atualizarStatus` é registrada pontualmente, não há trajeto), ocorrências, fotos, assinatura, chat, push recebido, modo offline.

**Observações de segurança no fluxo atual do entregador:**
- O endpoint de status **não verifica** se o `pedidosituacao_id` pertence ao grupo do entregador além do `exists` genérico (a mudança em si é tenant-scoped pela query do pedido, mas a situação não é validada por grupo).
- Não há checagem de permissão RBAC no app entregador (só posse: pedido atribuído ao `user->id`).

---

## 7. RASTREAMENTO EM TEMPO REAL — SUPORTE ATUAL

**Não suportado.** Evidências de código:
- **Sem broadcasting:** não há `config/broadcasting.php` nem `config/reverb.php`; `composer.json` não tem reverb/pusher/ratchet/swoole/soketi.
- **Push:** `PushService` envia via **FCM legacy** (`https://fcm.googleapis.com/fcm/send` + `server_key`) — endpoint **depreciado pelo Google** (migrar para HTTP v1/OAuth2). O envio é **síncrono dentro do request** (`Http::post`).
- **"Tempo real" hoje = push de status + polling:** `track.tsx` (30s) e `PixStatus` (polling).
- **Posição do entregador:** não é capturada/streamada. Existe streaming de **veículos** (`MonitoraService::registrarPosicao`) mas via **job agendado** (`monitora:sync-positions` a cada minuto, GPS externo SGCasa), não em tempo real e não por entregador-app.
- **Filas:** `QUEUE_CONNECTION=database` (default). Há `jobs`/`failed_jobs`/`job_batches`. Redis **não** é o padrão.

**Recomendação (detalhe no Plano):** Laravel **Reverb** (WebSocket nativo Laravel 12) + **Redis** (presence/posição); push fora do app por **FCM HTTP v1** em **job assíncrono**. Posição do entregador em canal `private-pedido.{id}` (dono valida tenant), snapshot leve no banco + Redis para trajeto efêmero.

---

## 8. PAINEL SUPERADMIN — ESTADO REAL

**Não existe.** Nenhuma rota/controller/model/tela para cidades, gestão cross-tenant de empresas, planos, assinaturas, licenciamento, recursos habilitados, monitoramento global, cobrança. 

Há **insumos** (não o painel): `config_globais` (por grupo), `audit_logs` (F11, trilha de negócio), `security_events`/`role_versions` (A6), `login_logs` (A5). Para um SuperAdmin operar cross-tenant será necessário **guard dedicado** e quebra controlada do isolamento (`withoutTenant()` / role com BYPASSRLS), com auditoria reforçada — hoje o runtime é deliberadamente `NOBYPASSRLS`.

---

## 9. SEGURANÇA — AUDITORIA

| Item | Estado | Evidência |
|---|---|---|
| Autenticação | ✅ Sanctum (cookie SPA + Bearer apps), guard único | `bootstrap/app.php`, `AuthController` |
| Auth app cliente | ✅ Firebase phone-auth verificado server-side | `ClienteAuthService`, `KreaitFirebaseVerifier` |
| Autorização RBAC | ✅ Gate central por chave do `PermissaoCatalogo` + bypass de `support` | `AuthServiceProvider`, trait `AutorizaPorPermissao` (286 usos) |
| ABAC + hierarquia | ✅ `PolicyEvaluator`: limite/ownership/horário + escopo A3 | `Acesso/PolicyEvaluator`, `permission_conditions` |
| Field-level (A7) | ✅ campos sensíveis filtrados em leitura/escrita | `Acesso/CamposPermitidos`, `ClienteController` |
| 2FA | ✅ TOTP nativo (RFC 6238) + recovery codes, cifrados | `Seguranca/Totp`, `user_2fa` |
| Lockout / brute-force | ✅ por e-mail **e** IP em janela; + `throttle:login` (10/min) | `Seguranca/LoginSeguranca`, `AuthController` |
| Isolamento tenant | ✅ scope + RLS auto-descoberta + role sem BYPASSRLS | §2.5 |
| Auditoria | ✅ `audit_logs` (trait `Auditavel`, exclui campos `encrypted`) + `security_events` | `Shared/Auditavel`, A6 |
| Segredos | ✅ casts `encrypted` (CSC, certificado, SMTP, CSRT, signAC); nunca voltam no GET | `ConfigGlobalController::payload`, `EmpresaConfig` |
| Webhook PIX | ✅ segredo compartilhado (`hash_equals`) + validação no service | `PixWebhookController` |
| Rate limiting | ✅ `throttle:api` (120/min por user/IP), `throttle:login`, marketplace `60/min` | `AppServiceProvider`, `routes/api.php` |

**Vulnerabilidades / pontos de atenção (todos corrigíveis, nenhum estrutural):**
1. **Login do APP sem lockout/2FA** — `AppAuthController::login` (email/senha) **não passa** por `LoginSeguranca` nem por 2FA, ao contrário do `AuthController` web. Inconsistência de hardening para o app do entregador/colaborador.
2. **Tokens Sanctum sem expiração** — `config/sanctum.php` → `expiration = null`. Para apps (entregador), definir TTL + rotação.
3. **FCM legacy** — endpoint depreciado; risco de quebra de push a qualquer momento.
4. **App entregador**: situação de pedido não validada por grupo; sem RBAC (só posse).
5. **SuperAdmin cross-tenant (futuro)** — será a maior superfície de risco; exigirá guard isolado + 2FA obrigatório + auditoria de cada acesso cross-tenant.
6. **Sem `config/cors.php` explícito** — confiar no default do Laravel; revisar origens ao publicar apps/superadmin.

---

## 10. ESCALABILIDADE

**Pontos fortes (já no código):**
- Tenant resolvido por request (stateless) → escala horizontal sem afinidade de sessão.
- RLS por auto-descoberta → tabela nova isolada sem código.
- Índices de performance começando por `empresa_id` (`f13`), e índices nas tabelas quentes.
- `TenantCache`, `NumeroSequencialService` (lock pessimista anti-duplicidade) prontos.

**Gargalos para "centenas de cidades, milhares de empresas":**
1. **Filas em `database`** — não escala para volume de push/fiscal/PIX; migrar para **Redis**.
2. **Polling** (track 30s, PIX) — carga ∝ pedidos ativos × clientes; resolver com WebSocket/Reverb.
3. **FCM síncrono no request** (`PushService`) — mover para job.
4. **Descoberta do marketplace em PHP** — `MarketplaceService` carrega cercas e roda ray-casting/Haversine em PHP; para muitas empresas/cidades, migrar para **PostGIS** (índice espacial GiST).
5. **Cache/sessão em `database`/file** por padrão — adotar **Redis**.
6. **Postgres único com RLS** é ótimo até certo ponto; em escala muito alta avaliar particionamento por `grupo_id`/tempo (pedidos, posições, logs). Hoje sem particionamento.

---

## 11. RISCOS, LIMITAÇÕES E RECOMENDAÇÕES

### Riscos
- **R1 — Modelo de "Cidade" indefinido** (muda schema, SuperAdmin e billing). Decidir cedo.
- **R2 — Tempo real ausente** — maior bloqueio à experiência "iFood do gás"; exige Reverb+Redis.
- **R3 — App entregador do zero** — novo app Expo + GPS background + offline + mídia.
- **R4 — Camada SaaS inexistente** — planos/assinaturas/feature-flags por tenant precisam existir antes de "vender".
- **R5 — FCM legacy depreciado**.
- **R6 — Hardening do login de app** (lockout/2FA/expiração de token) antes de escalar o app entregador.

### Recomendações arquiteturais
1. **Não reescrever o núcleo.** Tenancy, API única, centralização de regra e segurança estão corretos — construir **por cima**.
2. **Cidade por geolocalização (opção A, recomendada):** já há `MarketplaceService` + geofence; cidade vira **derivação/agrupamento** (catálogo `cidades_plataforma` global p/ relatório/cobrança), não um 4º nível de isolamento. Evita reescrever tenancy.
3. **Tempo real com Reverb + Redis**; posição do entregador em presence/private channel por pedido.
4. **SuperAdmin como prefixo `superadmin/*`** com guard dedicado, cross-tenant auditado, reusando Domain Services.
5. **App entregador como 2º app Expo no monorepo** (`app-entregador/`), consumindo `app/v1/entregador/*`, compartilhando lib http/auth com o consumidor.
6. **Infra:** Redis (cache+filas+broadcast), jobs assíncronos (push/PIX/fiscal), FCM HTTP v1, PostGIS para descoberta espacial.

---

## 12. VEREDITO FINAL

A arquitetura atual **suporta a base da visão** (backend único, API única, multi-empresa, multi-tenant real e endurecido, regras centralizadas e comprovadamente não duplicadas, monorepo, segurança forte) com **qualidade acima da média de mercado**. Ela **ainda não suporta**, por **ausência de funcionalidade** (não por defeito estrutural):

- Cidade como dimensão de plataforma (decisão A vs. B);
- App do entregador;
- Rastreamento em tempo real;
- Painel SuperAdmin com modelo SaaS (planos/assinaturas/licenciamento).

Todas as lacunas são **aditivas** e estão detalhadas, fase a fase, em **`PLANO_IMPLEMENTACAO_PLATAFORMA.md`**.
