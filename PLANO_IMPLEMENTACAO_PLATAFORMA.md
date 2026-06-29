# PLANO DE IMPLEMENTAÇÃO — PLATAFORMA SaaS (Gás em Casa / ERP-NOVO)

> **Backlog oficial de evolução.** Deriva da `AUDITORIA_ARQUITETURA_PLATAFORMA.md` (engenharia reversa por leitura integral do código). Cada fase traz objetivo, justificativa (com âncora no código real), mudanças por componente (Banco, ERP-NOVO, SPA, App Consumidor, App Entregador, API, SuperAdmin), segurança, multi-tenancy, testes, critérios de aceite, dependências e complexidade.
>
> **Premissa inegociável:** **não reescrever o núcleo.** Já estão corretos e comprovados no código: tenancy Grupo→Empresa (`BelongsToTenant` em 81 models + RLS auto-descoberta + role `erp_app` NOBYPASSRLS), API única (`/api/admin` + `/api/app/v1`), regras centralizadas em `app/Domain/*` (app e SPA não calculam), RBAC+ABAC+hierarquia+field-level, 2FA/lockout/auditoria. Tudo aqui é **aditivo**.
>
> **Fluxo do projeto:** uma fase = um commit + push direto na `main`. Sem branches.
>
> **Complexidade:** 🟢 Baixa · 🟡 Média · 🟠 Alta · 🔴 Muito alta.

---

## STATUS DA EXECUÇÃO

> **✅ Concluídas e na `main`:** P0 (push assíncrono FCM v1 + jobs tenant-aware), P1 (hardening de auth dos apps), P2 (camada SaaS), P3 (Cidade geolocalização-first), P4 (Painel SuperAdmin), P5 (tempo real — broadcasting de pedido/PIX), P6 (rastreamento do entregador em tempo real + fix de segurança da situação por grupo), P7 (ciclo da entrega — **backend** aceite/recusa/ocorrência/comprovação + **app Expo do entregador** em `app-entregador/`). Suíte verde a cada fase. **Decisão registrada:** Cidade = geolocalização-first (opção A).
> **▶️ Próximas:** P8 (app consumidor ao vivo — mapa do entregador em tempo real no `app-gas-em-casa`), P9 (escala). O backend que as serve já está pronto (P5/P6).

## VISÃO GERAL

| Fase | Tema | Complexidade | Depende de |
|---|---|---|---|
| **P0** | Fundação de infra (Redis, filas async, FCM v1, Reverb instalado, tenant-em-job) | 🟡 | — |
| **P1** | Hardening de auth para apps (lockout/2FA/expiração de token no `app/v1`) | 🟢 | — |
| **P2** | Camada SaaS: planos, assinaturas, feature-flags, licenciamento | 🟠 | P0 |
| **P3** | Cidade (geolocalização-first) + descoberta multi-cidade | 🟡 | P2 |
| **P4** | Painel SuperAdmin (cross-tenant, billing, monitoramento) | 🟠 | P2, P3 |
| **P5** | Tempo real (Reverb + Redis) — eventos de pedido/PIX | 🟠 | P0 |
| **P6** | Rastreamento do entregador em tempo real (posição no mapa) | 🔴 | P5 |
| **P7** | App do Entregador (novo app Expo no monorepo) | 🔴 | P0, P1, P5, P6 |
| **P8** | App Consumidor — tracking ao vivo (experiência iFood) | 🟡 | P5, P6 |
| **P9** | Escala (PostGIS, particionamento, observabilidade) | 🟠 | todas |

Sequência: **P0/P1 → P2 → P3 → P4** (vertente SaaS) em paralelo lógico com **P0/P1 → P5 → P6 → P7/P8** (vertente tempo real/entregador). P9 fecha.

---

## FASE P0 — FUNDAÇÃO DE INFRA

### Objetivo
Preparar a base que P2–P8 exigem: Redis (cache/filas/broadcast/presence), filas assíncronas, push moderno (FCM HTTP v1 em job) e o pacote Reverb instalado (sem emitir eventos de negócio ainda).

