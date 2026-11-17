# Rodada 3 — Agente E: App do consumidor (`app-gas-em-casa`)

**Data:** 2026-10-31 (retomado em 02/11 e fechado em 17/11) · **Commit de partida:** `9fcfde5a` · **Modo:** varredura + correção + validação (sem aparelho)
**Área:** `app-gas-em-casa/**` (exceto sessão/token, do Agente D) e o backend móvel do consumidor
(`AppPedidoController`, `PedidoMobileService`, `CatalogoMobileService`).

> **A jornada do consumidor continua PARCIAL por definição** (protocolo §3.4): nada foi executado em aparelho,
> emulador ou Maestro (proibidos nesta máquina). O que está provado aqui é: lógica e serviço do app por vitest,
> contrato app × API por teste de feature com o **payload literal** (lido do mesmo arquivo pelos dois lados) e a
> jornada HTTP real contra a API local como `erp_app` sob RLS, na ordem em que o app chama.

---

## 1. Ambiente

| Item | Valor |
|---|---|
| Banco | `pie_r3_e` (`create database pie_r3_e template pie_r3_base`), Postgres 15 local :55432 |
| API | `php artisan serve --port=8215`, `DB_USERNAME=erp_app`, `SAAS_ENFORCE_LICENCA=true`, `SAAS_ENFORCE_TENANT_ENVELOPE=true`, `FIREBASE_DRIVER=fake` |
| Dado manual no MEU banco | 2 condições de pagamento no grupo 1 (`Dinheiro na entrega`, `PIX pelo app` com `pix_online=true`) — o banco-base não tem nenhuma (ver R3-E-03); `php artisan saas:automacao:provisionar` (o banco-base não tem identidade de automação — ver R3-E-02) |
| Jornada HTTP | `scratchpad/jornada-app.mjs` (fetch, payloads copiados de `user.service.ts`, `address.service.ts` e do carrinho) |
| App | `npx tsc --noEmit` e `npx vitest run` em `app-gas-em-casa/` |
| PHP (sqlite) | `php artisan test --filter="Mobile\|AppPedido\|Marketplace\|AppAuth\|PieAppConsumidor\|PiePagamentoOnline\|AppLoginSobRls\|GasDoPovo"` |

## 2. Matriz perfil × jornada (Consumidor)

| Jornada | Estado | Elo que falta (§2) |
|---|---|---|
| Login por SMS (Firebase `fake:`) → 422 → cadastro | **PARCIAL** — HTTP real OK (422 `errors.cliente` → 201 com token); tela: lógica de erro testada | 1, 2 (tela no aparelho); SMS real (Firebase sem chave, PIE-22) |
| Endereço → cotação → pedido | **PARCIAL** — HTTP real 201; contrato com payload literal (Pg não; sqlite) | 1, 2; 10 em Postgres para o pedido do app (coberto só pelo `AppLoginSobRlsTest` existente) |
| Pedido com PIX → abandonar → reabrir PIX / cancelar → pedir de novo | **PARCIAL** — falha reproduzida em HTTP real (422 "já tem pedido"); depois da correção: cancelar + pedir de novo provado em teste e HTTP | 1, 2 (telas pix/track no aparelho) |
| Reenvio do pedido (timeout/duplo toque) | **IMPLEMENTADA E VERIFICADA no serviço** — o app passa a mandar `Idempotency-Key`; servidor devolve o mesmo pedido | 2 (rede real) |
| Acompanhar sem pedido | **IMPLEMENTADA NÃO VERIFICADA** (tela; sem renderização em teste) | 1, 2 |
| Toque na notificação → pedido | **PARCIAL** — destino testado; push real não (Firebase sem chave) | 11 |
| Histórico → avaliar | **PARCIAL** — `avaliado` no histórico provado; botão some (lógica testada) | 1, 2 |
| Excluir conta | **PARCIAL** — funciona como DESATIVAÇÃO (decisão P2-B-05 pendente) | 9 (anonimização) |
| Sair / trocar de revenda | **IMPLEMENTADA** — limpeza por transição de token testada; `POST logout` chamado | 2 |

## 3. Achados

