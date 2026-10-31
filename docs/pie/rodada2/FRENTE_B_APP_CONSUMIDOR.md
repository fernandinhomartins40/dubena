# Frente B — App do consumidor (`app-gas-em-casa`)

**Auditoria PIE V2, Rodada 2** · 2026-10-30 · commit de partida `133f588d` · revisor da Frente B
**Modo:** auditoria (nenhum arquivo do projeto alterado; sondas no scratchpad)
**Escopo:** `app-gas-em-casa/` (Expo 53, expo-router, RN 0.79.5), `mobile-shared/src/http.ts`, os controllers
`erp-novo/app/Http/Controllers/Api/Mobile/App{Auth,Loja,Perfil,Pedido}Controller.php` e os serviços que eles chamam,
`store-assets/` e as páginas públicas `/privacidade` e `/excluir-conta`.

**O que não repito.** A Rodada 1 já registrou PIE-01, PIE-03, PIE-11, PIE-16, PIE-18, PIE-22, PIE-33 e PIE-34, e a
BANCA_R2 registrou R2-02 e R2-13 a R2-16. Quando algum deles continua no código, cito o ID na seção "Resíduos da
Rodada 1" e não o renumero. Os achados abaixo são novos ou mostram que uma correção ficou incompleta.

---

## Sumário executivo

**Hoje o app do consumidor não consegue criar pedido.** O carrinho envia `{condicaopagamento_id, codigo_cupom,
gasdopovo, itens}`. O servidor exige `lat`/`lng` quando não recebe `cliente_id` e responde 422 a toda tentativa.
Isso foi provado com uma sonda PHP que manda o payload exato do app (P2-B-01). O defeito existe desde a reescrita
F3 (junho): nenhum teste do backend manda o payload que o app manda.

Atrás dele há uma fila de problemas de produto que um cliente encontraria assim que o pedido passasse:

- o endereço escolhido no app nunca chega ao pedido (P2-B-02);
- a sessão dura 24 h, não é renovada, e quando vence o app cai numa tela de erro sem saída (P2-B-03);
- um PIX abandonado prende o cliente: ele não consegue reabrir o PIX, cancelar nem pedir de novo (P2-B-04);
- "Excluir conta" só desativa o cadastro e guarda todo o dado pessoal (P2-B-05);
- toda condição de pagamento do grupo, inclusive a prazo, aparece para o consumidor (P2-B-06).

O app está bem encaminhado em alguns pontos:

- armazenamento local cifrado, com a chave no keystore;
- camada HTTP compartilhada com retry só em GET;
- recompra em destaque na Home;
- acompanhamento com mapa, ETA e tempo real opcional;
- permissões Android enxutas e justificadas;
- targetSdk 36;
- exclusão de conta acessível dentro do app;
- páginas de privacidade e exclusão já escritas no backend.

**Parecer: REPROVADO** (ver fechamento).

---

## 1. Inventário de telas e jornada

### 1.1 Inventário de telas

| Rota (`src/app`) | Endpoint(s) | Carregando / vazio / erro | Sem rede · sessão vencida · voltar/duplo toque |
|---|---|---|---|
| `index.tsx` (boot) | — | Loader 250 ms e roteia: `user` → Home; sem permissões → tutorial; sem revenda e sem `EMPRESA_ID` → seleção; senão → login | Roteia pelo `user` persistido, mesmo com token vencido (ver P2-B-03) |
| `startupvideo.tsx` | — | Rota inerte que volta para `/` | — |
| `(tutorial)/page1.tsx` | — | 3 slides. Pede localização (todas as plataformas) e notificação (só Android, `:57`) | "Pular" grava `permissions=true` e vai para `/login`. Pula a seleção de revenda (ver P2-B-12) |
| `selecionar-revenda.tsx` | `POST marketplace/empresas`, `GET marketplace/cidades` | Tem os 3 estados e "Tentar novamente" | Erro de rede vira mensagem com botão. Só aparece em build **sem** `EMPRESA_ID`; o perfil `production` do `eas.json` fixa `EMPRESA_ID=2`, então em produção a tela não existe |
| `login.tsx` | — (só valida localmente) | Alertas de validação | Regex de nome com defeito (R2-16, pendente) |
| `policies.tsx` | `GET https://www.gasemcasa.com.br/termos.php?type=json` (host externo) | Sem estado de erro: se a busca falhar, mostra a tela vazia com "Aceitar" habilitado | P2-B-07 |
| `sms.tsx` | Firebase `signInWithPhoneNumber`; `POST cliente/login` | Botão desabilitado durante o envio | Falha do SMS é engolida. Erro ≠ 422 abre `ErrorView` sem botão (P2-B-12) |
| `newuser.tsx` | `POST cliente/cadastro` | `Alert` no erro | O nome vem bloqueado (`UserForm` `disabled`). Não pede endereço |
| `(auth)/_layout.tsx` | `GET config`, `GET pedidos` | Loader | Com pedido PENDENTE, força `/track` |
| `(tabs)/home` | `GET init`, `GET pedidos`, `POST carrinho/cotacao` | Loader nos produtos | `isError` → `ErrorView` "erro desconhecido, contate a revenda", **sem ação** (P2-B-03) |
| `(auth)/carrinho.tsx` | `GET cupom`, `POST pedidos`, `POST pedidos/{id}/pix` | Carrinho vazio tratado | Total R$ 0 sem cotação (R2-14). Sem chave de idempotência (R2-13). **Todo POST dá 422** (P2-B-01). Não tem endereço, troco nem observação |
| `(auth)/pix.tsx` | `GET pedidos/{id}/pix/status` (15 s) | Sem estado de expiração | Sem botão voltar, sem gerar outro PIX, sem trocar a forma de pagamento. Texto falso de cancelamento (P2-B-04) |
| `(auth)/track.tsx` | `GET pedidos/{id}` (30 s), `GET pedidos/{id}/rota-entregador` (10 s), Echo | Sem pedido: caixa vazia "Pedido em Andamento" (P2-B-16) | Sem voltar, sem cancelar, WhatsApp vazio (`:117-119`) |
| `(tabs)/pedidos` | `GET pedidos`, `POST pedidos/{id}/avaliar` | Loader; busca local | "Pedir" de novo; "Avaliar" em todo concluído (P2-B-15) |
| `(tabs)/info` | `GET reseller` | Some quando não há dado | Abre WhatsApp, telefone e mapa |
| `(tabs)/perfil` | `GET perfil`, `GET enderecos`, `DELETE perfil` | Overlay na exclusão | Exclusão sem `onError` (falha silenciosa). "Sair" não revoga o token no servidor (P2-B-17) |
| `(auth)/perfil-dados.tsx` | `GET/PUT perfil` | Toast | Campos descartados e risco de apagar dado (R2-15, pendente) |
| `(auth)/address.tsx` + `AddressSheet` + `AddressFormModal` | `GET/POST/PUT/DELETE enderecos`, `PUT enderecos/{id}/favorito`, Google Geocode e Places **direto do cliente** | Mapa ancorado na revenda | O favorito não chega ao pedido (P2-B-02). Usa a chave do Maps em web service (P2-B-11) |
| `(auth)/error.tsx` | — | `ErrorView` com o texto que vem na URL | Sem saída |

