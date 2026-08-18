# PLANO DE MODERNIZAÇÃO DO APP MOBILE "Gás em Casa"

> Backlog oficial de desenvolvimento. Objetivo: transformar o app em produto **moderno, seguro, intuitivo, escalável e 100% integrado ao ERP-NOVO**, com o ERP como **única fonte da verdade**.
> Base: achados de `AUDITORIA_APP_MOBILE.md`. Cada fase é independentemente entregável e testável.

> **PREMISSA DE TRABALHO:** `app-gas-em-casa/` é uma **CÓPIA EDITÁVEL** do app legado. Editá-la **não afeta produção**. Esta cópia só substitui o app atual **depois** de pronta/testada/validada. Consequências:
> - **Alvo direto:** este app passa a falar **só com o ERP-NOVO (`app/v1`)**; o código legado é **removido** conforme a migração avança (sem caminho legado em paralelo dentro desta cópia).
> - **Sem trava de compatibilidade durante o desenvolvimento** — podemos refatorar agressivamente.
> - A preocupação com "clientes em versões antigas" pertence **somente ao momento do switch** do app real (F11), não ao desenvolvimento aqui.
> - **Caminho crítico no servidor:** 2 lacunas no ERP-NOVO precisam ser fechadas — login de **cliente** por telefone verificado (F1) e endpoints **PIX** no `app/v1` (F4).

---

## ESTADO DE EXECUÇÃO

| Fase | Status | Observações |
|---|---|---|
| **F0 — Fundação de segurança** | ✅ **Implementada** | Segredos saíram do código (`app.config.ts` + `.env.example` + `expo-constants`); `app_key`/Google key/`api_url` agora vêm de env; MMKV **cifrado** via `expo-secure-store` (chave no keychain/keystore, boot em `initSecureStorage()` + `skipHydration`); HTTP arbitrário desligado (removido `NSAllowsArbitraryLoads`, `allowHttp:false`). |
| **F2 — Camada HTTP nova** | ✅ **Implementada** | `helpers/http.ts` reescrito: instância única apontando para o ERP-NOVO, Bearer automático, timeout (20s), retry com backoff (GET/HEAD em rede/5xx), 401→logout, erro normalizado no padrão Laravel `{message,errors}`. Serviços (`product/order/store/user/address`) re-apontados para `app/v1`; assinaturas preservadas para não quebrar telas; métodos sem equivalente marcam erro 501 explícito (em vez de chamar o legado). |
| **F1 — Auth real** | ✅ **Implementada** | **Servidor:** `kreait/firebase-php` (gate `FIREBASE_DRIVER`, Fake no CI), `FirebaseVerifier` (contract/fake/kreait) + binding, `ClienteAuthService` (verifica ID token→telefone→cliente por `empresa_id`→cria/vincula `user`), `AppAuthController@loginCliente` + rota pública `POST /app/v1/cliente/login`. **App:** `sms.tsx` religado ao `UserService.Login()` (envia `firebase_id_token`+`empresa_id`+device); 422→cadastro. `EMPRESA_ID` no env. **Testes:** 442 verde (4 novos de login de cliente: sucesso, reuso de user, empresa errada→422, token inválido→401). |
| F3 — Catálogo/preço server-side | ⏳ | Telas Home/OrderConfirm e tipos (`Root`/`Product`/`Payment`) precisam casar com o shape do `app/v1/init` (`{produtos,condicoes}`). Endereço/vídeo/Gás-do-Povo precisam de endpoints novos no ERP-NOVO. |
| F4 — Checkout/PIX | ⏳ | **Pré-requisito no servidor:** criar `POST app/v1/pedidos/{id}/pix` + `GET .../pix/status` (a infra PIX existe no admin — `PixService`/`PixCobranca`/webhook — mas hoje acoplada a `financeiroparcela_id`, não a pedido). |

> **Como rodar (F0):** copie `.env.example` para `.env`, preencha `API_URL` (ERP-NOVO), `GOOGLE_MAPS_API_KEY` e `APP_ENV`. Sem `node_modules` neste checkout: `yarn install` antes de `expo start`.
> **Pendência de higiene (F0):** `google-services.json` / `GoogleService-Info.plist` seguem versionados (são necessários ao build). Removê-los do tracking + rotacionar é decisão de deploy, não de código.

---

## 0. PRINCÍPIOS (não negociáveis)