### R3-E-01 — Preço Gás do Povo concedido a quem mandasse `gasdopovo: true`
- **Onde:** `erp-novo/app/Domain/Mobile/PedidoMobileService.php` (`criarDoApp` repassava `gasdopovo` do payload à cotação sem conferir nada); `CotacaoMobileService::precoUnitario` aplica `preco_gasdopovo`.
- **Reprodução:** `PieAppConsumidorContratoTest::test_r3_e_01_…` — revenda sem o programa ligado e cliente não beneficiário, payload do app com `gasdopovo: true`.
- **Esperado × observado:** 422 × **201 com o preço do programa** (sem a correção: `Failed asserting that 201 is identical to 422`).
- **Severidade:** ALTA (dinheiro; qualquer cliente com token pagava o preço subsidiado chamando a API). **Estado:** CORRIGIDO.
- **Correção:** só aceita com `empresa_configs.dados.app.gaspovo_ativo` (o mesmo que `GET config` expõe) **e** `clientes.gasdopovo` marcado pela revenda. O app só mostra o seletor nessas duas condições.

### R3-E-02 — Banco-base sem identidade de automação: login do app dá 403
- **Reprodução:** `POST app/v1/cliente/login` no `pie_r3_e` recém-clonado → `403 "Empresa sem fronteira SaaS aprovada ou sem identidade de automação."` (`AutomacaoPorEmpresa::naEmpresa`). Depois de `php artisan saas:automacao:provisionar` → 422/200 normais.
- **Impacto:** toda revenda nova cujo provisionamento não rode esse comando nasce com o app do consumidor fechado (login e cadastro). **Severidade:** MÉDIA (infra/onboarding). **Estado:** registrado → **Agente C** (provisionamento de revenda) e coordenador (refazer o `pie_r3_base`).

### R3-E-03 — Banco-base sem condição de pagamento: `init` devolve `condicoes: []`
- O app fica sem forma de pagamento e o carrinho não finaliza. É o P2-D-13/P2-A-07 visto pelo app. → **Agente B/C** (catálogo inicial da revenda).

### R3-E-04 — "Em rota" inferido pelo texto da situação no app do consumidor
- **Onde:** `app-gas-em-casa/src/app/(auth)/track.tsx:41` (`/saiu|rota|caminho|atend/i`) — mesma classe do P2-C-04 (corrigido só no entregador). "Atend" casava qualquer "Em atendimento".
- **Correção:** a API expõe `situacao_papel` em `GET pedidos/{id}`; o app usa `acoesDoPedido()` (marca declarada; texto só se a API for anterior ao campo). Teste: `test_situacao_papel_em_rota_vem_da_marca_declarada` (sem a correção: `null is identical to 'EM_ROTA'`) + vitest.
- **Severidade:** BAIXA. **Estado:** CORRIGIDO.

### R3-E-06 — O store do app não persistia o token (nem permissões e dados de login)
- **Onde:** `app-gas-em-casa/src/store/appStore.ts`. `setToken`, `setLoginData`, `setPermissions` e `setNewAddress` mudavam o estado no lugar e devolviam o MESMO objeto. O zustand compara por referência, então ninguém era avisado e nada ia para o disco. No login passava despercebido porque o `setUser` seguinte gravava tudo junto.
- **Por que agora é grave:** com a renovação de token (RC7) ligada, o token novo ficaria só na memória. O refresh revoga o antigo no servidor, e ao reabrir o app ele carregaria do disco o token revogado e cairia no login: a renovação viraria logout.
- **Prova:** `src/helpers/sessao.test.ts` (store real e persist real; só o MMKV vira um Map). Sem a correção: `setToken notifica e PERSISTE` **falha** (0 avisos, disco sem o token). **Severidade:** ALTA (anularia a RC7 no consumidor). **Estado:** CORRIGIDO.

### R3-E-07 — Cadastro pelo app gravava CPF sem validar (achado da frente A, PIE-35)
- **Onde:** `erp-novo/app/Domain/Mobile/ClienteAuthService.php::cadastrar`. O painel já valida (`ClienteRequest`); o app aceitava "123" e "111.111.111-11", e o erro só aparecia na rejeição da NF-e.
- **Correção:** `DocumentoFiscal::canonico` + `cpfValido`: CPF inválido dá 422 `cpf`, e o válido é gravado só com os dígitos. Continua opcional.
- **Prova:** `PieAppConsumidorContratoTest::test_cadastro_do_app_recusa_cpf_invalido_e_grava_so_digitos`. Sem a correção: `Expected response status code [422] but received 201`. **Estado:** CORRIGIDO.