### 1.2 Contagem de passos (lida no código; não executada em aparelho)

- **Primeiro pedido, build de produção (`EMPRESA_ID=2`):** tutorial (3 toques) → nome + telefone + "Entrar" →
  "Aceitar" termos → código SMS + "Confirmar" → (se cliente novo) formulário + "Salvar" → `+` no produto →
  "Ver carrinho" → (forma de pagamento já vem pré-selecionada) → "Finalizar". São **cerca de 10 toques mais 3
  digitações**. Nenhum passo pede o endereço de entrega. Hoje esse caminho termina em 422 (P2-B-01).
- **Repetir pedido:** "Repetir" na Home → "Finalizar": **2 toques**, como o produto quer, mas só com token válido.
  O token dura 24 h (`config/sanctum.php:61`, `SANCTUM_EXPIRATION` padrão 1440) e o app nunca chama
  `token/refresh`. Quem compra gás a cada 30–60 dias encontra o token vencido em praticamente todo pedido. O
  caminho real fica: abrir o app → tela de erro sem botão → fechar o app à força → reabrir → digitar de novo nome e
  telefone (o logout limpa `loginData`) → SMS → "Repetir" → "Finalizar" (P2-B-03).

---

## 2. Achados

### P2-B-01 — Todo pedido criado pelo app recebe 422: o servidor exige `lat`/`lng` que o app nunca manda
- **Categoria:** contrato API × app / fluxo principal quebrado · **Severidade:** CRÍTICA (o app não cumpre a única
  função dele) · **Estado:** CONFIRMADO (sonda PHP + leitura do payload)
- **Onde:** `erp-novo/app/Http/Controllers/Api/Mobile/AppPedidoController.php:39-42`
  (`'lat' => 'required_without:cliente_id|numeric'`, idem `lng`). O `cliente_id` do token só é imposto **depois** da
  validação (`:59-65`). App: `app-gas-em-casa/src/app/(auth)/carrinho.tsx:116-121` envia só
  `condicaopagamento_id, codigo_cupom, gasdopovo, itens`.
- **Rota/tela:** `POST /api/app/v1/pedidos` · Carrinho → "Finalizar pedido".
- **Reprodução:** sonda `scratchpad/b/SondaContratoAppConsumidorTest.php::test_sonda_1_payload_exato_do_app`
  (usuário com cliente vinculado, produto, setor e situação PENDENTE).
- **Evidência (saída real):**
  `[SONDA1] status=422 body={"message":"validation.required_without (and 1 more error)","errors":{"lat":["validation.required_without"],"lng":["validation.required_without"]}}`
- **Esperado × observado:** pedido criado (201) × 422. O toast mostra a chave crua
  `validation.required_without (and 1 more error)`: a camada HTTP repassa `data.message`
  (`mobile-shared/src/http.ts:72-75`; confirmado pela sonda vitest `sonda-http.test.ts`, que também mostra PIE-34).
- **Desde quando:** a regra está assim desde `d39ef29f` (21/06). Pelo `git log -S`, nem o `OrderConfirm` antigo
  (antes de `48dce823`) nem o carrinho atual mandaram `lat` ou `cliente_id`. Todos os testes do backend
  (`MobileTest`, `AppPedidoCoberturaTest`, `AppPedidoEscopoClienteTest`) mandam `cliente_id` ou `lat/lng`, então
  nenhum reproduz o app.
- **Impacto:** nenhum consumidor consegue pedir pelo app novo. A ficha da Play e as capturas prometem exatamente
  esse fluxo.
- **Correção proposta:** (1) no servidor, resolver o cliente do token **antes** de validar e só exigir `lat/lng`
  quando não houver cliente vinculado nem `cliente_id`, por exemplo com `Rule::requiredIf(fn () => $clienteDoToken
  === null && ! $request->filled('cliente_id'))`; (2) no app, mandar o endereço de entrega escolhido (ver P2-B-02).
- **Teste de aceite:** um teste de feature que manda **o payload literal do carrinho** (sem `cliente_id`, sem
  coordenada) com usuário vinculado e recebe 201. Mais um teste de contrato que leia os campos do `CreateOrder` e
  falhe se o validador exigir campo que o app não envia.
- **Esforço:** P.

### P2-B-02 — O endereço escolhido no app é ignorado; cliente sem coordenada pula a validação de cobertura
- **Categoria:** produto / logística / regra de negócio · **Severidade:** ALTA · **Estado:** CONFIRMADO (sonda 2 + código)
- **Onde:** o pedido usa só a coordenada **inline** do cliente (`PedidoMobileService.php:124-125`). O
  `EnderecoMobileService::favoritar/criar` (`:26-67`) grava em `cliente_enderecos` e nunca atualiza
  `clientes.latitude/longitude/endereco`. `validarCobertura` retorna sem validar quando a coordenada é nula
  (`:247-249`), e `setorDeEntrega` cai no primeiro setor ativo (`:105`). O cadastro do app não pede endereço
  (`newuser.tsx:85-94`), e o checkout também não (`carrinho.tsx`).
- **Reprodução:** `test_sonda_2_endereco_favorito_do_app_e_ignorado`. Cliente novo sem coordenada inline, endereço
  favorito do app a ~250 km da revenda, raio de 5 km declarado.
- **Evidência:** `[SONDA2] status=201 ...` e `cliente.latitude=NULL`. O pedido nasceu fora da área e sem endereço
  geocodificado.
- **Impacto:** o entregador recebe o pedido com o endereço antigo do ERP, ou sem endereço nenhum para cliente novo.
  A cerca de entrega (F6-05) é contornada, e quem tem mais de um endereço ("Casa"/"Trabalho") recebe sempre no do
  cadastro.
- **Correção:** o app manda `endereco_id` (ou `lat/lng` do endereço escolhido). O servidor carrega o endereço do
  cliente do token, valida a cobertura com essa coordenada e recusa com 422 quando não há coordenada e a empresa
  declarou área (fail-closed). O checkout mostra "Entregar em: …" com troca de endereço.
