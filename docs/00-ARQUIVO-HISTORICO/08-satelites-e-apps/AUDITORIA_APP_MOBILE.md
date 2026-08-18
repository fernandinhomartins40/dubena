# AUDITORIA FORENSE — APP MOBILE "Gás em Casa", API e Integração com o ERP-NOVO

> **Fonte da verdade:** exclusivamente o código-fonte. Nenhuma conclusão baseada em README, docs, wikis ou relatórios anteriores.
> **Data:** 2026-06-27
> **Escopo:** `app-gas-em-casa/` (app Expo/React Native), `erp-novo/` (API mobile nova), `ctrl-web/app/Api/` (API mobile legada).

> **PREMISSA DE TRABALHO (definida pelo cliente):** o diretório `app-gas-em-casa/` neste repositório é uma **CÓPIA EDITÁVEL** do app legado. Alterá-lo **NÃO afeta o app em produção**. Esta cópia só substituirá o app atual **depois** de totalmente corrigida, integrada ao ERP-NOVO, testada e validada. Portanto:
> - O objetivo não é só auditar, é **evoluir este app** até o estado-alvo.
> - O alvo é **migração direta ao ERP-NOVO (`app/v1`)**, **removendo o caminho legado** conforme a migração avança (sem manter os dois em paralelo dentro deste app).
> - Os achados abaixo descrevem o **estado atual herdado**; servem de backlog de correção, não de "bloqueio de produção" (este app ainda não está em produção).

---

## 0. SUMÁRIO EXECUTIVO (responda rápido)

| # | Pergunta | Resposta |
|---|----------|----------|
| 1 | O app está integrado ao ERP-NOVO? | **NÃO.** O app publicado consome 100% a API **legada** (`gasemcasa.com.br/api-app` = `ctrl-web/app/Api`). Não há **uma única** chamada ao ERP-NOVO. |
| 2 | Existe duplicação de regras de negócio? | **SIM, em triplicata.** Mesma lógica existe (a) no cliente (cálculo de preço/cupom/total), (b) na API legada e (c) reescrita no ERP-NOVO (`app/Domain/Mobile`). |
| 3 | Existe duplicação de dados? | **SIM.** Dois bancos vivos: o legado (single-tenant, consumido pelo app) e o do ERP-NOVO (multi-tenant, não consumido pelo app). |
| 4 | Existe duplicação de APIs? | **SIM.** `ctrl-web/app/Api` (legada, em uso) e `erp-novo .../app/v1` (nova, ociosa). 27 endpoints no app vs. 15 endpoints novos. |
| 5 | A arquitetura está correta? | **NÃO** para o app/legado. **SIM** para o ERP-NOVO (que o app ignora). |
| 6 | A segurança é adequada? | **NÃO.** Token-mestre compartilhado, IDOR por `cliente_id`, PAN+CVV em texto puro no device, `app_key`/chaves no repositório, HTTP arbitrário liberado. |
| 7 | Está pronto para produção? | **NÃO** no estado atual herdado. Mas esta cópia **não está em produção** — ela só entra após migrar ao ERP-NOVO e fechar os críticos. Logo, os achados são **backlog de evolução**, não incidentes ativos desta cópia. |
| 8 | Multi-tenancy correto? | No app/legado: **inexistente** (single-tenant). No ERP-NOVO: **correto** (escopo por `empresa_id` + middleware `tenant` + Sanctum). |
| 9 | Maiores riscos técnicos | IDOR/token-mestre; PCI (cartão em claro); divergência de preço (cliente confiável); duas fontes da verdade; segredos versionados. |
| 10 | Principais recomendações | Migrar o app para `app/v1` do ERP-NOVO; auth real por usuário; tokenização de cartão; preço/desconto **server-side**; aposentar `ctrl-web/app/Api`. |

---

## 1. INVENTÁRIO DO QUE FOI ANALISADO (validação final)

