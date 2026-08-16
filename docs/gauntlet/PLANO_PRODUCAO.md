# PLANO DE PRODUÇÃO — ERP Gás em Casa

> **O objetivo deste plano:** entregar ao cliente uma aplicação que faça **tudo que o sistema antigo fazia**, de forma organizada e segura, carregada com os dados reais do dump — e pronta para ser vendida como SaaS a outras revendas de gás.
>
> **Para quem é.** Para um agente **Claude Opus 5** executando em sessões futuras, que **não leu a auditoria**. Cada tarefa carrega o contexto mínimo, o achado que a justifica (com arquivo e linha), os passos e uma definição binária de pronto.
>
> **Regra de ouro.** Nada aqui é "provavelmente feito". Cada tarefa tem um comando ou query que **prova**. Se o comando não roda ou não retorna o valor esperado, a tarefa não está pronta.
>
> **Fonte.** Cada tarefa rastreia a uma seção de `AUDITORIA.md`, verificada por crítico independente contra o código. Referências no formato *(Auditoria §N)*.

---

## 0. Como este plano se conecta ao seu objetivo

Seu objetivo tem três pernas, e elas têm dependência entre si:

| Perna | Onde é resolvida | Situação hoje |
|---|---|---|
| **1. O novo faz tudo que o antigo fazia** | **F4** (a fase mais pesada) | 20 de 46 áreas em paridade plena; 22 com lacuna; 4 ausentes |
| **2. Carregado com os dados reais do dump** | **F2** + **F6** | Financeiro ao centavo, mas espelho cobre 43 de 222 tabelas em produção |
| **3. Seguro e vendável como SaaS** | **F1** + **F3** | RLS multi-tenant já ativo; mas senha default de admin exposta e sem backup |

**Ordem obrigatória e por quê:**

**F1 (segurança) → F2 (dados) → F3 (infra) → F4 (paridade) → F5 (débito) → F6 (cutover)**

- **F1 primeiro** porque há uma conta admin com bypass total de RBAC e senha pública **ativa em produção agora**. Enquanto isso existir, qualquer outra coisa que você construa está exposta.
- **F2 antes de F3** porque o `cutover:check` é o critério de aceite da janela de virada, e hoje ele dá **verde falso** (passa por omissão, sem ter com o que comparar).
- **F3 antes de F4** porque **não existe backup do banco**: alterar funcionalidade em produção sem rede de segurança é irreversível.
- **F4 é o que decide se o cliente consegue trabalhar.** É onde está o grosso do esforço restante e a resposta à sua pergunta "falta o quê para funcionar como o antigo".
- **F6 só depois de F1+F2+F3 verdes.** F4 admite corte: só as tarefas **[BLOQUEANTE]** travam o go-live; as demais podem ir em release seguinte.

### Quadro das fases

| Fase | Objetivo | Bloqueia entrega? | Tarefas |
|---|---|---|---|
| **F1** | Segurança: credenciais, drivers fake, throttle, dependências | **SIM** | 9 |
| **F2** | Dados: deduplicação, espelho incompleto, invariantes que mentem | **SIM** | 8 |
| **F3** | Infra: backup/rollback, compose de produção, Reverb, observabilidade | **SIM** | 10 |
| **F4** | **Paridade funcional — o operador conseguir fechar o dia** | **SIM** (subconjunto) | 9 |
| **F5** | Débito técnico | Não | 2 |
| **F6** | Cutover e pós-cutover | — | 8 |
| | | **Total** | **46** |

### O que F4 precisa cobrir (resumo — detalhe na fase)

As 22 lacunas ⚠️ se agrupam em quatro famílias. Atacar por família é mais barato que por módulo:

1. **Saídas impressas** — não existe gerador de PDF operacional no novo. Boleto, recibo, vale-gás, contrato de comodato, DANFE, comanda. **No disk-gás o papel é o produto**: sem boleto impresso o título não chega ao cliente. *(maior família, maior impacto)*
2. **Escrita que virou leitura** — recessos e comissões (RH), trocas de óleo e pneu (frota): CRUD no legado, só GET no novo.
3. **Ação que fecha o ciclo** — `ignorarRua`/`ignorarBairro` (a fila de inconsistências nunca esvazia) e `extratoconfig` (a importação OFX existe mas sem classificação automática que a torne produtiva).
4. **Gestão e marketing** — fechamento mensal com envio, PowerPoint de vendas, campanhas de push, giro de compras.

E os 4 ❌: telefonia/bina, fechamento de malote, tipos de documento de veículo, vídeo de abertura do app. **Bina e malote são fluxos diários do disk-gás** — exigem decisão explícita de portar ou aposentar formalmente com o cliente.

### ⚠️ Advertência sobre o ambiente

A produção **diverge** do Docker local (Auditoria §7). Ao executar qualquer tarefa de dados, confirme **em qual ambiente** está atuando:

| | Docker local | VPS produção |
|---|---|---|
| `public.clientes` | 88.765 | 66.557 |
| Tabelas no schema `legado` | 121 | **43** |
| Pedidos apontando p/ cópias | 430 | **0** |
| `cutover:check` | 62 OK / 9 falhas | 16 OK / 0 falhas (**verde falso**) |

---

## FASE F1 — Estancar o sangramento de segurança

**Objetivo.** Eliminar os dois achados CRÍTICOS e os dois ALTOS de autenticação da auditoria de segurança (Auditoria §8), de modo que um `.env` de produção mal preenchido **não** resulte em acesso administrativo total nem em autenticação de cliente forjável. Hoje o sistema *fail-opens* nesses pontos.

**Contexto que o agente precisa saber.** O `erp-novo` tem um agregador de seeds (`DeploySeeder`) que roda em **todo** deploy e cria contas administrativas. Tem também um padrão de "drivers gate por env var" — Firebase, FCM, fiscal, cobrança, monitora — em que o valor default é `fake`. O PIX faz isso **certo** (rejeita explicitamente em `app()->isProduction()` sem segredo, em `app/Http/Controllers/Api/PixWebhookController.php`); os demais, não. A F1 replica o padrão do PIX nos outros gates.

---

### T1.1 — Fail-close das senhas seed em produção — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §8 C1 e Auditoria §9 §7.1.3. `erp-novo/database/seeders/DeployAdminSeeder.php:35-36` cria `admin@gasemcasa.com` com senha default `admin1234` e `support = true` (bypass total de RBAC via `Gate::before` em `erp-novo/app/Providers/AuthServiceProvider.php`). `erp-novo/database/seeders/SuperAdminSeeder.php:20-21` cria `superadmin@gasemcasa.com` / `superadmin1234` no guard `platform` (cross-tenant). `erp-novo/database/seeders/DeploySeeder.php:20-26` chama **ambos** em todo deploy. As senhas default estão no código versionado.

**Passos.**
1. Em `DeployAdminSeeder.php`, remover o segundo argumento (o default) das chamadas `env('ADMIN_SEED_EMAIL', ...)` / `env('ADMIN_SEED_PASSWORD', ...)`. No lugar, se `app()->environment('production')` **e** a env var estiver vazia, lançar `\RuntimeException` com mensagem explícita ("ADMIN_SEED_PASSWORD é obrigatória em produção"). Fora de produção, manter um default — mas **gerado**, não literal: use `Str::random(24)` e ecoe no console com `$this->command->warn(...)`.
2. Repetir exatamente o mesmo tratamento em `SuperAdminSeeder.php:20-21` para `SUPERADMIN_SEED_EMAIL`/`SUPERADMIN_SEED_PASSWORD`.
3. Aplicar o mesmo em `erp-novo/database/seeders/AcessoMigracaoSeeder.php:40-42,46-48` (achado Auditoria §8 B1 — não roda no `DeploySeeder`, mas tem os mesmos defaults `dubena@2026`/`operador@2026`/`super@2026`).
4. Adicionar validação de força mínima: rejeitar senha com menos de 12 caracteres em produção.

**Pronto quando (binário).**
```bash
# em erp-novo/, com APP_ENV=production e ADMIN_SEED_PASSWORD desligada
APP_ENV=production php artisan db:seed --class='Database\Seeders\DeploySeeder' --force
# DEVE terminar com exit code != 0 e a mensagem sobre ADMIN_SEED_PASSWORD
echo $?   # != 0
```
```bash
grep -rn "admin1234\|superadmin1234\|dubena@2026\|operador@2026\|super@2026" erp-novo/database/seeders/
# DEVE retornar 0 linhas
```

---

### T1.2 — Remover as credenciais de demonstração da UI de login — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §8 C1 (agravante) e Auditoria §8 M2/M3. `erp-novo/frontend/src/features/auth/LoginPage.tsx:11,33,134-138` renderiza um botão "Preencher com acesso de demonstração" que exibe e pré-preenche `admin@gasemcasa.com` / `admin1234`, **sem gate de `import.meta.env.DEV`** — aparece na tela de login em produção. `erp-novo/frontend/src/features/superadmin/SaLoginPage.tsx:8,47-50` faz o mesmo com `superadmin@gasemcasa.com` / `superadmin123` (grep por `import.meta.env` nesse arquivo retorna zero — confirmado na auditoria).

**Passos.**
1. Em `LoginPage.tsx`, envolver o bloco do botão de demonstração em `{import.meta.env.DEV && ( ... )}` **e** remover as constantes com o e-mail/senha literais das linhas 11 e 33 — mova-as para dentro do bloco condicional, ou melhor: leia de `import.meta.env.VITE_DEMO_EMAIL`/`VITE_DEMO_PASSWORD` (ausentes em produção → botão não renderiza).
2. Mesma coisa em `SaLoginPage.tsx:8,47-50`.
3. Em `app-entregador/src/app/login.tsx:21,134-138`: o gate `APP.debug` já existe (`login.tsx:128`) e está correto — **não mexer na lógica**, apenas remover a senha literal `entregador123` do código-fonte, lendo de `process.env`/`extra` do `app.config.ts`.

**Pronto quando (binário).**
```bash
cd erp-novo/frontend && npm run build
grep -rn "admin1234\|superadmin123\|entregador123" erp-novo/frontend/src/ app-entregador/src/
# DEVE retornar 0 linhas
grep -rn "admin@gasemcasa\|superadmin@gasemcasa" erp-novo/frontend/dist/ 2>/dev/null || echo "OK — ausente do bundle"
# nada no bundle buildado
```

---

### T1.3 — Fail-close dos drivers gate em produção (Firebase, FCM, fiscal, cobrança, monitora) — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §8 C2. `erp-novo/config/services.php:74` define `'driver' => env('FIREBASE_DRIVER', 'fake')`; `erp-novo/app/Providers/AppServiceProvider.php:121-123` faz o bind cair no `FakeFirebaseVerifier` se o valor não for `kreait`. E `erp-novo/app/Domain/Mobile/Drivers/FakeFirebaseVerifier.php:15-27` aceita **qualquer** token no formato `fake:+telefone` e devolve o telefone como verificado. Resultado: se `FIREBASE_DRIVER=kreait` faltar no `.env` de produção, qualquer pessoa loga como qualquer cliente enviando `firebase_id_token = "fake:+5542..."` para `POST /api/app/v1/cliente/login` (`erp-novo/routes/api.php:88`). O mesmo padrão de fallback silencioso existe em `FCM_DRIVER` (`config/services.php:66`, bind em `AppServiceProvider.php:126-129`), `FISCAL_DRIVER` (`config/services.php:83-85`), `COBRANCA_DRIVER` e `MONITORA_DRIVER` — um driver fiscal em `fake` em produção significa NF-e simulada.

**Passos.**
1. Em `AppServiceProvider.php`, criar um helper privado, ex.: `private function driverGate(string $config, string $real, string $classeReal, string $classeFake): string`. Ele lê `config($config)`; se o valor **não** é o driver real **e** `app()->isProduction()`, lança `\RuntimeException` na resolução do binding (não no boot — para não quebrar comandos de manutenção) com a mensagem nomeando a env var faltante.
2. Aplicar o gate aos binds das linhas 121-135 de `AppServiceProvider.php`: `FirebaseVerifier`, `PushTransport`, `SgcasaDriver`, e aos binds equivalentes de `SefazDriver` (fiscal) e do driver de cobrança.
3. **Exceção deliberada:** `MONITORA_DRIVER` é `[DESEJÁVEL]` por Auditoria §9 §7.3 (GPS SGCasa). Aplique o gate mas com nível **warning em log**, não exceção — GPS ausente degrada, não corrompe.
4. Espelhar o comportamento já correto do PIX (`app/Http/Controllers/Api/PixWebhookController.php`, que rejeita em `app()->isProduction()`) — use-o como referência de estilo.

**Pronto quando (binário).**
```bash
# em erp-novo/, produção sem FIREBASE_DRIVER
APP_ENV=production FIREBASE_DRIVER= php artisan tinker --execute='app(\App\Domain\Mobile\Contracts\FirebaseVerifier::class);'
# DEVE lançar RuntimeException citando FIREBASE_DRIVER
```
```bash
# e o inverso: com kreait configurado, resolve sem exceção
APP_ENV=production FIREBASE_DRIVER=kreait php artisan tinker --execute='echo get_class(app(\App\Domain\Mobile\Contracts\FirebaseVerifier::class));'
# DEVE imprimir KreaitFirebaseVerifier
```
Repetir o par para `FCM_DRIVER=v1`, `FISCAL_DRIVER=nfephp`, `COBRANCA_DRIVER=caixa`.

---

### T1.4 — Throttle nas três rotas públicas de login/cadastro do app — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §8 A1. Em `erp-novo/routes/api.php`, as linhas **85** (`POST /app/v1/login`), **88** (`POST /app/v1/cliente/login`) e **91** (`POST /app/v1/cliente/cadastro`) **não** têm `->middleware('throttle:...')`. Verificado no código: as rotas vizinhas têm — `/login` web na linha 79 usa `throttle:login`, marketplace nas linhas 94-99 usa `throttle:marketplace`, `/health` na linha 76 usa `throttle:60,1`. E não há rede de segurança global: o `throttle:api` só é aplicado ao **grupo autenticado** (`routes/api.php:102`). Os limiters já existem, definidos em `AppServiceProvider.php:146-175` (`api` 120/min, `login` 10/min, `api-tenant`, `marketplace` 60/min por IP, `gps-ping` 120/min, `missao-visita` 30/min).

**Passos.**
1. Adicionar `->middleware('throttle:login')` às linhas 85 e 88 de `routes/api.php` (mesmo limiter do login web — 10/min por IP).
2. Para a linha 91 (cadastro de cliente), criar um limiter **novo e mais estreito** em `AppServiceProvider.php` junto dos existentes (linhas 146-175), ex.: `RateLimiter::for('cadastro-cliente', fn (Request $r) => Limit::perMinute(5)->by($r->ip()))`. Cadastro é irreversível e cria linhas — merece teto menor que login.
3. Não tocar no lockout interno de `AppAuthController::login` (`AppAuthController.php:88`, via `LoginSeguranca::bloqueado()`) — ele é controle compensatório complementar, não substituto.

**Pronto quando (binário).**
```bash
cd erp-novo && php artisan route:list --path=app/v1 --json | \
  jq -r '.[] | select(.uri|test("app/v1/(login|cliente/login|cliente/cadastro)$")) | "\(.uri) :: \(.middleware|join(","))"'
# as 3 linhas DEVEM conter "throttle:"
```
Teste funcional: 11 POSTs seguidos a `/api/app/v1/cliente/login` do mesmo IP → o 11º retorna **HTTP 429**.

---

### T1.5 — Reduzir a expiração default dos tokens Sanctum — **[IMPORTANTE]**

**Achado que justifica.** Auditoria §8 A2. `erp-novo/config/sanctum.php:57` define `expiration = env('SANCTUM_EXPIRATION', 43200)` — 43200 minutos = **30 dias**. A SPA guarda o Bearer client-side (`erp-novo/frontend/src/lib/api.ts` usa localStorage para "manter conectado" e sessionStorage para sessão) e `AuthController.php:92` sempre emite o token no corpo, mesmo no fluxo cookie. Um token vazado vale um mês.

**Passos.**
1. Alterar o default em `config/sanctum.php:57` de `43200` para `1440` (24 h).
2. Documentar `SANCTUM_EXPIRATION` no template de produção (dependência: T3.1) com valor recomendado e a nota de que os apps mobile usam refresh (`AppAuthController::refresh`, `:199-217`), então uma janela curta é operacionalmente viável.
3. Verificar que `mobile-shared/src/http.ts` (que tem infraestrutura de refresh) e os dois apps tratam 401 disparando refresh — se não tratarem, corrigir aqui, senão o encurtamento derruba usuários.

**Pronto quando (binário).**
```bash
grep -n "SANCTUM_EXPIRATION" erp-novo/config/sanctum.php
# DEVE mostrar default 1440
```
Teste: token emitido, `personal_access_tokens.expires_at` ≈ agora+24h; e um 401 no app dispara refresh transparente (teste manual ou Vitest em `mobile-shared/`).

---

### T1.6 — Atualizar dependências PHP com CVEs — **[IMPORTANTE]**

**Achado que justifica.** Auditoria §8 A3. `composer audit` executado em `erp-novo/` na auditoria retornou **18 advisories em 3 pacotes**: `league/commonmark` (6 advisories, 3 high DoS/ReDoS incl. CVE-2026-71488 + 1 medium unsafe-link bypass CVE-2026-71478, versão < 2.9.0), `dompdf/dompdf` (6, medium — usado na geração de PDF de relatórios), `guzzlehttp/guzzle` (6 — cliente HTTP das integrações, PSP e Firebase). Distribuição: 5 high, 11 medium, 2 low.

**Passos.**
1. `cd erp-novo && composer update league/commonmark dompdf/dompdf guzzlehttp/guzzle --with-all-dependencies`.
2. Rodar a suíte completa (`php artisan test`) — `dompdf` e `guzzle` estão em caminhos de relatório e integração; regressão é plausível.
3. Se algum pacote não subir por conflito de constraint, subir o constraint em `composer.json` e reavaliar.

**Pronto quando (binário).**
```bash
cd erp-novo && composer audit
# DEVE retornar "No security vulnerability advisories found" (exit 0)
cd erp-novo && php artisan test
# DEVE passar
```

---

### T1.7 — Atualizar dependências do frontend com CVEs — **[IMPORTANTE]**