1. **Núcleo único:** nenhuma regra de negócio, preço, desconto ou validação crítica no cliente. O app é **cliente burro** do ERP-NOVO.
2. **Auth real por usuário** (Sanctum), nunca token-mestre.
3. **Cartão tokenizado no device** (SDK do adquirente); o app nunca trafega PAN/CVV.
4. **Tenant sempre derivado do token** no servidor.
5. **Alvo direto, sem legado em paralelo:** como esta cópia só vai a produção pronta, cada serviço migrado ao `app/v1` **remove** o equivalente legado deste app. (O desligamento do servidor legado `ctrl-web/app/Api` é evento à parte — F11 — e só depende da adoção do app real em produção.)
6. **Tudo coberto por testes** (servidor) e fluxos críticos por testes de integração (app).

---

## VISÃO GERAL DAS FASES

| Fase | Tema | Complexidade | Risco |
|---|---|---|---|
| F0 | Fundação de segurança + remoção de segredos | Média | Baixo |
| F1 | Auth real por usuário (Sanctum) + vínculo usuário↔cliente | Alta | Médio |
| F2 | Camada HTTP nova (cliente do `app/v1`) | Média | Baixo |
| F3 | Catálogo, carrinho e preço server-side | Alta | Médio |
| F4 | Checkout: pedido, cupom e PIX via ERP-NOVO | Alta | Médio |
| F5 | Pagamento online com cartão tokenizado (PCI) | Alta | Alto |
| F6 | Ciclo do pedido: histórico, acompanhar, cancelar, avaliar | Média | Baixo |
| F7 | Tempo real + push acionável (substituir polling) | Média | Médio |
| F8 | Offline-first, cache e resiliência | Alta | Médio |
| F9 | Modernização de UX/UI | Média | Baixo |
| F10 | Observabilidade, monitoramento e OTA updates | Média | Baixo |
| F11 | Corte final: desligar legado + cleanup | Média | Alto |

---

## FASE 0 — Fundação de Segurança e Higiene

**Objetivo:** eliminar exposições imediatas sem mudar arquitetura.
**Justificativa:** segredos versionados, HTTP liberado e storage em claro são exploráveis hoje.

- **App:** remover `app_key`/Google key de `constants/app.ts` → migrar para `expo-constants`/EAS secrets/`.env` por ambiente; rotacionar Google Maps key; configurar MMKV com `encryptionKey` (do Keychain/Keystore); desligar `NSAllowsArbitraryLoads`/`allowHttp` (forçar HTTPS); adicionar `.gitignore` para `google-services.json`/`GoogleService-Info.plist` (e rotacionar).
- **API:** confirmar `throttle` no legado em `getToken`; revisar `APP_TOKEN_KEY`.
- **ERP-NOVO:** nenhuma.
- **Banco:** nenhuma.
- **Segurança:** segredos fora do repo; HTTPS obrigatório; storage cifrado.
- **Testes:** build iOS/Android sem segredos hardcoded; smoke de rede só HTTPS.
- **Critérios de aceite:** `grep` por chaves no repo = vazio; app recusa HTTP; MMKV cifrado.
- **Dependências:** nenhuma. **Riscos:** rotação de chaves quebrar build → coordenar release.

---

## FASE 1 — Autenticação Real por Usuário + Vínculo Usuário↔Cliente

**Objetivo:** acabar com token-mestre e IDOR; cada cliente tem identidade própria no ERP-NOVO.
**Justificativa:** achados Críticos #1 e #2 da auditoria.

- **App:** novo fluxo: phone-auth Firebase **continua** como verificação de posse do número, mas o login passa a chamar `POST /app/v1/login` do ERP-NOVO retornando **Sanctum token por usuário/device**; enviar `device_id`+`push_token`+`plataforma`+`app_versao`. Persistir token cifrado; `logout` chama `POST /app/v1/logout` (revoga no servidor).
- **API/ERP-NOVO:** ajustar `AppAuthController@login` para suportar **login por cliente do app**. Hoje exige `email`+`password` (perfil colaborador/entregador). Adicionar estratégia "cliente por telefone verificado": após validar o ID token do Firebase no servidor (Firebase Admin), emitir Sanctum token vinculado ao `User` do cliente.
- **Banco:** criar/garantir coluna `clientes.user_id` (vínculo cliente↔usuário) — referenciada em `PushService` e `notificarStatus`, **verificar se já existe na migration**; criar `User` para clientes do app na primeira autenticação.
- **Segurança:** verificação server-side do token Firebase; token Sanctum por device; revogação real.
- **Testes:** `MobileTest` — login de cliente via telefone verificado emite token; acesso a pedidos de outro cliente → 403; logout revoga.
- **Critérios de aceite:** nenhum endpoint aceita `cliente_id` arbitrário; identidade vem do token.
- **Dependências:** F0. **Complexidade:** Alta. **Riscos:** integração Firebase Admin no backend; migração de base de clientes legados para `users`.