### R3-E-08 — Endereço inline aceitava número maior que a coluna (R3-G-04, recado do D)
- **Onde:** `AppPerfilController::atualizarEndereco` (`PUT app/v1/perfil/endereco`) validava `numero` com `max:20`, e `clientes.numero` é varchar(10). No Postgres, "1234 fundos B" virava 500.
- **Correção:** `max:10` nesse método. O `validarEndereco` (`cliente_enderecos`) **continua em `max:20`**, porque a coluna dele é `string('numero', 20)` (migration `2026_06_27_000800`). O formulário de endereço do app (`AddressFormModal.tsx`) grava em `cliente_enderecos` e ganhou `maxLength={20}`, não 10: usar 10 recusaria números válidos. Nenhuma tela do app chama o `perfil/endereco`.
- **Prova:** `test_endereco_inline_com_numero_acima_da_coluna_recebe_422`. Sem a correção: `Expected [422] but received 200`. **Estado:** CORRIGIDO.

### R3-E-09 — Pagar pedido de outro cliente respondia 422 em vez de 404 (R3-F-05, recado do F)
- **Onde:** `AppPedidoController::pagar` validava o corpo antes de resolver o pedido do cliente do token. O 422 (em vez de 404) revelava que o id existe.
- **Correção:** primeiro resolve o pedido (`findOrFail` no escopo do cliente), depois valida o corpo.
- **Prova:** `test_r3_f_05_pagar_pedido_alheio_e_404_antes_de_validar_o_corpo`. Sem a correção: `Expected [404] but received 422`. **Estado:** CORRIGIDO.

### R3-E-05 — Excluir conta falhava em silêncio
- `perfil/index.tsx`: `useMutation` sem `onError`. Agora mostra a mensagem. BAIXA, CORRIGIDO (sem teste de tela).

### Itens da Rodada 2 tratados

| ID | Situação em `9fcfde5a` | O que foi feito | Prova |
|---|---|---|---|
| **P2-B-01** | Corrigido (conferido) | — | jornada HTTP 201; `PieAppConsumidorPedidoTest` verde |
| **P2-B-02** (parte segura) | Corrigido (servidor usa `endereco_id`/favorito) | Checkout mostra **"Entregar em: …"** com troca entre os endereços do cliente e atalho para cadastrar; manda o `endereco_id` escolhido | vitest `enderecoDeEntrega`; contrato |
| **P2-B-04** | Reproduzido em runtime: PIX → `POST pedidos` = `422 "Você já tem um pedido em andamento."` | API expõe `pagamento.{pix,pago}` no acompanhar; tela PIX com validade real, "Gerar novo código", "Acompanhar" e "Cancelar"; acompanhamento com "Pagar com PIX" e "Cancelar pedido" (regra existente: só PENDENTE; pago → aviso de estorno, regra do dono de 29/10). **Texto falso "será cancelado em 5 minutos" removido** (não existe cancelamento automático) | `test_p2_b_04_…` (cancela e pede de novo); vitest `acoesDoPedido`, `validadePix` |
| **P2-B-08** | Aberto | Push leva `efeito`; toque → acompanhamento (aberto) ou histórico (encerrado), também com app em 2º plano/fechado (`onNotificationOpenedApp`/`getInitialNotification`); device registrado ao abrir e no `onTokenRefresh`; `ImageBanner` e `TestNotification` (código morto, host `ddns` do legado) removidos | vitest `destinoDaNotificacao`. Push real: não testado |
| **P2-B-12** | Aberto | Erros do Firebase e do login viram mensagem na tela; só `422 errors.cliente` leva ao cadastro; `confirm` nulo tratado; "Corrigir o número"; tutorial vai à seleção de revenda antes do login no marketplace (fim do SMS em dobro) | vitest `tratarErroLogin`, `mensagemErroSms` |
| **P2-B-13** | Aberto | Prazo (`tempo_entrega_min`) e Gás do Povo no carrinho, quando o dado existe; Gás do Povo só para beneficiário (ver R3-E-01) | vitest `textoPrazoEntrega`, `mostraGasDoPovo` |
| **P2-B-14** | Aberto (0 rótulos) | `Button` anuncia papel/rótulo/estado; **todos os 41 tocáveis** com `accessibilityRole` + `accessibilityLabel`; alvos do carrinho 44 dp. **Guardião** `acessibilidade.test.ts` (piso: >40 arquivos, >30 tocáveis) — acusou 30 tocáveis sem rótulo antes da correção | vitest |
| **P2-B-15** | Aberto | `avaliado` no histórico (1 consulta por página); botão e banner somem; erro da avaliação não é mais engolido | `test_p2_b_15_…`; vitest `podeAvaliar` |
| **P2-B-16** | Aberto | Estado vazio "Você não tem pedido em andamento", botão voltar, "Falar com a revenda" (WhatsApp/telefone de `GET reseller`) | sem teste de tela |
| **P2-B-03 / RC7** (renovação, núcleo do D) | Aberto no consumidor | `helpers/http.ts` liga `renovacao: { endpoint: "app/v1/token/refresh", onToken: setToken }`. Login e cadastro chamam `Http.marcarTokenNovo()`. 401 que sobra, inclusive conta desativada pelo `TokenDeContaAtiva` do C, chama `logout()`, e o `vigiarFimDeSessao` limpa cache, WebSocket e carrinho. Exigiu a R3-E-06 | `sessao.test.ts` (4): renova o token do disco e grava o novo; não renova logo após o login; 401 também no refresh encerra a sessão. **Os 4 falham sem a correção** |
| **P2-B-17** | Aberto | Limpeza por **transição do token** (Sair, troca de revenda, 401): `queryClient.clear()`, `disconnectRealtime()`, carrinho; "Sair" chama `POST app/v1/logout` | vitest `vigiarFimDeSessao` |
| **P2-B-18** | Aberto | Chave do MMKV de UUID v4 nativo (`expo-modules-core`, já no Expo — sem dependência nova), 16 caracteres/96 bits dentro do limite de 16 bytes do MMKV. Instalações existentes mantêm a chave | vitest (Math.random proibido no teste) |
| **P2-B-21** | Parcial | Contrato versionado `app-gas-em-casa/contrato/app-v1.json` lido pelo vitest **e** pelo PHP; teste de serviço com o núcleo HTTP real | ver §4 |
| **R2-13** | Servidor aceitava; app não mandava | Chave por conteúdo do carrinho (mesmo carrinho = mesma chave), carrinho esvaziado ao criar | `order.service.test.ts`; `test_r2_13_…` |
| **R2-14** | Aberto | Carrinho faz a própria cotação (mesma chave de cache da Home), mostra "Calculando…" e só finaliza com total | sem teste de tela |
| R2-02 (resíduo) | App chamava `GerarPix` após todo pedido | `init` expõe `pix_online`; só condição PIX abre o PIX | vitest `deveAbrirPix`; contrato |
| R2-15, R2-16 | Abertos | **Não corrigidos** nesta rodada (tempo). R2-15: remover "Sexo"/"Convênio" (minimização) e montar o `UserForm` só depois do perfil carregar; R2-16: regex de nome | — |