**Achado que justifica.** Auditoria §8 A4. `npm audit --omit=dev` em `erp-novo/frontend/` retornou **4 vulnerabilidades (2 high, 2 moderate)**: `react-router`/`react-router-dom` 6.0.0–7.17.0 (moderate — **open redirect** via backslash em `<Link>`/`useNavigate`, bypass de CVE-2025-68470, e injeção de construtor via `deserializeErrors()`), `nanoid` (high — loop infinito com size ≤ 0), `postcss` (high — path traversal / disclosure de `.map` via `sourceMappingURL`, build-time). O open redirect é o relevante em runtime: `erp-novo/frontend/src/routes.tsx` usa React Router com `React.lazy`.

**Passos.**
1. `cd erp-novo/frontend && npm audit fix`; se `react-router-dom` exigir major, atualizar manualmente e ajustar `routes.tsx`.
2. Rodar `npm run type-check` e `npm test` (Vitest) — a migração de major do React Router quebra APIs.
3. Verificar que as rotas protegidas por permissão RBAC continuam funcionando (`routes.tsx:114,134-138` — `/pedidos` exige `pedido.view`, `/central` exige `logistica.view`, `/fiscal` exige `fiscal.view`).

**Pronto quando (binário).**
```bash
cd erp-novo/frontend && npm audit --omit=dev
# DEVE retornar "found 0 vulnerabilities"
cd erp-novo/frontend && npm run type-check && npm test && npm run build
# todos exit 0
```

---

### T1.8 — Retirar `support` do `$fillable` do model User — **[DESEJÁVEL]**

**Achado que justifica.** Auditoria §8 B2. `erp-novo/app/Models/User.php:22-30` inclui `support` em `$fillable`. Esse flag causa bypass **total** de RBAC via `Gate::before` (`erp-novo/app/Providers/AuthServiceProvider.php:28`, confirmado em `User.php:132`). A auditoria verificou os pontos de escrita atuais (`UsuarioController::store` `:63-75` e `update` passam arrays explícitos; nenhum `$request->all()` alimenta `User::create/update` nos controllers admin) e **não** encontrou vetor explorável hoje — é armadilha para código futuro.

**Passos.**
1. Remover `'support'` do `$fillable` em `User.php:22-30`.
2. Nos seeders que legitimamente precisam setá-lo (`DeployAdminSeeder.php`, `AcessoMigracaoSeeder.php`), usar atribuição explícita: `$user->support = true; $user->save();` — ou `forceFill`.
3. Rodar a suíte: qualquer teste que crie um usuário support por mass-assign vai quebrar e mostrar os pontos a ajustar.

**Pronto quando (binário).**
```bash
grep -n "'support'" erp-novo/app/Models/User.php
# NÃO deve aparecer dentro do array $fillable
cd erp-novo && php artisan test
# DEVE passar
```

---

### T1.9 — Restringir e rotacionar a chave do Google Maps nos apps — **[DESEJÁVEL]**

**Achado que justifica.** Auditoria §8 M1. `app-entregador/eas.json:16` e `app-gas-em-casa/eas.json:16` têm `GOOGLE_MAPS_API_KEY` com valor literal (chave `AIzaSy…`) **commitado**. O mesmo padrão existe no legado em `ctrl-web/app/Api/Http/Controllers/PedidoController.php:209` — é a origem do hábito copiado.

**Passos.**
1. Rotacionar a chave no console Google (a atual está no histórico do git — considere-a queimada).
2. No console Google, aplicar restrições de aplicativo: package name + SHA-1 (Android) e bundle ID (iOS), e restringir por API (Maps SDK, Geocoding).
3. Nos dois `eas.json`, substituir o literal por referência a secret do EAS (`"GOOGLE_MAPS_API_KEY": "$GOOGLE_MAPS_API_KEY"` com o secret cadastrado via `eas secret:create`).
4. **Não** tocar em `ctrl-web/` (legado será aposentado no cutover).

**Pronto quando (binário).**
```bash
grep -rn "AIzaSy" app-entregador/ app-gas-em-casa/ --include=*.json --include=*.ts --include=*.tsx
# DEVE retornar 0 linhas
eas secret:list   # em cada app — GOOGLE_MAPS_API_KEY presente
```

---

### O QUE NÃO FAZER na F1

- ❌ **Não** apagar `DeployAdminSeeder`/`SuperAdminSeeder`. O sistema precisa de uma conta administrativa inicial; o problema é a senha default, não a existência da conta.
- ❌ **Não** remover o flag `support` do sistema. Ele é o mecanismo legítimo de suporte (`Gate::before` em `AuthServiceProvider.php:31-33`), auditado à parte no login (`AuthController.php:87-89`). Só sai do `$fillable`.
- ❌ **Não** deletar os drivers `Fake*`. Eles são necessários no CI e em homolog (a auditoria confirma que os workflows dependem deles). O que muda é o comportamento **em produção**.
- ❌ **Não** aplicar `throttle` ao webhook PIX (`routes/api.php:82`). O PSP chama de fora com volume legítimo e a segurança já é tripla (segredo compartilhado + HMAC por empresa + validação de estado) — throttle ali derruba confirmação de pagamento.
- ❌ **Não** mexer no `PixWebhookController`, nas policies de RLS, nos canais de broadcast (`routes/channels.php`) nem no `AppRole` middleware. A auditoria verificou os quatro como **corretos**; alterar aqui só cria risco.
- ❌ **Não** rodar `composer update` sem argumentos (só os 3 pacotes com CVE) — um update global reintroduz risco de regressão fora do escopo.

---

## MARCO DE VALIDAÇÃO M1 (antes de avançar para F2)

```bash
# 1) Suíte completa verde (sqlite e Postgres)
cd erp-novo && php artisan test
cd erp-novo/frontend && npm run type-check && npm test && npm run build

# 2) Zero credencial literal no código
grep -rn "admin1234\|superadmin1234\|superadmin123\|entregador123\|dubena@2026\|operador@2026" \
  erp-novo/database/seeders/ erp-novo/frontend/src/ app-entregador/src/
# → 0 linhas

# 3) Zero CVE
cd erp-novo && composer audit && cd frontend && npm audit --omit=dev
# → ambos limpos

# 4) Fail-close comprovado
APP_ENV=production FIREBASE_DRIVER= php artisan tinker \
  --execute='app(\App\Domain\Mobile\Contracts\FirebaseVerifier::class);'
# → RuntimeException

# 5) Throttle nas 3 rotas
php artisan route:list --path=app/v1 --json | grep -c 'throttle'
# → as 3 rotas públicas cobertas
```
**Não avance para F2 sem os 5 verdes.**

---

## FASE F2 — Corrigir os dados

**Objetivo.** Levar `php artisan cutover:check` de **62 OK / 9 falhas** para **N OK / 0 falhas**, com a duplicação de clientes desfeita e as FKs remapeadas. O portão do cutover só é útil se for confiável — hoje ele mistura falhas reais com falhas por desenho da invariante, e foi por isso que a corrupção passou.

**Contexto que o agente precisa saber.** O ETL vive em `erp-novo/app/Etl/`. Os diretórios `Loaders/`, `Readers/`, `Transformers/` são cascas vazias (só `.gitkeep`) — o pipeline real é `MigratorRegistry` → 28 classes `*Migrator` monolíticas em `app/Etl/Migrators/`. A ordenação é um **topological sort real** sobre `Migrator::dependeDe()` (`MigratorRegistry.php:120-154`), com detecção de ciclo — isso está correto. O portão é `app/Console/Commands/CutoverCheck.php:20-57`, que roda todas as invariantes **sem re-migrar** (read-only, nunca chama `migrar()`).

**Ambiente de trabalho.** Os três bancos de origem estavam de pé em Docker local na auditoria: `dubena-ora2` (Oracle XE 11, snapshot CTRL2QTI, porta 51521), `dubena-mysql` (`sgcm_api` + `monitora`, 53306), `dubena-pg` (`erp_novo`, schemas `public` + `legado`, 55432). A conexão `legado` **não é o Oracle** — é o próprio Postgres apontando para o schema `legado`, materializado pelo espelho `erp-novo/database/etl/espelhar_oracle.py`. Origem e destino são o mesmo banco físico, separados por schema.

**Aviso.** Toda tarefa desta fase é sobre o **ambiente local**. A produção (VPS `gasemcasa.com`) **não foi verificada** pela auditoria — a T2.8 existe justamente para descobrir se a produção tem o mesmo problema.

---

### T2.1 — Tornar `AppGasEmCasaMigrator` idempotente (causa da duplicação 4×) — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §5 A-1, o achado mais grave da auditoria de dados. Números medidos:
```sql
SELECT count(*) AS linhas_app,
       count(DISTINCT substring(observacoes from 'id de origem: ([0-9]+)')) AS api_ids
FROM public.clientes WHERE observacoes LIKE 'Cadastro originado do app%';
--  linhas_app = 44416   |   api_ids = 11104     (11.104 × 4 = 44.416, todos exatamente 4×)
```

**Causa-raiz no código, verificada.** `erp-novo/app/Etl/Migrators/AppGasEmCasaMigrator.php:167-246` cria como cliente do ERP cada usuário do app sem par. A dedup depende de `$this->mapaClientes` (linha **187**: `if (isset($this->mapaClientes[$apiId])) continue;`). Mas `montarCorrelacoes()` (`AppGasEmCasaMigrator.php:477-500`) popula esse mapa **exclusivamente** de `$legado->table('clientes')->whereNotNull('api_id')->pluck('id','api_id')` — a ponte gravada pelo ERP **legado**. Ele **nunca** consulta os clientes que o próprio migrator criou em execuções anteriores. Como o id novo vem de `$proximoId = (int) DB::table('clientes')->max('id')` (linha **182**) e é incrementado (linha **191**), cada re-execução escolhe uma faixa de ids **nova**, e o `upsert` por `id` de `PreservaIdsDoLegado.php:47` (`$conexao->table($tabela)->upsert($bloco, $chave)`) **insere** em vez de atualizar.

O trait `PreservaIdsDoLegado` está correto — a idempotência que ele promete (`PreservaIdsDoLegado.php:16-18`) vale só quando a chave vem da origem. Aqui a chave é sintética e móvel. **O trait não é o defeito; o uso que o migrator faz dele é.**

**Passos.**
1. Adicionar ao destino uma **coluna-ponte persistida**: migration nova criando `public.clientes.api_id` (nullable, indexado, UNIQUE por `(empresa_id, api_id)`), com comentário explicando que é o id de origem em `sgcm_api.clienteimportacoes`. Isso substitui o parsing frágil de `observacoes LIKE 'Cadastro originado do app%'`.
2. Em `montarCorrelacoes()` (`:477-500`), **unir duas fontes** no `$this->mapaClientes`: (a) a ponte do legado que já existe, e (b) `DB::table('clientes')->whereNotNull('api_id')->pluck('id','api_id')` — os clientes que o próprio migrator criou antes.
3. Trocar a gravação em `:221` para `upsert` pela chave natural `['empresa_id','api_id']` em vez de `['id']` — assim uma segunda execução **atualiza** em vez de inserir.
4. **Mesmo tratamento em telefones**: o bloco `:236-243` replica o `max(id)+1`. Confirmado o efeito colateral: `SELECT count(*), count(DISTINCT telefone) FROM public.clientetelefones WHERE cliente_id>101122` → 40.656 linhas / 10.150 telefones distintos. Aplique chave natural equivalente (ex.: `(cliente_id, telefone)`).
5. Preencher retroativamente `clientes.api_id` a partir de `observacoes` na mesma migration (ver T2.2, que depende disso).

**Pronto quando (binário).**
```bash
cd erp-novo && php artisan etl:run AppGasEmCasaMigrator
psql -c "SELECT count(*) FROM public.clientes;"   # anote N
cd erp-novo && php artisan etl:run AppGasEmCasaMigrator   # 2ª vez
psql -c "SELECT count(*) FROM public.clientes;"   # DEVE ser exatamente N
```
Query de prova de não-duplicação (deve retornar **0 linhas**):
```sql
SELECT api_id, count(*) FROM public.clientes
WHERE api_id IS NOT NULL GROUP BY api_id HAVING count(*) > 1;
```

---

### T2.2 — Deduplicar os 44.416 clientes com remapeamento de FK — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §5 A-1, impacto de negócio. A base de clientes do app está inflada 4×. Um cliente que ligar aparece em 4 cadastros; histórico, crédito e convênio ficam repartidos entre eles. **430 pedidos já apontam para as cópias** (`SELECT count(*) FROM public.pedidos WHERE cliente_id>101122` → 430). Por isso **deduplicar exige remapear FKs, não apagar**.

Referência de faixa: o Oracle tem `MIN(ID)=2, MAX(ID)=101.122` em `CLIENTES`, e `public.clientes` tem `min(id)=2` — os ids do legado foram preservados exatamente (Auditoria §5 §4.6, verificação empírica). As 44.416 linhas do problema estão **todas acima de 101.122**, sem colidir com a faixa legada. Isso torna a faixa um discriminador seguro.

**Passos.** Escrever um comando artisan novo, ex.: `erp-novo/app/Console/Commands/DedupClientesApp.php` (`dados:dedup-clientes-app`), com `--dry-run` obrigatório por default:
1. **Eleger o sobrevivente** de cada grupo `api_id`: o de **menor id** (é o da primeira execução do ETL, o mais antigo, e o mais provável de já ter FKs apontando).
2. **Levantar todas as tabelas que referenciam `clientes.id`** — não presuma; consulte o catálogo:
   ```sql
   SELECT tc.table_name, kcu.column_name FROM information_schema.table_constraints tc
   JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
   JOIN information_schema.constraint_column_usage ccu ON tc.constraint_name = ccu.constraint_name
   WHERE tc.constraint_type='FOREIGN KEY' AND ccu.table_name='clientes';
   ```
   Esperado, entre outras: `pedidos.cliente_id`, `clientetelefones.cliente_id`, `cliente_enderecos.cliente_id`, `financeiros`, `comodatos`, `vale_gas`, `pos_vendas`.
3. **Remapear** em transação única: para cada tabela/coluna do passo 2, `UPDATE ... SET cliente_id = <sobrevivente> WHERE cliente_id IN (<duplicatas>)`.
4. **Fundir dados divergentes antes de apagar**: se as cópias tiverem telefones/endereços distintos, mover para o sobrevivente e deduplicar por chave natural. **Não perca dado** — se houver conflito irreconciliável, gravar num CSV de descartes e abortar.
5. **Só então** `DELETE FROM clientes WHERE id IN (<duplicatas>)`.
6. Ressincronizar a sequence via o mesmo mecanismo de `PreservaIdsDoLegado.php:106-118` (`setval(pg_get_serial_sequence(...), COALESCE(MAX(id),1))`).
7. Rodar em `--dry-run` primeiro, conferir os totais no relatório, e só depois executar de verdade.

**Pronto quando (binário).**
```sql
-- 1) zero grupos duplicados
SELECT api_id, count(*) FROM public.clientes WHERE api_id IS NOT NULL
GROUP BY api_id HAVING count(*) > 1;                       -- 0 linhas

-- 2) contagem bate com os distintos originais
SELECT count(*) FROM public.clientes;                       -- 88765 - 33312 = 55453
-- (44416 linhas → 11104 sobreviventes ⇒ remove 33.312)

-- 3) ZERO órfão após o remapeamento
SELECT count(*) FROM public.pedidos p LEFT JOIN public.clientes c ON c.id=p.cliente_id
WHERE p.cliente_id IS NOT NULL AND c.id IS NULL;            -- 0
SELECT count(*) FROM public.clientetelefones t LEFT JOIN public.clientes c ON c.id=t.cliente_id
WHERE c.id IS NULL;                                          -- 0

-- 4) os 430 pedidos continuam existindo, agora apontando para sobreviventes
SELECT count(*) FROM public.pedidos WHERE cliente_id > 101122;  -- ≤ 430, todos com cliente válido
```
E `php artisan cutover:check` deixa de listar `clientes` e `clientetelefones` nas falhas.

---

### T2.3 — Corrigir `PagamentoMigrator` (tabelas de origem inventadas) — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §5 A-3 + §4.7 item 4 (resolvido na crítica). Duas falhas do `cutover:check`:
```
[FALHA] contagem cartaotransacoes→cartao_transacoes       — origem `cartaotransacoes` NÃO existe
[FALHA] contagem gasdopovobeneficios→gasdopovo_beneficios — origem `gasdopovobeneficios` NÃO existe
```
`erp-novo/app/Etl/Migrators/PagamentoMigrator.php` lê nomes de tabela que **nunca existiram** no Oracle. A crítica resolveu onde os dados realmente estão: a query `SELECT table_name FROM user_tables WHERE table_name LIKE '%CARTAO%' OR '%GASDOPOVO%' OR '%TRANSAC%' OR '%BENEF%'` no Oracle retorna **`BENEFICIARIOS`** e **`PIXTRANSACTIONS`**. Ou seja: não é "origem ausente", é **migrator escrito contra um schema imaginado**. Nenhuma das grafias corretas está no `MAPA` de `erp-novo/database/etl/espelhar_oracle.py` (confirmado), então o espelho também não as trouxe. Hoje `PagamentoMigrator` "roda com sucesso" e migra **zero linhas** — a única razão de isso ser visível é a checagem `hasTable` de `CountInvariant.php:47-55`.

**Passos.**
1. No Oracle, inspecionar a estrutura real: `DESC BENEFICIARIOS` e `DESC PIXTRANSACTIONS`, mais `SELECT COUNT(*)` de cada. **Se alguma vier vazia, pare e documente** — pode ser que o módulo simplesmente não existisse no legado, e nesse caso a correção é remover o migrator, não reescrevê-lo.
2. Adicionar as duas tabelas ao `MAPA` de `espelhar_oracle.py` (o arquivo tem 427 linhas; a fronteira "── Ampliacao pos-auditoria ──" está na linha **92** — coloque as novas entradas ali).
3. Re-rodar o espelho e confirmar que `legado.beneficiarios` e `legado.pixtransactions` existem com as contagens do Oracle.
4. Reescrever o mapeamento em `PagamentoMigrator.php` contra as colunas **reais** dessas tabelas. Atenção: `PIXTRANSACTIONS` pode se sobrepor a `pix_cobrancas` do domínio novo (`erp-novo/app/Domain/Cobranca/PixService.php`) — decida explicitamente qual tabela de destino recebe o quê e registre a decisão em comentário no migrator.
5. Ajustar as `invariantes()` do migrator para citar os nomes corretos.