---

## FASE 2 — Nova Camada HTTP (cliente do `app/v1`)

**Objetivo:** ponto único de acesso ao ERP-NOVO, robusto.
- **App:** reescrever `helpers/http.ts` com `axios` + **interceptors** (Bearer automático, `tenant` implícito no token), **timeout**, **retry com backoff** em idempotentes, tratamento de 401 (→ re-login), normalização de erros (`{message, errors[]}` do Laravel). Base URL por ambiente via F0. Mapear todos os serviços (`order/product/store/user/address`) para os endpoints `app/v1`.
- **API/ERP-NOVO:** garantir contrato de erro consistente (`ValidationException` → 422 com `errors`).
- **Banco:** nenhuma.
- **Testes:** mocks de 401/timeout/422; contrato de erro.
- **Critérios de aceite:** todas as chamadas passam por uma instância; 401 dispara re-auth.
- **Dependências:** F1. **Complexidade:** Média. **Riscos:** baixo.

---

## FASE 3 — Catálogo, Carrinho e Preço 100% Server-Side

**Objetivo:** remover `calculateTotal`/`applyDiscount`/resolução de preço do cliente.
**Justificativa:** achado Crítico #4 (preço confiável no cliente).

- **App:** consumir `GET /app/v1/init` (produtos + condições) e `GET /app/v1/produtos`. O carrinho local guarda **apenas `produto_id` + quantidade**; total/desconto **sempre** vêm de um endpoint de cotação. Remover auto-add hardcoded do "13kg" (tornar configurável via ERP). Remover `productPrices`/`calculateTotal`/`applyDiscount` do `flashStore`.
- **API/ERP-NOVO:** adicionar `POST /app/v1/carrinho/cotacao` (itens + condição + cupom + geoloc → total, descontos, frete, indisponibilidades) reaproveitando `CatalogoMobileService`/`PedidoMobileService`. Retornar itens indisponíveis por forma de pagamento (regra que hoje está no cliente).
- **Banco:** nenhuma (ou índice por `empresa_id, ativo`).
- **Segurança:** preço nunca aceito do cliente.
- **Testes:** cotação com/sem cupom, item indisponível, Gás do Povo; snapshot de valores.
- **Critérios de aceite:** payload de pedido **não** contém preço; valores exibidos = resposta do servidor.
- **Dependências:** F2. **Complexidade:** Alta. **Riscos:** paridade de cálculo com o legado (validar lado a lado).

---

## FASE 4 — Checkout: Pedido, Cupom e PIX

**Objetivo:** criar pedido pelo ERP-NOVO.
- **App:** `OrderConfirm` chama `POST /app/v1/pedidos` enviando **itens + condição + `codigo_cupom` + geoloc/endereço** (sem preços). Cupom validado por `GET /app/v1/cupom?codigo=`. PIX: criar pedido → endpoint de PIX → tela `pix.tsx` consumindo status do ERP-NOVO. Corrigir bug de `datahoraprevisao` (servidor define a data).
- **API/ERP-NOVO:** expor geração de **PIX** (cobrança + `pixcopiaecola` + QR + status `pago`) — hoje a auditoria não encontrou endpoint PIX no `app/v1`; **criar** `POST /app/v1/pedidos/{id}/pix` e `GET /app/v1/pedidos/{id}/pix/status` (ou webhook + push). Reusar `PixWebhookController` existente.
- **Banco:** tabela de cobranças PIX (se inexistente) escopada por `empresa_id`.
- **Segurança:** idempotência na criação de pedido (evitar duplicidade por retry).
- **Testes:** criar pedido por geoloc e por cliente; 1-pendente (já testado); PIX pago via webhook simulado.
- **Critérios de aceite:** pedido criado no ERP-NOVO com valores do servidor; PIX confirma via webhook/push.
- **Dependências:** F3. **Complexidade:** Alta. **Riscos:** integração PIX/PSP.

---

## FASE 5 — Pagamento Online com Cartão Tokenizado (PCI)

**Objetivo:** eliminar PAN/CVV do device e do tráfego.
**Justificativa:** achado Crítico #3 (PCI-DSS).