## 4. Correções e prova

**Arquivos (backend):** `ClienteAuthService.php` (CPF, R3-E-07), `AppPerfilController.php` (número, R3-E-08), `AppPedidoController.php` (acompanhar: `situacao_papel`, `pagamento`), `PedidoMobileService.php`
(histórico `avaliado`; push com `efeito`; trava do Gás do Povo), `CatalogoMobileService.php` (`pix_online`).
**Teste novo:** `erp-novo/tests/Feature/PieAppConsumidorContratoTest.php` (9 testes, sqlite).

| Execução | Com a correção | Sem a correção (`git show HEAD:` dos 3 arquivos PHP) |
|---|---|---|
| `PieAppConsumidorContratoTest` | **9 passed (69 assertions)** (17/11) | **4 failed, 2 passed**: contrato (campos), `avaliado`, `situacao_papel` (`null is identical to 'EM_ROTA'`), Gás do Povo (`201 is identical to 422`). Os 2 que passam são guardas: idempotência e cancelamento já existiam no servidor — a correção delas é no app |
| `npx vitest run` (app) | **7 arquivos, 44 testes verdes** em 17/11 (eram 3/17) | `order.service.test.ts` **falhou** com a 1ª versão da idempotência (chave em `Symbol` — o `mergeConfig` do axios clona o `data`; cabeçalho não saía). Corrigido para chave por conteúdo. `acessibilidade.test.ts` acusou 30 tocáveis antes dos rótulos |
| `npx tsc --noEmit` (app) | **exit 0** em 17/11 (o top-level await do `order.service.test.ts` virou import normal) | — |
| `sessao.test.ts` (RC7 + R3-E-06) | 4 passed | **4 failed** com `appStore.ts` e `helpers/http.ts` de `HEAD` |
| Teste de CPF (R3-E-07) | passed | **failed**: `received 201` com `ClienteAuthService.php` de `HEAD` |
| Número (R3-E-08) e pagar (R3-E-09) | 2 passed | **2 failed**: `received 200` (esperado 422) e `received 422` (esperado 404), com os dois controllers de `HEAD` |
| Lote PHP do consumidor | ver §4.1 | — |