**Pronto quando (binário).**
```bash
cd erp-novo && php artisan cutover:check 2>&1 | grep -E "cartaotransacoes|gasdopovobeneficios"
# → 0 linhas (as falhas desaparecem do placar)
psql -c "SELECT count(*) FROM public.cartao_transacoes; SELECT count(*) FROM public.gasdopovo_beneficios;"
# → contagens > 0 e IGUAIS às do Oracle (ou 0 documentado com justificativa no migrator)
```

---

### T2.4 — Dar à `CountInvariant` o conceito de "acréscimo legítimo" — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §5 A-2, e é o achado que explica **por que a corrupção passou despercebida**. `public.pedidos` = 402.476 vs 400.070 no Oracle; 56.130 linhas têm `id > 400070` (faixa criada por `criarPedidosRecentesDoApp()`, `AppGasEmCasaMigrator.php:252-258`). Contra 61.502 pedidos em `sgcm_api.pedidos`, os números são **plausíveis** — a lógica funcionou; o excedente é por desenho (pedidos do app posteriores ao corte do dump, correlacionados por `apipedido_id`). **Mas o `CountInvariant` não sabe disso e falha.**

Defeito estrutural: `erp-novo/app/Etl/Invariants/CountInvariant.php:19` aceita `descartesEsperados` mas **não tem o conceito simétrico de acréscimos**. Migrators que criam linhas de uma segunda origem são estruturalmente incapazes de passar. Resultado: falhas legítimas (a duplicação da T2.2) e falhas por desenho ficam indistinguíveis no mesmo placar vermelho — foi exatamente assim que a real virou ruído.

**Passos.**
1. Em `CountInvariant.php`, adicionar um parâmetro `acrescimosEsperados` — mas **não** como número mágico. Aceite um `Closure|int`: quando closure, ela **calcula** o acréscimo consultando a segunda origem (ex.: `fn() => DB::connection('app_legado')->table('pedidos')->where('created_at','>',$corte)->count()`). Um número fixo vira mentira na próxima recarga.
2. A fórmula passa a ser: `count(origem) - descartes + acrescimos == count(destino)`.
3. **Não relaxe a checagem de `hasTable`** (`CountInvariant.php:38-55`) — ela é a correção mais valiosa já feita neste pipeline: conexão ausente = skip (dev/CI), conexão presente com tabela ausente = **FALHA**. Foi ela que pegou a T2.3.
4. Declarar `acrescimosEsperados` nos migrators afetados: `pedidos` (via `AppGasEmCasaMigrator`) e `empresas`/`cidades` (só depois da T2.5 explicar a causa — não declare acréscimo para esconder divergência não compreendida).
5. Fazer o mesmo em `SumInvariant.php` se ele tiver o mesmo assimetria (o espelho da lógica está em `SumInvariant.php:39-53`).

**Pronto quando (binário).**
```bash
cd erp-novo && php artisan test --filter=CountInvariant
# teste novo: origem=100, descartes=5, acrescimos=10, destino=105 → OK
# e: origem=100, acrescimos=10, destino=200 → FALHA (não vira passe-livre)
cd erp-novo && php artisan cutover:check 2>&1 | grep "contagem pedidos"
# → 0 linhas
```

---

### T2.5 — Explicar e resolver as divergências pequenas — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §5 A-5 e §4.7 item 3. Quatro divergências confirmadas em número mas **sem causa isolada**:

| Par | Origem | Destino | Delta |
|---|---|---|---|
| `empresas` → `empresas` | 7 | 10 | **+3** |
| `cidades` → `cidades` | 106 | 105 | **−1** |
| `boletos` → `boletos` | 21.544 | 21.135 | **−409** |
| `nfemitidas` → `notas_fiscais` | 241.024 | 241.021 | **−3** |
| `nfemitidaitems` → `nota_itens` | 254.308 | 254.305 | −3 (coerente com o acima) |

A auditoria é explícita: "409 boletos é volume demais para ser acidente sem explicação. Não localizei no código a regra de descarte que produz esses números específicos, e nenhum `descartesEsperados` está declarado para eles. **Nota fiscal e boleto são documentos com valor legal — perda silenciosa aqui é risco fiscal, não estético.**"

**Passos.**
1. Para cada par, isolar **as linhas** com `EXCEPT` por id:
   ```sql
   SELECT id FROM legado.boletos EXCEPT SELECT id FROM public.boletos;      -- os 409
   SELECT id FROM legado.nfemitidas EXCEPT SELECT id FROM public.notas_fiscais;  -- os 3
   SELECT id FROM public.empresas EXCEPT SELECT id FROM legado.empresas;    -- os +3
   SELECT id FROM legado.cidades EXCEPT SELECT id FROM public.cidades;      -- a 1
   ```
2. Amostrar 10 das 409 e ler os campos — procure o padrão (situação cancelada? banco não suportado? FK nula anulada por `anularFksInvalidas`, `PreservaIdsDoLegado.php:68-103`?).
3. **Decidir e registrar** cada caso em uma das três categorias, e implementar de acordo:
   - **Descarte legítimo** → declarar `descartesEsperados` na invariante do migrator, **com comentário citando a regra**.
   - **Acréscimo legítimo** (provável para `empresas` +3: empresas criadas no novo) → `acrescimosEsperados` da T2.4.
   - **Perda real** → corrigir o migrator e recarregar. Para boleto e NF, esta é a hipótese default até prova em contrário.
4. Não feche esta tarefa com "provavelmente é X". Cada um dos 4 precisa de causa escrita.

**Pronto quando (binário).**
```bash
cd erp-novo && php artisan cutover:check 2>&1 | grep -E "empresas|cidades|boletos|nfemitidas"
# → 0 linhas
```
E existe um comentário no migrator correspondente explicando cada delta, citando a query `EXCEPT` que o comprovou.

---

### T2.6 — Eliminar os `catch (\Throwable)` silenciosos do ETL — **[IMPORTANTE]**

**Achado que justifica.** Auditoria §5 A-4. **68 blocos** `catch (\Throwable)` em 28 arquivos de migrator (só o `AppGasEmCasaMigrator` tem 15). O padrão quase universal é engolir a exceção e devolver vazio/falso, **sem log e sem contabilizar o erro**:

| Arquivo:linha | Corpo |
|---|---|
| `ClientesMigrator.php:159-161` | `return [];` |
| `ClientesMigrator.php:217-219` | `return [];` |
| `AppGasEmCasaMigrator.php:172-174` | `return 0;` |
| `AppGasEmCasaMigrator.php:487-489` | `$this->mapaClientes = [];` |
| `AppGasEmCasaMigrator.php:618-619` | `{ }` — **corpo vazio** |
| `AppGasEmCasaMigrator.php:625-626` | `{ }` — **corpo vazio** |
| `GestaoMigrator.php:120-122` | `return [];` |
| `CobrancaMigrator.php:343-345` | `return false;` |

O mais grave é **`AppGasEmCasaMigrator.php:487-489`**: se a leitura da ponte `api_id` falhar por qualquer motivo, `mapaClientes` vira `[]` — e o migrator passa a considerar **todos** os clientes do app como "sem par no ERP", recriando a base inteira. **É o mecanismo da T2.1/T2.2 na sua forma mais destrutiva, armado por uma exceção engolida.** Atenuante honesto: `MigrationResult` já tem um campo `avisos` (`erp-novo/app/Etl/Support/MigrationResult.php:15`) que esses catches **não usam** — a informação existe e é jogada fora.

**Passos.**
1. **Prioridade absoluta:** `AppGasEmCasaMigrator.php:481-489`. A falha de leitura da ponte deve ser **fatal** (re-lançar), não silenciosa. Um mapa vazio ali não é degradação — é gatilho de corrupção.
2. Para os demais 67: cada `catch (\Throwable $e)` passa a (a) logar com `Log::warning` incluindo migrator, tabela e mensagem, e (b) empilhar em `MigrationResult::$avisos`.
3. Distinguir os dois casos legítimos dos ilegítimos: em `ClientesMigrator.php:157-161` o catch protege contra tabela ausente no legado — **isso é aceitável** (as invariantes pegam via `hasTable`, a T2.3 é a prova). Converta em catch tipado (`QueryException` com checagem de "relation does not exist") em vez de `\Throwable` genérico.
4. Fazer o `EtlRun` (`erp-novo/app/Console/Commands/EtlRun.php:27-75`) imprimir os `avisos` acumulados ao final e retornar código de saída ≠ 0 se houver aviso categoria "origem indisponível".

**Pronto quando (binário).**
```bash
grep -rn "catch (\\\\Throwable)" erp-novo/app/Etl/Migrators/ | wc -l
# DEVE ser 0 (todos tipados ou com $e nomeado e logado)
grep -rn -A2 "catch (\\\\Throwable" erp-novo/app/Etl/Migrators/ | grep -c "^\s*}\s*$"
# → 0 corpos vazios
cd erp-novo && php artisan etl:run --dry-run 2>&1 | tail -20
# imprime bloco "Avisos:" (mesmo que vazio)
```

---

### T2.7 — Decidir o destino das 15 tabelas confirmadas sem migração — **[BLOQUEANTE — elevado após verificação de 15/08]**

> **⚠️ ESTA TAREFA JÁ FOI PARCIALMENTE EXECUTADA.** A auditoria exploratória foi feita e o resultado está em `AUDITORIA.md` §6. **Não refaça o levantamento** — parta do resultado.
>
> **Números reais (verificados no Oracle e no Postgres):** 222 tabelas Oracle, 121 espelhadas, **109 fora** (não 101 — o número anterior estava desatualizado), das quais **71 têm dados** e **15 não têm nenhum destino no sistema novo**, somando **56.395 linhas** de dado de negócio.
>
> **As 15:** `LOGCERCAS` (39.929), `LIGACOESTELEFONICAS` (13.214), `COLABORADORCOMISSAOS` (872), `NFRECEBIDAPARCELAS` (862), `BENEFICIARIOS` (480), `CREDITOPISCOFINS` (246), `ESTOQUEFISICOSETORS` (218), `CLIENTEPRODUTOSCONVENIOS` (135), `ESTOQUEREQUISICAOS` (109), `ESTOQUEREQUISICAOITEMS` (109), `CLIENTECONVENIOS` (97), `COLABORADORS` (81), `APPNOTIFICATIONS` (67), `CONTAEXTRATOCONFIGS` (16), `ESTOQUEPRODUTOS` (12).
>
> **O que esta tarefa passa a ser:** para cada uma das 15, registrar uma decisão explícita — **portar** (criar tabela + migrator + invariante), **aposentar** (documentar que o negócio abandona o dado) ou **arquivar** (manter só como histórico consultável). Nenhuma pode ficar sem decisão registrada; foi o implícito que criou este buraco.
>
> **Ordem por risco:** `CREDITOPISCOFINS` + `NFRECEBIDAPARCELAS` (fiscal/tributário) → `COLABORADORS` + `COLABORADORCOMISSAOS` (RH/folha) → `BENEFICIARIOS` (fecha junto com a T2.3) → `CONTAEXTRATOCONFIGS` (fecha a lacuna funcional da matriz, linha 11) → estoque/convênios → `LOGCERCAS`/`LIGACOESTELEFONICAS`/`APPNOTIFICATIONS` (histórico; provavelmente arquivar).
>
> **Pronto quando (binário).** Existe um documento com 15 linhas, uma por tabela, cada uma com a decisão tomada e — nas que forem "portar" — o migrator correspondente registrado no `MigratorRegistry` com invariante declarada.

**Contexto original da tarefa (mantido para referência):**

**Achado que justifica.** Auditoria §5 §4.2.3 e §4.7 item 2. Contagem exata: o `MAPA` de `erp-novo/database/etl/espelhar_oracle.py` tem **121 entradas / 121 destinos únicos**; o Oracle real tem **222 tabelas** (`SELECT COUNT(*) FROM user_tables` = 222). Confirmado no destino: `information_schema.tables` com `table_schema='legado'` = 121. Ou seja: **101 tabelas Oracle seguem fora do espelho** e, para elas, nenhum migrator pode nem sequer tentar ler.

O número "43 de 200" de documentos históricos está desatualizado (o espelho foi ampliado — a fronteira está em `espelhar_oracle.py:92`), mas o risco de fundo persiste. A auditoria classifica isto como **"a lacuna mais provável de esconder módulos vazios adicionais"** — e a T2.3 é a prova de que o padrão existe.

**Passos.**
1. No Oracle: `SELECT table_name, num_rows FROM user_tables ORDER BY num_rows DESC;`
2. Subtrair as 121 do `MAPA` de `espelhar_oracle.py` → lista das 101.
3. **Filtrar por relevância**: descartar as com `num_rows = 0` (mas confirme com `COUNT(*)` real — `num_rows` é estatística e pode estar velha).
4. Para cada tabela com dados, cruzar com a matriz de paridade (Auditoria §2) e responder por escrito: **este dado é necessário para operar?** Tabelas de log/auditoria antiga do legado provavelmente não; tabelas de negócio sim.
5. Adicionar ao `MAPA` as que forem necessárias, e escrever/ajustar migrator para elas.
6. **Registrar as descartadas** num comentário-bloco no `espelhar_oracle.py`, com a razão. A ausência precisa ser uma decisão documentada, não um esquecimento.

**Pronto quando (binário).**
```bash
# existe um arquivo de decisão versionado listando as 101 com veredito
# e a soma bate:
python -c "import re;print(len(re.findall(r'...', open('erp-novo/database/etl/espelhar_oracle.py').read())))"
# entradas no MAPA == 121 + (as promovidas), e o comentário-bloco cobre 101 - (promovidas)
```
Definição binária: **zero** tabelas Oracle com `COUNT(*) > 0` fora do MAPA **sem** uma linha de justificativa escrita.

---

### T2.8 — Verificar se a produção tem a mesma corrupção — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §5 §4.7 item 1, textual: *"Produção (VPS `gasemcasa.com`). Instruído a não acessar. Tudo aqui é o ambiente local Docker do desenvolvedor. **Não sei se a produção tem a mesma duplicação 4× do A-1** — plausível se o ETL rodou lá o mesmo número de vezes, mas é conjectura. Para verificar: rodar `php artisan cutover:check` na VPS e a query de histograma do A-1."*

Esta tarefa fecha o único buraco de conhecimento que pode invalidar toda a F2.

**Passos.**
1. Obter autorização explícita do dono antes de acessar a VPS. Acesso via **paramiko** (não `ssh` nativo) — é o procedimento estabelecido para este ambiente.
2. Rodar, em modo **estritamente read-only**:
   ```bash
   php artisan cutover:check      # read-only por construção: CutoverCheck.php nunca chama migrar()
   ```
3. Rodar o histograma do A-1 contra o banco de produção:
   ```sql
   SELECT vezes, count(*) FROM (
     SELECT substring(observacoes from 'id de origem: ([0-9]+)') a, count(*) vezes
     FROM public.clientes WHERE observacoes LIKE 'Cadastro originado do app%' GROUP BY 1) t
   GROUP BY 1;
   ```
4. Comparar com o local (11.104 api_ids × 4). Se a produção mostrar N× com N ≠ 1, a T2.2 precisa rodar lá também — **depois** do backup da T3.4.
5. Registrar o resultado. Se a produção estiver limpa, isso muda o plano da janela de cutover (T6.x).

**Pronto quando (binário).** Existe um registro escrito com: o número de falhas do `cutover:check` de produção, e o histograma. **Sem `SELECT`? Não está pronto.** Nenhuma escrita foi feita na produção nesta tarefa.

---

### O QUE NÃO FAZER na F2

- ❌ **Não** apague as duplicatas com um `DELETE` direto. **430 pedidos apontam para as cópias** — apagar antes de remapear cria órfãos onde hoje há **zero** (a auditoria verificou: `pedidoitens` sem pedido = 0, `financeiroparcelas` sem título = 0, `clientetelefones` sem cliente = 0, `cliente_enderecos` sem cliente = 0, `pedidos` com `cliente_id` inexistente = 0; e as 15 `IntegrityInvariant` passaram todas). Não estrague o único aspecto impecável da migração.
- ❌ **Não** relaxe nem remova a checagem `hasTable` de `CountInvariant.php:38-55` para "passar" as falhas de `cartaotransacoes`/`gasdopovobeneficios`. Ela é a razão pela qual esses migrators quebrados são visíveis; sem ela seriam mais dois "módulos vazios com sucesso".
- ❌ **Não** declare `descartesEsperados` ou `acrescimosEsperados` sem ter **isolado a linha de dado** que os produz. Declarar número para calar o placar é reproduzir a falha de processo que a auditoria diagnosticou.
- ❌ **Não** mexa em `PreservaIdsDoLegado.php` (linhas 47, 29, 106-118). A auditoria verificou o mecanismo como **bem projetado** (Query Builder em vez de Eloquent porque `id` não está em `$fillable`; upsert por `['id']`; `setval(pg_get_serial_sequence(...))`). O defeito é o **uso** que o `AppGasEmCasaMigrator` faz dele.
- ❌ **Não** mexa no topological sort de `MigratorRegistry.php:120-154` nem reverta as duas correções visíveis e confirmadas: `UsersMigrator` antes de pedidos (`:51-53` — `pedidos.atendente_id/entregador_id` referenciam `user_id` do legado) e a remoção do `MonitoraMigrator` morto (`:97-100`).
- ❌ **Não** escreva nada na produção na T2.8. É levantamento, não correção.
- ❌ **Não** aponte `BalanceInvariant` para `contamovimentos` (410.417 linhas): `BalanceInvariant.php:52-57` faz **uma query por chave** dentro do loop (N+1). Em estoque com 115 saldos é irrelevante; ali trava.

---

## MARCO DE VALIDAÇÃO M2 (antes de avançar para F3)