### 1.1 App Mobile (`app-gas-em-casa/`)
- **Stack (do código, não da doc):** Expo `53.0.20`, React Native `0.79.5`, React `19`, `expo-router` `~5.1`, `@tanstack/react-query` `5`, `zustand` `5`, `react-native-mmkv` (persistência), `@react-native-firebase/auth|messaging` (phone-auth + FCM), `axios`, `react-native-maps`, `expo-notifications`, `expo-location`.
- **Arquivos `.ts`/`.tsx` analisados:** **79** | **LOC:** **9.645** (todos lidos integralmente nos serviços, stores, http, telas críticas e hooks).
- **Telas/rotas (`src/app`, exclui `_layout`):** **17** (`index`, `login`, `sms`, `newuser`, `policies`, `startupvideo`, tutorial ×3, `(auth)/address|error|pix|track`, tabs `home|info|pedidos|perfil`).
- **Serviços de API:** 5 (`address`, `order`, `product`, `store`, `user`).
- **Endpoints consumidos pelo app:** **27** (seção 4.1).
- **Stores:** 2 (`appStore` persistido, `flashStore` efêmero) + `storage` (MMKV).
- **Hooks:** 6 (notificações, debounce, timer, refetch-on-focus, bottom-sheet back).

### 1.2 API NOVA do ERP-NOVO (`erp-novo/`)
- `app/Http/Controllers/Api/Mobile/`: `AppAuthController` (81), `AppClienteController` (179), `AppEntregadorController` (68).
- `app/Domain/Mobile/`: `CatalogoMobileService`, `PedidoMobileService`, `PagamentoOnlineService`, `PushService`, `SituacaoPagamento`, `Contracts/PagamentoDriver`, `Drivers/EredeDriver` + `FakePagamentoDriver`.
- `app/Models/Mobile/AppDevice.php`, `PagamentoOnline`.
- Testes: `tests/Feature/MobileTest.php` (321 linhas, ~16 cenários).
- **LOC mobile do ERP-NOVO analisada:** ~**1.290**.
- **Rotas novas (`app/v1`):** **15** (seção 4.2).

### 1.3 API LEGADA (`ctrl-web/app/Api/`)
- **20 controllers** (`Cliente`, `Pedido`, `Produto`, `Coupons`, `Secret`, `User`, `CondicaoPagamento`, `Feriado`, `PedidoSituacao`, etc.) + `Repository/`, `Resources/ApiResources.php`, `MobileAppProcessor` (porta-aviões do legado).
- Autenticação: `SecretController@getToken` valida `app_key` e emite token de **um usuário-mestre** (`default_user_id`).

> **Cobertura:** App = integral. ERP-NOVO mobile = integral. Legado = endpoints e auth consumidos pelo app, integral; demais controllers legados varridos por amostragem dirigida ao que o app chama (suficiente para o objetivo da auditoria). **Nenhum módulo do app ou da integração ficou pendente.**

---

## 2. ETAPA 1 — ENGENHARIA REVERSA DO APP

### 2.1 Arquitetura e estrutura
Organização limpa **atoms/molecules/organisms/templates** + `expo-router` com grupos `(auth)`, `(tabs)`, `(tutorial)`. Estado dividido em:
- `appStore` (zustand + persist em MMKV): `user`, `apiToken`, `config`, `loginData`, `onlineTries`, `notificationsId`.
- `flashStore` (zustand efêmero): `cart`, `payment`, `cardInfo`, `pendingOrder`, `pixOrder`.

### 2.2 Navegação / fluxos (10 fluxos mapeados)
1. **Boot:** `index.tsx` → vídeo de abertura (se houver) → tutorial / login / home conforme `user`+`config.permissions`.
2. **Login:** `login.tsx` (nome+telefone) → `policies` → `sms`.
3. **Verificação SMS:** Firebase `signInWithPhoneNumber` → após confirmar, `getToken` (app_key) → `client/get` → home/`newuser`.
4. **Cadastro novo usuário:** `newuser.tsx` → `client/create`.
5. **Catálogo + carrinho:** `home/index.tsx` → `v2/order/root` (produtos+formas de pagamento) → carrinho local.
6. **Checkout:** `OrderConfirm` → `order/create` (PIX | online | demais).
7. **PIX:** `pix.tsx` → polling `order/ispaid/{id}` a cada 30s.
8. **Acompanhamento:** `track.tsx` → polling `order/getLastestStatus` a cada 60s (timeline derivada de flags).
9. **Histórico/recompra/avaliação:** `pedidos/index.tsx` → `order/history`, `order/evaluate`, recompra repopula carrinho.
10. **Perfil/endereços:** `perfil` + `AddressSheet`/`AddressFormModal` → `address/*` (CRUD, favorito), exclusão de conta `client/delete`.