**Arquivos (app):** `helpers/{pedidoApp,idempotencia,notificacao,erroLogin,chaveCifra,sessao}.ts` (novos, puros e
testados), `helpers/http.ts` (renovação), `services/{order,user}.service.ts`, `store/{flashStore,storage,appStore}.ts`, `newuser.tsx`, telas `carrinho`, `pix`, `track`,
`(auth)/_layout`, `_layout`, `sms`, `(tutorial)/page1`, `pedidos`, `perfil`, componentes com rótulos,
`contrato/app-v1.json`; removidos `components/atoms/{ImageBanner,TestNotification}.tsx` (mortos).

### 4.1 Execuções

**Jornada HTTP real** (API 8215, `erp_app`, RLS, `pie_r3_e`; `FONE=42999990003 IDEMP=1 node jornada-app.mjs excluir`, 31/10, depois das correções):

```
POST cliente/login -> 422 errors.cliente   (1ª execução) -> POST cliente/cadastro -> 201 {token,user}
GET config -> 200 · GET init -> 200 · GET enderecos -> 200 [] · POST enderecos -> 201 {id:2}
POST carrinho/cotacao -> 200 total 114
POST pedidos (Idempotency-Key K) -> 201 {id:502}
POST pedidos (mesma K, reenvio)  -> 201 {id:502}          <- R2-13: mesmo pedido, sem duplicar
POST pedidos/502/pix -> 201 ATIVA, expira_em 03:52:57    <- validade real que a tela mostra
GET pedidos/502 -> 200 {"efeito":"PENDENTE","situacao_papel":"NENHUM","pagamento":{"pix":true,"pago":false}}
POST pedidos (pedido novo com o PIX aberto) -> 422 "Você já tem um pedido em andamento."
GET pedidos -> 200 [{"id":502,…,"avaliado":false}]
DELETE perfil -> 200 · GET pedidos -> 401 (token revogado)
```
Antes das correções (31/10, `node jornada-app.mjs sem`): mesmo fluxo até o 422 do segundo pedido, **sem saída
pelo app**. O `GET pedidos/{id}` não tinha `pagamento` nem `situacao_papel`, e o histórico não tinha `avaliado`.
A saída pelo cancelamento (`POST pedidos/{id}/cancelar` → 200 → pedido novo 201) está provada no
`test_p2_b_04_…`. A primeira tentativa de cadastro nesta máquina deu `500 Maximum execution time of 30 seconds`
duas vezes, com sete agentes e a suíte PHP disputando a CPU. Na terceira passou (ambiente, não código).

**Lote PHP do consumidor (sqlite):** `--filter="Mobile|AppPedido|Marketplace|AppAuth|PieAppConsumidor|PiePagamentoOnline|AppLoginSobRls|GasDoPovo"`
→ 31/10: **91 passed, 4 skipped** (os 4 são de Postgres/RLS). 17/11, com CPF, número, pagar, `FaseF1Seguranca` e `PieClienteEmCampoNumero` no filtro: **118 passed, 4 skipped (421 assertions)**, 0 falhas.

**Ambiente em 17/11:** o `node_modules` do `app-gas-em-casa` e o do `mobile-shared` tinham sumido. Reinstalei sem tocar em arquivo versionado: `npm install --no-save --package-lock=false --legacy-peer-deps --ignore-scripts` no app e `npm ci --ignore-scripts` no `mobile-shared`. A dependência git `react-native-ratings` foi compilada à mão (`tsc -p` no próprio pacote). **Achado de ambiente:** `npm ci` falha no app porque o `package-lock.json` está fora de sincronia com o `package.json` (faltam os `lightningcss-*` 1.27.0). O CI que use `npm ci` vai quebrar. Fica para o coordenador, porque mexer no lock afeta o build dos AABs.

## 5. Para outros donos