```bash
# 1) O PORTÃO VERDE — o critério central da fase
cd erp-novo && php artisan cutover:check
# DEVE terminar com "0 falha(s)" e exit code 0
echo $?   # → 0

# 2) Idempotência provada: rodar o ETL duas vezes não muda contagem
cd erp-novo && php artisan etl:run --check && \
  psql -Atc "SELECT count(*) FROM public.clientes" > /tmp/c1 && \
  php artisan etl:run --check && \
  psql -Atc "SELECT count(*) FROM public.clientes" > /tmp/c2 && diff /tmp/c1 /tmp/c2
# → diff vazio

# 3) Zero duplicata e zero órfão
psql -c "SELECT api_id,count(*) FROM public.clientes WHERE api_id IS NOT NULL
         GROUP BY api_id HAVING count(*)>1;"     # 0 linhas
psql -c "SELECT count(*) FROM public.pedidos p LEFT JOIN public.clientes c
         ON c.id=p.cliente_id WHERE p.cliente_id IS NOT NULL AND c.id IS NULL;"  # 0

# 4) Núcleo financeiro intacto (era o ponto mais forte da migração — não regrida)
psql -Atc "SELECT round(sum(valor)::numeric,2) FROM public.financeiros;"
# → 250029904.80  (idêntico a legado.financeiros)

# 5) Produção diagnosticada (T2.8) — registro escrito existe
```
**Não avance para F3 sem os 5 verdes.** O item 1 é o portão literal do cutover.

---

## FASE F3 — Infraestrutura de produção

**Objetivo.** Criar o que **não existe no repositório**: template de `.env` de produção, compose e workflow de produção, backup/restore/rollback, proxy do Reverb e observabilidade mínima. Hoje o único deploy do `erp-novo` é o de homologação.

**Contexto que o agente precisa saber.** O `docker-compose.homolog.yml` já materializa a topologia completa em 7 containers: `app` (php-fpm), `web` (nginx :3120), `queue`, `scheduler`, `reverb` (:3121), `db` (postgres:15-alpine), `redis`. O oitavo bloco (`erpnovo`, linha 154) é a **rede**, não um container. O workflow `.github/workflows/deploy-erp-novo-homolog.yml` roda num runner **self-hosted na própria VPS**, restaura o `.env` de `/opt/dubena-env/erp-novo-homolog.env`, builda a SPA no deploy via `node:20-alpine` (linhas 67-74 — `public/app` não é mais commitado), roda `migrate --force --database=pgsql_owner` (linha 160) e seeds. `deploy/nginx/gasemcasa-com.conf` já roteia `/` e `/novo/` para o container novo (:3120) e o resto para o legado (:3110) — a coexistência strangler está **desenhada no repo**; a aplicação real na VPS é NÃO VERIFICADA.

---

### T3.1 — Criar `.env.production.example` completo — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §9 §7.1.1 e §7.1.2. O repo tem `erp-novo/.env.example` (dev: sqlite, debug on) e `erp-novo/.env.homolog.example` (60 linhas, só app/db/redis/sanctum). **Não existe** `.env.production.example`. O template de homolog **omite chaves que o código usa**: `BROADCAST_CONNECTION`/`REVERB_*` (sem elas o broadcast cai no default `log` — `config/broadcasting.php:16`), `MAIL_*` (default `log` — `config/mail.php:17`), `FIREBASE_*`, `FCM_DRIVER`, `GOOGLE_MAPS_KEY`, `PIX_*`, `FISCAL_DRIVER`, `COBRANCA_DRIVER`, `EREDE_*`, `SGCASA_*`, `IBPT_CSV_URL`, `SANCTUM_EXPIRATION`, `SESSION_SECURE_COOKIE`, `ADMIN_SEED_*`.

Chaves usadas pelo código e **não documentadas em nenhum example** (levantadas por varredura de `env()`):

| Chave | Onde | Nota |
|---|---|---|
| `FIREBASE_DRIVER`, `FIREBASE_CREDENTIALS`, `FIREBASE_PROJECT_ID` | `config/services.php` | Só em comentário no `.env.example:114` |
| `PIX_PSP`, `PIX_AMBIENTE`, `PIX_WEBHOOK_SECRET` | `config/services.php` | `PIX_WEBHOOK_SECRET` ≠ `PIX_WEBHOOK_HMAC_SECRET` — **dois segredos distintos** |
| `APP_LEGADO_DB_*` | `config/database.php:153-159` | default `root`/senha vazia |
| `MONITORA_LEGADO_DB_*` | `config/database.php:171-177` | idem |
| `LEGADO_DB_SCHEMA` | `config/database.php:143` | default `public` (**errado** para o ETL — precisa ser `legado`) |
| `ADMIN_SEED_EMAIL/PASSWORD` | `DeployAdminSeeder.php:35-36` | ver T1.1 |
| `SUPERADMIN_SEED_EMAIL/PASSWORD` | `SuperAdminSeeder.php:20-21` | comentadas no `.env.example:134-135` |
| `SESSION_SECURE_COOKIE` | `config/session.php:172` | sem default (null) — em HTTPS deve ser `true` |
| `DB_OWNER_URL`, `LEGADO_DB_CHARSET`, `QUEUE_FAILED_DRIVER`, `SANCTUM_TOKEN_PREFIX` | vários | defaults funcionais, impacto baixo |
| `OPERADOR_SEED_PASSWORD`, `DONO_SEED_PASSWORD`, `GERENTE_SEED_PASSWORD` | `AcessoMigracaoSeeder.php:40-42`, `AcessoRedeDubenaSeeder.php:52,79` | seeds de acesso |
| `GEOCODING_API_KEY` | `config/services.php` | fallback de `GOOGLE_MAPS_KEY` |
| `RLS_APP_DB_PASSWORD` | `2026_06_26_000400_rls_role_app_sem_bypass.php:33` | **se ausente, a migration é NO-OP silencioso** |

**Passos.**
1. Criar `erp-novo/.env.production.example` cobrindo **todas** as chaves acima, agrupadas por bloco (app, db, fila/cache/sessão, sanctum/cors, broadcast/reverb, drivers-gate, integrações, seeds, legado/ETL, mail, observabilidade).
2. Marcar cada chave como **OBRIGATÓRIA** ou **OPCIONAL**, com o efeito de deixá-la vazia escrito ao lado. Ex.: `# FIREBASE_DRIVER=kreait  ← OBRIGATÓRIA: sem ela o login do app aceita token forjado (ver T1.3)`.
3. Valores de produção que **precisam** estar corretos: `APP_ENV=production`, `APP_DEBUG=false`, `DB_CONNECTION=pgsql`, `DB_USERNAME=erp_app` (a role restrita `NOSUPERUSER NOBYPASSRLS` — nunca o owner), `SESSION_SECURE_COOKIE=true`, `LEGADO_DB_SCHEMA=legado`, `SANCTUM_EXPIRATION=1440` (T1.5), `BROADCAST_CONNECTION=reverb`, `MAIL_MAILER` ≠ `log`, `LOG_STACK=daily` (T3.9), `FILESYSTEM_DISK` conforme T3.4.
4. Documentar no cabeçalho que o arquivo real vive em `/opt/dubena-env/erp-novo-producao.env` na VPS (espelhando o padrão do homolog em `deploy-erp-novo-homolog.yml`) e **nunca** é commitado.

**Pronto quando (binário).**
```bash
# toda env() do código está no template
grep -rhoE "env\('([A-Z0-9_]+)'" erp-novo/config erp-novo/app erp-novo/database erp-novo/routes \
  | sed "s/env('//" | sort -u > /tmp/usadas.txt
grep -oE "^#?\s*([A-Z0-9_]+)=" erp-novo/.env.production.example | tr -d '#= ' | sort -u > /tmp/docs.txt
comm -23 /tmp/usadas.txt /tmp/docs.txt
# DEVE retornar 0 linhas
```

---

### T3.2 — Criar `docker-compose.producao.yml` — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §9 §7.2 e §7.4.1: *"Não existe compose/manifesto de PRODUÇÃO — só o de homologação."*

**Passos.**
1. Partir de `erp-novo/docker-compose.homolog.yml` (7 containers: `app`, `web`, `queue`, `scheduler`, `reverb`, `db`, `redis` + a rede `erpnovo` na linha 154).
2. Diferenças obrigatórias em produção:
   - **Imagem com tag de versão** (`erpnovo-app:${GIT_SHA}`), não `:homolog` fixa — é pré-requisito do rollback (T3.5).
   - **Código na imagem**, não por bind-mount do checkout (o homolog usa bind-mount; isso torna rollback impossível).
   - `env_file` apontando para `/opt/dubena-env/erp-novo-producao.env`.
   - Portas diferentes das do homolog para coexistirem na mesma VPS durante a transição.
   - `restart: unless-stopped` em todos os serviços.
   - **Volumes nomeados e explicitados**: `db_data` (Postgres) e `app_storage` (que contém os **certificados A1 fiscais** — `CertificadoService` grava no disco `local` em `storage/app/certificados/empresa_<id>/<timestamp>.pfx`).
   - Healthcheck em `app` e `db`.
3. Ajustar o worker: hoje é `php artisan queue:work --sleep=1 --tries=3 --max-time=3600`. Ver T3.3.
4. Verificar que `scheduler` roda `schedule:work` — sem ele **PIX nunca expira e missões não são geradas** (`routes/console.php` define 8 agendamentos; a documentação interna do compose fala em "11 comandos" e está desatualizada).

**Pronto quando (binário).**
```bash
docker compose -f erp-novo/docker-compose.producao.yml config
# exit 0, sem warning de variável indefinida
docker compose -f erp-novo/docker-compose.producao.yml config --services | wc -l   # → 7
docker compose -f erp-novo/docker-compose.producao.yml up -d && sleep 30 && \
  docker compose -f erp-novo/docker-compose.producao.yml ps --format '{{.Service}} {{.Status}}'
# os 7 "healthy"/"running"
```

---

### T3.3 — Corrigir `retry_after` < timeout do job de migração — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §9 §7.1.5 e §7.2. `erp-novo/config/queue.php:43` (`DB_QUEUE_RETRY_AFTER`) e `:71` (`REDIS_QUEUE_RETRY_AFTER`) têm default **90 s**. Mas `erp-novo/app/Jobs/ExecutarMigracaoJob.php:21` declara `$timeout = 21600` (**6 horas**) e `$tries = 1`. Consequência verificada: com fila redis ou database, o job de migração é **re-entregue a cada 90 s enquanto ainda está rodando** — e colide com o `--tries=3` do worker do compose. Se a migração via UI SuperAdmin (`/api/superadmin/migracoes/*`) for usada em produção, é **corrida de duplicação de migração**.

Contexto adicional: **nenhum job usa `onQueue()`** (varredura em `app/`) — `GeocodificarClienteJob`, `AtribuirPedidoJob`, `EnviarPushJob`, `NotificarEstoqueBaixoJob` e `ExecutarMigracaoJob` compartilham a fila default. Um worker atende tudo.

**Passos.**
1. Criar uma **conexão de fila dedicada** para jobs longos em `config/queue.php`, ex.: `'redis-longo'` com `retry_after` = 25200 (7 h > 6 h de timeout).
2. Em `ExecutarMigracaoJob.php`, declarar `public $connection = 'redis-longo';` (ou `onQueue('migracao')` com worker próprio).
3. Adicionar ao `docker-compose.producao.yml` um segundo worker: `queue:work redis-longo --queue=migracao --tries=1 --timeout=21600` — **sem `--max-time`**, ou com valor > 6 h.
4. Manter o worker default como está (`--tries=3 --max-time=3600`) para os jobs curtos.
5. Documentar `REDIS_QUEUE_RETRY_AFTER` e `DB_QUEUE_RETRY_AFTER` no `.env.production.example` (T3.1).

**Pronto quando (binário).**
```bash
cd erp-novo && php artisan tinker --execute='
  $j = new \App\Jobs\ExecutarMigracaoJob(1);
  $c = $j->connection ?? config("queue.default");
  echo config("queue.connections.$c.retry_after") . " > " . $j->timeout;'
# DEVE imprimir retry_after MAIOR que timeout
```
Teste funcional: enfileirar um job de 3 min com timeout longo → `jobs`/Horizon mostra **uma** execução, não re-entregas.

---

### T3.4 — Backup automatizado de banco e storage — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §9 §7.6, textual: *"**Nenhum mecanismo de backup ou restore existe no repositório**: grep por `pg_dump|backup|restore` em `deploy/`, `.github/`, `erp-novo/docker*`, `DEPLOY_HOMOLOG.md` retorna apenas `restore-keys` de cache do CI."* Agravante: os dados vivem em **volumes Docker** — `db_data` (Postgres) e `app_storage` (que contém os **certificados A1 fiscais**, uploads e logs). Um `docker volume rm` ou recriação do host perde **banco e certificados**, sem cópia em lugar algum (`FILESYSTEM_DISK=local`, sem Auditoria §2 configurado).

Escala a considerar: `public.financeiros` soma **R$ 250.029.904,80** sobre 443.714 títulos; `public.pedidosituacaohistorico` tem 2.077.510 linhas; `public.notas_fiscais` tem 241.021 — documentos com valor legal.

**Passos.**
1. Criar `deploy/backup/backup.sh`: `pg_dump -Fc` do banco de produção + `tar` do volume `app_storage` (certificados A1 são insubstituíveis — reemiti-los custa dinheiro e tempo com a certificadora).
2. Nomear com timestamp UTC e SHA do deploy corrente; comprimir; calcular checksum.
3. **Destino fora do host** — a auditoria é explícita sobre volume Docker ser ponto único de falha. Se não houver S3/B2, no mínimo outro disco/servidor, com o rsync verificado.
4. Retenção: diário 7 dias, semanal 4, mensal 6.
5. Agendar via cron do host (não via `schedule:work` do Laravel — o backup precisa sobreviver ao container estar quebrado).
6. Backup **pré-deploy obrigatório**: adicionar ao workflow de produção (T3.6) um passo que chama `backup.sh` e **falha o deploy** se o backup falhar.

**Pronto quando (binário).**
```bash
bash deploy/backup/backup.sh
ls -la <destino>/  # arquivo .dump e .tar.gz com timestamp
sha256sum -c <destino>/*.sha256   # OK
# e o backup mais recente tem < 24h:
find <destino> -name '*.dump' -mtime -1 | wc -l    # ≥ 1
```

---

### T3.5 — Procedimento de restore testado — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §9 §7.6: *"Rollback de banco: nenhum procedimento; migrations rodam com `--force` a cada deploy sem snapshot prévio. **[BLOQUEANTE]** como pacote (backup + procedimento de restore testado antes do go-live)."*

**Um backup nunca testado não é um backup.** Esta tarefa é a metade que prova a T3.4.

**Passos.**
1. Criar `deploy/backup/restore.sh`: recebe um arquivo de backup, sobe um Postgres limpo, `pg_restore`, restaura o `app_storage`, e roda verificações.
2. Escrever `deploy/backup/README_RESTORE.md` como **runbook operacional**: passo a passo, tempo esperado, o que fazer se falhar no meio, quem avisar.
3. **Executar o restore de verdade** num ambiente descartável, a partir de um backup real de produção.
4. Verificações pós-restore obrigatórias (as mesmas usadas pela auditoria para atestar integridade):
   ```sql
   SELECT round(sum(valor)::numeric,2) FROM public.financeiros;  -- bate com a origem
   SELECT count(*) FROM public.pedidos;
   SELECT count(*) FROM public.notas_fiscais;
   ```
   e `php artisan cutover:check` retorna o mesmo placar de antes do backup.
5. Verificar que os certificados A1 voltaram: `ls storage/app/certificados/empresa_*/` não-vazio, e o `golive:check` não reclama de certificado ausente.
6. **Cronometrar** o restore completo. Esse número é o RTO real e entra na decisão de rollback da T6.7.

**Pronto quando (binário).**
```bash
bash deploy/backup/restore.sh <backup-real> && \
  psql -Atc "SELECT round(sum(valor)::numeric,2) FROM public.financeiros;"
# → valor idêntico ao da produção de origem
cd erp-novo && php artisan cutover:check   # mesmo placar
```
E o runbook registra o tempo medido do restore.

---

### T3.6 — Workflow de deploy de PRODUÇÃO com `golive:check --strict` bloqueante — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §9 §7.4.1: *"Não existe workflow/compose de deploy de PRODUÇÃO — só homolog. Nada no repo define como o erp-novo sobe como produção (domínio, `.env` de produção, `golive:check --strict` como portão bloqueante — hoje é `|| true`)."* Verificado: `.github/workflows/deploy-erp-novo-homolog.yml` linha **214** roda `php artisan golive:check || true` — informativo, com o comentário do próprio workflow admitindo *"Em produção plena, use `--strict` e trate a saída ≠0 como bloqueio."*

**Passos.**
1. Criar `.github/workflows/deploy-erp-novo-producao.yml`, partindo do de homolog, com estas diferenças:
   - Gatilho **manual** (`workflow_dispatch`) com confirmação — não push automático na `main`.
   - Restaura `.env` de `/opt/dubena-env/erp-novo-producao.env`.
   - **Primeiro passo: backup** (T3.4). Falha do backup = deploy abortado.
   - Build da imagem **taggeada com o SHA** (T3.2), não bind-mount.
   - `migrate --force --database=pgsql_owner` (mantém o padrão da linha 160 do homolog — migrations pelo owner, runtime pela role restrita).
   - `optimize:clear` + `config:cache` + **`route:cache` + `view:cache`** (Auditoria §9 §7.4.4: hoje só `config:cache` roda).
   - `db:seed --class=DeploySeeder` — **e nenhum outro seeder** (ver T3.7).
   - `php artisan golive:check --strict` **sem `|| true`**. Saída ≠ 0 = deploy falha e dispara rollback (T3.8).
   - Health checks nas URLs de produção.
2. Manter a restauração do `.env` do ctrl-web (linhas 50-65 do workflow de homolog) enquanto os dois coexistirem — Auditoria §9 §7.4.5 documenta o acoplamento do runner self-hosted compartilhado.

**Pronto quando (binário).**
```bash
# o workflow existe e NÃO tem "|| true" no golive:check
grep -n "golive:check" .github/workflows/deploy-erp-novo-producao.yml
# → linha com --strict, SEM "|| true"
grep -c "|| true" .github/workflows/deploy-erp-novo-producao.yml   # → 0
grep -n "route:cache\|view:cache\|backup" .github/workflows/deploy-erp-novo-producao.yml  # presentes
```
E um dry-run do workflow contra staging conclui verde.

---