- **Aceite:** um pedido com `endereco_id` fora da área recebe 422 `endereco`. Dentro da área, o pedido grava o
  endereço escolhido. Cliente sem nenhuma coordenada recebe 422 numa empresa com área.
- **Esforço:** M.

### P2-B-03 — Sessão de 24 h sem renovação; o 401 desloga sem navegar e deixa a Home numa tela de erro sem saída
- **Categoria:** sessão / UX / custo de SMS · **Severidade:** ALTA · **Estado:** CONFIRMADO (código); a duração real
  em produção depende do `.env` (não verificado)
- **Onde:** `src/helpers/http.ts:16-20` (`onUnauthorized` → `logout()`, sem navegação); `src/app/(auth)/_layout.tsx`
  não confere `user`; `(tabs)/home/index.tsx:109-111` (`isError` → `ErrorView` sem botão,
  `components/templates/errorview.tsx`); `store/appStore.ts:128-143` (o logout limpa `loginData`).
  `token/refresh` existe (`routes/api.php`, `AppAuthController::refresh`) e o app do consumidor **nunca** o chama
  (grep: só `app-entregador/src/services/auth.service.ts:30` chama). `docs/gauntlet/PLANO_PRODUCAO.md:171` presume
  que "os apps mobile usam refresh", o que não vale para este app.
- **Reprodução:** login → esperar o token vencer, ou revogá-lo → reabrir o app. O `index` vê `user` persistido e vai
  para a Home. O `GET init` dá 401, `logout()` zera o `user` e a tela mostra "Houve um erro desconhecido, por favor
  contate a revenda". Nenhum botão leva ao login.
- **Evidência:** sonda vitest `sonda-http.test.ts`: `401 -> onUnauthorized chamado 1 vez(es)`. Quem chama devolve
  só o erro; nenhuma rota reage à mudança de `user`.
- **Impacto:** o ciclo de compra de gás (semanas) é maior que a sessão (1 dia). Quase todo pedido repetido exige
  fechar o app, digitar nome e telefone de novo e receber outro SMS, que é pago no Firebase. A promessa de "pedir em
  2 toques" deixa de valer. Também gera chamado indevido para a revenda.
- **Correção:** (1) chamar `POST app/v1/token/refresh` ao abrir o app e quando faltar menos de N horas; (2) no 401,
  navegar para `/login` preservando `loginData`. Um `useEffect` no `(auth)/_layout` que redirecione quando
  `!apiToken` resolve; (3) avaliar uma expiração mais longa para o token de papel `role:cliente`, que não abre o
  painel, sem mudar a do staff.
- **Aceite:** com o token revogado, abrir o app leva ao login com o telefone preenchido em até 1 s. Com o token
  perto de vencer, o app o renova sem pedir SMS.
- **Esforço:** P (redirecionamento) + M (refresh).

### P2-B-04 — PIX abandonado prende o cliente: sem reabrir o PIX, sem cancelar, sem pedir de novo; texto de cancelamento falso
- **Categoria:** fluxo de pagamento / correção incompleta de PIE-11 (R2-02) · **Severidade:** ALTA · **Estado:** CONFIRMADO (código)
- **Onde:**
  - `pix.tsx:114-115` continua a dizer "Você tem até 5 minutos… o pedido será cancelado". `PixService::expirarVencidas`
    (`Domain/Cobranca/PixService.php:232-239`) só marca a cobrança como EXPIRADA, e nenhum código cancela o pedido
    (grep `EXPIRADA` só encontra esse ponto).
  - O app não tem botão de cancelar: `OrderService.Cancel` (`services/order.service.ts:67-68`) não é usado em nenhuma tela.
  - O `pixOrder` vive no `flashStore`, que não é persistido. Ao reabrir, o `(auth)/_layout.tsx:43-47` manda para
    `/track`, e de lá não há caminho de volta ao PIX.
  - O servidor só aceita 1 pedido PENDENTE por cliente (`PedidoMobileService.php:149-151, 279-285`).
- **Reprodução:** pedido com condição `pix_online` → tela PIX → fechar o app ou esperar 5 min → reabrir.
- **Esperado × observado:** pedido cancelado, ou PIX novo, ou troca de forma de pagamento × o pedido fica PENDENTE
  para sempre. O app prende o cliente em "Acompanhar" com o passo "Pedido Recebido pela Revenda", e qualquer pedido
  novo recebe "Você já tem um pedido em andamento".
- **Impacto:** o cliente não compra de novo até um operador cancelar manualmente. Ele acredita (pelo texto) que o
  pedido foi cancelado, ou acha que já pagou.
- **Correção:** (1) cancelar no servidor o pedido do app cuja cobrança expirou sem pagamento, ou tirá-lo da regra de
  "1 pendente"; (2) no `/track`, botões "Pagar com PIX" (gera de novo via `POST pedidos/{id}/pix`) e "Cancelar
  pedido"; (3) texto alinhado com a regra real.
- **Aceite:** um pedido com PIX expirado não bloqueia um pedido novo. A tela de acompanhamento de um pedido não pago
  oferece pagar ou cancelar.
- **Esforço:** M.

### P2-B-05 — "Excluir conta" só desativa: nome, CPF, e-mail, nascimento, telefones, endereços e usuário permanecem
- **Categoria:** LGPD / política de loja · **Severidade:** ALTA · **Estado:** CONFIRMADO (sonda 3). Rejeição na loja: HIPÓTESE
- **Onde:** `AppPerfilController::excluirConta` (`:71-80`) → `ClienteService::encerrarPeloTitular`
  (`Domain/Cliente/ClienteService.php:112-126`): grava `ativo=false`, `user_id=null` e revoga os tokens. Nada é
  anonimizado. O app promete "Remover permanentemente sua conta… não poderá ser desfeita"
  (`(tabs)/perfil/index.tsx:56,111`). A página `/excluir-conta`
  (`resources/views/publico/excluir-conta.blade.php`) diz que só os dados fiscais serão guardados.
- **Evidência:** `[SONDA3] status=200 nome_mantido=true cpf_mantido=true email_mantido=true user_existe=true ativo=false`.
- **Impacto:** o que o app e a página prometem não é o que o código faz. A LGPD (art. 18, VI) dá ao titular direito
  à eliminação, ressalvada a guarda legal. Google Play (*account deletion requirements*) e Apple (guideline
  5.1.1(v)) exigem excluir a conta e os dados associados, e permitem reter o que a lei exige desde que isso seja
  informado. HIPÓTESE: um revisor que teste a exclusão e depois entre de novo com o mesmo telefone pode ver os dados
  antigos. Não reproduzi o novo login após a exclusão.