- **App:** **remover** captura de PAN/CVV em estado próprio (`OnlinePayment.tsx`/`cardInfo`). Integrar **SDK de tokenização** do adquirente (eRede/`gateway` em uso) que retorna **token** do cartão no device. Enviar **somente o token** para `POST /app/v1/pedidos/{id}/pagar` (contrato já existente). Implementar limite de tentativas no servidor (não só `onlineTries` local).
- **API/ERP-NOVO:** `pagar` já aceita `token`+`parcelas` (pronto). Adicionar rate-limit por pedido/usuário e tratamento de 402.
- **Banco:** `pagamentos_online` (já existe) — garantir não armazenar dado de cartão.
- **Segurança:** SAQ-A (cartão nunca toca o app/servidor); 3DS se aplicável.
- **Testes:** aprovado (201) / negado (402) com `FakePagamentoDriver` (já testado); fluxo de token mockado no app.
- **Critérios de aceite:** nenhum dado de cartão em logs/estado/payload; só token trafega.
- **Dependências:** F4. **Complexidade:** Alta. **Riscos:** Alto (homologação com adquirente).

---

## FASE 6 — Ciclo do Pedido (histórico, acompanhar, cancelar, avaliar)

**Objetivo:** mover o pós-venda para o ERP-NOVO.
- **App:** `pedidos`/`track` consomem `GET /app/v1/pedidos`, `GET /app/v1/pedidos/{id}`, `POST .../cancelar`, `POST .../avaliar`. Recompra usa cotação (F3). Timeline derivada de `situacao.efeito` do servidor (não de flags locais).
- **API/ERP-NOVO:** endpoints já existem e testados. Ajustar `clienteDoUsuario` para **derivar o cliente do token** (não aceitar `cliente_id`) — fecha resíduo de IDOR.
- **Banco:** `pedido_avaliacoes` (já existe).
- **Testes:** histórico/acompanhar/cancelar/avaliar 1× (já cobertos); negativa para pedido de outro usuário.
- **Critérios de aceite:** pós-venda 100% no ERP-NOVO, sem `cliente_id` no cliente.
- **Dependências:** F1, F2. **Complexidade:** Média. **Riscos:** baixo.

---

## FASE 7 — Tempo Real + Push Acionável

**Objetivo:** substituir polling (PIX 30s, status 60s) por eventos.
- **App:** tratar push de mudança de status (`acao: orderUpdated`) para invalidar/atualizar query do pedido; deep-link do push para a tela de acompanhamento. Opcional: WebSocket/SSE para status ao vivo.
- **API/ERP-NOVO:** `PedidoMobileService::notificarStatus` + `PushService` já enviam push ao cliente; garantir push também na confirmação de PIX. Opcional: broadcasting (Laravel Reverb/Echo).
- **Banco:** nenhuma.
- **Segurança:** payload de push sem dados sensíveis.
- **Testes:** push muda a UI sem polling; deep-link abre acompanhamento.
- **Critérios de aceite:** atualização de status sem polling perceptível.
- **Dependências:** F6. **Complexidade:** Média. **Riscos:** médio (entrega de push).

---

## FASE 8 — Offline-First, Cache e Resiliência

**Objetivo:** app utilizável com rede instável.
- **App:** persistir cache do React Query (MMKV), fila de mutações com retry, indicador de conectividade, estados otimistas com rollback. Catálogo cacheado; checkout exige online.
- **API/ERP-NOVO:** idempotency keys nas mutações; ETags/`updated_at` para sync incremental.
- **Banco:** colunas `updated_at` consistentes.
- **Testes:** voo-avião → leitura de cache; reconexão drena fila.
- **Critérios de aceite:** abrir app offline mostra último catálogo; mutações não se perdem.
- **Dependências:** F2. **Complexidade:** Alta. **Riscos:** médio.

---

## FASE 9 — Modernização de UX/UI

**Objetivo:** sensação de produto profissional sem perder funções.
- **App:** skeletons/loading states, mensagens de erro acionáveis (substituir "erro desconhecido (1)"), onboarding enxuto (condensar tutorial+vídeo), acessibilidade (labels, contraste, dynamic type), i18n das mensagens, design tokens consistentes, microinterações.
- **API/ERP-NOVO:** banners/configs de app servidos pelo ERP (sem hardcode).
- **Testes:** visual/regressão de telas-chave; a11y básica.
- **Critérios de aceite:** zero strings de erro genéricas; onboarding ≤ 2 passos opcionais.
- **Dependências:** F3–F6. **Complexidade:** Média. **Riscos:** baixo.