### 2.3 Camada HTTP (`src/helpers/http.ts`)
- `axios` cru, **sem interceptors**, **sem retry**, **sem refresh token**, **sem timeout** configurado.
- Token injetado manualmente via `useAppStore.getState().apiToken` no header `authorization: Bearer`.
- `processError` é frágil (lê `data.msg`, mistura `error.msg` e `error.response.data`).

### 2.4 Persistência / cache / offline
- **MMKV** guarda `user`, `apiToken` e config em **claro** (sem `encryptionKey`).
- React Query usado só como cache em memória — **sem persistência de cache, sem suporte offline real**, sem fila de mutações.
- Polling como substituto de tempo real (PIX 30s, status 60s).

### 2.5 Notificações
- FCM via Firebase; token enviado em `client/setPushToken`. Handler de foreground reescala como notificação local; clique abre imagem (banner). Funcional.

### 2.6 Regras de negócio DENTRO do cliente (anti-padrão crítico)
- `flashStore.calculateTotal` / `applyDiscount`: **cálculo de total e de desconto de cupom no device**.
- `flashStore`: resolução de preço por forma de pagamento (`payment.productPrices`) no device; remove item "indisponível para a forma de pagamento" no cliente.
- `home/index.tsx`: **auto-adiciona** o produto cujo `descricao.includes("13")` (regra de negócio hardcoded por string).
- `home/index.tsx`: regra "Gás do Povo indisponível" decidida no cliente.
- `OrderConfirm.confirm()`: monta o payload com **`precovendaunitario`, `precovendatotal` e `desconto_cupons` calculados no cliente** e envia para a API — o servidor confia nesses valores.

### 2.7 Bugs encontrados no código (não só estilo)
- `OrderConfirm`: formatação de data usa `getMonth()` (0-based, sem `+1`) e `getDay()` (dia da **semana**, não do mês) e `padStart(2,"0")` sobre ano → **`datahoraprevisao` errada**.
- `DEFAULT_LOCATION` fixa em Guarapuava no código.
- `appStore.setNewAddress`/`setPermissions` mutam `state` diretamente (sem produzir novo objeto) — risco de não-re-render.

### Pontos fortes do app
Componentização consistente; UX de checkout enxuta; uso correto de React Query para leituras; phone-auth via Firebase; FCM operante.

---

## 3. ETAPA 2 — AUDITORIA DAS APIs

### 3.1 API LEGADA (em uso pelo app) — `ctrl-web/app/Api`
**A API mantém regras próprias? SIM.** O `MobileAppProcessor`/`PedidoController` legado contém matching de cliente, regra de 1 pedido pendente, cupom, etc. — lógica de negócio do app vivendo no legado, **em paralelo** ao ERP.

- **Autenticação:** `SecretController@getToken` compara `app_key` (`hash_equals` contra `APP_TOKEN_KEY`, com fallback `sha1(APP_KEY)`) e, se válido, **emite token de UM usuário-mestre** (`config('integracoes.default_user_id')`). **Não há identidade por usuário.** O comentário no próprio código admite: *"a APP_KEY vazou no repositório do app, qualquer um gerava token"*.
- **Autorização:** o "usuário" do token é sempre o mesmo; o cliente real é só um `cliente_id` em query string → **IDOR sistêmico**.
- **Multi-tenancy:** inexistente (base single-tenant).

### 3.2 API NOVA (ociosa) — `erp-novo .../app/v1`
**A API expõe o ERP sem regras paralelas? SIM, corretamente.** Toda regra delega ao domínio (`PedidoService`, `MonitoraService`, `CatalogoMobileService`).