- **Correção:** anonimizar o que não é fiscal (e-mail, nascimento, telefones e endereços de app sem pedido,
  `app_devices`, linha de `users` com e-mail sintético) e guardar o que a NF-e exige pelo prazo legal. Ajustar o
  texto do diálogo para dizer o que é guardado.
- **Aceite:** depois do `DELETE /app/v1/perfil`, o cliente sem pedido fica sem PII. O cliente com pedido mantém só os
  campos fiscais, e `app_devices` e `users` do titular são removidos ou anonimizados.
- **Esforço:** M.

### P2-B-06 — O consumidor vê e pode escolher toda condição de pagamento ativa do grupo, inclusive a prazo
- **Categoria:** produto SaaS / risco de crédito · **Severidade:** ALTA · **Estado:** CONFIRMADO (código). Haver
  condição a prazo ativa na base: FORTE INDÍCIO (não consultei o banco)
- **Onde:** `CatalogoMobileService::condicoes` (`:49-61`) devolve todas as `ativo=true` do grupo. `criarPedido` só
  valida `ExisteNoTenant` (`AppPedidoController.php:44`). O app pré-seleciona a primeira em ordem alfabética
  (`home/index.tsx:71`, `carrinho.tsx:70`). O model não tem marcação de canal (`CondicaoPagamento::$fillable` tem
  `pix_online`, mas nada como "disponível no app"), e `pix_online` nem chega ao app.
- **Impacto:** qualquer pessoa com um telefone verificado pode pedir gás "boleto 30 dias" ou "fiado", sem análise. A
  condição padrão pode ser uma a prazo, só pela ordem alfabética. Pela regra do plano SaaS, quais formas aparecem no
  app tem de ser configuração da revenda.
- **Correção:** um campo `disponivel_app` (ou "canais") na condição, configurável no painel, filtrado no `init` e
  validado no `criarPedido`. Mandar `pix_online` para o app decidir a tela de PIX sem a tentativa cega de `GerarPix`
  (resíduo de R2-02).
- **Aceite:** uma condição sem `disponivel_app` não aparece no `init`, e o `POST pedidos` com ela recebe 422.
- **Esforço:** M.

### P2-B-07 — O consentimento é colhido sobre termos de outra empresa, num site externo; não há política dentro do app logado
- **Categoria:** LGPD / prontidão de loja · **Severidade:** ALTA · **Estado:** CONFIRMADO (código + `curl` público)
- **Onde:** `services/user.service.ts:57-58` busca `https://www.gasemcasa.com.br/termos.php?type=json`. Em
  30/10/2026 o primeiro bloco devolvido identifica como proprietária e operadora do app a empresa fornecedora do
  sistema legado (QTI), não a revenda controladora que a nova `/privacidade` nomeia (`PUBLICO_*`). `policies.tsx`
  mostra a tela com "Aceitar" habilitado mesmo se a busca falhar (sem tratamento de erro; `disabled` só durante
  `isLoading`). Não há link para a política nem para os termos depois do login (grep `privacidade|Linking` só acha
  WhatsApp, telefone e mapa em `info/index.tsx`). As páginas novas ainda não estão no ar:
  `curl https://gasemcasa.com/novo/privacidade` → **404** e `/novo/excluir-conta` → **404**. Era esperado: elas
  dependem do próximo deploy e de `PUBLICO_*` (`docs/06-runbooks/PUBLICACAO_LOJAS.md:22`).
- **Impacto:** o aceite registrado vale para outro controlador e outro texto. O usuário pode "aceitar" uma tela
  vazia. A Apple exige link para a política acessível dentro do app (5.1.1(i)). As URLs informadas à Play
  respondem 404 hoje.
- **Correção:** servir os termos e a política pelo erp-novo (por tenant), abrir `/privacidade` no `policies` e no
  Perfil ("Privacidade e termos"), bloquear "Aceitar" sem texto carregado e registrar versão e data do aceite no
  servidor.
- **Aceite:** com a rede desligada, "Aceitar" fica desabilitado e aparece um aviso. O Perfil tem um link que abre
  `/privacidade` (200).
- **Esforço:** P.

### P2-B-08 — Notificações: o toque não leva ao pedido e o token FCM não é renovado
- **Categoria:** produto / push · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (código). A entrega real não foi testada: o Firebase segue sem chave (PIE-22)
- **Onde:** o servidor manda `{pedido_id, acao: 'orderUpdated'}` (`PedidoMobileService.php:60`).
  `hooks/useNotificationClick.ts:10-34` só trata `imageurl`, e esse dado vai para o `ImageBanner`, que **não está
  montado em nenhuma tela** (grep). O token FCM só sai no login e no cadastro (`sms.tsx:256-265`,
  `newuser.tsx:76-83`). `UserService.StoreFcmToken` e `POST app/v1/devices` nunca são chamados, e não existe
  `onTokenRefresh`. `TestNotification.tsx` ainda aponta para um host `http://…ddns.net` do legado (código morto).
- **Impacto:** "Seu pedido saiu para entrega" não abre o acompanhamento. Quem negou a permissão no login, ou cujo
  token o FCM rotacionou, deixa de receber push até entrar de novo.
- **Correção:** no clique, `acao==='orderUpdated'` → `setPendingOrder({id})` + `/track`. Registrar o device com
  `onTokenRefresh` e ao abrir o app. Remover `ImageBanner` e `TestNotification`, ou montá-los.
- **Aceite:** teste unitário do handler (dado `orderUpdated` → navegação para `/track`), e `POST devices` disparado
  no `onTokenRefresh`.
- **Esforço:** P.

### P2-B-09 — Ficha da loja promete "PIX ou cartão no app"; o app não tem fluxo de cartão
- **Categoria:** prontidão de loja / metadado enganoso · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (código). Risco de política: HIPÓTESE
- **Onde:** `store-assets/gasemcasa/textos.md` ("escolha a forma de pagamento disponível, com PIX ou cartão no app").
  No app, nenhuma tela chama `POST pedidos/{id}/pagar`. `SupportedBrands`, `onlineTries` e `tryPayOnline`
  (`constants/app.ts`, `store/appStore.ts`) não são usados. A descrição também diz "Escolha a revenda", e o build de
  produção fixa `EMPRESA_ID=2`, sem seleção.
- **Impacto:** a política de metadados da Play pede que a descrição corresponda às funções do app. O consumidor
  procura o cartão e não encontra.
- **Correção:** tirar "cartão" e "escolha a revenda" do texto até existirem, ou implementar a tokenização.
- **Esforço:** P.