### T3.7 — Remover os seeds de demonstração do caminho do deploy de produção — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §9 §7.4.2. Dois seeders no caminho do deploy criam massa fake:
- **`DemoGuarapuavaSeeder`** — guard duplo: no workflow (`deploy-erp-novo-homolog.yml:174-190`, pula se >50 clientes) e interno (`erp-novo/database/seeders/DemoGuarapuavaSeeder.php:108`: `if (Cliente::withoutTenant()->count() > 50) return`). Comportamento verificado: **um banco de produção recém-criado (antes do ETL) tem 0 clientes → recebe 200 clientes e 500 pedidos fake de Guarapuava.** A trava protege contra *duplicação*, não contra *rodar em produção vazia*.
- **`MarketplaceDemoSeeder`** — roda **incondicionalmente em todo deploy** (`deploy-erp-novo-homolog.yml:192-198`), criando a "Unidade Batel" demo aderida ao marketplace, mesmo em banco populado.

`DeploySeeder` (admin/RBAC/planos/cidades/superadmin) é idempotente e adequado, **desde que** T1.1 esteja feita.

**Passos.**
1. No workflow de produção (T3.6), **simplesmente não incluir** os passos dos dois seeders demo.
2. **Defesa em profundidade** (não confie só na ausência do passo): adicionar guard interno em ambos os seeders — `if (app()->environment('production')) { $this->command->warn('Seeder demo ignorado em produção.'); return; }`. Em `DemoGuarapuavaSeeder.php` isso vai **antes** do guard de contagem da linha 108. Fazer o mesmo em `MarketplaceDemoSeeder`.
3. Registrar em comentário no `DeploySeeder.php` quais seeders são "produção-safe" e quais não.

**Pronto quando (binário).**
```bash
grep -n "DemoGuarapuava\|MarketplaceDemo" .github/workflows/deploy-erp-novo-producao.yml
# → 0 linhas
APP_ENV=production php artisan db:seed --class='Database\Seeders\DemoGuarapuavaSeeder' --force
# → exit 0 mas ZERO linhas criadas; a mensagem de skip aparece
psql -Atc "SELECT count(*) FROM public.clientes WHERE nome ILIKE '%guarapuava%demo%';"  # 0
```

---

### T3.8 — Procedimento de rollback de aplicação — **[IMPORTANTE]**

**Achado que justifica.** Auditoria §9 §7.4.3: *"Rollback inexistente: imagem única `erpnovo-app:homolog` sem tag de versão, código por bind-mount do checkout; não há passo de rollback nem migrations reversíveis testadas como estratégia. **Voltar = novo push.**"*

**Passos.**
1. Depende de T3.2 (imagem taggeada por SHA). Manter as **N últimas** tags no registry/host.
2. Criar `deploy/rollback.sh <sha>`: para os containers, troca a tag no compose, sobe, roda `config:cache`, e verifica health.
3. **Migrations são o caso difícil.** Rollback de código com banco já migrado só funciona se as migrations da versão nova forem *aditivas* (adicionar coluna nullable, criar tabela) e não destrutivas. Estabelecer como regra de projeto: **nenhuma migration destrutiva (drop de coluna/tabela, rename) entra em produção no mesmo deploy que a introduz** — a remoção vem num deploy posterior, depois de o código antigo estar fora.
4. Documentar o gatilho: se o rollback de código não resolve (porque uma migration destrutiva passou), o caminho é o **restore da T3.5** — e o RTO é o tempo cronometrado lá.
5. Testar o rollback de verdade: deploy vN → deploy vN+1 → `rollback.sh vN` → sistema funcional.

**Pronto quando (binário).**
```bash
bash deploy/rollback.sh <sha-anterior> && curl -sf https://<dominio>/novo/up
# → HTTP 200
docker compose -f erp-novo/docker-compose.producao.yml images | grep app
# → tag == sha-anterior
```

---

### T3.9 — Proxy wss:// para o Reverb + observabilidade mínima — **[IMPORTANTE]**

**Achado que justifica.** Auditoria §9 §7.2 (Reverb) e §7.5 (observabilidade).

*Reverb:* há **5 eventos `ShouldBroadcast`** — `PixConfirmado`, `PedidoAtribuido`, `PedidoEntrouNaFila`, `EntregadorPosicaoAtualizada`, `PedidoStatusAtualizado`. O container `reverb` expõe `127.0.0.1:3121`, mas **nenhum arquivo em `deploy/nginx/` faz proxy de `wss://` para 3121** — grep por `3121|reverb|wss` em `deploy/nginx/*` = **vazio**; `homolog-erp.conf` e `gasemcasa-com.conf` só proxiam 3120/3110. Os consumidores são os apps mobile (`app-entregador/src/helpers/realtime.ts`, `app-gas-em-casa/src/helpers/realtime.ts` — laravel-echo + pusher-js), que **degradam para polling** se `REVERB_*` não chegar no build (`realtimeDisponivel()`). A SPA web não usa Echo (confirmado: grep por `laravel-echo` em `erp-novo/frontend/src` retorna vazio).

*Observabilidade:* sem Sentry/Bugsnag/Flare (grep em `composer.json`/`package.json`/`frontend/package.json` = vazio); log em arquivo único sem rotação (`LOG_CHANNEL=stack` + `LOG_STACK=single` nos dois templates, embora o canal `daily` exista em `config/logging.php:71`).

**Passos.**
1. Criar/estender o vhost em `deploy/nginx/` com um `location` para o Reverb: `proxy_pass http://127.0.0.1:3121;` + `proxy_http_version 1.1` + `Upgrade`/`Connection` headers + `proxy_read_timeout` alto.
2. Preencher `BROADCAST_CONNECTION=reverb` e `REVERB_APP_ID/KEY/SECRET/HOST/PORT/SCHEME` no `.env.production.example` (T3.1) — hoje ausentes do template de homolog.
3. **Rebuildar os dois apps mobile** com `extra.reverb` apontando para o endpoint público (Auditoria §9 §7.2 marca isto como item separado — sem o rebuild, o proxy não serve para nada).
4. Trocar `LOG_STACK=single` por `daily` no template de produção.
5. Adicionar Sentry (`composer require sentry/sentry-laravel` + `@sentry/react` no frontend), com DSN por env e `traces_sample_rate` baixo.
6. Configurar monitor de uptime externo apontando para `/novo/up` (o health nativo, `bootstrap/app.php:19`).

**Pronto quando (binário).**
```bash
# handshake WebSocket real
npx wscat -c "wss://<dominio>/reverb/app/<REVERB_APP_KEY>?protocol=7"
# → conecta e recebe pusher:connection_established

# rotação de log ativa
grep -n "LOG_STACK" erp-novo/.env.production.example    # → daily

# Sentry recebendo
cd erp-novo && php artisan tinker --execute='throw new \Exception("teste-sentry");'
# → evento aparece no painel Sentry
```

---

### T3.10 — Provisionar os gates externos obrigatórios — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §9 §7.3 e §7.8 item 6. Três gates são `[BLOQUEANTE]` e dependem de provisionamento **fora do código**:

| Gate | O que o código exige | Evidência |
|---|---|---|
| **Firebase** (login do app consumidor) | `FIREBASE_DRIVER=kreait` + `FIREBASE_CREDENTIALS` = caminho de um **arquivo JSON de service account no servidor** (não versionado — precisa ser provisionado no volume) + `FIREBASE_PROJECT_ID` | `config/services.php` bloco `firebase`; `AppServiceProvider.php:119-121`; `KreaitFirebaseVerifier` |
| **Certificado A1 por empresa** (faturar) | `FISCAL_DRIVER=nfephp` + upload do `.pfx` pela SPA, gravado em `storage/app/certificados/empresa_<id>/<timestamp>.pfx` (disco `local`), senha cifrada em `empresa_configs`. **`golive:check` FALHA se alguma empresa estiver sem certificado com fiscal real** | `app/Domain/Fiscal/CertificadoService.php`; `GoliveCheck.php:197-202`; deps `nfephp-org/sped-nfe` + `ext-soap` no Dockerfile |
| **PIX** (cobrança) | `PIX_DRIVER` real + `PIX_ENABLED=true`; credencial **POR EMPRESA** em `empresa_configs.dados['integracoes']['pix']` (fail-closed); `PIX_WEBHOOK_SECRET` e `PIX_WEBHOOK_HMAC_SECRET` (**segredos distintos**) | `config/services.php` bloco `pix`; `GoliveCheck.php:215-221` |

Importantes (não bloqueantes): `FCM_DRIVER=v1` (usa a mesma service account do Firebase), `COBRANCA_DRIVER=caixa|itau` + conta por empresa em `empresa_configs.dados['cobranca'][<banco>]` (`GoliveCheck.php:205-211`), `GOOGLE_MAPS_KEY`, SMTP (default `MAIL_MAILER=log` — **nada sai**; há mailer dinâmico `empresa_smtp` em `EmpresaConfigController.php:155,173`), eRede (`GoliveCheck.php:224-230`).

**Passos.**
1. Colocar o JSON de service account do Firebase no volume de produção, com permissão restrita (`0400`, dono do processo PHP), e apontar `FIREBASE_CREDENTIALS`.
2. Para **cada empresa** que vai faturar: fazer upload do certificado A1 pela SPA (`/api/admin/empresas/{id}/certificado`), com a senha. Confirmar que `openssl_pkcs12_read` aceitou (o service valida — `EmpresaConfigController.php:104`).
3. Para **cada empresa** que vai cobrar por PIX: preencher a credencial do PSP em `empresa_configs.dados['integracoes']['pix']` via a tela de integrações. O resolvedor é `app/Domain/Integracao/IntegracaoTenant.php`, ordem EMPRESA → GRUPO → PLATAFORMA(env), fail-closed.
4. Gerar `PIX_WEBHOOK_SECRET` e `PIX_WEBHOOK_HMAC_SECRET` (valores **diferentes**) e registrar o endpoint `/api/pix/webhook` no painel do PSP.
5. Configurar SMTP real.

**Pronto quando (binário).**
```bash
cd erp-novo && php artisan golive:check --strict
# → exit 0, sem FAIL de certificado, PIX, Firebase ou role de runtime
```
Teste de ponta a ponta: um login real de cliente no app (Firebase real, não `fake:`) e uma NFC-e emitida em homologação da SEFAZ.

---

### O QUE NÃO FAZER na F3

- ❌ **Não** reutilize o banco de homologação como banco de produção. Auditoria §9 §7.7.4 é explícito: a massa demo (Guarapuava/marketplace) **não pode existir** em produção. O banco de produção nasce vazio + `DeploySeeder` + ETL.
- ❌ **Não** use o mesmo `.env` de homolog em produção. `APP_ENV=homologation`, drivers em `fake` e ausência de `SESSION_SECURE_COOKIE` são incompatíveis.
- ❌ **Não** rode `DB_USERNAME` = owner do banco em runtime. A role restrita `erp_app` é criada `NOSUPERUSER NOBYPASSRLS` (`2026_06_26_000400_rls_role_app_sem_bypass.php:49`) e é o que faz a RLS multi-tenant ser defesa real — superuser **ignora RLS**. Migrations pelo `pgsql_owner`, runtime pelo `erp_app`.
- ❌ **Não** deixe `RLS_APP_DB_PASSWORD` vazia: a migration que cria a role restrita lê `env()` direto (`:33`) e vira **NO-OP silencioso** se ausente — o próprio `golive:check` trata isso como causa-raiz de FAIL (`GoliveCheck.php:108-142`).
- ❌ **Não** instale PostGIS "por precaução". A migration `2026_06_29_000100_p9_indices_escala.php` declara explicitamente "Não introduz PostGIS" — usa índice lat/lng + Haversine em PHP. É `[DESEJÁVEL]`, não requisito.
- ❌ **Não** mantenha `golive:check` com `|| true` no workflow de produção. Esse é o defeito literal apontado (linha 214 do workflow de homolog).
- ❌ **Não** declare o backup pronto sem ter feito o restore da T3.5 dar certo.

---

## MARCO DE VALIDAÇÃO M3 (antes de avançar para F4)

```bash
# 1) Compose de produção sobe íntegro
docker compose -f erp-novo/docker-compose.producao.yml config >/dev/null && \
docker compose -f erp-novo/docker-compose.producao.yml up -d && sleep 30 && \
docker compose -f erp-novo/docker-compose.producao.yml ps   # 7 saudáveis

# 2) Portão de prontidão ESTRITO verde
cd erp-novo && php artisan golive:check --strict && echo "GOLIVE OK"
echo $?   # → 0

# 3) Ciclo backup → restore → verificação COMPLETO e cronometrado
bash deploy/backup/backup.sh && bash deploy/backup/restore.sh <ultimo> && \
  psql -Atc "SELECT round(sum(valor)::numeric,2) FROM public.financeiros;"

# 4) Rollback funciona
bash deploy/rollback.sh <sha-anterior> && curl -sf https://<dominio>/novo/up

# 5) Reverb responde por wss
npx wscat -c "wss://<dominio>/reverb/app/<KEY>?protocol=7"

# 6) Nenhum seeder demo entra em produção
APP_ENV=production php artisan db:seed --class='Database\Seeders\DemoGuarapuavaSeeder' --force
psql -Atc "SELECT count(*) FROM public.clientes;"   # inalterado
```
**Não avance para F4 sem os 6 verdes.** Especialmente o 3 — a partir daqui o sistema começa a receber mudanças funcionais, e sem restore testado não há rede.

---

## FASE F4 — Fechar lacunas funcionais bloqueantes da paridade

**Objetivo.** Garantir que o operador consegue **concluir o trabalho diário** no sistema novo. Não é atingir paridade total — é remover os pontos onde a operação trava.

**Contexto que o agente precisa saber.** A matriz de paridade (Auditoria §2) tem **46 linhas**: 20 ✅ migradas e funcionais, 22 ⚠️ migradas com problemas, 4 ❌ não migradas. Cobertura bruta = 42/46 (91,3%); paridade plena = 20/46 (43,5%).

O padrão dominante das ⚠️ **não é módulo vazio** — é o núcleo transacional migrado com quatro famílias de lacuna:
1. **Saídas impressas** (boleto, recibo, vale-gás, contratos, DANFE, comanda, etiquetas) — nenhum gerador de PDF operacional existe no novo fora de relatórios;
2. **Sub-operações de escrita viradas leitura** (recessos, comissões, troca de óleo/pneu);
3. **Ferramentas de gestão/marketing** por cima do transacional;
4. **Ações de escrita que fecham o ciclo de uma tela de leitura** — a família mais insidiosa, porque *"são telas que 'existem' no novo e mesmo assim não substituem a do legado, porque o operador não consegue concluir o trabalho nelas; auditorias por checklist de tela tendem a marcá-las como migradas."*

A F4 ataca a família (4) e os ❌ operacionais primeiro — são as que travam a operação com o menor esforço.

---

### T4.1 — Ação de escrita das inconsistências geográficas (`ignorarRua`/`ignorarBairro`) — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §2 linha 4 e §3.3-4. No legado, o operador **resolve** o par duplicado com `ctrl-web/app/Http/Controllers/InconsistenciaController.php` métodos `ignorarRua`/`ignorarBairro` (`:48-91`) — um POST transacional com `DB::beginTransaction()` que grava o par na tabela de ignorados: `$rua->ignorados()->attach($ruaignore_id, ['ignored_type' => Rua::class])`. Rotas em `ctrl-web/routes/web.php:1002-1003`.

No novo, o **detector** foi portado (`erp-novo/app/Http/Controllers/Api/Admin/GeoController.php:101-116` + `erp-novo/app/Domain/Apoio/InconsistenciaService.php`, que expõe apenas `ruas()`/`bairros()`/`todas()`) mas a **resolução não**: em `erp-novo/routes/api.php:226` existe só o GET `cadastros/inconsistencias`, e o service **não tem método de escrita**. Resultado verificado: *"os falsos positivos voltam a cada consulta e a fila nunca esvazia"* — a tela vira relatório somente-leitura em vez de fila de trabalho.

**Passos.**
1. Migration nova: tabela pivô de ignorados, polimórfica como no legado (`ignorable_id`, `ignorable_type`, `ignorado_id`, `empresa_id`, `grupo_id`) — com `empresa_id`/`grupo_id` porque o novo é multi-tenant e precisa de RLS (siga o padrão de `2026_07_03_000300_rls_cobertura_tabelas_novas.php` para cobrir a tabela nova com policy).
2. Em `InconsistenciaService.php`, adicionar `ignorarPar(string $tipo, int $id, int $ignoradoId): void` em transação, e fazer `ruas()`/`bairros()`/`todas()` **excluírem** os pares já ignorados.
3. Em `GeoController.php`, adicionar o método `ignorar()` com `$this->autorizar($request, 'cadastro.edit')` — siga o padrão do trait `AutorizaPorPermissao` usado por 45/47 controllers admin.
4. Rota `POST /api/admin/cadastros/inconsistencias/ignorar` em `routes/api.php`, dentro do grupo `admin` (linha 119).
5. Frontend: em `erp-novo/frontend/src/features/geografico/GeograficoPage.tsx`, botão "Ignorar par" por linha, com invalidação da query.

**Pronto quando (binário).**
```bash
# a rota existe
cd erp-novo && php artisan route:list --path=inconsistencias
# → 1 GET + 1 POST

# teste de ciclo fechado
php artisan test --filter=Inconsistencia
# cenário: GET lista N pares → POST ignorar 1 → GET lista N-1
```

---

### T4.2 — Automação de classificação do extrato bancário (`extratoconfig`) — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §2 linha 11 e §3.3-11. No legado, `ctrl-web/app/Http/Controllers/ContaController.php:670-700` (`addEditExtratoconfig`, rota `ctrl-web/routes/web.php:155`) cadastra, **por conta bancária**, regras que associam uma descrição do extrato a uma das ações do enum `ContaextratoAcao` — **Lancar**, **LancarBaixar** ou **Transferir** — exigindo `condicaopagamento_id`, `contamovimentotipo_id`, `pc_id` e `cc_id` nas duas primeiras, e `contaorigem_id` na transferência.

No novo: `grep -rniE "extratoconfig|contaextrato"` em `erp-novo/app/` e `erp-novo/frontend/src/` retorna **zero**. Existe a importação OFX (`erp-novo/routes/api.php:571-572` → conciliação bancária via `app/Domain/Financeiro/ConciliacaoService.php`) mas **não a classificação automática que a torna produtiva**. Consequência verificada: *"a conciliação importa o extrato mas cada linha precisa ser classificada à mão."* Com o volume real (`public.contamovimentos` = **410.417 linhas**), classificação manual é inviável.

