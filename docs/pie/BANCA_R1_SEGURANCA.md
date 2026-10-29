# BANCA R1 — Red Team / Segurança, LGPD e proteção de receita

**Auditoria PIE V2** · Commit `48b29f72` (main) · Ambiente: homologação (`ssh dubena-vps`, só leitura) + suíte local sqlite + consultas Postgres read-only.
**Revisor:** R1 · **Data:** 2026-10-29
**Parecer da área:** **REPROVADO** (1 bloqueador CRÍTICO confirmado por PoC: tomada de conta administrativa pelo cadastro do app).

> Enforcement em homologação (lido no contêiner `erpnovo-app` e via `config()`):
> `SAAS_ENFORCE_TENANT_ENVELOPE=true`, `tenant_envelope=true`; **`licenca=false`** (`SAAS_ENFORCE_LICENCA` desligado); `APP_ENV=homologation`, `APP_DEBUG=false`; `services.firebase.driver=fake`, `services.pix.driver=fake`; `sanctum.expiration=1440`.

---

## Resumo executivo

| ID | Título | Sev. | Estado |
|---|---|---|---|
| **R1-01** | Tomada de conta de qualquer usuário (admin inclusive) pelo **cadastro do app** via colisão de e-mail | **CRÍTICA** | CONFIRMADO (PoC verde) |
| **R1-02** | IDOR dentro do tenant: rotas do app (`perfil`, `pedidos`, etc.) expõem PII de **qualquer cliente** da empresa a usuário sem RBAC, pelo fallback `cliente_id` | **ALTA** | CONFIRMADO (PoC verde) |
| **R1-03** | Suspensão de empresa pelo SuperAdmin **não bloqueia** login nem operação do tenant | **ALTA** | CONFIRMADO (PoC verde) |
| **R1-04** | Sem `TrustProxies`: `request->ip()` é o IP do proxy para todos → throttle `login` e eixo-IP do lockout são globais/inócuos | **MÉDIA** | CONFIRMADO |
| **R1-05** | SSH da VPS: `PermitRootLogin yes` + `PasswordAuthentication yes`, deploy por senha de root, `fail2ban` inativo | **MÉDIA** | CONFIRMADO (infra compartilhada) |
| **R1-06** | `licenca=false` em homologação: módulos premium e recurso não contratado passam sem 402 | **MÉDIA** | CONFIRMADO (config) |
| **R1-07** | `FakeFirebaseVerifier` aceito fora de produção: em homolog qualquer um loga como qualquer telefone | **MÉDIA** | CONFIRMADO (config) / mitigado em prod |
| **R1-08** | Robustez: `logout`/`refresh` do app e do SuperAdmin chamam `currentAccessToken()->delete()` sem null-check | **BAIXA** | FORTE INDÍCIO |

Áreas revisadas e consideradas **sólidas** (sem achado): webhook PIX (HMAC por empresa sobre corpo cru, binding de valor, idempotência por `lockForUpdate`); isolamento RLS no Postgres (policies `app_tenant_can_read/operate` por `tenant_membership`, `FORCE ROW LEVEL SECURITY` conferido em `clientes`, `clientetelefones`, `tenant_company_grants`, `pix_cobrancas`, `app_devices`); ausência de SQL dinâmico explorável; ausência de `dangerouslySetInnerHTML`; nenhum segredo vivo versionado nos arquivos rastreados em escopo.

---

## Achados

### R1-01 — Tomada de conta via cadastro do app (colisão de e-mail) — CRÍTICA — CONFIRMADO

**Categoria:** Autenticação / elevação de privilégio / BOLA.
**Arquivo:** `erp-novo/app/Domain/Mobile/ClienteAuthService.php:169-213` (método `garantirUsuario`), em especial `firstOrCreate(['email' => $email], ...)` nas linhas 184-196; entrada por `erp-novo/app/Http/Controllers/Api/Mobile/AppAuthController.php:340-369` (`executarCadastroCliente`) / rota `POST /api/app/v1/cliente/cadastro` (`routes/api.php:114`).

