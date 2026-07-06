# Plano — Segurança Multi-Tenant dos Apps (Consumidor + Entregador)

> Fecha os furos encontrados na auditoria de 2026-07-05 sobre a interação dos apps
> com a plataforma multi-empresa: credenciais de pagamento fail-closed, webhook PIX
> fail-closed, separação de papel nos tokens, escopo por cliente, credencial por
> recurso (não por contexto ambient) e caminho do app consumidor para marketplace.
>
> **Execução: uma fase por vez, commit+push na main ao concluir cada fase.**

## STATUS — TODAS AS FASES IMPLEMENTADAS (2026-07-06)

| Fase | Commit | Testes |
|---|---|---|
| 1 Webhook fail-closed | `bdb1d13` | PixWebhookFailClosedTest |
| 2 Credencial fail-closed | `a43b5a4` | IntegracaoFailClosedTest |
| 3 Ability de papel | `31d8db2` | AppRoleTest |
| 4 Escopo por cliente | `340f8ba` | AppPedidoEscopoClienteTest |
| 5 Credencial em jobs | `a0db628` | IntegracaoForaDeRequestTest |
| 6 PixDriver por empresa | `dfac0c3` | PixDriverTest |
| 7 Marketplace no app + cobertura | `dc43e80` | AppPedidoCoberturaTest |
| 8 Regressão multi-tenant | (este commit) | MultiTenantIsolamentoTest |

Pendências que continuam DE FORA deste plano (dependem de terceiros):
driver PIX real por PSP (homologação bancária — o contrato/gate `PIX_DRIVER`
já existe e o binding EXPLODE com driver desconhecido) e restrição da Maps key
embarcada no app por package/SHA-1 (console do Google).

---

## ⚠️ REGRA DE OURO — REANALISAR ANTES DE CADA FASE

**Antes de escrever qualquer linha de código de uma fase, RELEIA os arquivos reais
envolvidos naquela fase. NÃO confie em NENHUMA documentação — nem neste plano, nem
em `INTEGRACOES_MULTITENANT.md`, nem em docblocks, nem em memória de sessões
anteriores.** O código é a única fonte de verdade:

1. Abra e leia os arquivos listados na fase (e os que eles chamam/injetam).
2. Confirme que o problema descrito AINDA existe e se manifesta como descrito
   (linhas mudam; outra sessão pode já ter corrigido parcialmente).
3. Confirme assinaturas, nomes de métodos, formato de retorno e testes existentes
   ANTES de decidir o desenho final — se o código real divergir deste plano,
   **o código real manda** e o plano deve ser ajustado (e atualizado neste .md).
4. Rode a suíte relevante antes de mexer, para conhecer a linha de base.

Este aviso vale para TODAS as fases e não é opcional.

---

## Contexto (estado observado em 2026-07-05 — reverificar!)

- `app/Domain/Integracao/IntegracaoTenant.php` — resolvedor central de credenciais
  (empresa → grupo → plataforma/env). Segredos cifrados por valor, write-only.
- `app/Http/Controllers/Api/PixWebhookController.php` — webhook público; resolve a
  empresa pelo `txid` e valida HMAC da empresa. **Fail-open sem segredo.**
- `app/Domain/Cobranca/PixService.php` — cria cobrança e processa webhook; BR Code
  ainda sintético (recebedor fixo), não consome `IntegracaoTenant::pix()`.
- `app/Domain/Mobile/Drivers/EredeDriver.php` — cartão; credencial da empresa ativa
  via `IntegracaoTenant::cartao()`, que **cai no env incondicionalmente**.
- `app/Http/Controllers/Api/Mobile/AppAuthController.php` — tokens Sanctum emitidos
  **sem abilities** (cliente e entregador indistinguíveis no token).
- `app/Http/Controllers/Api/Mobile/AppPedidoController.php` — `pagar()` escopa só
  por `empresa_id` (sem `cliente_id`); `gerarPix`/`statusPix`/`acompanhar` escopam certo.
- `app/Providers/AppServiceProvider.php` — bindings `MatrizDistancia`/`TracadorRota`
  resolvem a Google key do grupo via `TenantContext` ambient (vazio em fila/cron).
- `routes/api.php` — grupo `app/v1` autenticado compartilhado entre cliente e
  entregador, sob `auth:sanctum` + `tenant` + `throttle:api`.