### Justificativa (código)
Auditoria §7/§10: `QUEUE_CONNECTION=database`; `PushService` usa **FCM legacy síncrono no request**; **sem** `config/broadcasting.php`/`reverb`; cache/sessão em database/file.

### Banco
- Nenhuma mudança estrutural (tabelas `jobs`/`failed_jobs`/`job_batches` já existem em `0001_*`).

### ERP-NOVO
- `config/database.php` + `.env`: conexão Redis. `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`.
- Instalar `laravel/reverb`; publicar `config/broadcasting.php` + `config/reverb.php` (sem canais de negócio).
- Refatorar `App\Domain\Mobile\PushService`: migrar `fcm/send` → **FCM HTTP v1** (OAuth2 service account); envio em novo `EnviarPushJob` (assíncrono).
- **Tenant em job**: criar trait/middleware de job que re-seta `TenantContext` + `set_config('app.empresa_id'…)` no `handle()` (jobs rodam fora do `ResolveTenant`). Reusar conceito já presente no `BelongsToTenant` (herança de empresa do pai) e no `TenantCache`.

### SPA / App Consumidor / App Entregador / SuperAdmin
- Nenhuma mudança funcional. (Confirmar que `AppAuthController::registrarDevice` já cobre o push token — cobre.)

### API
- Nenhuma rota nova.

### Segurança
- Service account FCM em secret (padrão `SEGREDOS_LOCAIS.md`). Reverb com auth de canal preparado (sem canais ainda).

### Multi-Tenancy
- Jobs devem **propagar o tenant** no payload e re-aplicá-lo no `handle()` (1ª barreira scope + 2ª barreira RLS via `set_config`). Sem isso, job tenant-scoped vaza ou nasce com `empresa_id` errado.

### Testes
- `EnviarPushJob` com transporte fake (espelhar `FakeFirebaseVerifier`).
- Teste: job re-aplica tenant e respeita RLS (linha de outro tenant invisível).
- Smoke de Redis em CI.

### Critérios de aceite
- Push sai por fila Redis, via FCM v1, com tenant correto; `queue:work redis` processa; `reverb:start` sobe.

### Dependências
Nenhuma. **Primeira fase.**

### Complexidade
🟡 Média.

---

## FASE P1 — HARDENING DE AUTH PARA OS APPS

### Objetivo
Trazer ao login do `app/v1` o mesmo nível de segurança do login web, e dar expiração/rotação aos tokens de app.

### Justificativa (código)
Auditoria §9: `AppAuthController::login` (email/senha do colaborador/entregador) **não** passa por `LoginSeguranca` (lockout) nem 2FA, ao contrário de `AuthController`. `config/sanctum.php` → `expiration = null` (tokens nunca expiram).

### Banco
- Nenhuma (reusa `login_logs`, `user_2fa`).

### ERP-NOVO
- `AppAuthController::login`: aplicar `LoginSeguranca::bloqueado()` + `registrar()` e, se `user_2fa.habilitado`, exigir OTP (mesmo fluxo do `AuthController`).
- Sanctum: definir `expiration` (ex.: token de app expira em N dias) e endpoint de **refresh**/reemissão; revogar no logout (já há `currentAccessToken()->delete()`).
- Login do **cliente** (Firebase): manter (o telefone já é 2º fator via SMS); apenas registrar em `login_logs`.

### SPA
- Nenhuma.

### App Consumidor / App Entregador
- Tratar `423 two_factor_required` (colaborador/entregador com 2FA) e expiração de token (refresh transparente). O `helpers/http.ts` já trata 401 → logout; estender para refresh.

### API
- `POST /app/v1/token/refresh` (opcional) ou reemissão no login.

### Segurança
- Fecha o vetor de brute-force no app e limita o blast-radius de token vazado.

### Multi-Tenancy
- Sem impacto (auth precede tenant).

### Testes
- Lockout no app após N falhas; 2FA exigido no app quando habilitado; token expirado → 401 → refresh.

### Critérios de aceite
- Login de app com lockout + 2FA + token expirável, paridade com o web.

### Dependências
Nenhuma (pode ir junto com P0).

### Complexidade
🟢 Baixa.

---

## FASE P2 — CAMADA SaaS (PLANOS, ASSINATURAS, LICENCIAMENTO, FEATURE-FLAGS)