---

## FASE 10 — Observabilidade, Monitoramento e OTA

**Objetivo:** operar com visibilidade e atualizar rápido.
- **App:** Crashlytics/Sentry; analytics de funil (catálogo→checkout→pago); EAS Update (OTA) para correções sem store.
- **API/ERP-NOVO:** logs estruturados nos endpoints `app/v1`, métricas (latência/erros), correlação por `device_id`/`request_id`.
- **Banco:** nenhuma.
- **Segurança:** logs sem PII/cartão.
- **Testes:** crash de teste reportado; dashboard recebe métricas.
- **Critérios de aceite:** erro do app rastreável fim a fim; OTA publica patch.
- **Dependências:** F2. **Complexidade:** Média. **Riscos:** baixo.

---

## FASE 11 — Corte Final: Switch para Produção + Desligar o Legado

> **Nota de premissa:** a remoção do código legado **dentro desta cópia** já acontece de forma incremental em F2–F6 (princípio #5). Esta fase trata do **evento de switch do app real** e do **desligamento do servidor legado** — onde mora o risco residual.

**Objetivo:** colocar esta cópia em produção no lugar do app atual e ter uma única fonte da verdade de fato.
- **App:** garantir que **nenhum** vestígio de serviço/endpoint legado restou (já removidos nas fases anteriores); build de release apontando 100% para ERP-NOVO; **kill-switch por versão** (forçar atualização mínima) para tirar de circulação o app legado real.
- **API:** depreciar e desligar `ctrl-web/app/Api`; manter **janela de compatibilidade** enquanto houver instalações do app legado real não atualizadas.
- **Banco:** plano de migração/reconciliação de dados legado→ERP-NOVO; congelar escrita no legado.
- **Segurança:** revogar `APP_TOKEN_KEY`/token-mestre; remover `default_user_id`.
- **Testes:** paridade funcional completa antes do switch; **canary** por % de usuários; rollback ensaiado.
- **Critérios de aceite:** nova versão publicada nas lojas; 0 chamadas ao legado em telemetria após a janela; legado read-only e depois off.
- **Dependências:** F1–F10. **Complexidade:** Média. **Riscos:** Alto — **mas concentrado no app real em produção** (usuários em versões antigas), não nesta cópia. Mitigar com update mínimo obrigatório + janela de transição do servidor legado.

---

## ROADMAP SUGERIDO (ordem de execução)

`F0 → F1 → F2 → F3 → F4 → F5 → F6 → F7 → F8 → F9 → F10 → F11`

- **MVP seguro (pré-requisito para liberar o switch de F11):** F0, F1, F3, F5 (fecham os 4 Críticos). *Não há produção desta cópia a "desbloquear" — são os críticos a fechar antes de ela poder substituir o app atual.*
- **Paridade funcional:** + F2, F4, F6.
- **Excelência:** + F7, F8, F9, F10.
- **Consolidação:** F11.

## MATRIZ ACHADO → FASE

| Achado da auditoria | Fase(s) que resolve |
|---|---|
| App não integrado ao ERP-NOVO | F2, F3, F4, F6, F11 |
| Token-mestre / IDOR | F1, F6 |
| PAN+CVV em claro (PCI) | F5 |
| Preço/desconto no cliente | F3, F4 |
| Segredos versionados / HTTP liberado | F0 |
| Storage não cifrado / logout local | F0, F1 |
| Sem offline / refresh / observabilidade | F2, F8, F10 |
| Polling no lugar de tempo real | F7 |
| Duas fontes da verdade | F11 |
| UX datada / erros genéricos | F9 |

---

## OBSERVAÇÕES DE COMPATIBILIDADE

- O ERP-NOVO **já implementa e testa** auth por usuário, tenant, catálogo, pedido, pagamento (token), cancelar/avaliar, entregador e push. A maior parte do esforço é **no app** (migrar o cliente, que é uma cópia editável) + **2 lacunas no servidor**: (a) login de **cliente** via telefone verificado (F1) e (b) endpoints de **PIX** no `app/v1` (F4). Confirmar a coluna `clientes.user_id` antes da F1/F7.
- Como esta cópia só vai a produção quando pronta, **não há trava de compatibilidade durante o desenvolvimento** — a refatoração pode ser direta ao alvo. O **servidor legado** (`ctrl-web/app/Api`) permanece vivo apenas para o **app real de produção** até o switch (F11), garantindo zero downtime e rollback seguro lá.