- **Agente D (sessão, `mobile-shared`)**
  - `createHttp().PrepareRequest` não aceita cabeçalho por chamada. O app contorna com um interceptor (`app-gas-em-casa/src/helpers/idempotencia.ts`, chave registrada pelo conteúdo). Proposta: parâmetro opcional `headers` no núcleo; aí o helper vira uma linha.
  - A limpeza de cache e WebSocket no 401 já acontece pelo `vigiarFimDeSessao` (o app observa o token sumir). O `onUnauthorized` pode continuar só chamando `logout()`. A renovação de token (P2-B-03) é sua; o app não toca nela.
  - Agora o "Sair" do consumidor chama `POST app/v1/logout`. Se a renovação trocar o token, nada muda aqui.
  - Chave do MMKV: o D já trocou o `Math.random` do entregador (`app-entregador/src/store/chaveMmkv.ts`, P2-C-20), com o mesmo método (uuid v4 nativo, 16 caracteres de 6 bits). Não mexi para não conflitar com ele. Sobram duas cópias equivalentes, `chaveCifra.ts` e `chaveMmkv.ts`. Proposta para depois da rodada: mover uma delas para o `mobile-shared` e apagar a outra.
  - Renovação ligada no consumidor como você pediu. A R3-E-06 (setters que não persistiam) era pré-requisito; confira se o `appStore` do entregador não tem o mesmo padrão `state.x = …; return state`.
- **Agente C / coordenador:** R3-E-02 (a revenda precisa da identidade de automação, senão o login do app dá 403; o `pie_r3_base` não tem). R3-E-03 (o base não tem condição de pagamento).
- **Agente B:** `PixService::expirarVencidas` só marca a cobrança como expirada. Se o dono quiser cancelar sozinho o pedido do app com PIX vencido, a regra nasce lá (ver decisão 4).
- **Coordenador:** teste novo `Tests\Feature\PieAppConsumidorContratoTest` (sqlite). Nenhum teste Postgres novo. O banco `pie_r3_e` ficou (sem conexões abertas), com 2 condições de pagamento e a identidade de automação criadas por mim. Pode apagar.

## 6. Decisões pendentes (com recomendação)

1. **P2-B-05, exclusão de conta.** Hoje só desativa. Recomendo anonimizar nome, e-mail, nascimento, telefones, endereços sem pedido, `app_devices` e o `users` do titular, e manter CPF e nome só onde houver documento fiscal, pelo prazo legal. O texto do app e o de `/excluir-conta` dizem o que fica guardado. Depende de validação jurídica.
2. **P2-B-06, formas de pagamento no app.** Recomendo o campo `disponivel_app` na condição, configurável no painel, desligado por padrão para condição a prazo. O app já recebe `pix_online` e passaria a filtrar por esse campo também.
3. **P2-B-07, termos e política.** Recomendo texto próprio do produto por tenant em `/privacidade`, um link no Perfil e "Aceitar" bloqueado enquanto o texto não carregar. A tela continua buscando `gasemcasa.com.br/termos.php`, do controlador antigo.
4. **Cancelamento automático do pedido com PIX vencido.** Não existe. Saiu o texto que o prometia. Se o dono quiser, a regra vai para o servidor (job). Hoje o cliente cancela pelo app.
5. **Cancelar com o entregador a caminho.** A regra atual do servidor deixa cancelar enquanto o efeito é PENDENTE, inclusive "Em Rota". O app segue a regra e pede confirmação. A pergunta para o dono: o app deve esconder o "Cancelar" quando o papel é `EM_ROTA`?
6. **Gás do Povo.** Hoje exige programa ligado **e** cliente beneficiário marcado pela revenda (R3-E-01). Falta o dono confirmar que a elegibilidade é marcada no ERP, e não autodeclarada.
7. **P2-B-09/10, ficha da loja.** Tirar "cartão no app" e "escolha a revenda" de `store-assets/gasemcasa/textos.md` até existirem, e refazer as capturas com dado fictício.
8. **Contraste da marca (P2-B-14).** Branco sobre `#FF6200` dá 3,00:1. Passa só como texto grande, e o `Button` (20 dp, negrito) se enquadra. Texto pequeno sobre laranja precisa de um tom mais escuro (ex.: `#C24A00`), e trocar a cor da marca é decisão do dono. Os links novos usam `primaryDark`.

## 7. Não testado

- Nenhuma tela foi renderizada: não há `react-native-web` e o aparelho, o emulador e o Maestro são proibidos aqui. Carrinho, PIX, acompanhamento, SMS e acessibilidade na prática (TalkBack, fonte grande) estão IMPLEMENTADOS, não VERIFICADOS.
- SMS e push reais: o Firebase está em `fake`. O `onTokenRefresh` e o toque em notificação com o app fechado dependem do aparelho.
- A trava do Gás do Povo e o acompanhar com `pagamento` foram provados só em sqlite (a jornada HTTP roda no Postgres, mas sem Gás do Povo).
- R2-15 e R2-16 não foram corrigidos.
- `npm audit` não foi refeito (P2-B-20).