### Objetivo
Transformar o ERP multi-empresa em **produto SaaS**: cada empresa tem plano, assinatura e recursos habilitados.

### Justificativa (código)
Auditoria §0/§8: não existe nenhuma noção de plano/assinatura/licença/feature-flag. É o que falta para vender e administrar a plataforma.

### Banco (novas migrations)
- `planos` (nome, descricao, preco_mensal, ativo) — **global** → entrar na `allowlist` da RLS.
- `plano_recursos` (plano_id, recurso_chave) — flags por plano (ex.: `marketplace`, `tempo_real`, `app_entregador`, `nfce`, `monitora`).
- `assinaturas` (empresa_id, plano_id, status [trial/ativa/inadimplente/cancelada], inicio, fim, trial_ate) — tenant-scoped → RLS automática.
- `recurso_overrides` (empresa_id, recurso_chave, habilitado) — override por tenant.
- `assinatura_eventos` (auditoria de plano/status).

### ERP-NOVO
- Models `Plano`, `Assinatura`, `RecursoHabilitado`; `App\Domain\Saas\LicencaService::recursoHabilitado(empresaId, chave)` / `assinaturaAtiva(empresaId)`.
- **Middleware `recurso:chave`** (espelha o `Permissao` middleware): 402/403 se o tenant não tem o recurso. Aplicar nas rotas dos módulos opcionais.
- Estender `/me` e `payloadAuth()` (em `User`) para incluir `features` do tenant ativo.
- Integrar ao **catálogo único**: registrar recursos num `RecursoCatalogo` (espelho do `PermissaoCatalogo`), com teste de contrato.

### SPA
- `auth.tsx`: ler `features` do `/me`; `useFeature('marketplace')` esconde menus/telas sem licença. Tela read-only do plano/assinatura do próprio tenant.

### App Consumidor / Entregador
- Ler flags relevantes via `init`/`/me` (ex.: marketplace ligado).

### API
- `GET /api/admin/assinatura` (status do próprio tenant). Gestão cross-tenant fica no SuperAdmin (P4).

### Segurança
- `recurso:` é defense-in-depth de produto, não substitui `permissao:`. Override só via SuperAdmin (auditado).

### Multi-Tenancy
- Assinatura por empresa; recursos resolvidos no tenant ativo. `planos` global (allowlist RLS).

### Testes
- Empresa sem recurso → 402/403 na rota protegida; `/me` lista features por plano + override; trial expira → bloqueia.

### Critérios de aceite
- Associar empresa↔plano e ligar/desligar features refletindo em SPA e API.

### Dependências
P0 (eventos de cobrança assíncronos — opcional).

### Complexidade
🟠 Alta (domínio novo + enforcement transversal).

---

## FASE P3 — MODELO DE CIDADE (GEOLOCALIZAÇÃO-FIRST)

### Objetivo
Atender "multi-cidade" **sem** 4º nível rígido de tenancy: cidade = dimensão de descoberta/agrupamento/relatório, resolvida por geolocalização (reusando MP1).

### Justificativa (código)
Auditoria §2.6/§3: hierarquia real é Grupo→Empresa; "Cidade" só existe como cadastro geográfico por grupo. `MarketplaceService::empresasNoPonto` já descobre empresas por ponto (geofence poligonal `MonitoraService::dentroDaCerca` ou `raio_entrega_km`).

### Banco
- `cidades_plataforma` (nome, uf, cod_ibge, centro_lat, centro_lng, ativo) — **catálogo global** (≠ `cidades` por grupo) → allowlist RLS.
- `empresa_cidade` (empresa_id, cidade_plataforma_id) — onde a empresa atua (tenant-scoped).
- (Opcional BI) `cidade_id` derivado em `pedidos`/`clientes` — **não** para isolamento.

### ERP-NOVO
- Estender `MarketplaceService` para rotular a cidade resolvida e filtrar por `cidades_plataforma.ativo`. `App\Domain\Saas\CidadeService` (ativar/desativar; empresas por cidade).

### SPA
- Cadastro de empresa: multiselect de cidades atendidas (`empresa_cidade`).