**Causa raiz.** O cadastro do cliente pelo app aceita `email` **arbitrário do atacante** e, ao garantir o `User`, faz `User::firstOrCreate(['email' => $email], [...])`. A tabela `users` é **compartilhada** entre staff do back-office e clientes do app, e `email` é `unique`. Se o e-mail informado já pertence a um usuário existente (um administrador da revenda, por exemplo), o `firstOrCreate` **devolve aquele usuário** em vez de criar um novo. O controller então emite um token Sanctum **para aquele usuário**. As rotas `/api/admin/*` autenticam por `auth:sanctum` e **não verificam a ability `role:cliente`** do token (o middleware `approle` só cobre os subgrupos `app/v1`), então o token recém-emitido abre o back-office inteiro com o RBAC da vítima.

Requisito do ataque: ter um telefone verificado (o próprio, via Firebase real em produção) e conhecer/adivinhar o e-mail de um usuário-alvo da empresa. Nenhuma senha é necessária.

**Esperado × observado.** Esperado: o cadastro cria/vincula apenas um usuário-cliente novo e isolado. Observado: devolve o `User` administrativo e emite token válido para ele; efeito colateral grava `cliente.user_id = <id do admin>`.

**Reprodução (PoC isolada, fora do projeto):**
`scratchpad/poc/R1TakeoverPocTest.php` — cria um admin com permissão, dispara `POST /api/app/v1/cliente/cadastro` com telefone do atacante e `email` do admin; asserta `user.id == admin.id` e que o token abre `GET /api/admin/clientes` (200).
Resultado: `OK (1 test, 3 assertions)`.

**Impacto.** Tomada total de conta administrativa de qualquer tenant por um estranho que só precise do e-mail do alvo — leitura/escrita de clientes, financeiro, fiscal, etc., dentro daquele tenant. É bloqueador de deploy. (A suíte sqlite não pega porque o fluxo "feliz" nunca colide e-mail; nenhum teste exercita a colisão.)

**Correção proposta.** Separar identidade de cliente-do-app de usuário de staff: NÃO reusar `User` por e-mail no cadastro. Opções: (a) criar sempre um `User` novo para o cliente, vinculando por telefone/UID do Firebase, nunca por e-mail informado; (b) antes do `firstOrCreate`, recusar (422) se já existir `User` com aquele e-mail que **não** seja um usuário-cliente do app (sem `cliente.user_id` apontando para si) — fail-closed; (c) marcar usuários-cliente com um discriminador e exigir que `firstOrCreate` só reaproveite usuários desse tipo. Em qualquer caso, e-mail duplicado deve virar e-mail sintético (como já ocorre quando ausente), nunca adoção de conta existente.

**Teste de aceite.** Cadastro com e-mail de um `User` administrativo existente retorna 4xx (ou cria usuário-cliente distinto) e **nunca** emite token cujo `user.id` seja o do administrador; token de cliente recebe 403 em `/api/admin/*`.

---

### R1-02 — IDOR intra-tenant: PII de qualquer cliente via fallback `cliente_id` — ALTA — CONFIRMADO

**Categoria:** Autorização / IDOR-BOLA / exposição de PII.
**Arquivo:** `erp-novo/app/Http/Controllers/Api/Mobile/Concerns/ResolveClienteDoApp.php` (fallback `cliente_id`, trecho "Fallback (transição)"); consumido por `AppPerfilController::perfil` (`...Mobile/AppPerfilController.php:27-41`, devolve `cpf`, `email`, `datanascimento`, telefones) e demais rotas do grupo `approle:cliente` (`routes/api.php:841-873`).

**Causa raiz.** `clienteDoUsuario()` primeiro tenta resolver o cliente pelo `user_id` do token; **se não encontrar** (caso de qualquer usuário autenticado que não seja um cliente-do-app — ou seja, staff), cai no fallback que lê `cliente_id` da query/body e devolve **qualquer** cliente da empresa (escopo só por `empresa_id`). As rotas do app não aplicam RBAC (`$this->autorizar(...)`); a separação é só por ability de papel. Um token de SPA de staff tem abilities `['*']` (ver `AuthController::login` → `createToken('spa')`), que **passa** no `AppRole` (`PersonalAccessToken::can('role:cliente')` é verdadeiro para `*`). Logo um funcionário **sem nenhuma permissão** lê o CPF de todos os clientes da empresa iterando `cliente_id`.

**Esperado × observado.** Esperado: usuário sem `cliente.view` não lê cadastro de cliente (a rota admin equivalente nega 403). Observado: `GET /api/app/v1/perfil?cliente_id=<vítima>` devolve 200 com CPF.