- Apps Expo: `app-gas-em-casa` (consumidor; `empresa_id` embarcado no build via
  `extra.empresaId`) e `app-entregador` (tenant só do token — correto).

## Invariantes que este plano instala (critério de aceite global)

- **I1** — Credencial de dinheiro (PIX/cartão) é sempre resolvida pela **empresa do
  recurso** (`pedido.empresa_id` / `cobranca.empresa_id`), nunca por input do
  cliente, nunca por contexto ambient dentro de job/cron.
- **I2** — **Fail-closed em produção**: empresa sem credencial própria → operação
  de pagamento indisponível (erro claro); webhook sem segredo verificável → 401.
  Fallback env só existe fora de produção.
- **I3** — Segredos nunca descem ao app nem aparecem em GET; app recebe apenas
  artefatos (copia-e-cola, QR, status).
- **I4** — Token do app declara o papel (ability `role:cliente` / `role:entregador`)
  e as rotas exigem o papel correto.
- **I5** — Todo acesso a pedido no app do cliente é escopado por
  `empresa_id + cliente_id`; no app do entregador por `empresa_id + entregador_id`.

---

## FASE 1 — Webhook PIX fail-closed (fraude real; a mais urgente)

**Problema (reconfirmar no código):** em `PixWebhookController`, a camada 1
(`X-Webhook-Token`) só valida se `services.pix.webhook_secret` estiver setado, e
`hmacValido()` retorna `true` quando nem a empresa nem o env têm HMAC. Cenário de
fraude: o cliente gera o PIX do próprio pedido (conhece `txid` e valor exato) e
chama o webhook público ele mesmo → pedido "pago" sem pagamento.

**Reanálise obrigatória antes de codar:** ler `PixWebhookController.php`,
`PixService.php` (métodos `empresaIdDoTxid` e `processarWebhook`),
`IntegracaoTenant::pix()/pixConfigurado()`, `config/services.php` (bloco pix) e os
testes existentes (`tests/Feature/*Pix*`, `IntegracaoTenantTest.php`). Verificar
como o ambiente de produção é detectado no projeto (padrão já usado em outros
gates) e se existe flag de "PIX real ativo" (driver/gate).

**Mudança:**
1. Em produção (`app()->isProduction()` ou padrão equivalente já usado no repo):
   - exigir que **pelo menos uma** verificação criptográfica real aconteça:
     HMAC da empresa OU HMAC da plataforma OU `X-Webhook-Token`. Se nenhum segredo
     existir → responder **401 e logar** (nunca processar).
   - se a empresa da cobrança tem PIX configurado (`pixConfigurado()`), o HMAC
     **da empresa** passa a ser obrigatório — sem herdar o env.
2. Fora de produção, manter comportamento permissivo atual (CI/homolog).
3. Se `empresaIdDoTxid()` não encontrar a cobrança → 401/404 antes de qualquer
   processamento (hoje segue para `hmacValido(null)` que cai no env).

**Testes (novos):**
- webhook sem nenhum segredo configurado em produção → 401.
- empresa com HMAC próprio: assinatura com o segredo da empresa B não confirma
  cobrança da empresa A → 401.
- txid inexistente → rejeitado sem tocar no banco.
- reentrega idempotente continua funcionando (regressão).

**Aceite:** suíte verde; impossível confirmar cobrança sem segredo em produção.

---

## FASE 2 — Fail-closed de credenciais de dinheiro no `IntegracaoTenant`

**Problema (reconfirmar):** `cartao()` devolve `pv`/`token`/`url` do env quando a
empresa não configurou; o docblock diz "empresa obrigatório em produção" mas nada
impõe. Uma empresa sem credenciamento cobraria na conta configurada no env.

**Reanálise obrigatória:** ler `IntegracaoTenant.php` inteiro, `EredeDriver.php`,
`PagamentoOnlineService.php`, `AppPedidoController::pagar()`, o binding
`PagamentoDriver` no `AppServiceProvider`, e os testes `IntegracaoTenantTest.php`.
Mapear TODOS os call-sites de `cartao()` e `pix()` (grep) — pode haver consumidores
novos desde esta auditoria.

**Mudança:**
1. Criar exceção de domínio (ex.: `CredencialNaoConfiguradaException`) em
   `app/Domain/Integracao/`.