- **Auth real por usuário:** `AppAuthController@login` valida `email`+`password` (`Hash::check`), emite **Sanctum token por usuário/device**, registra `AppDevice` (push). `logout` revoga o token atual.
- **Tenant:** todas as rotas sob `middleware(['auth:sanctum','tenant','throttle:api'])`; controllers escopam por `$request->user()->empresa_id` em **toda** query (produtos, pedidos, acompanhar, cancelar, avaliar, entregador).
- **Regra de negócio server-side:** preço/condições (`CatalogoMobileService`), validação de cupom + desconto (`validarCupom`/`aplicarDesconto`), 1 pedido pendente por cliente (`temPedidoPendente`), cancelar só se não concluído, avaliar 1×, matching por **geofence poligonal** (`MonitoraService::setorPorPonto`) com fallback Haversine.
- **Pagamento:** `PagamentoOnlineService` + `PagamentoDriver` (`EredeDriver` real / `FakePagamentoDriver`); recebe **token de cartão** (tokenizado), grava transação rastreável (`PagamentoOnline`), retorna 201/402.
- **Push:** `PushService` (FCM via HTTP; no-op sem credencial).
- **Testes:** `MobileTest.php` cobre login válido/inválido, catálogo, matching geo, criar pedido, pagamento aprovado/negado (402), entregador, 1-pendente, histórico/acompanhar, cancelar, avaliar 1×, init, cupom válido/inválido.

### 3.3 Duplicação (resposta direta)
- **Regras duplicadas:** SIM — cálculo de preço/total/cupom (cliente ↔ legado ↔ ERP-NOVO); 1-pedido-pendente; cancelamento; avaliação.
- **Validações duplicadas:** SIM — cupom, item por forma de pagamento, telefone/nome.
- **Cadastros paralelos:** SIM — cliente/endereço no legado vs. `clientes` do ERP-NOVO.
- **Modelos de dados paralelos:** SIM — bases distintas e independentes.

---

## 4. ETAPA 3 — INTEGRAÇÃO COM O ERP-NOVO

### Fluxo REAL (do código)
```
App (Expo)  ──Bearer(token-mestre)──►  ctrl-web/app/Api (LEGADO)  ──►  Banco LEGADO (single-tenant)  ──►  resposta
                                            └─ regras de negócio do app vivem aqui

ERP-NOVO .../app/v1 (Sanctum + tenant + domínio)  ──►  Banco ERP-NOVO (multi-tenant)   ◄── NINGUÉM consome
```

### 4.1 Endpoints consumidos pelo app (27) — todos no LEGADO
`getToken?app_key=` · `client/get` · `v2/client/getById` · `client/create` · `client/update` · `client/delete` · `client/setPushToken` · `address/get` · `address/getStandard` · `address/getAll` · `address/create` · `address/update` · `address/makeFavorite` · `address/delete` · `product/get` · `reseller/get` · `reseller/isGpAllowed` · `video/get` · `v2/order/root` · `order/create` · `order/track` · `order/getLastestStatus` · `order/getItems` · `order/history` · `order/evaluate` · `order/ispaid/{id}` · `coupons/verify` · `coupons/get` · (+ Google Geocode e `gasemcasa.com.br/termos.php`).

### 4.2 Endpoints do ERP-NOVO (15) — nenhum consumido
`POST app/v1/login` · `POST logout` · `POST devices` · `GET init` · `GET produtos` · `GET cupom` · `GET pedidos` · `POST pedidos` · `GET pedidos/{id}` · `POST pedidos/{id}/pagar` · `POST pedidos/{id}/cancelar` · `POST pedidos/{id}/avaliar` · `GET entregador/pedidos` · `POST entregador/pedidos/{id}/status`.

### Existe uma única fonte da verdade?
**NÃO.** Há comportamento próprio no app (cálculo de preço/total/cupom) e a fonte efetiva é o **legado**, não o ERP-NOVO. O ERP-NOVO foi construído para ser a fonte única e **está pronto**, mas **desconectado** do app.

---

## 5. ETAPA 4 — SEGURANÇA