### P2-B-10 — As capturas da Play mostram nome completo e celular plausível
- **Categoria:** LGPD / material de loja · **Severidade:** MÉDIA · **Estado:** HIPÓTESE (não sei se é pessoa real)
- **Onde:** `store-assets/gasemcasa/play/phone-02.png` (login) e `store-assets/gasemcasa/raw/11-perfil.png`
  exibem nome e sobrenome com celular de DDD 42 no formato válido. Não reproduzo os valores aqui.
- **Impacto:** se for cliente ou colaborador real, é exposição pública de PII na vitrine. O briefing proíbe usar dado
  da homologação.
- **Correção:** confirmar com o dono e refazer as capturas com dado fictício (ex.: "Maria Exemplo", `(42) 90000-0000`).
- **Esforço:** P.

### P2-B-11 — A chave do Google Maps é usada no cliente para Geocoding e Places (web services)
- **Categoria:** segurança / custo · **Severidade:** MÉDIA · **Estado:** FORTE INDÍCIO (não consultei o console do Google)
- **Onde:** `services/address.service.ts:11-15` (`maps.google.com/maps/api/geocode/json?key=…`),
  `(auth)/address.tsx:392` (`GooglePlacesAutocomplete` com `APP.gap_key`). É a mesma chave do Maps SDK (manifest
  local `android/app/src/main/AndroidManifest.xml`, `com.google.android.geo.API_KEY`, valor não reproduzido).
  `.env.example` manda restringir "por package name + SHA-1". Restrição de app Android vale para o SDK, mas
  requisição REST feita pelo JS não leva essa identificação.
- **Impacto:** das duas uma: com a restrição de app, o geocode e a busca de endereço falham em produção; sem ela, a
  chave extraível do APK fica aberta para uso de terceiros, faturado na conta da revenda.
- **Correção:** duas chaves (SDK restrita por app; web services só no backend) e um proxy `GET app/v1/geocode` /
  `places` no erp-novo, que já tem `GOOGLE_MAPS_KEY` e o job de geocodificação.
- **Aceite:** grep sem `gap_key` em URL REST no app. As buscas passam pelo backend com throttle.
- **Esforço:** M.

### P2-B-12 — Login por SMS: falhas engolidas, mensagem errada, beco sem saída e SMS em dobro no marketplace
- **Categoria:** UX / robustez · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (código)
- **Onde:**
  - `sms.tsx:296-303`: erro de `signInWithPhoneNumber` (cota, número inválido, Play Integrity) vai só para
    `console.error`, e a tela fica esperando um SMS que não vem.
  - `sms.tsx:316-337`: com `confirm` nulo, `confirm.confirm` lança `TypeError`, que vira "Código de SMS Inválido".
  - `sms.tsx:356-358`: qualquer erro de login diferente de 422 (401 de token Firebase, 429 do throttle, falta de
    rede) mostra `ErrorView` sem ação.
  - Marketplace: o tutorial vai direto para `/login` (`page1.tsx:108-109`). Sem revenda, o `finishLogin` só percebe
    a falta **depois** do SMS (`sms.tsx:270-273`), manda para a seleção, e a seleção manda de volta para o login
    (`selecionar-revenda.tsx:124`): são dois SMS.
- **Impacto:** o usuário não sabe por que não entrou e não tem como tentar de novo sem fechar o app.
- **Correção:** toast específico por código do Firebase. `ErrorView` com "Tentar de novo" e "Voltar". Tutorial →
  seleção de revenda antes do login quando não houver `EMPRESA_ID`.
- **Esforço:** P.

### P2-B-13 — O produto não entrega prazo, Gás do Povo nem lembrete de reposição
- **Categoria:** produto · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (código)
- **Onde:** `setGasDoPovo` não é chamado em lugar nenhum (grep), então o preço Gás do Povo (`init?gasdopovo=1`,
  cotação, pedido) é inalcançável. A caixa do perfil não é salva (R2-15). `tempo_entrega_min` aparece só na aba
  Informações e na seleção de revenda; não aparece na Home, no carrinho nem no acompanhamento antes de o entregador
  ter posição. O acompanhamento marca "Pedido Recebido pela Revenda" como passo atual logo após a criação
  (`track.tsx:85-91`), antes de a revenda agir. `pagamentos_online` do `GET config` é ignorado. Não há lembrete de
  reposição, agendamento (mesmo com `GET feriados` servido) nem promoção vinda do servidor: o banner da Home é texto
  fixo (`home/index.tsx:162-171`).
- **Impacto:** o consumidor não vê "chega em ~40 min" antes de confirmar. O programa federal Gás do Povo, que a
  revenda pode atender, fica fora do app.
- **Esforço:** M (prazo e Gás do Povo); G (lembrete).

### P2-B-14 — Acessibilidade: zero rótulos, contraste da marca abaixo de AA, alvos de toque pequenos
- **Categoria:** acessibilidade · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (grep + cálculo)
- **Evidência:**
  - `accessibilityLabel`: 0 ocorrências; `accessibilityRole`: 0; elementos `<Pressable>`/`<TouchableOpacity>`: 36.
  - Botões só com ícone (voltar, `+`/`−`/lixeira do carrinho, copiar PIX) ficam sem nome no TalkBack/VoiceOver.
  - Contraste calculado (WCAG): branco sobre `#FF6200` (`colors.primary`, todos os botões) = **3,00:1**, abaixo de
    4,5:1 para texto normal. `textMuted` sobre o fundo = 4,51:1 (no limite).
  - O stepper do carrinho tem 30×30 dp com `hitSlop` 6 (≈ 42 dp), abaixo de 48 dp (Material) e 44 pt (Apple).
  - Não há `maxFontSizeMultiplier`. Com fonte do sistema grande, as alturas fixas (`checkoutBar` 60, painel do SMS
    `height: 320`) tendem a cortar texto (HIPÓTESE: não renderizei).
- **Correção:** `accessibilityRole="button"` + `accessibilityLabel` nos tocáveis, laranja mais escuro para fundo de
  texto (ex.: `#C24A00` dá ≈ 4,9:1 com branco; HIPÓTESE de cor a validar com a marca), alvo mínimo de 44–48 dp.
- **Esforço:** M.

### P2-B-15 — Avaliação: o app não sabe o que já foi avaliado e descarta a segunda avaliação em silêncio
- **Severidade:** BAIXA · **Estado:** CONFIRMADO (código)
- **Onde:** `historico` não devolve `avaliado` (`PedidoMobileService.php:366-378`; `HistoryItem` em
  `types/types.ts:327-334`). O banner volta a cada montagem (`avaliacaoDispensada` é estado local) e "Avaliar"
  aparece em todo pedido concluído. O servidor recusa a repetição (`:324-326`), e o modal engole o erro
  (`EvaluateModal.tsx:31-35`, `.catch(() => {})`).