2. `cartao()` e (quando consumido) `pix()`: **em produção com gate real ativo**,
   empresa sem credencial própria → lançar a exceção; **nunca** devolver env.
   Fora de produção, fallback env permanece (dev/homolog/CI).
3. No fluxo do app (`pagar()` / handler de exceções): converter a exceção em
   resposta clara (ex.: 503 `"Pagamento online indisponível nesta revenda."`),
   sem stack trace, sem citar credencial.
4. `googleMapsKey()` NÃO entra no fail-closed (não é dinheiro; fallback env ok).

**Testes (novos):**
- produção + driver erede + empresa sem credencial → exceção → resposta 503 no
  endpoint, e NENHUMA chamada HTTP ao gateway (Http::fake + assertNothingSent).
- produção + empresa com credencial → usa a credencial DELA (assert no basic auth
  via Http::fake).
- não-produção → fallback env preservado (regressão dos testes atuais).

**Aceite:** impossível autorizar cartão com credencial que não seja da empresa do
pedido em produção.

---

## FASE 3 — Ability de papel no token do app + guarda de rotas

**Problema (reconfirmar):** tokens de `loginCliente`/`cadastrarCliente`/`login`
são criados sem abilities; rotas de cliente e entregador vivem no mesmo grupo
`app/v1`. Token de cliente alcança `entregador/veiculos` (placas/km da frota),
`entregador/jornada/iniciar` etc.; token de entregador alcança rotas de cliente.

**Reanálise obrigatória:** ler `AppAuthController.php` (os três logins + `refresh`
— o refresh precisa PRESERVAR as abilities do token atual), `routes/api.php`
(grupo `app/v1` completo, incluindo missões), como o app do entregador loga
(`app-entregador/src/services/auth.service.ts`) e se `login()` por e-mail/senha é
usado também por perfis não-entregador. Verificar se o middleware
`abilities`/`ability` do Sanctum está registrado em `bootstrap/app.php`.

**Mudança:**
1. `loginCliente`/`cadastrarCliente` → `createToken(nome, ['role:cliente'])`.
2. `login` (e-mail/senha do app) → `['role:entregador']` (confirmar na reanálise
   se esse endpoint atende só entregador; se atender outros perfis, decidir a
   ability pelo perfil real do usuário — NÃO chutar).
3. `refresh` → reemitir com as MESMAS abilities do token vigente.
4. `routes/api.php`: dividir o grupo `app/v1` autenticado em dois sub-grupos com
   `abilities:role:cliente` e `abilities:role:entregador` (rotas comuns — logout,
   refresh, devices — ficam fora dos sub-grupos).
5. **Migração de tokens vivos:** tokens antigos não têm ability → decidir na
   reanálise: ou aceitar `*` legado por N dias (janela curta e documentada), ou
   invalidar tokens de app forçando re-login (preferível: ainda não há produção).

**Testes (novos):**
- token de cliente em rota de entregador → 403; e vice-versa.
- refresh preserva a ability.
- fluxos felizes de ambos os papéis continuam passando (regressão).

**Aceite:** nenhuma rota de entregador responde a token de cliente e vice-versa.

---

## FASE 4 — Escopo por cliente no `pagar()` (cartão)

**Problema (reconfirmar):** `AppPedidoController::pagar()` busca o pedido só por
`empresa_id`; qualquer cliente da empresa pode disparar cobrança de cartão contra
pedido de outro cliente (e ler `situacao`/`tid`/`mensagem`).

**Reanálise obrigatória:** ler `AppPedidoController.php` inteiro (conferir TODOS
os métodos que carregam Pedido — pode haver outro com o mesmo furo, ex.:
`rotaEntregador`, `cancelar`, `avaliar`) e o helper `clienteDoUsuario()`.

**Mudança:** aplicar `->where('cliente_id', $cliente->id)` (via
`clienteDoUsuario`) em `pagar()` e em qualquer outro método que a reanálise
encontre sem o filtro.

**Testes (novos):** cliente A tenta pagar pedido do cliente B (mesma empresa) →
404; dono do pedido paga normalmente (regressão).

**Aceite:** todos os métodos do controller escopam por `empresa_id + cliente_id`.

---