### App Consumidor
- Descoberta por endereço já existe (`marketplace/empresas`); exibir nome da cidade resolvida.

### API
- `GET /api/app/v1/marketplace/cidades` (público, rate-limited); `GET /api/admin/cidades` + vínculo empresa↔cidade.

### Segurança
- Catálogo de cidades é público (descoberta) — rate-limit, sem dados sensíveis.

### Multi-Tenancy
- Cidade **não** é tenancy. Isolamento segue por empresa_id/grupo_id. `cidades_plataforma` global; `empresa_cidade` tenant-scoped.

### Testes
- Ponto em cidade ativa retorna empresas; cidade inativa some; empresa sem geofence usa raio e ainda resolve cidade.

### Critérios de aceite
- Plataforma opera em N cidades; descoberta rotula a cidade; SuperAdmin (P4) liga/desliga cidades.

### Dependências
P2. Reusa MP1.

### Complexidade
🟡 Média.

> **Alternativa (Opção B — Cidade como entidade de tenancy):** FK `cidade_id` em `empresas` + nova camada de RLS por cidade. Maior custo/risco (🔴). Só se o negócio exigir isolamento/cobrança por cidade acima da empresa — decisão a confirmar antes de P3.

---

## FASE P4 — PAINEL SUPERADMIN

### Objetivo
Administrar **toda a plataforma** cross-tenant: cidades, empresas, planos, assinaturas, usuários da plataforma, recursos, monitoramento, logs, auditoria, cobrança.

### Justificativa (código)
Auditoria §8: inexiste. Insumos existem (`config_globais`, `audit_logs`, `security_events`, `login_logs`), mas não o painel nem o cross-tenant.

### Banco
- `platform_admins` (usuários da plataforma — global, fora dos tenants) → allowlist RLS.
- `platform_audit_logs` (append-only) — toda ação cross-tenant. Reuso de `planos`/`assinaturas` (P2), `cidades_plataforma` (P3).

### ERP-NOVO
- **Novo guard `platform`** (Sanctum) + **novo prefixo `Route::prefix('superadmin')`** — separado de `admin`/`app/v1`.
- Acesso cross-tenant **controlado**: `Model::withoutTenant()` e/ou role Postgres dedicada com `BYPASSRLS` (hoje o runtime é `erp_app` NOBYPASSRLS — `2026_06_26_000400`); **toda** query cross-tenant auditada em `platform_audit_logs`.
- `App\Domain\Saas\SuperAdminService`: CRUD de grupos/empresas, planos, assinaturas, cidades, overrides, suspensão de tenant; dashboards (GMV, empresas ativas) read-only com cache Redis.

### SPA
- **Área/app isolado** `frontend/src/features/superadmin/*` atrás de login `platform` (layout próprio, não misturar com a SPA de tenant). Telas: Cidades, Empresas, Planos, Assinaturas, Recursos, Monitoramento, Logs/Auditoria, Cobrança.

### App Consumidor / Entregador
- Nenhuma.

### API
- `POST /api/superadmin/login` (guard platform); `Route::prefix('superadmin')->middleware(['auth:platform','throttle:api'])` com CRUDs + dashboards.

### Segurança
- Maior superfície de risco. **2FA obrigatório** para `platform_admins`; cada ação em `platform_audit_logs` (quem/quando/tenant/antes-depois); cross-tenant só via `SuperAdminService`; rate-limit + IP allowlist opcional.

### Multi-Tenancy
- SuperAdmin é a **única** camada autorizada a cruzar tenants, explícita e auditada.

### Testes
- `platform_admin` enxerga múltiplos tenants; admin de tenant **não** acessa `superadmin/*` (403); suspender tenant bloqueia login/uso; toda mutação gera audit log.

### Critérios de aceite
- Operador administra cidades, empresas, planos, assinaturas e recursos por painel próprio, com auditoria completa.

### Dependências
P2, P3.

### Complexidade
🟠 Alta.

---

## FASE P5 — TEMPO REAL (REVERB + REDIS): EVENTOS DE PEDIDO/PIX

### Objetivo
Substituir polling por **eventos em tempo real** para mudanças de status de pedido e confirmação de PIX (consumidor e admin).