**Passos.**
1. Migration: tabela `conta_extrato_regras` com `conta_id`, `descricao` (padrão de match), `acao` (enum `LANCAR|LANCAR_BAIXAR|TRANSFERIR`), `condicaopagamento_id`, `contamovimentotipo_id`, `plano_conta_id`, `centro_custo_id`, `conta_origem_id`, `empresa_id`, `grupo_id`, `ativo`. Cobrir com RLS.
2. Enum PHP `ContaExtratoAcao` em `app/Domain/Financeiro/`, espelhando o do legado.
3. Service `RegraExtratoService` com `aplicar(array $linhasOfx): array` — casa cada linha por descrição e devolve a ação sugerida + os ids. **Validar as obrigatoriedades por ação exatamente como o legado** (a regra de negócio está em `ContaController.php:670-700`).
4. Integrar no `ConciliacaoService.php`: a importação OFX passa pelo classificador antes de devolver as linhas.
5. Endpoints CRUD sob `financeiro/contas/{id}/extrato-regras` em `routes/api.php` (grupo admin), com `autorizar()`.
6. Frontend: aba de regras em `erp-novo/frontend/src/features/financeiro/tabs/` (o diretório já existe com o padrão de abas), e exibição da sugestão na tela de conciliação.

**Pronto quando (binário).**
```bash
cd erp-novo && php artisan test --filter=RegraExtrato
# cenário: cadastrar regra "PIX RECEBIDO" → LANCAR_BAIXAR; importar OFX com essa
# descrição → a linha volta pré-classificada com os ids preenchidos
php artisan route:list --path=extrato-regras   # CRUD completo
```

---

### T4.3 — Decidir e executar: fechamento de malote — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §2 linha 33 (❌ não migrada) e §3.3-33. `ctrl-web/app/Http/Controllers/FechamentomaloteController.php` faz a **conferência de valores do malote do entregador** — pedidos do malote, parcelas, `updatePedido`, `fechar` (rotas em `ctrl-web/routes/web.php:598-604`), mais o relatório `ReportvendasmaloteController.php` (`web.php:784-785`). No novo: *"grep `malote` no erp-novo: só um campo de config contábil em `frontend/src/features/empresas/config/ContabilTab.tsx`"* — nem endpoint, nem service, nem tela.

A auditoria classifica: *"Bina (32) e malote (33) são fluxos operacionais diários do modelo disk-gás legado e merecem decisão explícita: portar ou aposentar formalmente."*

**Passos.**
1. **Primeiro: decidir com o dono do negócio.** O acerto físico de valores do entregador ainda acontece? Se sim, é bloqueante — o dinheiro do dia não fecha sem isso.
2. **Se aposentar:** documentar por escrito a decisão e o que a substitui. Verificar se o fluxo de missões/entregas do novo (`app/Domain/Logistica/`, `app/Domain/Missao/`) já cobre o acerto por outro caminho.
3. **Se portar:** ler `FechamentomaloteController.php` inteiro para extrair a regra (quais pedidos entram no malote, como parcelas são conferidas, o que "fechar" faz ao pedido e ao caixa). Implementar como `app/Domain/Caixa/MaloteService.php` (o domínio Caixa já existe, com `CaixaService.php`), endpoints `admin/malotes/*`, e tela em `frontend/src/features/financeiro/tabs/`.
4. Ligar ao `CaixaService` existente — o fechamento do malote alimenta o caixa.

**Pronto quando (binário).** Uma das duas:
- **Aposentado:** documento de decisão assinado pelo dono + confirmação de que nenhum processo diário depende do fluxo.
- **Portado:** `php artisan route:list --path=malote` retorna as rotas, e `php artisan test --filter=Malote` passa cobrindo o ciclo abrir → conferir → fechar.

---

### T4.4 — Decidir e executar: telefonia/bina no atendimento — **[BLOQUEANTE se o call-center usa]**

**Achado que justifica.** Auditoria §2 linha 32 (❌) e §3.3-32. No legado, o atendimento do disk-gás identifica a chamada entrante e abre a ficha do cliente: `ctrl-web/app/Http/Controllers/NotificacoesController.php` (`meliganotification`), rotas `excluirTelefoneChamada` e `rejeitaligacao` (`web.php:911-912,1016-1043`), models `Monitoramentochamadas` e `Ligacoestelefonicas`, `ApiController@gravarTelefone`, `SearchController@searchTelefonesMonitoramento`.

No novo: greps por `ligac`, `chamada`, `bina` retornam 19 ocorrências em `app/` e `frontend/src/`, **todas ruído linguístico** ("chamada" no sentido de chamada de função/HTTP em comentários; "combina/combinação" casando `bina`). Greps específicos por `ligacoestelefonicas`/`monitoramentochamadas`/`\bbina\b`: **zero**. A auditoria conclui: *"Se o call-center ainda opera com bina, é bloqueador de virada."*

**Passos.**
1. **Perguntar ao dono: o call-center usa bina hoje?** Esta pergunta decide se a tarefa é bloqueante ou some do plano.
2. **Se não usa:** documentar a aposentadoria e remover da lista de bloqueantes.
3. **Se usa:** ler o fluxo legado, identificar a integração com a central telefônica (o `meliganotification` sugere um webhook do PABX). Implementar: tabela de chamadas, endpoint de recepção do evento do PABX (com autenticação — **não** repita o `sha1(APP_KEY)` do legado, Auditoria §4 §2.6-7), e broadcast do evento para a SPA via os canais privados já existentes (`routes/channels.php` — o padrão `empresa.{empresaId}.*` serve).
4. Na SPA, um listener que abre a ficha do cliente pelo telefone. **Nota:** a auditoria registra que a SPA hoje **não usa Echo** (grep por `laravel-echo` em `frontend/src` = vazio) — esta seria a primeira assinatura de canal do frontend web, então prevê o trabalho de instalar e configurar o Echo lá.

**Pronto quando (binário).** Uma das duas: documento de aposentadoria assinado, **ou** `php artisan test --filter=Telefonia` verde + demonstração de chamada entrante abrindo a ficha na SPA.

---

### T4.5 — Cadastro de tipos de documento de veículo — **[IMPORTANTE]**

**Achado que justifica.** Auditoria §2 linha 34 (❌) e §3.3-34. Cadastro de apoio pequeno, mas **ausência total**. No legado: `ctrl-web/app/Http/Controllers/TipodocumentoController.php`, model `ctrl-web/app/Tipodocumento.php`, policy `ctrl-web/app/Policies/TipodocumentoPolicy.php`, view `resources/views/documento/tipodocumentos.blade.php`, rota `Route::resource('tipodocumento', ...)` em `ctrl-web/routes/web.php:280`. Consumido pelo pivô `ctrl-web/app/Veiculodocumento.php:45` (`belongsTo('App\Tipodocumento')`) e pelo dropdown do cadastro de veículo em 4 pontos de `VeiculoController.php:15,52,149,183` (sempre filtrado por `ativo` + `grupo_id`).

No novo: `grep -rniE "tipo[-_]?documento|tipodoc"` em `erp-novo/app/` e `erp-novo/frontend/src/` = **zero**. O `CadastroApoioRegistry.php` tem `tipos-exame`, `tipos-pessoa`, `tipos-movimento`, mas **nenhuma chave `tipos-documento`**. O endpoint `veiculos/{id}/documentos` (`routes/api.php:610`) grava documentos **sem domínio de valores** por trás.

**Cuidado com o homônimo:** `ctrl-web/app/Http/Controllers/DocumentotipoController.php` (model `Documentotipo`, rota `web.php:1106`) é o tipo da **gestão documental** (linha 30 da matriz) e é um controller **diferente**. Não confunda.

**Passos.**
1. Migration da tabela `tipos_documento_veiculo` (`nome`, `ativo`, `empresa_id`, `grupo_id`), coberta por RLS.
2. Registrar a chave `tipos-documento-veiculo` em `erp-novo/app/Domain/Apoio/CadastroApoioRegistry.php` — o registry já é o mecanismo genérico de cadastros de apoio, então o CRUD vem de graça via `cadastros/{tipo}` (`routes/api.php:229`).
3. Adicionar `tipo_documento_id` (nullable) à tabela de documentos de veículo, e expor no endpoint `veiculos/{id}/documentos`.
4. Migrar os valores existentes do legado: adicionar `TIPODOCUMENTOS` ao MAPA de `espelhar_oracle.py` (se ainda não estiver — provável, dado o achado das 101 tabelas na T2.7) e um migrator, **ou** semear os tipos padrão (CRLV, seguro, ANTT) via `DeploySeeder` se o volume for trivial.
5. Frontend: dropdown em `erp-novo/frontend/src/features/frota/VeiculosPage.tsx`.

**Pronto quando (binário).**
```bash
cd erp-novo && php artisan route:list --path=cadastros | grep -i "tipos-documento-veiculo"
curl -s .../api/admin/cadastros/tipos-documento-veiculo   # lista não-vazia
```

---

### T4.6 — Gerador de PDF operacional (boleto, recibo, vale-gás, comodato, comanda) — **[BLOQUEANTE — boleto]**

**Achado que justifica.** Auditoria §2 §3.4, família (1) das lacunas: *"saídas impressas (boleto, recibo, vale-gás, contratos, DANFE, comanda, etiquetas — **nenhum gerador de PDF operacional existe no novo fora de relatórios**)."* Detalhamento por linha:
- **Linha 14 (boletos)** — CNAB completo (`app/Domain/Cobranca/Cnab/`), mas *"o boleto em si não é impresso (legado: `ctrl-web/app/Http/Controllers/BoletoPdfController.php`); **sem o PDF o título não pode ser entregue ao cliente**"*. Este é o bloqueante real da família.
- **Linha 12 (caixa)** — `CaixaController@gerarRecibo`/`gerarReciboCR`; grep `recibo` no erp-novo = **zero**.
- **Linha 19 (vale-gás)** — vale impresso, duplicata em PDF, `confirmaImpressao` (`gerarPDF`, `imprimirGas`).
- **Linha 20 (comodato)** — contrato em PDF (`ComodatoController@contrato`).
- **Linha 7 (pedidos)** — comanda de impressão (`pedido.comanda`).

**Passos.**
1. Estabelecer **uma** infraestrutura de PDF em `app/Domain/Shared/` (ex.: `PdfService`), sobre o `dompdf` que já está no `composer.json` (**atenção: só depois da T1.6**, que corrige os 6 advisories do dompdf) — Blade view → PDF, com header/footer padronizados.
2. **Prioridade 1 — boleto** (bloqueante): `BoletoService` ganha `gerarPdf(Boleto $b)`. O legado usa `eduardokum/laravel-boleto` (`BoletoPdfController.php:4-7` importa `Contracts\Boleto\Boleto` e `Render\Pdf`) — avalie reusar a mesma lib, já que o CNAB do novo (`app/Domain/Cobranca/Cnab/`) implementa os mesmos layouts.
3. **Prioridade 2** — recibo de caixa, vale-gás/duplicata, contrato de comodato, comanda de pedido. Cada um: método no service do domínio + endpoint `GET .../pdf` + botão na tela.
4. Todos os endpoints com `autorizar()` — um PDF de boleto é dado financeiro de cliente.

**Pronto quando (binário).**
```bash
cd erp-novo && php artisan route:list | grep -cE "(boletos|caixa|vale-gas|comodatos|pedidos)/.*pdf"
# → ≥ 5
curl -s -o /tmp/b.pdf .../api/admin/boletos/1/pdf && file /tmp/b.pdf
# → "PDF document"
```
Verificação humana obrigatória para o boleto: **código de barras e linha digitável legíveis e válidos** — um boleto com barcode errado é pior que nenhum boleto.

---

### T4.7 — Restaurar as sub-operações de escrita viradas leitura — **[IMPORTANTE]**

**Achado que justifica.** Auditoria §2 §3.4, família (2). Três casos, todos com evidência de linha:
- **Linha 21 (RH)** — *"recessos e comissões só leitura (legado tem resource CRUD completo p/ ambos — `erp-novo/routes/api.php:586-587` do novo só expõe GET)"*. Legado: `ctrl-web/app/Http/Controllers/RecessosController` + `ColaboradorcomissoesController.php` (ambos `Route::resource`).
- **Linha 22 (frota)** — *"troca de óleo e pneus só leitura"*; legado tem resource CRUD + **alerta de troca vencida** (`VeiculotrocaoleoController@getTrocas`, `report.trocaoleovencido*`). No novo, `routes/api.php:597-610`: `trocas-oleo` e `pneus` só GET.

Sem POST, o operador não consegue lançar um recesso, uma comissão ou uma troca de óleo — precisa voltar ao legado, o que inviabiliza a aposentadoria dele.

**Passos.**
1. Em `erp-novo/app/Http/Controllers/Api/Admin/ColaboradorController.php`: adicionar `store`/`update`/`destroy` para recessos e comissões, com `autorizar()` (o padrão do trait `AutorizaPorPermissao`).
2. Em `erp-novo/app/Http/Controllers/Api/Admin/VeiculoController.php`: mesma coisa para trocas de óleo e pneus.
3. Registrar as rotas em `routes/api.php` (linhas 578-594 para RH, 597-610 para frota).
4. Portar o **alerta de troca vencida**: um slug novo no catálogo de relatórios de `app/Domain/Relatorio/RelatorioService.php` (o catálogo tem 17 slugs, definido em `RelatorioController.php:151-169`), ou uma checagem no `notify:alertas` (`routes/console.php`, diário 07:00).
5. Frontend: formulários nas abas de `features/rh/` e `features/frota/`.

**Pronto quando (binário).**
```bash
cd erp-novo && php artisan route:list --path=colaboradores | grep -cE "POST|PUT|DELETE"   # ≥ 6
cd erp-novo && php artisan route:list --path=veiculos | grep -cE "trocas-oleo|pneus" | grep -c POST  # ≥ 2
php artisan test --filter="Recesso|Comissao|TrocaOleo"   # verde
```

---

### T4.8 — Motivos de atraso e de não-venda no pedido — **[IMPORTANTE]**

**Achado que justifica.** Auditoria §2 linha 7 e §3.3-7. O kanban (`erp-novo/frontend/src/features/pedidos/KanbanView.tsx`) substitui o monitoramento legado, mas *"três apoios operacionais do disk-gás não existem: motivos de atraso com justificativa obrigatória (`ctrl-web/app/Http/Controllers/PedidomotivoatrasoController.php` + `PedidoController@justificaMotivoAtraso`), motivos de não-venda (`ctrl-web/app/Http/Controllers/MotivonaovendaController.php` — **zero ocorrências no novo**) e a comanda impressa"* (a comanda está na T4.6).

Motivo de não-venda também aparece na linha 26 (venda ativa/missões) — o entregador em campo precisa registrar por que a venda não aconteceu.

**Passos.**
1. Registrar `motivos-atraso` e `motivos-nao-venda` como chaves em `erp-novo/app/Domain/Apoio/CadastroApoioRegistry.php` — o CRUD sai de graça pelo endpoint genérico `cadastros/{tipo}`.
2. Adicionar `motivo_atraso_id` + `justificativa_atraso` a `pedidos` (migration), e o endpoint de justificativa em `PedidoController`.
3. Adicionar `motivo_nao_venda_id` ao fluxo de missão/venda em campo — `app/Http/Controllers/Api/Mobile/AppMissaoController.php` já tem os endpoints de visita (`routes/api.php:684-694`).
4. Migrar os cadastros do legado (tabelas `pedidomotivoatrasos` e `motivonaovendas` do Oracle) — verificar se estão no MAPA de `espelhar_oracle.py` (relaciona com T2.7).
5. Frontend: campo no diálogo de mudança de situação em `features/pedidos/PedidoDialogs`, e no app entregador.

**Pronto quando (binário).**
```bash
cd erp-novo && php artisan route:list --path=cadastros | grep -cE "motivos-atraso|motivos-nao-venda"  # ≥ 1
php artisan test --filter="MotivoAtraso|MotivoNaoVenda"
# cenário: pedido marcado atrasado SEM motivo → 422; COM motivo → 200
```

---

### T4.9 — Triagem escrita das 22 lacunas ⚠️ restantes — **[BLOQUEANTE — a triagem, não a implementação]**

**Achado que justifica.** Auditoria §2 §3.4 e a tabela de totais: 22 linhas ⚠️ e 4 ❌. As tarefas T4.1–T4.8 cobrem as de maior impacto operacional; as demais precisam de **decisão explícita antes do go-live** — não de implementação obrigatória. A auditoria alerta que *"auditorias por checklist de tela tendem a marcá-las como migradas"*, então uma triagem escrita é a defesa contra virar sem saber o que falta.

Lacunas ainda abertas após T4.1–T4.8, com o achado de origem:

| Linha | Lacuna | Fonte |
|---|---|---|
| 8 | Exportar XMLs em lote p/ contador, envio de NF por e-mail, importação TXT, visualização de DANFE | Auditoria §2 §3.3-8 |
| 10 | **SPED Créditos não existe** (grep `spedcredito` = zero); sem download do `.txt` gerado | Auditoria §2 §3.3-10 |
| 13 | Desconto/antecipação de cheque em banco — sem situação equivalente no enum `SituacaoCheque.php` | Auditoria §2 §3.3-13 |
| 14 | CRUD de layouts de banco (viraram drivers em código — sem banco novo sem deploy) | Auditoria §2 §3.3-14 |
| 16 | Parser do arquivo do adquirente de cartão (baixa em lote) → virou registro manual de NSU | Auditoria §2 §3.3-16 |
| 18 | Fechamento de convênio **sem emissão encadeada de NF e boleto** (grep em `ConvenioFechamentoService.php` = zero); sem PDF/XLS; sem dashboard GB | Auditoria §2 §3.3-18 |
| 20 | Gestão analítica de comodato (saldos por cliente, vencidos, giro) | Auditoria §2 §3.3-20 |
| 24 | DANFE/boleto/duplicata no dispositivo do entregador; parcelas vencidas do cliente na entrega | Auditoria §2 §3.3-24 |
| 26 | Filtros de prospecção da venda ativa (média de giro, última compra); tipos de ocorrência; relatórios de promotor | Auditoria §2 §3.3-26 |
| 27 | Envio de e-mail em massa (gate SMTP) e etiquetas na mala direta | Auditoria §2 §3.3-27 |
| 28 | **~17 slugs contra ~40 relatórios**: mapas georreferenciados de entrega, tempo de entrega, follow-up, clientes sem compra/inativos, fluxo de caixa, log de senha mestra, vendas por malote | Auditoria §2 §3.3-28 |
| 29 | Fechamento mensal com e-mail/export; PowerPoint de vendas mensais (grep = zero); dashboards de documentos e convênio-GB | Auditoria §2 §3.3-29 |
| 30 | Versionamento de documento com upload/download (grep `versao` em `app/Domain/Gestao` e `app/Models/Gestao` = zero); impressão do MCMM | Auditoria §2 §3.3-30 |
| 31 / A11 | Campanhas de push segmentadas, vídeo de abertura, giro de compras/recompra | Auditoria §2 §3.3-31 |
| A10 | Vídeo de abertura do app — desligado deliberadamente (`app-gas-em-casa/src/app/startupvideo.tsx` virou redirect) | Auditoria §2 §3.3-A10 |
| 3 | Impressão de contrato/etiquetas de convênio (mala direta cobre etiquetas via CSV) | Auditoria §2 linha 3 |
| 6 | PDFs de requisição/transferência de estoque | Auditoria §2 linha 6 |