## FASE 5 — Credencial por recurso fora de request (jobs/cron/broadcast)

**Problema (reconfirmar):** bindings `MatrizDistancia`/`TracadorRota` no
`AppServiceProvider` resolvem `googleMapsKey()` do `TenantContext` no momento do
`make()`. Em worker de fila/cron o contexto está vazio → cai no env da plataforma
**silenciosamente** (a rede gasta a quota da plataforma; padrão perigoso se
replicado para dinheiro).

**Reanálise obrigatória:** mapear (grep) TODOS os pontos que rodam fora de
request e tocam integração: jobs em `app/Domain/**/Jobs`, comandos de cron
(`routes/console.php` / `app/Console`), listeners queued, broadcast. Identificar
quais resolvem `IntegracaoTenant`, `MatrizDistancia`, `TracadorRota` ou drivers de
pagamento. Ler `RoteirizadorService` e como ele é invocado (request? fila?).

**Mudança (direção — fechar o desenho na reanálise):**
1. Todo job/comando que precisa de credencial recebe `empresa_id`/`grupo_id`
   **explícitos** no payload e os passa aos métodos do `IntegracaoTenant`
   (`pix($empresaId)`, `googleMapsKey($grupoId)`, …) — nunca depender do
   `TenantContext` ambient fora de request.
2. Onde o job hoje é disparado de dentro de uma request, capturar o tenant no
   dispatch (não no handle).
3. Avaliar guarda de segurança: métodos do `IntegracaoTenant` chamados SEM id
   explícito E sem tenant resolvido, em contexto de fila, devem logar warning
   (dinheiro: lançar exceção — coerente com a Fase 2).

**Testes:** job de roteirização com `grupo_id` explícito usa a key DO grupo
(assert via fake/spy); job sem tenant não consome env silenciosamente para
dinheiro.

**Aceite:** nenhum caminho de fila resolve credencial de dinheiro por contexto
ambient.

---

## FASE 6 — PIX real por empresa (quando o driver do PSP entrar)

**Estado (reconfirmar):** `PixService::montarBrCode()` gera BR Code sintético com
recebedor fixo; `IntegracaoTenant::pix()` só é consumido pelo webhook (HMAC).
Esta fase define o CONTRATO para o driver real — implementar junto com a
homologação do PSP.

**Reanálise obrigatória:** reler `PixService.php` completo, `PixCobranca` (model
e migration), o gate/driver pattern dos outros serviços (Boleto/Sefaz no
`AppServiceProvider`) e `IntegracaoTenant::pix()`. O desenho abaixo deve ser
revalidado contra a API real do PSP escolhido.

**Contrato:**
1. Interface `PixDriver` (ex.: `criarCobranca(dados, credencial): {txid, copia_e_cola, qrcode}`)
   com Fake (CI/homolog) e driver real por PSP — mesmo padrão gate dos demais.
2. O driver real recebe a credencial resolvida por
   `IntegracaoTenant::pix($pedido->empresa_id)` — id **do recurso**, explícito
   (I1). Empresa sem PIX configurado em produção → exceção da Fase 2, endpoint
   responde "PIX indisponível nesta revenda" (o app oferece só os meios ativos).
3. `criarCobrancaPedido`/`criarCobranca` passam a delegar o copia-e-cola ao
   driver; o BR Code sintético fica APENAS no Fake.
4. Expor no `GET /app/v1/config` (ou equivalente — confirmar endpoint real) quais
   meios de pagamento a empresa ativa suporta (`pixConfigurado`,
   `cartaoConfigurado`) para o app montar o checkout — booleanos, nunca credencial.

**Testes:** cobrança da empresa A criada com credencial da A (spy no driver);
empresa sem PIX em produção → 503 no `gerarPix`; Fake preserva fluxo de CI.

---

## FASE 7 — App consumidor multi-empresa (marketplace de verdade)

**Estado (reconfirmar):** `app-gas-em-casa` embarca `extra.empresaId` no build
(single-tenant por build); endpoints `marketplace/empresas|cidades` existem no
servidor e não são consumidos. Entregador já é multi-tenant correto (não mexer).