### Justificativa (código)
Auditoria §7: sem broadcasting; `track.tsx` faz polling 30s; `PixStatus` por polling. Base obrigatória para P6/P8.

### Banco
- Nenhuma estrutural (eventos efêmeros). Opcional: `pedido_eventos` (append-only) para replay.

### ERP-NOVO
- Habilitar broadcasting (Reverb instalado em P0).
- Eventos `PedidoStatusAtualizado` e `PixConfirmado` implementam `ShouldBroadcast`.
- Canais **privados por tenant e entidade**: `private-empresa.{empresaId}.pedidos`, `private-pedido.{pedidoId}` — autorização em `routes/channels.php` validando posse + `TenantContext`.
- Disparar evento em `PedidoService::mudarSituacao` e no `PixWebhookController`/`PixService`.

### SPA
- `laravel-echo` + reverb escutando `empresa.{id}.pedidos` → atualiza Kanban/listas sem refresh.

### App Consumidor
- Echo no `track.tsx`: substituir `refetchInterval` por subscription em `pedido.{id}`; manter polling como **fallback** offline. PIX: escutar `PixConfirmado` em vez de `PixStatus`.

### App Entregador
- (Preparado para P7.)

### API
- `routes/channels.php` (autorização de canais) + endpoint de broadcasting auth sob Sanctum.

### Segurança
- Autorização de canal valida tenant + posse do pedido. Nenhum canal público de pedido.

### Multi-Tenancy
- Canais namespaced por `empresa.{id}`; vazamento impossível se a autorização checar `TenantContext`.

### Testes
- Mudança de status emite no canal certo; usuário de outro tenant não autoriza; fallback de polling ainda funciona sem Reverb.

### Critérios de aceite
- Status do pedido/PIX atualiza em < 2s sem polling, isolado por tenant.

### Dependências
P0.

### Complexidade
🟠 Alta.

---

## FASE P6 — RASTREAMENTO DO ENTREGADOR EM TEMPO REAL

### Objetivo
Consumidor acompanha **posição do entregador no mapa**, ETA e status — experiência de mobilidade.

### Justificativa (código)
Auditoria §6/§7: `AppEntregadorController::atualizarStatus` registra `lat/lng` **pontual**, sem streaming. Existe infra de posição **por veículo** (`MonitoraService::registrarPosicao`, `monitora_ultima_posicao`) — **reaproveitável** — mas via job/GPS externo, não por entregador-app, e sem broadcast.

### Banco
- `entregador_posicoes_ultima` (entregador_user_id, empresa_id, lat, lng, atualizado_em) — snapshot leve (espelha `monitora_ultima_posicao`), tenant-scoped → RLS automática.
- Trajeto efêmero em **Redis** (TTL); opcional `entregador_trajeto` particionado p/ auditoria.

### ERP-NOVO
- `App\Domain\Mobile\RastreamentoService`: recebe ping, persiste último ponto, publica em `private-pedido.{id}.entregador`. **Reusar** `MonitoraService::distanciaMetros` para ETA simples (ou Google Directions atrás de Contract/Driver, regra no backend).
- Evento `EntregadorPosicaoAtualizada` (ShouldBroadcast).

### SPA
- (Opcional) Dashboard logístico: entregadores no mapa em tempo real.

### App Consumidor
- `track.tsx`: mapa (já há `react-native-maps`) com marcador do entregador via canal; ETA.

### App Entregador
- (Envio de posição implementado em P7.)

### API
- `POST /api/app/v1/entregador/posicao` (ping lat/lng; throttle específico alto); autorização do canal `pedido.{id}.entregador`.

### Segurança
- Posição só de pedidos **ativos** atribuídos ao entregador; cliente só vê o entregador **do seu** pedido em rota; **cessar** ao concluir; TTL no trajeto.
- Corrigir o gap apontado na auditoria §6: validar a situação por grupo e exigir posse na mudança de status.

### Multi-Tenancy
- Ping carrega tenant do token; canal namespaced por pedido (dono valida tenant).

### Testes
- Ping atualiza snapshot e publica no canal do pedido; cliente de outro pedido não recebe; posição cessa após "entregue".