**Passos.**
1. Sentar com o dono e classificar **cada linha** em: `PRÉ-GO-LIVE` / `PÓS-GO-LIVE (com prazo)` / `APOSENTADO (com justificativa)`.
2. Para as `PRÉ-GO-LIVE`, criar tarefas com o mesmo formato desta fase.
3. Para as `PÓS-GO-LIVE`, definir prazo e o **workaround** durante o intervalo — inclusive "consultar o legado em modo leitura", se o legado for mantido de pé (ver T6.6).
4. **Atenção especial ao item 28 (relatórios).** É o de maior volume: ~23 relatórios sem equivalente. Muitos são consulta gerencial que pode esperar; mas *fluxo de caixa* e *clientes sem compra/inativos* costumam ser rotina. Classifique um a um, não em bloco.
5. Publicar a triagem como documento versionado.

**Pronto quando (binário).** Existe um documento com **as 17+ linhas acima classificadas individualmente**, cada uma com veredito e (se `PÓS-GO-LIVE`) prazo e workaround. **Zero linhas sem veredito.**

---

### O QUE NÃO FAZER na F4

- ❌ **Não** tente atingir paridade de 100%. A meta é operação diária destravada. Cobertura bruta já é 91,3%; o esforço de fechar os últimos 8,7% em módulos de gestão/marketing não é go-live.
- ❌ **Não** reimplemente o menu-no-banco do legado. Auditoria §2 linha 1 é explícita: a navegação declarativa da SPA (`frontend/src/layouts/`) substituindo `Menu::menus()` (`ctrl-web/app/Menu.php:81-104`) é **decisão de arquitetura, não lacuna**.
- ❌ **Não** porte os bypasses do legado. Especificamente: o bypass de autorização por AJAX (`ctrl-web/app/Http/Middleware/AuthorizeCustom.php:52-53`, que o próprio código admite ser *"o vetor de bypass de autorização mais amplo"*), o `sha1(APP_KEY)` como autenticação de WebSocket (`ctrl-web/app/Api/Repository/PedidoRepository.php:167`) e os 137 `whereRaw` com concatenação. A auditoria de segurança do novo (Auditoria §8) confirma **zero** achados de SQLi — não introduza o primeiro.
- ❌ **Não** porte `consultasoracle`/`organizarestoque` (`ctrl-web/routes/web.php:1049-1103`). São scripts one-off de correção, não funcionalidade (Auditoria §2 linha 35).
- ❌ **Não** adicione endpoint de escrita sem `autorizar()`. 45/47 controllers admin usam o trait `AutorizaPorPermissao`; a auditoria verificou que **nenhum endpoint de escrita sensível está sem autorização** hoje. Mantenha o placar.
- ❌ **Não** crie tabela nova sem cobri-la com RLS. Existem 22 migrations com `CREATE POLICY`; uma tabela nova descoberta é vazamento cross-tenant.

---

## MARCO DE VALIDAÇÃO M4 (antes de avançar para F6)

```bash
# 1) Suíte completa verde após todas as mudanças funcionais
cd erp-novo && php artisan test
cd erp-novo/frontend && npm run type-check && npm test && npm run build

# 2) Portões continuam verdes (mudanças funcionais não regrediram dados nem prontidão)
cd erp-novo && php artisan cutover:check && php artisan golive:check --strict

# 3) Ciclos fechados provados
php artisan test --filter="Inconsistencia|RegraExtrato|Recesso|Comissao|TrocaOleo|MotivoAtraso"

# 4) Boleto imprime com barcode válido (verificação humana)
curl -s -o /tmp/b.pdf .../api/admin/boletos/1/pdf && file /tmp/b.pdf

# 5) Triagem T4.9 publicada, zero linhas sem veredito

# 6) Nenhuma tabela nova sem RLS
psql -Atc "SELECT tablename FROM pg_tables WHERE schemaname='public'
           AND tablename NOT IN (SELECT tablename FROM pg_policies WHERE schemaname='public');"
# → só tabelas sem tenant (estados, bancos, migrations, jobs...)
```

---

## FASE F5 — Higienização de débito técnico

> **⚠️ DETALHAMENTO PENDENTE DA SEÇÃO 5 — E A SEÇÃO 5 NÃO FOI VALIDADA.**
>
> A seção 5 da auditoria — *"erros da refatoração"* — existe (`AUDITORIA.md` §10), mas **não passou pelo crítico do gauntlet**: a verificação foi interrompida por limite de sessão. Seus 4 achados são **hipóteses não confirmadas**.
>
> Mais grave: antes de ser cortado, o crítico **já havia derrubado uma das refutações** da seção. Ela afirma que "os 6 jobs todos declaram `$tries`", e isso é falso — `TenantAwareJob` é uma *trait*, não um job, e **`NotificarEstoqueBaixoJob` é um `ShouldQueue` sem `$tries`, sem `$backoff` e sem `failed()`**, com dois `return` silenciosos em caminhos de falha. Ou seja, a seção declarou ausente um defeito que existe.
>
> **Portanto, a primeira tarefa desta fase não é absorver a seção 5 — é reauditá-la.**

### T5.0 — Corrigir o job sem tratamento de falha — **[rastreado: crítico da Auditoria §10, achado parcial]**

**Contexto.** `NotificarEstoqueBaixoJob` implementa `ShouldQueue` mas não declara `$tries`, `$backoff` nem `failed()`, e retorna silenciosamente em dois caminhos de erro sem log. Com o `retry_after` de 90s (`config/queue.php:43`), um job sem `$tries` explícito herda comportamento de retry indefinido do driver.

**Passos.** Localizar o arquivo (`erp-novo/app/Jobs/NotificarEstoqueBaixoJob.php`); declarar `$tries` e `$backoff` coerentes com os demais jobs do projeto; implementar `failed(\Throwable $e)` com log; substituir os `return` silenciosos por log de aviso.

**Pronto quando (binário).** `grep -c 'tries\|backoff\|failed' erp-novo/app/Jobs/NotificarEstoqueBaixoJob.php` ≥ 3, e nenhum caminho de falha retorna sem registrar log.

### T5.1 — Reauditar e absorver a seção 5 — **[placeholder]**

**Instrução ao agente Opus 5 que retomar este plano.**

0. **Antes de qualquer coisa:** submeter a seção 5 ao mesmo teste cego das demais — 5 perguntas difíceis, respondidas primeiro pelo texto e depois verificadas no código. Tratar cada uma das quatro *refutações* dela (migrations 100% snake_case, jobs com `$tries`, TODOs falsos positivos, testes com asserções reais) como suspeita, dado que uma delas já caiu. Corrigir a seção antes de derivar tarefas dela.
1. Ler `docs/gauntlet/AUDITORIA.md` seção 5 (e `.../scratchpad/gauntlet/AUDITORIA.md §10`, se disponível).
2. Para cada achado, escrever uma tarefa no formato desta fase: contexto mínimo, referência ao achado com arquivo:linha, passos, definição binária de pronto.
3. **Classificar por risco de virada**, não por elegância. Débito técnico que causa comportamento errado em produção sobe para F1/F2/F4. Débito que só custa manutenção fica aqui, fora do caminho crítico.
4. Não trate F5 como pré-requisito de F6 salvo se a seção 5 apontar defeito que produza comportamento errado em produção.

**Sinais já conhecidos de débito estrutural, das outras seções** (**[não rastreado à seção 5]** — são achados de forma, registrados nas seções 1, 2 e 4; podem ou não coincidir com o que a seção 5 apontar):
- `erp-novo/app/Etl/Loaders/`, `Readers/`, `Transformers/` são **cascas vazias** (só `.gitkeep`) — a estrutura de diretórios anuncia uma arquitetura Reader→Transformer→Loader que **não existe**. Migrators monolíticos de 4.000 a 37.470 bytes (`ComplementosMigrator.php`). (Auditoria §5 §4.1.1)
- `erp-novo/app/Policies/` está **vazio** — a autorização não usa Policies do Laravel, e sim Gates em `AuthServiceProvider.php`. Funciona, mas o diretório vazio engana quem lê. (Auditoria §3 §1.3)
- `BalanceInvariant` é a invariante mais valiosa do desenho (Σ movimentos = saldo materializado) e **pode estar declarada e nunca usada** — não apareceu na saída do `cutover:check`. *Para verificar:* `grep -rn "BalanceInvariant" erp-novo/app/Etl/Migrators/`. (Auditoria §5 §4.7-6)
- Comentário do compose fala em "11 comandos" agendados; `routes/console.php` define **8**. Documentação interna desatualizada. (Auditoria §9 §7.2)
- `MigrationResult` tem campo `avisos` (`app/Etl/Support/MigrationResult.php:15`) que **nenhum** dos 68 catches usa. (Auditoria §5 §4.4) — parcialmente endereçado pela T2.6.

**Pronto quando (binário).** Esta seção do plano deixa de conter a palavra "placeholder" e contém N tarefas rastreadas à seção 5 com critérios de aceite verificáveis.

---

## FASE F6 — Cutover e pós-cutover

**Objetivo.** Sair do legado (`ctrl-web/` em `gasemcasa.com`) para o novo (`erp-novo/`) em produção, com rollback executável a qualquer momento da janela.

### O que o código realmente suporta (leia antes de planejar a janela)

Estes são fatos verificados, não suposições — e eles **determinam** o formato da janela:

- **`etl:run` é carga cheia, sempre.** `erp-novo/app/Console/Commands/EtlRun.php` roda os 28 migrators em ordem de dependência. As **únicas** opções são `{migrator?}`, `--dry-run` e `--check`. **Não existe modo incremental/delta por timestamp.** (Auditoria §9 §7.7)
- **A recarga é idempotente por upsert preservando id** (`PreservaIdsDoLegado.php`: upsert por `['id']` + `setval(pg_get_serial_sequence(...))`). Três consequências verificadas no código:
  - Re-rodar o ETL **sobrescreve** qualquer linha de id legado editada no sistema novo — o upsert vence;
  - Linhas **excluídas no legado não são removidas** no novo — upsert não deleta;
  - Registros criados no novo (ids acima da sequence ressincronizada) são preservados.
  - **Isto é o que torna o congelamento obrigatório:** qualquer trabalho feito no sistema novo sobre uma linha do legado é perdido na recarga final.
- **`cutover:check` é o portão de dados** (`app/Console/Commands/CutoverCheck.php:20-57`): roda todas as invariantes **sem re-migrar**, exit ≠ 0 bloqueia. Read-only por construção.
- **`golive:check` é o portão de infra** — existe, mas hoje roda **informativo** no workflow (`|| true` na linha 214 do de homolog). A T3.6 o torna bloqueante em produção.
- **Duração e memória:** `EtlRun` seta `memory_limit=3G` (justificado no comentário pelos ~443 mil títulos) e o job fala em **horas** sobre ~16 milhões de linhas. A janela de congelamento precisa comportar isso, **e o deploy não pode reciclar o container no meio**. (Auditoria §9 §7.7.3)
- **`deploy/nginx/gasemcasa-com.conf`** já roteia `/` e `/novo/` para o container novo (:3120) e o resto para o legado (:3110) — a coexistência strangler está **desenhada no repositório**. A virada é uma mudança nesse arquivo. (Auditoria §9 §7.4)

---

### T6.1 — Escrever o runbook do cutover — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §9 §7.7.1: *"Janela de congelamento do legado + recarga final completa é o único caminho suportado (não há delta). Procedimento: congelar escrita no legado → `etl:run` completo contra o legado de produção → `cutover:check` → `golive:check --strict` → virada do Nginx. **Nada disso está roteirizado como runbook/workflow.**"*

**Passos.** Escrever `deploy/CUTOVER_RUNBOOK.md` contendo, na ordem:

**D-7 (uma semana antes)**
1. Ensaio completo em staging, com dump **real** de produção. Cronometrar cada passo — é assim que se dimensiona a janela.
2. `cutover:check` verde no ensaio.
3. Restore testado (T3.5) e cronometrado.
4. Comunicação aos usuários com data, hora e duração.

**D-1**
5. Backup completo do legado (banco Oracle + MySQLs) e do que já existe do novo.
6. `golive:check --strict` verde em produção.
7. Confirmar que os gates externos (T3.10) estão provisionados: Firebase, certificados A1 por empresa, PIX por empresa.

**D-0, janela**
8. **Congelar escrita no legado.** Método concreto: colocar o `ctrl-web` em modo manutenção (`php artisan down`) **e** revogar o privilégio de escrita da role de aplicação no Oracle/MySQL — cinto e suspensório. Anotar o timestamp exato do congelamento.
9. Recarga final: `php artisan etl:run --check` contra o legado **completo de produção** (as conexões `legado`, `app_legado`, `monitora_legado` de `config/database.php`). Esperar horas. **Não** reciclar o container.
10. **PORTÃO 1:** `php artisan cutover:check` → **0 falhas**. Se falhar, ver T6.7.
11. **PORTÃO 2:** `php artisan golive:check --strict` → exit 0.
12. Smoke test funcional (T6.4).
13. **PORTÃO 3:** decisão humana de virar (T6.5).
14. Virada do Nginx (T6.3).
15. Verificação pós-virada (T6.6).

**Pronto quando (binário).** `deploy/CUTOVER_RUNBOOK.md` existe, foi **ensaiado por inteiro em staging pelo menos uma vez**, e cada passo tem tempo medido registrado ao lado.

---

### T6.2 — Preparar o banco de produção limpo — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §9 §7.7.4: *"Dados criados em homolog (massa demo Guarapuava/marketplace) **não podem existir** no banco de produção — o banco de produção deve nascer vazio + `DeploySeeder` + ETL (nunca reaproveitar o banco de homolog)."*

**Passos.**
1. Criar o banco de produção **vazio**, com a role restrita `erp_app` (`NOSUPERUSER NOBYPASSRLS`) e o owner separado (`pgsql_owner`). Confirmar que `RLS_APP_DB_PASSWORD` está no `.env` — sem ela a migration `2026_06_26_000400_rls_role_app_sem_bypass.php:33` é **NO-OP silencioso**.
2. `php artisan migrate --force --database=pgsql_owner`.
3. `php artisan db:seed --class=DeploySeeder --force` — **só ele**, com as `*_SEED_PASSWORD` reais no ambiente (T1.1 agora impede rodar sem).
4. **Verificar que nenhum seeder demo rodou** (T3.7).
5. Provisionar as conexões de origem no `.env` de produção: `LEGADO_DB_*` com `LEGADO_DB_SCHEMA=legado`, `APP_LEGADO_DB_*`, `MONITORA_LEGADO_DB_*` — nenhuma delas está em template hoje (Auditoria §9 §7.1.2), então vêm da T3.1.
6. Rodar o espelho `espelhar_oracle.py` contra o Oracle de produção, já com o MAPA ampliado pelas T2.3 e T2.7.

**Pronto quando (binário).**
```sql
SELECT count(*) FROM public.clientes;    -- 0 (antes do ETL)
SELECT count(*) FROM public.pedidos;     -- 0
SELECT count(*) FROM public.users;       -- só as contas do DeploySeeder
SELECT rolbypassrls, rolsuper FROM pg_roles WHERE rolname='erp_app';  -- f, f
SELECT count(*) FROM information_schema.tables WHERE table_schema='legado';  -- ≥ 121
```

---

### T6.3 — Estratégia de virada do Nginx (com reversão em 1 comando) — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §9 §7.4: `deploy/nginx/gasemcasa-com.conf` já roteia `/` e `/novo/` para o container novo (:3120) e o resto para o legado (:3110). A aplicação real na VPS é **NÃO VERIFICADA** — confirmar antes.

**Passos.**
1. **Verificar o estado real na VPS**: `nginx -T` e comparar com `deploy/nginx/gasemcasa-com.conf` do repo. Se divergirem, o repo é a ficção — reconcilie antes de planejar.
2. Preparar **dois** arquivos de vhost completos: `gasemcasa-com.legado.conf` (estado atual) e `gasemcasa-com.novo.conf` (tudo para :3120, exceto o que a triagem T4.9 decidiu manter no legado).
3. Virada = symlink + `nginx -t && nginx -s reload`. Script `deploy/nginx/virar.sh <legado|novo>`.
4. **Testar a reversão antes da janela**: virar para novo, verificar, virar de volta para legado, verificar. Em staging.
5. Cronometrar. A reversão de Nginx é o rollback mais rápido disponível (segundos) — é a primeira linha de defesa.

**Pronto quando (binário).**
```bash
bash deploy/nginx/virar.sh novo && curl -sf https://gasemcasa.com/ && \
bash deploy/nginx/virar.sh legado && curl -sf https://gasemcasa.com/
# ambos HTTP 200; ensaiado em staging; tempo medido registrado
```

---

### T6.4 — Smoke test funcional pós-recarga — **[BLOQUEANTE]**

**Achado que justifica.** Auditoria §5 §4.7-5: *"Cobertura coluna-a-coluna. Auditei contagens, somas e FKs — **não** comparei valores campo a campo. **Um migrator pode gravar a contagem certa com a coluna trocada e passar em tudo aqui.**"* O `cutover:check` prova volume e integridade; **não** prova que o dado certo está na coluna certa. O smoke test é a única defesa contra isso.