**Reprodução (PoC isolada):** `scratchpad/poc/R1IdorClienteFallbackPocTest.php` — staff `semPapel` com token `spa`; `GET /api/admin/clientes/{id}` → 403; `GET /api/app/v1/perfil?cliente_id={id}` → 200 com `data.cpf`. Resultado: `OK (1 test, 3 assertions)`.

**Mitigação parcial (RLS).** O escopo de empresa é reforçado pela RLS no Postgres (não há vazamento cross-tenant; a suíte sqlite não prova RLS, mas o `where('empresa_id', ...)` já limita). O problema é **horizontal/privilégio dentro do tenant**: qualquer autenticado da empresa lê PII de todos os clientes, sem checagem de permissão.

**Impacto.** Exposição de dados pessoais (CPF, e-mail, nascimento, telefones, endereços) de toda a base de clientes de uma revenda a qualquer conta autenticada dela, contornando o RBAC — relevante para LGPD (minimização e controle de acesso).

**Correção proposta.** No fallback, exigir permissão explícita quando o chamador **não** é o próprio cliente do token (ex.: `autorizar('cliente.view')`), ou remover o fallback `cliente_id` das rotas `approle:cliente` (ele é "transição"). Alternativamente, restringir essas rotas a tokens cuja ability seja estritamente `role:cliente` (negar `*`).

**Teste de aceite.** Usuário autenticado sem `cliente.view` recebe 403 ao pedir `perfil`/`pedidos` de `cliente_id` que não é o seu.

---

### R1-03 — Suspender empresa não bloqueia login nem operação — ALTA — CONFIRMADO

**Categoria:** Proteção comercial / controle de acesso.
**Arquivo:** `erp-novo/app/Domain/Saas/SuperAdminService.php:68-77` (`suspenderEmpresa` só faz `empresa.ativo=false`); nenhum middleware do grupo autenticado checa `empresa.ativo` (`ResolveTenant.php` e `ResolveTenantEnvelope` não avaliam o flag).

**Causa raiz.** A suspensão apenas marca `ativo=false`. O pipeline de requisição (`auth:sanctum` → `tenant` → `tenant.saas` → `licenca.rota`) não reavalia se a empresa está ativa, e o login (`AuthController`) só checa `user.ativo`, não `empresa.ativo`. Resultado: tokens já emitidos continuam valendo e **novos logins** continuam funcionando após a suspensão.

**Esperado × observado.** O próprio teste do projeto (`tests/Feature/SuperAdminTest.php:141`) e o comentário de cabeçalho afirmam "suspender empresa **bloqueia** o tenant"; na prática o bloqueio não acontece.

**Reprodução (PoC isolada):** `scratchpad/poc/R1SuspensaoPocTest.php` — suspende a empresa, confirma `ativo=false`, **loga de novo** (`/api/login` 200) e opera (`/api/admin/clientes` 200). Resultado: `OK (1 test, 4 assertions)`.

**Impacto.** Revenda inadimplente/suspensa segue usando o SaaS normalmente — falha direta de proteção de receita. (Observação: `SAAS_ENFORCE_LICENCA=false` em homolog, ver R1-06, agrava, mas o defeito é independente do flag.)

**Correção proposta.** Middleware no grupo autenticado que negue (403/402) quando `empresa.ativo=false` (ou status de assinatura suspenso), e recusa no login. Idealmente revogar tokens ativos ao suspender.

**Teste de aceite.** Após suspender, login e qualquer rota `/api/admin/*` do tenant retornam bloqueio; token pré-existente deixa de operar.

---

### R1-04 — Sem TrustProxies: throttle de login e lockout por IP são globais — MÉDIA — CONFIRMADO

**Categoria:** Força bruta / disponibilidade.
**Evidência:** `bootstrap/app.php` não chama `trustProxies()` e não há middleware `TrustProxies` em `app/Http/Middleware` (grep vazio). Em `login_logs` (homolog) **49/49** registros têm `ip` em faixa privada (`172.x`), ou seja o IP do contêiner nginx. `nginx` passa `X-Forwarded-For`/`X-Real-IP` (conferido em `/etc/nginx/sites-enabled/gasemcasa-com`), mas sem `TrustProxies` o Laravel ignora o header e `request->ip()` é sempre o proxy.