### Critérios de aceite
- Consumidor vê o entregador se movendo no mapa durante a entrega ativa.

### Dependências
P5, P0.

### Complexidade
🔴 Muito alta (geo + tempo real + privacidade + bateria).

---

## FASE P7 — APP DO ENTREGADOR (NOVO APP EXPO)

### Objetivo
Criar o app do entregador do zero, no monorepo, consumindo exclusivamente `app/v1/entregador/*`.

### Justificativa (código)
Auditoria §6: backend tem só 2 endpoints; **não há tela** de entregador. Pilar da visão.

### Banco
- `pedido_ocorrencias` (pedido_id, empresa_id, tipo, descricao), `pedido_fotos`, `pedido_assinaturas` (comprovação) — tenant-scoped (RLS automática).
- Perfil de entregador: estender `colaboradores` (já tem `entregador` bool) com vínculo a veículo (`frota`)/turno (`colaborador_turnos` já existe).

### ERP-NOVO
- Expandir `AppEntregadorController`/`App\Domain\Mobile`: aceite/recusa de corrida, fila de entregas, ocorrências, upload de foto, assinatura, conclusão com comprovação — **reusando `PedidoService`** (sem regra nova no app).
- Storage de fotos/assinaturas (S3/disk privado) com path por tenant.

### SPA
- Admin: atribuir pedido a entregador; ver ocorrências/comprovações.

### App Consumidor
- Ver comprovação de entrega (foto/assinatura) no histórico.

### App Entregador (NOVO `app-entregador/`)
- App Expo: login email/senha (`/app/v1/login`, com hardening de P1), lista de entregas, aceite, **mapa+navegação** (deep-link Waze/Google ou mapa embutido), **GPS background** → `posicao` (P6), status, ocorrências, câmera, assinatura (canvas), push recebido, **modo offline** (fila local sincronizada). Compartilhar `helpers/http`/auth com `app-gas-em-casa` (extrair pacote comum).

### API
- `app/v1/entregador/*`: `corridas`, `corridas/{id}/aceitar|recusar`, `pedidos/{id}/ocorrencia|foto|assinatura|concluir`, `posicao` (P6).

### Segurança
- Token de app com TTL+revogação (P1); upload validado (tipo/tamanho); GPS só em turno/entrega; RBAC/posse no app entregador.

### Multi-Tenancy
- Entregador pertence a uma empresa; tudo via token + RLS. `AppEntregadorController` já escopa por `empresa_id` + `entregador_user_id`.

### Testes
- Entregador só vê seus pedidos da sua empresa; fluxo aceite→navegação→posição→foto→assinatura→concluir; offline sincroniza.

### Critérios de aceite
- Entregador opera entregas ponta a ponta pelo app; consumidor vê a evolução em tempo real.

### Dependências
P0, P1, P5, P6.

### Complexidade
🔴 Muito alta.

---

## FASE P8 — APP CONSUMIDOR: EXPERIÊNCIA "iFOOD DO GÁS"

### Objetivo
Tracking ao vivo do entregador, push in-app, avaliações e descoberta multi-empresa fluida.

### Justificativa (código)
Auditoria §5: app sólido, mas tracking é polling textual sem mapa do entregador. Avaliações já existem (`pedido_avaliacoes`, `avaliar`).

### Banco
- Nenhuma nova (reusa P5/P6).

### ERP-NOVO
- Garantir eventos (P5/P6) consumidos; `init`/`config` com flags (P2: marketplace/tempo real).

### SPA
- (Opcional) dashboard de satisfação a partir das avaliações.

### App Consumidor
- `track.tsx`: mapa ao vivo (P6) + timeline em tempo real (P5) + ETA; push handling (deep link p/ o pedido); fluxo de descoberta (marketplace) → escolher empresa → pedir; avaliação pós-entrega (já há `pedidos/{id}/avaliar`).

### App Entregador / SuperAdmin
- Nenhuma.

### API
- Reuso; `init` eventualmente enriquecido com flags.

### Segurança
- Deep links validados; push sem dados sensíveis no corpo.

### Multi-Tenancy
- Cliente vinculado à empresa escolhida; tudo via token + RLS (`clienteDoUsuario` já deriva do token, anti-IDOR).