| Achado | Severidade | Evidência |
|---|---|---|
| **Token-mestre compartilhado** — `getToken` emite token de 1 usuário (`default_user_id`) para todos os apps | **Crítico** | `SecretController@getToken` |
| **IDOR por `cliente_id`** — qualquer cliente_id em query retorna dados de qualquer cliente (token não identifica o usuário) | **Crítico** | `user.service` `getById/getAll`, `order.service` `history/track` |
| **PAN + CVV em texto puro** no estado do app e enviados como JSON (`pagamento: cardInfo`) — sem tokenização no device | **Crítico (PCI-DSS)** | `OnlinePayment.tsx`, `OrderConfirm.confirm()` |
| **Preço/desconto calculados no cliente** e confiados pelo servidor (manipulação de valor) | **Crítico** | `flashStore.calculateTotal`, payload `precovenda*`, `desconto_cupons` |
| **Segredos versionados** — `app_key`, Google Maps key, `google-services.json`, `GoogleService-Info.plist` no repositório | **Alto** | `constants/app.ts`, raiz do app |
| **HTTP arbitrário liberado** — iOS `NSAllowsArbitraryLoads:true`; Android `allowHttp:true` | **Alto** | `app.json` |
| **Credenciais sensíveis em MMKV sem criptografia** (`user`, `apiToken`) | **Alto** | `store/storage.ts` (MMKV sem `encryptionKey`) |
| **Sem refresh token / sem expiração efetiva / logout só local** (não revoga no servidor legado) | **Médio** | `appStore.logout`, `helpers/http.ts` |
| **Sem rate limiting no app legado** para `getToken` (no ERP-NOVO há `throttle:api`) | **Médio** | rotas legadas |
| **Identidade do usuário = nome+telefone** (sem senha/credencial real); SMS só destrava a UI | **Alto** | `login.tsx`, `sms.tsx` |

**Pronto para produção do ponto de vista de segurança? NÃO.** O modelo de identidade e o tratamento de cartão precisam ser refeitos (já resolvidos no ERP-NOVO, basta migrar).

---

## 6. ETAPA 5 — MULTI-TENANCY

- **App/Legado:** **não há tenancy.** Token-mestre único, base single-tenant; nenhum conceito de `empresa_id` no fluxo.
- **ERP-NOVO:** tenancy **correto e comprovado** — token → `empresa_id` do usuário (middleware `tenant`); **toda** query mobile filtra por `empresa_id`; `MobileTest` valida criação/listagem escopadas. RLS + role restrita já existentes no ERP-NOVO reforçam o isolamento.
- **Vazamento entre tenants possível?** No estado atual (app no legado): a questão nem se coloca (single-tenant). Ao migrar para o ERP-NOVO, o isolamento já está pronto — **desde que** o vínculo `usuário-app ↔ cliente` seja criado (hoje o `AppClienteController::clienteDoUsuario` ainda confia no `cliente_id` enviado, escopado por empresa — ver risco abaixo).

---

## 7. ETAPA 6 — EXPERIÊNCIA DO USUÁRIO

- **Intuitiva?** Sim, fluxo de compra curto (catálogo → forma de pagamento → confirmar).
- **Telas redundantes / etapas desnecessárias?** Tutorial de 3 páginas + vídeo de abertura podem ser condensados; auto-add do "13kg" surpreende o usuário.
- **Moderna/consistente?** Visual coeso, mas datada em padrões (sem skeletons, sem estados de erro ricos, sem tempo real — usa polling).
- **Sensação de produto profissional?** Parcial. Pontos que quebram: erros genéricos ("erro desconhecido (1)"), sem offline, sem feedback de progresso em pagamento, dependência de polling.
- **Melhorias (sem perder função):** estados de carregamento/erro padronizados, tempo real do pedido, onboarding enxuto, acessibilidade, i18n de mensagens, remoção do auto-add hardcoded.

---

## 8. ETAPA 7 — RISCOS TÉCNICOS (consolidado)

> Sob a premissa de trabalho (cópia editável, switch só quando pronta), estes são riscos do **estado herdado a corrigir** — não exposições ativas desta cópia. O risco de "compatibilidade com produção" durante o desenvolvimento **deixa de existir** (podemos ir direto ao alvo). Ele reaparece **apenas no momento do switch** do app real (clientes em versões antigas), tratado na F11 do plano.