**Causa raiz + impacto.** O limiter `login` é `Limit::perMinute(10)->by('login:'.$r->ip())` (`AppServiceProvider.php:323`) e o eixo-IP do `LoginSeguranca::bloqueado` (`LoginSeguranca.php:68-73`) usam esse IP único para todos. Consequências: (a) o teto de 10/min de login vale para a **plataforma inteira** — um cliente pode esgotar a capacidade de login de todos (DoS de autenticação); (b) o eixo-IP do lockout nunca distingue origens. Mitigação existente: o **eixo por e-mail** do lockout (5 falhas/15 min, `LoginSeguranca.php:46-52`) continua protegendo cada conta contra brute-force direcionado — por isso MÉDIA, não ALTA.

**Correção proposta.** Configurar `TrustProxies` para a rede do proxy (ou `trustProxies(at: '*')` atrás de nginx interno) para que `request->ip()` reflita o `X-Forwarded-For` real; reavaliar os limiters por IP.

**Teste de aceite.** `request->ip()` retorna IP de cliente distinto; dois IPs distintos têm baldes de throttle independentes.

---

### R1-05 — SSH da VPS: root por senha, fail2ban inativo — MÉDIA — CONFIRMADO (infra compartilhada)

**Categoria:** Configuração / cadeia de deploy.
**Evidência (read-only na VPS):** `sshd -T` → `permitrootlogin yes`, `passwordauthentication yes`, `maxauthtries 6`; `systemctl is-active fail2ban` → `inactive`; conexão atual como `root`. O pipeline (`.github/workflows/erp-novo.yml:313,373`) faz deploy com `sshpass` usando `secrets.VPS_PASSWORD` como **root** (host key fixada, o que é bom — `VPS_HOSTKEY`/`StrictHostKeyChecking`).

**Impacto.** Login de root por senha num IP público (72.60.10.108), sem fail2ban, numa VPS que hospeda **várias** aplicações de terceiros — superfície de brute-force e ponto único cuja queda compromete todos os contêineres, inclusive `erpnovo-*`. O comprometimento da `VPS_PASSWORD` dá root direto.

**Correção proposta.** Desabilitar `PasswordAuthentication` e `PermitRootLogin` (usar usuário de deploy dedicado com chave e `sudo` restrito), migrar o CI para chave SSH em vez de senha, ativar fail2ban. (Pertence também ao escopo do R5; registrado aqui pelo risco de segurança.)

---

### R1-06 — Licença não imposta em homologação — MÉDIA — CONFIRMADO (config)

**Categoria:** Proteção comercial.
**Evidência:** `config('saas_transformation.enforcement')` → `{tenant_envelope:true, licenca:false}`; `SAAS_ENFORCE_LICENCA` ausente no contêiner. Com `licenca=false`, `RecursoPorRota` (`app/Http/Middleware/RecursoPorRota.php:35-37`) e `Recurso` fazem *passagem livre*: módulo não contratado (monitora, crm, frota, cobrança/pix, nfce, relatórios avançados) responde sem 402.

**Avaliação do mecanismo (código).** Quando ligado, o enforcement é razoável: `LicencaService::calcular` monta recursos a partir da **assinatura vigente** (`STATUS_TRIAL|ATIVA` e `vigente()`), overrides respeitam `expira_em`, limites numéricos existem (`dentroDoLimite`). O desenho protege receita — o problema é estar **desligado**. Em homolog pode ser intencional; para produção/SaaS é um gate obrigatório.

**Correção proposta.** Ligar `SAAS_ENFORCE_LICENCA=true` em produção e cobrir com teste de fumaça que um módulo fora do plano retorna 402. (Não validável aqui porque a suíte sqlite e o ambiente estão com o flag off.)

---

### R1-07 — Verificador Firebase FAKE aceito fora de produção — MÉDIA — CONFIRMADO / mitigado em prod

**Categoria:** Autenticação.
**Evidência:** `AppServiceProvider.php:223-233` + `exigirDriverReal` (`:83-98`): o driver real só é **exigido** quando `isProduction()`. Em `APP_ENV=homologation` (caso atual) o `FakeFirebaseVerifier` (`app/Domain/Mobile/Drivers/FakeFirebaseVerifier.php`) é aceito e trata qualquer `fake:+55...` como telefone verificado. Confirmado por `config('services.firebase.driver')='fake'` no contêiner.