### Testes
- E2E: pedido → PIX confirmado (evento) → entregador a caminho (mapa) → entregue → avaliar.

### Critérios de aceite
- Acompanhamento em tempo real comparável a apps de delivery.

### Dependências
P5, P6.

### Complexidade
🟡 Média.

---

## FASE P9 — ESCALA + OBSERVABILIDADE

### Objetivo
Preparar para centenas de cidades / milhares de empresas / alto volume simultâneo.

### Justificativa (código)
Auditoria §10: filas em database, polling, FCM síncrono, descoberta espacial em PHP, sem cache distribuído.

### Banco
- **PostGIS**: `geography` + índice GiST; migrar `MarketplaceService` (ponto-em-polígono/Haversine em PHP) e a descoberta para consulta espacial nativa.
- Avaliar **particionamento** (pedidos, posições, logs) por `empresa_id`/tempo. Estender índices (`f13`) conforme profiling.

### ERP-NOVO
- Cache distribuído Redis para catálogos/`/me`/descoberta (reusar `TenantCache`). I/O externo (PIX/fiscal/push/e-mail) em filas com retry/backoff. Health (`/up` já existe) + métricas. Revisar TTL/rotação de tokens por tipo de cliente (complementa P1).

### SPA / Apps / SuperAdmin
- Lazy-load por feature (reconciliar com flags P2); SuperAdmin com dashboards de capacidade/uso por tenant.

### API
- Manter versionamento (`app/v1`); paginação obrigatória em listas grandes; preparar `v2` se necessário.

### Segurança
- Rate-limit por tenant; revisar exposição do marketplace público (anti-scraping); pen-test focado em SuperAdmin cross-tenant e canais de broadcasting.

### Multi-Tenancy
- Validar RLS sob particionamento; teste de isolamento sob concorrência/carga.

### Testes
- Carga (descoberta espacial, broadcasting, filas); isolamento multi-tenant concorrente.

### Critérios de aceite
- Latência/throughput dentro de SLO sob carga simulada de N cidades/empresas.

### Dependências
Todas as anteriores.

### Complexidade
🟠 Alta.

---

## MATRIZ DE RASTREABILIDADE (VISÃO → FASE)

| Requisito da visão | Fase(s) |
|---|---|
| Backend/API/regras únicas | ✅ já existe (manter disciplina em todas) |
| Multi-empresa / multi-tenant | ✅ já existe (endurecido) |
| Multi-cidade | P3 (geo) / P4 (gestão) |
| Planos/assinaturas/licenciamento | P2, P4 |
| Painel SuperAdmin | P4 |
| App entregador completo | P7 (+P5/P6) |
| Rastreamento tempo real | P5, P6 |
| App consumidor moderno | P8 |
| Push moderno + filas | P0 |
| Hardening de auth de app | P1 |
| Escalabilidade (centenas de cidades) | P9 |
| Segurança SaaS | transversal; foco em P1, P4, P7, P9 |

---

## PRINCÍPIOS INEGOCIÁVEIS (toda fase)

1. **Toda regra de negócio em `app/Domain/*`.** SPA e apps nunca calculam/decidem (já é assim — `CotacaoMobileService` é a autoridade de preço; manter).
2. **Uma única API** (`routes/api.php`): SPA→`/admin`, apps→`/app/v1`, plataforma→`/superadmin`. Sem APIs paralelas.
3. **Um único banco** com isolamento por `empresa_id`/`grupo_id` (scope + RLS auto-descoberta). Cross-tenant só no SuperAdmin, auditado.
4. **Monorepo**: `erp-novo` + `app-gas-em-casa` + `app-entregador` (novo) + SuperAdmin (sub-front).
5. **Jobs propagam tenant** (P0) — nada que escape do `ResolveTenant` ignora o isolamento.
6. **Reusar antes de criar**: geofence/distância (`MonitoraService`), cache por tenant (`TenantCache`), numeração atômica (`NumeroSequencialService`), catálogo de permissões (`PermissaoCatalogo`) e o padrão Contract+Driver para todo gate externo novo.
7. **Uma fase = um commit + push na `main`.**