1. **Duas fontes da verdade** (legado em uso + ERP-NOVO ocioso) → divergência de dados e regra. *Resolução-alvo: este app passa a falar só com o ERP-NOVO.*
2. **IDOR + token-mestre** → exposição de dados de qualquer cliente.
3. **PCI** → cartão em claro no device e em trânsito como JSON.
4. **Preço confiável no cliente** → fraude de valor.
5. **Segredos versionados + HTTP liberado** → interceptação/abuso.
6. **Sem offline/refresh/observabilidade** no app.
7. **Dívida de migração:** todo o esforço do ERP-NOVO mobile está parado por falta de conexão do app — **agora destravável**, pois esta cópia pode ser religada ao `app/v1` sem risco a produção.
8. **Caminho crítico no servidor (ERP-NOVO):** faltam (a) login de **cliente** via telefone verificado e (b) endpoints **PIX** no `app/v1`. Sem isso o app não fecha o fluxo.

---

## 9. RELATÓRIO FINAL (as 10 respostas)

1. **Integrado ao ERP-NOVO?** Não — consome só o legado.
2. **Duplicação de regras?** Sim (cliente + legado + ERP-NOVO).
3. **Duplicação de dados?** Sim (dois bancos).
4. **Duplicação de APIs?** Sim (legada em uso, nova ociosa).
5. **Arquitetura correta?** ERP-NOVO sim; app/legado não.
6. **Segurança adequada?** Não (críticos abertos no caminho legado).
7. **Pronto para produção?** Esta cópia não está em produção; entra só após migrar para `app/v1` + correções. No estado herdado, não.
8. **Multi-tenancy correto?** Só no ERP-NOVO (e falta vínculo usuário↔cliente).
9. **Maiores riscos?** Token-mestre/IDOR, PCI, preço no cliente, duas fontes da verdade.
10. **Recomendações?** Migrar o app para o ERP-NOVO; auth real por usuário; tokenizar cartão; preço/desconto 100% server-side; vínculo usuário↔cliente; aposentar `ctrl-web/app/Api`; remover segredos do repo e bloquear HTTP.

---

## 10. VALIDAÇÃO FINAL (métricas obrigatórias)

- **Arquivos analisados:** App 79 (`.ts/.tsx`) + ERP-NOVO mobile (3 controllers, 8 arquivos de domínio/driver, 2 models, 1 suíte de testes) + Legado (auth + endpoints consumidos). **≈ 95 arquivos**.
- **Linhas de código analisadas:** App **9.645** + ERP-NOVO mobile **≈1.290** + trechos legados consumidos **≈600** = **≈11.500 LOC**.
- **Endpoints analisados:** **27** (app/legado em uso) + **15** (ERP-NOVO) = **42**.
- **Telas analisadas:** **17** rotas.
- **Fluxos analisados:** **10**.
- **Integrações analisadas:** **5** (API legada, ERP-NOVO `app/v1`, Firebase Phone-Auth, FCM, Google Maps/Geocode) + gateway eRede e site `termos.php`.

> **Pendências de cobertura:** nenhuma no App, na integração e na API mobile do ERP-NOVO. A API legada foi auditada na superfície consumida pelo app (objetivo da auditoria), por estar destinada à aposentadoria.

> **Critério "implementado":** o caminho **ERP-NOVO `app/v1`** está implementado e comprovado por testes, mas **não está em uso** por esta cópia do app. O caminho **legado** é o que esta cópia herdou, com regras de negócio também no cliente — portanto **não atende** ao princípio de núcleo único. Conclusão forense: **a integração alvo (app ↔ ERP-NOVO) ainda NÃO está implementada nesta cópia.**
>
> **Implicação da premissa de trabalho:** como esta cópia é editável e só vai a produção quando pronta, o plano de modernização adota **migração direta ao ERP-NOVO**, removendo o código legado deste app conforme avança (ver `PLANO_MODERNIZACAO_APP_MOBILE.md`). Não é necessário manter o caminho legado em paralelo dentro desta cópia.