**Impacto.** Em homologação, **qualquer um** faz login/cadastro como qualquer telefone (`POST /app/v1/cliente/login` com `firebase_id_token:"fake:+55..."`) — e, combinado com R1-01, toma contas sem sequer precisar de telefone real. Em **produção** o boot falha se o driver não for `kreait`, então está mitigado lá; o risco é a homologação conter dados reais migrados. Mesmo padrão vale para `services.pix.driver=fake`.

**Correção proposta.** Tratar homologação com dados reais como produção para fins de drivers (exigir driver real quando o banco tem PII), ou isolar a base de homolog. No mínimo, documentar que homolog não deve receber tráfego externo.

---

### R1-08 — logout/refresh sem null-check no token atual — BAIXA — FORTE INDÍCIO

**Arquivo:** `AppAuthController::logout` (`:371-376`), `refresh` (`:383-401`); `Api/SuperAdmin/AuthController::logout` (`currentAccessToken()->delete()`). Se a rota for alcançada por autenticação sem token pessoal (ex.: sessão de cookie), `currentAccessToken()` pode ser null e o `->delete()` lança. Baixo risco (rotas são para apps/Bearer), mas vale o `?->`.

---

## O que foi inspecionado / testado / não testado

**Inspecionado:** `routes/api.php` inteiro (público, admin, app `approle`, pontes legado, superadmin); `AppAuthController`, `ClienteAuthService`, `IdentificarOuCriarCliente`, `AuthController`, `SuperAdmin/AuthController`; concern `AutorizaPorPermissao`, middleware `AppRole`, `ResolveTenant`, `ResolveTenantEnvelope`, `RecursoPorRota`; `ConexaoDeOwner`, `AutomacaoPorEmpresa` (uso de owner nos commits `d1b2878b`/`b2f151bb`); `PixWebhookController`, `PabxWebhookController`; `ResolveClienteDoApp`, `AppPedidoController`, `AppPerfilController`, `AppEntregadorController`; `LicencaService`, `SuperAdminService`, `LoginSeguranca`; drivers HTTP externos; `config/sanctum.php`, `config/cors.php`, `config/auth.php`, `bootstrap/app.php`.

**Testado (PoC isoladas em `scratchpad/poc`, não escritas no projeto, rodadas com PHP 8.3 portátil):** R1-01, R1-02, R1-03 — três testes verdes comprovando os achados. Revisão dos commits recentes do login por owner: a consulta de owner (`ClienteAuthService::clientePorTelefone`) devolve **só ids presos à `empresa_id` pedida** e o cliente é relido pelo runtime sob RLS — **não** encontrei vazamento cross-tenant nem enumeração por ali (o pré-filtro `like` por dígitos roda escopado; a entrega depende do runtime/RLS). A escrita de fronteira por owner (`garantirFronteiraSaas`) cria grant só para a empresa do próprio cliente.

**Verificado no Postgres (homolog, read-only):** RLS/FORCE ativos nas tabelas sensíveis; definição das funções `app_tenant_can_read/operate` (por `tenant_membership` + grant aprovado); `login_logs` com IPs de proxy; contagem de `SQLSTATE[42501]`/`[23514]` no log (RLS/CHECK barrando — sem PII nos logs amostrados).

**Não testado / limitações:** não rodei a suíte inteira (baseline em curso, conforme briefing); gate RLS no Postgres local não executado (PoC rodaram em sqlite — por isso R1-01/02/03 valem independentemente de RLS, que reforça só o escopo de empresa); não testei webhooks com tráfego real; não avaliei conformidade jurídica de LGPD (fora do escopo técnico); análise de dependências (`composer audit`/`npm audit`) não executada offline. Produção não foi acessada.

**Nota LGPD (técnica, sem parecer jurídico):** há direito do titular implementado (`excluirConta` → `encerrarPeloTitular`, soft-delete + revogação de tokens) e expurgo de posições por retenção configurável (`monitora:expurgar-posicoes`). Pontos de atenção: R1-02 (acesso amplo a PII sem RBAC); confirmar expurgo/retenção para `missao_trilha` (trilha de GPS de colaborador) — não localizei rotina de expurgo equivalente à de `entregador_posicoes`.