**Passos.** Roteiro executado por um **operador humano** que conhece o negócio, na janela, com o sistema já carregado e antes de virar o Nginx:
1. **Cliente conhecido:** buscar um cliente real pelo nome, conferir telefone, endereço, histórico de pedidos e saldo financeiro **contra o legado aberto ao lado**. Repetir para 5 clientes de perfis diferentes (PF, PJ, convênio, inadimplente, do app).
2. **Pedido de ponta a ponta:** criar pedido pela SPA → mudar situação para CONCLUÍDO → verificar que baixou estoque (`estoquesaldos`) e gerou financeiro (`financeiros`/`financeiroparcelas`). Este é o fluxo central de `app/Domain/Pedido/PedidoService.php` + `EfeitoPedido.php`.
3. **Fiscal:** emitir uma NFC-e real (`POST /api/admin/pedidos/{id}/emitir-nfce`) e conferir na SEFAZ. **Este é o teste que só funciona com o certificado A1 real** (T3.10).
4. **App do cliente:** login com Firebase **real** (não `fake:`), criar pedido, gerar PIX, confirmar o webhook.
5. **App do entregador:** login, iniciar jornada, aceitar pedido, atualizar posição, concluir entrega. Verificar o tempo real chegando (Reverb — T3.9).
6. **Financeiro:** conferir totais. Referência da auditoria: `SELECT round(sum(valor)::numeric,2) FROM public.financeiros` deve bater com `legado.financeiros` **ao centavo** — isso foi verificado como R$ 250.029.904,80 no ambiente local e é o ponto mais forte da migração.
7. **Multi-tenant:** logar como usuário de uma empresa e confirmar que **não vê** dados de outra (a RLS deve barrar mesmo se a aplicação falhar).

**Pronto quando (binário).** Checklist com **todos** os itens marcados por um humano nomeado, e a soma financeira batendo ao centavo. **Qualquer item reprovado = não vira.**

---

### T6.5 — Critérios de GO / NO-GO — **[BLOQUEANTE]**

**Contexto.** A auditoria de dados é categórica sobre o defeito de processo: *"o autoteste da migração existe, funciona e está reprovando — e mesmo assim a migração consta como concluída. **O portão não está sendo respeitado como portão.**"* Esta tarefa existe para que isso não se repita.

**Critérios de GO — todos obrigatórios, sem exceção:**

| # | Critério | Comando que prova |
|---|---|---|
| 1 | Portão de dados verde | `php artisan cutover:check` → **0 falhas**, exit 0 |
| 2 | Portão de infra verde | `php artisan golive:check --strict` → exit 0 |
| 3 | Smoke test 100% aprovado | Checklist T6.4 assinado |
| 4 | Backup pré-virada concluído e **verificado** | T3.4 + checksum conferido |
| 5 | Restore ensaiado, RTO conhecido | T3.5, tempo registrado |
| 6 | Reversão de Nginx ensaiada | T6.3 |
| 7 | Equipe de plantão disponível | escala nomeada |
| 8 | Janela com folga ≥ 2× o tempo ensaiado do ETL | do ensaio T6.1 |

**NO-GO automático** — qualquer um destes aborta a virada, sem discussão:
- `cutover:check` com **≥ 1 falha**;
- `golive:check --strict` com exit ≠ 0;
- qualquer item do smoke test reprovado;
- backup pré-virada falho ou não verificado;
- ETL estourando a janela planejada.

**Passos.**
1. Registrar estes critérios como seção do runbook (T6.1).
2. Nomear **quem** decide (uma pessoa, não um comitê).
3. Definir a hora-limite de decisão: se os portões não estiverem verdes até `H`, aborta e remarca — **não** se estende a janela improvisando.

**Pronto quando (binário).** A seção existe no runbook, com o decisor nomeado e a hora-limite definida. Na janela, a decisão é registrada por escrito com a saída dos comandos anexada.

---

### T6.6 — Verificação pós-virada e período de observação — **[BLOQUEANTE]**

**Passos.**
1. **Primeiros 15 minutos:** health checks (`/novo/up`), taxa de erro no Sentry (T3.9), logs do worker e do scheduler, conexões do banco.
2. **Primeira hora:** confirmar que os 8 agendamentos de `routes/console.php` rodaram — especialmente `pix:expirar` (a cada minuto) e `logistica:gerar-missoes` (a cada 10 min). Confirmar que os jobs estão sendo consumidos (`AtribuirPedidoJob`, `EnviarPushJob`) — sem worker, auto-atribuição e push ficam **inertes** (Auditoria §9 §7.2).
3. **Primeiro dia:** acompanhar com o operador. Cada "não consigo fazer X" é uma lacuna da triagem T4.9 se materializando — registre e cruze.
4. **Legado em modo leitura:** manter o `ctrl-web` **de pé mas sem escrita** por pelo menos 30 dias. É a rede de consulta para o que a T4.9 classificou como `PÓS-GO-LIVE` e a fonte para qualquer reconciliação.
5. **Congelar o ETL:** depois da virada, o legado não recebe mais escrita, então re-rodar `etl:run` **sobrescreveria** dados criados no novo sobre ids legados (comportamento verificado do upsert). Desabilitar o comando ou protegê-lo com uma flag explícita `--eu-sei-o-que-estou-fazendo`.

**Pronto quando (binário).**
```bash
# scheduler rodou
docker compose -f erp-novo/docker-compose.producao.yml logs scheduler | grep -c "pix:expirar"   # > 0
# fila consumindo
psql -Atc "SELECT count(*) FROM failed_jobs WHERE failed_at > now() - interval '1 hour';"        # 0
# health
curl -sf https://gasemcasa.com/novo/up
```
E 24 h sem incidente crítico.

---

### T6.7 — Gatilho e procedimento de rollback — **[BLOQUEANTE]**

**Contexto.** Três níveis, do mais barato ao mais caro. **Escolha sempre o mais barato que resolve.**

**Nível 1 — Reversão de Nginx (segundos).** *Gatilho:* o sistema novo está errado, mas o legado ainda está intacto e sem escrita nova. *Procedimento:* `bash deploy/nginx/virar.sh legado` + `nginx -s reload`; reabrir escrita no legado; comunicar. *Perda:* qualquer trabalho feito no novo depois da virada. **Este é o caminho padrão durante a janela e nas primeiras horas.**

**Nível 2 — Rollback de aplicação (minutos).** *Gatilho:* o deploy do novo quebrou, mas os dados estão bons. *Procedimento:* `bash deploy/rollback.sh <sha-anterior>` (T3.8). *Restrição:* só funciona se as migrations do deploy revertido forem aditivas — daí a regra da T3.8.3 de nunca introduzir migration destrutiva no mesmo deploy.

**Nível 3 — Restore de banco (horas — o RTO cronometrado na T3.5).** *Gatilho:* corrupção de dados detectada no novo depois de escrita real. *Procedimento:* `bash deploy/backup/restore.sh <backup>` (T3.5); reverter Nginx; reconciliar manualmente o que foi criado entre o backup e o restore. *Custo:* alto e com perda de dados no intervalo.

**Passos.**
1. Escrever os três níveis no runbook, com o gatilho de cada um **em linguagem observável** ("taxa de erro 5xx acima de X% por Y minutos", "operador não consegue fechar o caixa", "divergência financeira detectada"), não em linguagem de julgamento.
2. Definir a **janela de decisão de rollback**: até `T+N horas` após a virada, a reversão é Nível 1 e não exige aprovação — é a decisão default diante da dúvida. Depois disso, o legado já está defasado demais e cada nível fica mais caro.
3. Ensaiar o Nível 1 e o Nível 2 em staging. O Nível 3 já foi ensaiado na T3.5.
4. Nomear quem pode acionar cada nível.

**Pronto quando (binário).** Os três níveis estão no runbook com gatilho observável, procedimento em comandos, custo estimado e responsável nomeado. Níveis 1 e 2 ensaiados com tempo medido.

---

### T6.8 — Aposentadoria formal do legado — **[pós-cutover]**

**Achado que justifica.** Auditoria §4 §2.6 (7 famílias de problema de segurança no legado, incluindo SQLi sistêmico com 137 `whereRaw` e o bypass de autorização por AJAX ligado por default) e Auditoria §9 §7.4.5 (o runner self-hosted compartilhado obriga o deploy do erp-novo a restaurar o `.env` do ctrl-web para não derrubar o legado — acoplamento frágil documentado no próprio workflow).

**Passos** (após ≥ 30 dias de operação estável):
1. Confirmar com a triagem T4.9 que nada `PÓS-GO-LIVE` ainda depende do legado.
2. Backup final e arquivamento do Oracle e dos MySQLs.
3. Desligar o container do legado (:3110); remover o roteamento residual do Nginx.
4. **Remover o acoplamento do workflow**: apagar do workflow de produção os passos que restauram o `.env` do ctrl-web (linhas 50-65 do de homolog).
5. Revogar as credenciais do legado, incluindo a `APP_KEY` que a auditoria registra como vazada (Auditoria §4 §2.6-7: *"a app_key era `sha1(APP_KEY)` — e a APP_KEY vazou no repo"*).
6. Arquivar `ctrl-web/` como somente-leitura.

**Pronto quando (binário).**
```bash
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3110/    # → 000 (não responde)
grep -c "ctrl-web" .github/workflows/deploy-erp-novo-producao.yml  # → 0
```
E o backup final do Oracle/MySQL está arquivado com checksum verificado.

---

### O QUE NÃO FAZER na F6

- ❌ **NUNCA** vire com `cutover:check` vermelho. Este é o defeito de processo que a auditoria diagnosticou como raiz de tudo: *"o portão não está sendo respeitado como portão."* Se ele está vermelho, o problema está nos dados ou na invariante — resolva a causa, não o placar.
- ❌ **Não** rode `etl:run` depois da virada sem entender o efeito. O upsert por `id` **sobrescreve** linhas de id legado editadas no sistema novo. Uma recarga "por precaução" após 3 dias de operação apaga 3 dias de correções de cadastro.
- ❌ **Não** planeje cutover incremental. **Não existe modo delta** — as únicas opções de `etl:run` são `{migrator?}`, `--dry-run`, `--check`. Congelamento + recarga cheia é o único caminho suportado pelo código.
- ❌ **Não** recicle o container durante o ETL. O `EtlRun` roda por horas com `memory_limit=3G`; um `--force-recreate` no meio deixa a carga pela metade sem sinalizar.
- ❌ **Não** desligue o legado no dia da virada. Ele é a rede de consulta e a fonte de reconciliação por pelo menos 30 dias (T6.6.4). Desligar cedo transforma um Nível 1 (segundos) num Nível 3 (horas).
- ❌ **Não** vire numa sexta-feira, nem em véspera de feriado, nem em pico de demanda (para uma distribuidora de GLP: início de mês e frio). Vire quando houver dia útil seguinte para acompanhar.
- ❌ **Não** deixe a decisão de GO/NO-GO para o calor da janela. Os critérios da T6.5 são escritos e acordados **antes**.
- ❌ **Não** reaproveite o banco de homologação. Auditoria §9 §7.7.4 é explícito, e a massa demo de Guarapuava misturada com dados reais é praticamente irreversível.

---

## Apêndice A — Índice de rastreamento tarefa → achado

| Tarefa | Achado | Evidência primária |
|---|---|---|
| T1.1 | Auditoria §8 C1, Auditoria §9 §7.1.3 | `DeployAdminSeeder.php:35-36`, `SuperAdminSeeder.php:20-21`, `DeploySeeder.php:20-26` |
| T1.2 | Auditoria §8 C1-agravante, M2, M3 | `frontend/src/features/auth/LoginPage.tsx:11,33,134-138`; `SaLoginPage.tsx:8,47-50` |
| T1.3 | Auditoria §8 C2 | `AppServiceProvider.php:121-129`; `config/services.php:66,74,83`; `FakeFirebaseVerifier.php:15-27` |
| T1.4 | Auditoria §8 A1 | `routes/api.php:85,88,91` vs `:79,102` |
| T1.5 | Auditoria §8 A2 | `config/sanctum.php:57`; `AuthController.php:92` |
| T1.6 | Auditoria §8 A3 | `composer audit` — 18 advisories / 3 pacotes |
| T1.7 | Auditoria §8 A4 | `npm audit --omit=dev` — 4 vulns |
| T1.8 | Auditoria §8 B2 | `app/Models/User.php:22-30`; `AuthServiceProvider.php:28` |
| T1.9 | Auditoria §8 M1 | `app-entregador/eas.json:16`, `app-gas-em-casa/eas.json:16` |
| T2.1 | Auditoria §5 A-1 | `AppGasEmCasaMigrator.php:182,187,191,221,477-500`; `PreservaIdsDoLegado.php:47` |
| T2.2 | Auditoria §5 A-1 (impacto) | 44.416 linhas / 11.104 api_ids × 4; 430 pedidos em `cliente_id>101122` |
| T2.3 | Auditoria §5 A-3, §4.7-4 | `PagamentoMigrator.php`; Oracle: `BENEFICIARIOS`, `PIXTRANSACTIONS`; `espelhar_oracle.py:92` |
| T2.4 | Auditoria §5 A-2 | `CountInvariant.php:19,38-55`; `AppGasEmCasaMigrator.php:252-258` |
| T2.5 | Auditoria §5 A-5, §4.7-3 | empresas +3, cidades −1, boletos −409, nfemitidas −3 |
| T2.6 | Auditoria §5 A-4 | 68 catches; `AppGasEmCasaMigrator.php:487-489,618-619,625-626`; `MigrationResult.php:15` |
| T2.7 | Auditoria §5 §4.2.3, §4.7-2 | MAPA=121 vs Oracle=222 |
| T2.8 | Auditoria §5 §4.7-1 | produção NÃO VERIFICADA |
| T3.1 | Auditoria §9 §7.1.1, §7.1.2 | `.env.homolog.example` (60 linhas); tabela de chaves não documentadas |
| T3.2 | Auditoria §9 §7.2, §7.4.1 | `docker-compose.homolog.yml` (7 containers + rede na linha 154) |
| T3.3 | Auditoria §9 §7.1.5, §7.2 | `config/queue.php:43,71` vs `ExecutarMigracaoJob.php:21` |
| T3.4 | Auditoria §9 §7.6 | grep `pg_dump\|backup\|restore` = só `restore-keys` do CI |
| T3.5 | Auditoria §9 §7.6 | "backup + restore testado antes do go-live" |
| T3.6 | Auditoria §9 §7.4.1, §7.4.4 | `deploy-erp-novo-homolog.yml:214` (`\|\| true`) |
| T3.7 | Auditoria §9 §7.4.2 | workflow `:174-190,192-198`; `DemoGuarapuavaSeeder.php:108` |
| T3.8 | Auditoria §9 §7.4.3 | imagem sem tag; bind-mount |
| T3.9 | Auditoria §9 §7.2 (Reverb), §7.5 | grep `3121\|reverb\|wss` em `deploy/nginx/*` = vazio; sem Sentry; `LOG_STACK=single` |
| T3.10 | Auditoria §9 §7.3, §7.8-6 | `GoliveCheck.php:197-202,215-221`; `CertificadoService.php` |
| T4.1 | Auditoria §2 linha 4, §3.3-4 | `InconsistenciaController.php:48-91` vs `routes/api.php:226` |
| T4.2 | Auditoria §2 linha 11, §3.3-11 | `ContaController.php:670-700` vs grep `extratoconfig` = zero |
| T4.3 | Auditoria §2 linha 33, §3.3-33 | `FechamentomaloteController.php` vs grep `malote` = só config contábil |
| T4.4 | Auditoria §2 linha 32, §3.3-32 | `NotificacoesController.php` vs greps `bina`/`chamada` = ruído |
| T4.5 | Auditoria §2 linha 34, §3.3-34 | `TipodocumentoController.php` vs grep `tipodoc` = zero |
| T4.6 | Auditoria §2 §3.4 família (1); linhas 7,12,14,19,20 | `BoletoPdfController.php`; grep `recibo` = zero |
| T4.7 | Auditoria §2 §3.4 família (2); linhas 21,22 | `routes/api.php:586-587,597-610` (só GET) |
| T4.8 | Auditoria §2 linha 7, §3.3-7 | `PedidomotivoatrasoController.php`, `MotivonaovendaController.php` |
| T4.9 | Auditoria §2 §3.4, §3.5 | 22 ⚠️ + 4 ❌ de 46 linhas |
| T5.1 | **seção 5 — pendente** | — |
| T6.1–T6.8 | Auditoria §9 §7.7 | `EtlRun.php`; `CutoverCheck.php:20-57`; `PreservaIdsDoLegado.php`; `gasemcasa-com.conf` |

## Apêndice B — Comandos de verificação de uso recorrente

```bash
# PORTÃO DE DADOS (read-only; nunca re-migra)
cd erp-novo && php artisan cutover:check

# PORTÃO DE INFRA
cd erp-novo && php artisan golive:check --strict

# ETL com validação de invariantes por migrator
cd erp-novo && php artisan etl:run --check
cd erp-novo && php artisan etl:run <Migrator> --dry-run

# Suítes
cd erp-novo && php artisan test
cd erp-novo/frontend && npm run type-check && npm test && npm run build

# Segurança de dependências
cd erp-novo && composer audit
cd erp-novo/frontend && npm audit --omit=dev

# Duplicação de clientes (deve retornar 0 linhas)
psql -c "SELECT api_id, count(*) FROM public.clientes
         WHERE api_id IS NOT NULL GROUP BY api_id HAVING count(*) > 1;"

# Órfãos (todos devem ser 0)
psql -c "SELECT count(*) FROM public.pedidos p LEFT JOIN public.clientes c
         ON c.id=p.cliente_id WHERE p.cliente_id IS NOT NULL AND c.id IS NULL;"

# Fidelidade financeira (o ponto mais forte da migração — não regrida)
psql -c "SELECT round(sum(valor)::numeric,2) FROM public.financeiros;"   -- 250029904.80

# Tabelas sem RLS
psql -c "SELECT tablename FROM pg_tables WHERE schemaname='public'
         AND tablename NOT IN (SELECT tablename FROM pg_policies WHERE schemaname='public');"

# Role de runtime sem bypass de RLS
psql -c "SELECT rolname, rolsuper, rolbypassrls FROM pg_roles WHERE rolname='erp_app';"  -- f, f
```