- **Correção:** `avaliado: bool` no histórico; esconder banner e botão quando verdadeiro.
- **Esforço:** P.

### P2-B-16 — "Acompanhar" sem pedido mostra um pedido fantasma; a tela não tem voltar nem contato
- **Severidade:** BAIXA · **Estado:** CONFIRMADO (código)
- **Onde:** o atalho da Home (`home/index.tsx:115`) abre `/track` com `pendingOrder` nulo. A tela mostra "Pedido em
  Andamento" vazio, com "Pedido Realizado" como passo atual (`track.tsx:79-84`), e não tem botão de voltar.
  `goToWhatsApp` está vazio (`:117-119`).
- **Correção:** estado vazio "Você não tem pedido em andamento", botão de voltar, e WhatsApp/telefone da revenda
  vindos de `GET reseller`.
- **Esforço:** P.

### P2-B-17 — Logout e troca de revenda não limpam o cache, o WebSocket nem o token no servidor
- **Severidade:** BAIXA · **Estado:** CONFIRMADO (código)
- **Onde:** `queryClient` é de módulo (`_layout.tsx:14`), e `resetStorage` (`helpers/utils.ts:182-186`) não o limpa:
  `["perfil"]`, `["addresses"]` e `["order-history"]` do usuário anterior aparecem até o refetch.
  `disconnectRealtime` nunca é chamado, e o Echo guarda o Bearer da primeira conexão (`helpers/realtime.ts:29-46`).
  "Sair" não chama `POST app/v1/logout`, que existe, e o token continua válido no servidor até vencer.
- **Impacto:** num aparelho compartilhado, os dados do usuário anterior aparecem por um instante. Num novo login no
  mesmo processo, o tempo real falha (cai para o polling).
- **Correção:** `queryClient.clear()`, `disconnectRealtime()` e `POST logout` no `handleLogout` e no `setEmpresaAtiva` com troca.
- **Esforço:** P.

### P2-B-18 — A chave de cifra do armazenamento local vem de `Math.random`; o fallback sem cifra é silencioso
- **Severidade:** BAIXA · **Estado:** CONFIRMADO (código). Truncamento a 16 bytes: HIPÓTESE (documentação do MMKV)
- **Onde:** `store/storage.ts:22-28` gera 64 hex com `Math.random` (não é CSPRNG) e o comentário diz "256 bits". O
  `react-native-mmkv` documenta `encryptionKey` com no máximo 16 bytes. Se o keychain falhar, `:52-58` cria a
  instância sem cifra e só registra no console. `android:allowBackup="true"` no manifest local. O mesmo gerador está
  copiado no `app-entregador/src/store/storage.ts:23`.
- **Correção:** `expo-crypto.getRandomBytes(16)`, aviso ou telemetria no fallback, `allowBackup=false` (ou regras de
  backup que excluam o MMKV) e um único `storage.ts` em `mobile-shared`.
- **Esforço:** P.

### P2-B-19 — O release Android depende de uma pasta `android/` local, não versionada, que permite HTTP em claro
- **Severidade:** BAIXA · **Estado:** FORTE INDÍCIO (não rodei Gradle, por proibição)
- **Onde:** `app-gas-em-casa/android/` está no `.gitignore` (linha 41). O
  `android/app/src/main/AndroidManifest.xml` local tem `android:usesCleartextTraffic="true"`. `app.json` usa a chave
  `"allowHttp": false`, que não é configuração do Expo e não tem efeito. `scripts/build-aab-producao.sh` gera o AAB
  dessa pasta.
- **Impacto:** a API é HTTPS, então o risco imediato é baixo. Mas o artefato publicado não é reproduzível a partir
  do repositório, e um `API_URL` http passaria sem erro.
- **Correção:** `expo-build-properties` → `android.usesCleartextTraffic: false` e prebuild limpo no script.
- **Esforço:** P.

### P2-B-20 — Dependências com vulnerabilidade conhecida e SDK atrasado
- **Severidade:** BAIXA · **Estado:** CONFIRMADO (`npm audit`)
- **Evidência:** `npm audit --omit=dev` → **50 vulnerabilidades (8 low, 15 moderate, 23 high, 4 critical)**. Quase
  todas vêm de ferramentas de build (`@expo/cli`, `metro`, `tar`, `shell-quote`, `ws`), que estão em `dependencies`.
  Com efeito em runtime ficam `axios` (direta, DoS por tamanho de resposta) e
  `query-string`/`decode-uri-component` (via `@react-navigation/core`, parse de deep link). O próprio audit propõe
  `expo-dev-client@58` como correção, o que sugere o Expo 53 várias versões atrás (HIPÓTESE quanto ao número exato).
- **Correção:** `npm audit fix` sem `--force` para as transitivas, `axios` ≥ versão corrigida, e plano de subida do SDK.
- **Esforço:** M.

### P2-B-21 — A suíte do app não cobre tela, serviço nem contrato; foi por isso que P2-B-01 passou
- **Severidade:** MÉDIA · **Estado:** CONFIRMADO
- **Evidência:** `npx vitest run` → 3 arquivos, **17 testes**, todos de helpers puros (`polyline`, `dataNascimento`,
  `validators`). Nenhum cobre `services/*`, `flashStore`, carrinho, PIX ou login. `react-dom`/`react-native-web` não
  estão instalados, então não há renderização web possível. Do lado do backend, nenhum teste usa o payload do app.
- **Correção:** um teste de contrato no backend com os payloads exportados dos `services` do app (ou um JSON de
  exemplos versionado e lido pelos dois lados); testes vitest do `flashStore` e dos services com adapter falso
  (modelo: a sonda `sonda-http.test.ts`); colocar `tsc` e `vitest` do app no CI (pendência citada em PIE-18).
- **Esforço:** M.

---

## 3. Resíduos da Rodada 1 (ainda no código; não renumerados)

| ID R1 | Situação em `133f588d` | Evidência |
|---|---|---|
| R2-02 / PIE-11 | **Parcial.** O servidor recusa PIX para condição sem `pix_online`, mas o app ainda chama `GerarPix` depois de **todo** pedido (`carrinho.tsx:95`), e o texto falso do PIX continua (ver P2-B-04) | `pix.tsx:114-115` |
| R2-13 | **Parcial.** O servidor aceita `Idempotency-Key` em `POST pedidos` (`routes/api.php`, middleware `idempotente`), mas o app não manda a chave e não esvazia o carrinho ao criar | sonda vitest: `tentativas=1 idempotency=null`, timeout → "Sem conexão" |
| R2-14 | Pendente | `carrinho.tsx:124-126, 259-264` |
| R2-15 | Pendente | `perfil-dados.tsx:57`; `UserForm` |
| R2-16 | Pendente | `login.tsx:47` |
| PIE-34 | Aparece no app: o toast mostra `validation.required_without (and 1 more error)` | sonda PHP 1 + vitest |
| PIE-22 | Agravado pelo perfil `preview`: `APP_DEBUG=true` + API da homologação (`eas.json`), e o app manda `fake:+55…` quando `APP.debug` (`sms.tsx:276-278`, `newuser.tsx:72-74`) | Não repito a análise |