**Reanálise obrigatória:** ler `app-gas-em-casa/src/constants/app.ts`,
`user.service.ts`, o fluxo de login/cadastro (`sms.tsx`, `newuser.tsx`), o store
de sessão, `MarketplaceService` (server) e `AppLojaController::init/config`.
Confirmar como o app obtém endereço/GPS hoje e o que `ClienteAuthService` faz
quando o telefone existe em OUTRA empresa.

**Mudança (direção):**
1. **Descoberta:** fluxo inicial do app = endereço/GPS →
   `POST /app/v1/marketplace/empresas` → usuário escolhe a revenda ("loja ativa"
   no estado do app). `extra.empresaId` vira apenas default opcional de build
   white-label — se ausente, o fluxo marketplace é obrigatório.
2. **Identidade multi-empresa:** o mesmo telefone deve poder comprar de N
   empresas. Manter o modelo atual (cliente por empresa, `User` por cliente):
   ao trocar de loja, o app refaz `cliente/login` com a nova `empresa_id`
   (Firebase token é reutilizável dentro da validade) ou cai no fluxo de cadastro
   daquela empresa. **Um token por loja ativa; nunca reutilizar token de uma
   empresa em outra.** (Alternativa — identidade global com N vínculos — só se a
   reanálise mostrar que o refluxo de login por troca é inviável em UX.)
3. **Validação server-side:** `criarPedido` deve revalidar que a empresa do token
   atende o endereço de entrega (polígono/cidade) — o app escolher a loja é UX,
   não segurança. Confirmar se `PedidoMobileService` já valida polígono; se não,
   adicionar.
4. **Maps key do app:** manter uma key DA PLATAFORMA embarcada, restrita por
   package/SHA-1 e por API no console do Google. As keys por grupo
   (`googleMapsKey`) são exclusivamente server-side — **nunca** enviá-las em
   nenhuma resposta de API do app (grep para garantir que nenhum endpoint devolve).

**Testes:** pedido com endereço fora da área da empresa → 422; troca de loja gera
novo token e o antigo não acessa a nova empresa; nenhum endpoint do app devolve
key/segredo (teste de contrato sobre as respostas de `init`/`config`/`reseller`).

---

## FASE 8 — Testes de regressão multi-tenant (blindagem permanente)

**Reanálise obrigatória:** inventariar os testes de feature existentes de
app/pagamento/webhook para não duplicar; conferir factories de Empresa/Cliente/
Pedido para o cenário "duas empresas".

**Suite dedicada (ex.: `tests/Feature/MultiTenantIsolamentoTest.php`):**
- Cliente da empresa A: 404 em pedido da B (todas as rotas de pedido do app).
- Entregador da empresa A: 404 em pedido da B; 403 em rota de cliente (ability).
- Webhook: HMAC da empresa B não confirma cobrança da A.
- Cartão: autorização do pedido da empresa A usa credencial da A (Http::fake,
  assert no basic auth) mesmo com credencial da B também cadastrada.
- Produção sem credencial → fail-closed (fases 1 e 2) — cobrir via config fake de
  ambiente.
- `X-Empresa-Id` forjado por token de cliente/entregador não troca o tenant
  (usuário do app não tem pivot de empresas).

**Aceite:** suíte verde no Postgres com RLS (role restrita), conforme o fluxo já
usado no projeto.

---

## Ordem, dependências e entrega

| Fase | Depende de | Tamanho | Urgência |
|---|---|---|---|
| 1 Webhook fail-closed | — | P | **crítica** (fraude) |
| 2 Credencial fail-closed | — | P | **crítica** (dinheiro cruzado) |
| 3 Ability de papel | — | M | alta |
| 4 Escopo `pagar()` | — | PP | alta (1 linha + testes) |
| 5 Credencial em jobs | 2 | M | média |
| 6 PIX real por empresa | 2, 5 | G (com PSP) | quando homologar PSP |
| 7 App marketplace | 3 | G | quando priorizar produto |
| 8 Regressão multi-tenant | 1–4 | M | junto com as fases 1–4 |

- Fases 1, 2, 4 podem sair no mesmo dia; a 8 acompanha cada uma (teste junto do fix).
- Cada fase: **reanálise → implementação → suíte verde → commit+push na main**.
- Ao final de cada fase, atualizar a tabela de estado em
  `docs/01-vigente/INTEGRACOES_MULTITENANT.md` — mas lembrando: documentação
  descreve, não manda. **Na próxima sessão, reanalise o código de novo.**