---

## 4. Contrato com a API (`src/services` × `routes/api.php` × controllers)

Todas as 24 chamadas do app têm rota. Divergências encontradas:

| Chamada do app | Rota/controller | Situação |
|---|---|---|
| `POST app/v1/pedidos` | `AppPedidoController::criarPedido` | **Diverge:** exige `lat/lng` sem `cliente_id` (P2-B-01); ignora o endereço do app (P2-B-02) |
| `POST pedidos/{id}/pix` | `gerarPix` | O app chama sempre; o servidor recusa sem `pix_online` (resíduo R2-02) |
| `GET pedidos` | `historico` (limite 50) | Falta `avaliado` (P2-B-15). O tipo `HistoryItem` bate com o resto |
| `GET pedidos/{id}` | `acompanhar` | Bate (o app usa `any`) |
| `GET pedidos/{id}/rota-entregador` | `rotaEntregador` | Bate com `RotaEntregadorData`. O `destino` é a coordenada **inline** do cliente (mesma causa de P2-B-02) |
| `GET config` | `config` | `tempo_entrega_min` e `pagamentos_online` são ignorados pelo app |
| `GET perfil` / `PUT perfil` / `DELETE perfil` | `AppPerfilController` | Bate. O PUT não aceita `gasdopovo`/`sexo` (R2-15) |
| `POST cliente/login` / `cadastro` | `AppAuthController` | Bate. O 422 "telefone não encontrado" é usado como desvio para o cadastro (contrato frágil: qualquer outro 422 também desvia) |
| Rotas sem uso no app | `token/refresh`, `logout`, `devices`, `perfil/endereco`, `pedidos/{id}/pagar`, `pedidos/{id}/cancelar`, `feriados`, `poligonos` | Funções que o servidor oferece e o app não usa (P2-B-03, -04, -08, -09, -17) |

---

## 5. Checklist de loja

| Requisito | Situação | Evidência / observação |
|---|---|---|
| Pacote / bundle | OK | `br.com.dubena.gasemcasa` (`app.json`, `android/app/build.gradle:92`) |
| versionCode / versionName | OK (manual) | 67 / 3.1.10. `eas.json` usa `appVersionSource: remote` + `autoIncrement`; o build local depende de subir à mão (runbook) |
| targetSdk / compileSdk | OK | 36 (`expo-build-properties`; `gradle.properties:61`) |
| Assinatura de upload | OK no código | Plugin `withUploadSigning`; não executado (Gradle proibido) |
| Permissões Android | OK | Localização (fina+aproximada, só em uso, com texto de propósito), rede, notificações, vibração. Bloqueadas: mídia, overlay, armazenamento, biometria, microfone. Sem câmera nem contatos |
| `usesCleartextTraffic` | **Atenção** | `true` no manifest local (P2-B-19) |
| Ícones / splash | OK | `ios-light.png` 1024² opaco (sem alfa); adaptativo 1024²; ícone da Play 512² (`validacao-play.md`) |
| iOS: manifesto de privacidade, criptografia, push, URL scheme do Firebase | OK no `app.json` | Não validado em build (sem conta Apple) |
| Política de privacidade (URL) | **Falha hoje** | `/novo/privacidade` → 404 (depende de deploy + `PUBLICO_*`) |
| Política acessível dentro do app | **Falha** | Só os termos legados antes do login (P2-B-07) |
| Exclusão de conta no app | Existe, mas **incompleta** | Perfil → Excluir conta; só desativa (P2-B-05) |
| Exclusão por URL | **Falha hoje** | `/novo/excluir-conta` → 404; o texto promete mais do que o código faz |
| Data Safety (Play) | **Incompleta** (HIPÓTESE quanto ao rigor do revisor) | A tabela do runbook omite CPF e data de nascimento ("outras informações pessoais"), forma/situação de pagamento ("informações financeiras/compras") e a mensagem de avaliação ("conteúdo gerado pelo usuário"). Também não cita o que os SDKs Firebase coletam |
| Metadados fiéis ao app | **Falha** | "cartão no app", "escolha a revenda" (P2-B-09) |
| Capturas sem PII | **Verificar** | P2-B-10 |
| Conta de teste para o revisor | Planejada | Telefone e código de teste no runbook; precisa do número de teste cadastrado no Firebase real |
| Fluxo principal funcional para o revisor | **Falha** | O pedido dá 422 (P2-B-01): a revisão da Play/Apple, que testa o fluxo, encontraria o app quebrado (HIPÓTESE quanto à profundidade do teste do revisor) |

---

## 6. Segurança e LGPD no cliente (resumo)

- **Token:** Sanctum no MMKV cifrado, com a chave no SecureStore. Bom desenho; a geração da chave é fraca (P2-B-18).
- **Logs com PII:** 11 `console.*`, nenhum com telefone ou CPF em claro. Os `console.log` do `UserForm:159,168,193`
  estão em campos `disabled` e não disparam.
- **Bundle:** sem segredo de servidor. A chave do Maps é pública por natureza, mas o uso em web service a deixa sem
  restrição efetiva (P2-B-11). `google-services.json` e `GoogleService-Info.plist` estão no `.gitignore` (linhas
  22-23) e vão ao EAS pelo `.easignore`; não verifiquei restrição no console do Firebase.
- **Deep links:** esquema `dubenagasemcasa` (+ `exp+gasemcasa` do dev-client). O grupo `(auth)` não tem guarda de
  sessão, mas as telas só exibem dado do servidor, e o servidor exige token: risco baixo.
- **Minimização:** "Sexo", "Convênio" e "Gás do Povo" são coletados e descartados (R2-15). A exclusão guarda tudo (P2-B-05).
- **Consentimento:** colhido sobre texto de terceiro (P2-B-07).

---

## 7. Qualidade (execuções reais)

| Comando (em `app-gas-em-casa/`) | Resultado |
|---|---|
| `npx tsc --noEmit` | **exit 0** |
| `npx vitest run` | **3 arquivos, 17 testes, todos verdes** (~0,5 s) |
| `npm audit --omit=dev` | **50 vulnerabilidades** (8 low, 15 moderate, 23 high, 4 critical); ver P2-B-20 |
| Sonda vitest `scratchpad/b/sonda-http.test.ts` | 3/3. Timeout de POST → status 0 "Sem conexão", 1 tentativa, sem `Idempotency-Key`; 401 → `onUnauthorized`; 422 → mensagem crua |
| Sonda PHP `scratchpad/b/SondaContratoAppConsumidorTest.php` (`vendor/bin/phpunit` no erp-novo, sqlite) | 3/3 rodadas. Saídas citadas em P2-B-01, -02 e -05 |

**Tamanho e organização:** 9.510 linhas em `src/`. As maiores telas são `address.tsx` (633), `pedidos/index.tsx`
(500), `carrinho.tsx` (352) e `home/index.tsx` (350). Há código morto: `SupportedBrands`, `onlineTries`,
`TestNotification`, `ImageBanner`, `startupvideo`, `startupVideoCache`, `StoreFcmToken`, `GetFeriados`,
`GetPoligonos`, `OrderService.Cancel`.

**Duplicação:** a camada HTTP já é compartilhada (`mobile-shared/src/http.ts`). `helpers/polyline.ts` é idêntico nos
dois apps (0 linhas diferentes). `store/storage.ts` (40 linhas diferentes) e `helpers/realtime.ts` (31) são cópias
divergentes do mesmo código: candidatas a ir para `mobile-shared`.

---

## 8. Oportunidades de produto (ELIMINAR → JUNTAR → AUTOMATIZAR → ANTECIPAR → CRIAR)

**Fundamentadas (evidência acima):**
1. **ELIMINAR** o nome no login para quem já é cliente: o nome só é usado no cadastro (`newuser.tsx:53`), mas é
   pedido antes de saber se o telefone existe (`login.tsx:36-60`). Pedir só o telefone corta um campo e o erro de
   regex (R2-16) de todo cliente recorrente.
2. **ELIMINAR** o código morto listado na seção 7 e a tentativa cega de `GerarPix` (P2-B-06).
3. **JUNTAR** endereço e checkout: "Entregar em: Casa ▸" no carrinho, usando o favorito (P2-B-02).
4. **JUNTAR** os termos e a política num único texto servido pelo erp-novo, por tenant (P2-B-07).
5. **AUTOMATIZAR** a renovação da sessão (`token/refresh`) e o registro do token FCM (P2-B-03, P2-B-08).
6. **AUTOMATIZAR** o cancelamento do pedido do app com PIX expirado (P2-B-04).
7. **ANTECIPAR** o prazo: mostrar `tempo_entrega_min` na Home e no botão "Finalizar" ("chega em ~40 min") (P2-B-13).
8. **ANTECIPAR** a ida ao acompanhamento: tocar na notificação abre o pedido (P2-B-08).
9. **CRIAR** marcação de canal "app" nas condições de pagamento, configurável pela revenda (P2-B-06).
10. **CRIAR** Gás do Povo de verdade: seletor na Home quando `gaspovo_ativo`, com preço próprio (já existe no servidor) (P2-B-13).

**Hipóteses a validar com revendas:**
- **Lembrete de reposição:** "seu último P13 foi há 38 dias; pedir de novo?". Exige histórico por cliente (há) e
  push funcional (depende do Firebase real). Hipótese: aumenta a recompra pelo app em vez do telefone.
- **Pedido em 1 toque** pela notificação de reposição (ação "Pedir igual"), sem abrir o app, com a mesma condição e
  o mesmo endereço do último pedido.
- **Troco para dinheiro e observação** ("portão azul") no checkout: o servidor já aceita `observacao`, mas o app não
  envia. Hipótese: reduz ligação do entregador.
- **Marketplace multi-revenda num só app × um build por revenda:** hoje o build de produção é de uma revenda
  (`EMPRESA_ID=2`). Para N revendas, decidir se a vitrine é única (seleção já existe) ou white-label por loja (custo
  de publicação × marca da revenda).

---

## 9. Fechamento

**Inspecionado:**
- Todas as 20 rotas de `src/app/**`, `services/*`, `store/*`, `helpers/http.ts`, `realtime.ts` e os hooks de
  notificação;
- `mobile-shared/src/http.ts`;
- `app.json`, `app.config.ts`, `eas.json`, `.gitignore`, o manifest e o `build.gradle` locais;
- os quatro controllers `App*`, `PedidoMobileService`, `EnderecoMobileService`, `CatalogoMobileService`,
  `PixService` e `ClienteService::encerrarPeloTitular`;
- as rotas `app/v1` de `routes/api.php`;
- `store-assets` (README, `textos.md`, `validacao-play.md`, duas capturas);
- as views `/privacidade` e `/excluir-conta`;
- `docs/06-runbooks/PUBLICACAO_LOJAS.md`.

**Executado:**
- `tsc` e `vitest` do app;
- `npm audit --omit=dev`;
- sonda vitest da camada HTTP (3 testes);
- sonda PHPUnit de contrato e LGPD (3 testes, sqlite);
- `curl` GET em 3 URLs públicas e nos termos legados;
- conferência de alfa dos ícones (Pillow);
- cálculo de contraste WCAG.

**Não testado:**
- nada em aparelho, emulador ou build (proibido);
- renderização: `react-dom`/`react-native-web` não estão instalados;
- SMS e push reais (o Firebase está em `fake`, PIE-22);
- teclado, voltar físico, rotação, fonte grande e telas pequenas (só leitura de estilo);
- o Postgres/RLS dos endpoints do app (só sqlite);
- a existência de condição a prazo ativa na base;
- a restrição real das chaves do Google e do Firebase;
- o novo login após a exclusão de conta;
- a revisão real de Play e Apple.

**Limitações:**
- regras de política de loja citadas sem consulta online nesta sessão: onde marcado, são HIPÓTESE com a referência
  (Play: *account deletion*, *Data safety*, *metadata*; Apple: 5.1.1(i) e 5.1.1(v));
- a duração da sessão em produção depende do `.env`, que não li;
- as capturas da loja podem ter dado fictício (não confirmei).

**Parecer: REPROVADO.**
- **Bloqueia publicação e homologação com cliente real:** P2-B-01 (CRÍTICA, confirmado por execução: o app não
  consegue criar pedido).
- **Precisam entrar antes da liberação, por perda de pedido, entrega no endereço errado, cliente travado, crédito
  indevido ou dívida com LGPD e loja:** P2-B-02, P2-B-03, P2-B-04, P2-B-05, P2-B-06 e P2-B-07.
- **Ressalva com prazo:** as demais.
- **Esforço:** quase tudo é P ou M. Corrigido o P2-B-01 (P), o app fica utilizável e a recompra em 2 toques passa a
  existir de fato, desde que também se resolva a sessão (P2-B-03).
