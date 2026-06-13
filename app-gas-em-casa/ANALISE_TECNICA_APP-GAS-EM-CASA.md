# Análise Técnica Completa — Aplicação "app-gas-em-casa"

> **Documento gerado por auditoria de código-fonte.**
> Base da análise: **exclusivamente o código-fonte real** do workspace `app-gas-em-casa`.
> Documentação auxiliar (`AGENTS.md`, comentários) foi **ignorada como fonte de verdade** — exceto onde o próprio código/config versionado expõe segredos, registrado como evidência de segurança.
> Data da análise: 2026-06-12.
> Cada conclusão referencia o arquivo/linha que a fundamenta. Itens sem evidência conclusiva estão marcados como **[Necessita Validação]**.

> **Nota sobre a premissa de "25 anos":** o código **não sustenta** essa idade. É um aplicativo **React Native / Expo SDK 53 / React 19** (stack de **2024–2025**), reescrito recentemente (versão `app.json` 3.1.10). Trata-se do **cliente mobile mais novo** do ecossistema "Gás em Casa", consumidor da API `api-app-gc`. A premissa de 25 anos aplica-se ao negócio, não a este componente — que é o **mais moderno** dos analisados.

---

## 1. Visão Geral da Aplicação

### Objetivo principal
O `app-gas-em-casa` é o **aplicativo móvel (iOS/Android) de venda de gás/GLP ao consumidor final** — "Gás em Casa". É o **front-end mobile** que consome a API REST `api-app-gc` para cadastro de clientes, pedidos, pagamento (PIX e cartão online), rastreamento de entrega e notificações push.

Evidência: `app.json` (`name: "Gás em Casa"`, `bundleIdentifier: com.qti.gasemcasa`), `src/services/*` apontando para a API (`order/create`, `coupons/verify`, `client/get`), integração Firebase (auth/messaging).

### Principais funcionalidades identificadas (por evidência de código)
| Funcionalidade | Evidência |
| --- | --- |
| Login por telefone + verificação SMS (OTP) | `src/app/login.tsx`, `src/app/sms.tsx` (Firebase `signInWithPhoneNumber`) |
| Cadastro de novo usuário | `src/app/newuser.tsx` |
| Aceite de termos/políticas | `src/app/policies.tsx`, `UserService.GetPolicies` |
| Seleção de revenda e catálogo de produtos | `src/services/store.service.ts`, `product.service.ts` |
| Carrinho de compras | `src/store/flashStore.ts` (`addToCart`/`removeFromCart`/`calculateTotal`) |
| Cupons de desconto | `OrderService.VerifyCoupon/GetCoupon`, `applyDiscount` |
| Pedido e checkout | `OrderService.CreateOrder` |
| Pagamento PIX | `src/app/(auth)/pix.tsx`, `OrderService.IsPixPaid` |
| Pagamento com cartão (crédito/débito online) | `src/components/organism/OnlinePayment.tsx`, `SupportedBrands` |
| Rastreamento de entrega em mapa | `src/app/(auth)/track.tsx`, `react-native-maps`, `expo-location` |
| Histórico e avaliação de pedidos | `OrderService.GetHistory/Evaluate` |
| Endereços (com Google Places autocomplete) | `src/services/address.service.ts`, `react-native-google-places-autocomplete` |
| Notificações push (Firebase FCM) | `@react-native-firebase/messaging`, `useForegroundNotifications` |
| Vídeo de abertura (startup) | `src/app/startupvideo.tsx`, `startupVideoCache.ts`, `expo-video` |
| Tutorial de onboarding | `src/app/(tutorial)/page1-3.tsx` |

### Fluxo geral de funcionamento
1. **Onboarding/Login** (`login.tsx`): usuário informa nome + telefone → aceite de termos (`policies.tsx`) → **OTP via Firebase** (`sms.tsx`).
2. Após OTP confirmado, o app **obtém token da API** (`UserService.GetToken` com `app_key` fixa) e busca o cliente (`GetClient`). Se não existir, vai para `newuser`.
3. **Área autenticada** (`(auth)/(tabs)`): home (revenda/produtos), pedidos, perfil, info.
4. **Compra**: monta carrinho (`flashStore`) → escolhe pagamento (PIX/cartão) → `CreateOrder` → acompanha em `track.tsx`/`pix.tsx`.
5. **Pós-venda**: histórico, avaliação, recompra; notificações push.

### Público usuário
**Consumidor final** (pessoa física comprando gás pelo celular). Não há perfil administrativo neste app — gestão fica no ERP/painel.

### Módulos existentes
Auth/OTP, Cadastro, Catálogo/Revenda, Carrinho, Cupom, Checkout (PIX/Cartão), Rastreamento, Endereços, Perfil, Notificações, Tutorial, Vídeo.

---

## 2. Stack Tecnológica

> Versões lidas de `package.json` / `app.json` / `yarn.lock`.

| Tecnologia | Versão | Utilização | Status |
| --- | --- | --- | --- |
| TypeScript | ^5.1.3 (strict) | Linguagem principal | ✅ Atual |
| React | **19.0.0** | UI runtime | ✅ Atual |
| React Native | **0.79.5** | Framework mobile | ✅ Atual |
| Expo (SDK) | **53.0.20** | Plataforma/tooling | ✅ Atual |
| Expo Router | ~5.1.4 | Navegação file-based + typed routes | ✅ Atual |
| Zustand | ^5.0.0-rc.2 | Estado global (appStore/flashStore) | ⚠️ Em **release candidate** (não estável) |
| @tanstack/react-query | ^5.59.8 | Cache/estado servidor | ✅ Atual |
| axios | ^1.7.7 | Cliente HTTP | ✅ Atual |
| react-native-mmkv | ~3.3.0 | Storage local (persistência) | ✅ — **mas sem encryptionKey** (ver §7) |
| @react-native-firebase/app·auth·messaging | ^22.4.0 | OTP por telefone + push (FCM) | ✅ Atual |
| react-native-maps | 1.20.1 | Mapa de rastreamento | ✅ |
| react-native-google-places-autocomplete | 2.5.6 | Autocomplete de endereço | ✅ |
| expo-location / expo-notifications / expo-video | atuais | Localização, push, vídeo | ✅ |
| react-native-reanimated / gesture-handler | ~3.17 / ~2.24 | Animações/gestos | ✅ |
| @gorhom/bottom-sheet | ^5.1.8 | Bottom sheets (pagamento) | ✅ |
| react-native-fast-image | ^8.6.3 | Imagens | ⚠️ Lib com manutenção reduzida **[Necessita Validação]** |
| react-native-ratings | git (fork `onepiecejigsaw`) | Avaliação por estrelas | ⚠️ **Dependência apontando para fork git** (não versão publicada) |
| Prettier | ^3.3.3 | Formatação | ✅ |
| Yarn | (yarn.lock) | Gerenciador de pacotes | ✅ |

### Serviços externos / APIs
- **API consumida (principal):** `api-app-gc` em `https://gasemcasa.com.br/api-app/public/api/` (`constants/app.ts:107`).
- **API secundária:** `https://www.gasemcasa.com.br/termos.php` (políticas).
- **Firebase**: Auth (OTP telefone) + Cloud Messaging (push).
- **Google Maps / Places**: mapa e autocomplete (`googleMapsApiKey`).

### Ferramentas de build / deploy
- **Expo / EAS** (build nativo iOS/Android via `expo run:*`, `expo-dev-client`).
- Não há workflow CI/CD versionado no repositório **[Necessita Validação]**.

### Obsolescência (resumo)
Stack **atual e saudável** — destoa positivamente dos demais sistemas do ecossistema. Pontos de atenção: **Zustand em RC**, **dependência git de `react-native-ratings`**, e libs de manutenção reduzida.

---

## 3. Arquitetura da Aplicação

### Padrão arquitetural
- **App mobile SPA-like** com **Expo Router (file-based routing)**.
- **Atomic Design** explícito na camada de UI: `src/components/{atoms,molecules,organism,templates}`.
- **Camada de serviços** isolando chamadas HTTP (`src/services/*.service.ts`), com cliente HTTP central (`src/helpers/http.ts`).
- **Estado**: Zustand para estado global (persistente `appStore` + efêmero `flashStore`) + React Query para estado de servidor.
- Separação clara: `constants`, `helpers`, `hooks`, `services`, `store`, `styles`, `types`.

### Monólito ou microsserviços
**Aplicativo cliente único (monolito mobile)**, parte de uma arquitetura distribuída (consome a API `api-app-gc` que integra o ERP `ctrl+`).

### Camadas existentes
```
Telas (Expo Router: src/app/**)                 ← roteamento file-based + (auth)/(tabs)
   │
Componentes (atoms→molecules→organism→templates) ← Atomic Design
   │
Hooks (useDebounce, useTimer, useNotification…)  ← lógica reutilizável
   │
Services (user/order/product/store/address)      ← contratos da API
   │
helpers/http.ts (axios + Bearer token)           ← cliente HTTP central
   │
Store (Zustand: appStore persistido via MMKV / flashStore in-memory) + React Query (cache)
   │
APIs externas: api-app-gc · Firebase(Auth/FCM) · Google Maps/Places
```

### Acoplamentos / observações
- **Baixo acoplamento geral** — arquitetura bem fatiada para um app mobile.
- `helpers/http.ts` acessa diretamente `useAppStore.getState().apiToken` (linha 52) — acoplamento do client HTTP ao store global (aceitável, comum em RN).
- **`constants/app.ts` concentra configuração e segredos** (api_url, app_key, gap_key) hardcoded — ver §7.

### Dependências circulares
Não evidenciadas. A separação por camadas e o uso de tipos (`src/types`) reduzem o risco. **[Necessita Validação por ferramenta estática]**

### Fluxo de requisições
`Tela → hook/React Query → Service → Http.PrepareRequest → axios (Bearer token do appStore) → API`. Respostas tratadas por `processError` (`http.ts:14`).

### Fluxo de dados
- **Persistente** (`appStore` + MMKV): `user`, `apiToken`, `config`, `loginData`, `notificationsId`.
- **Efêmero** (`flashStore`, **não persistido**): `cart`, `cardInfo` (dados de cartão), `pixOrder`, `pendingOrder`. ✅ Boa decisão de não persistir dados de cartão em disco.

---

## 4. Modelagem de Dados

> Este é um **app cliente**: **não possui banco de dados relacional próprio**. A "modelagem" local resume-se a **storage chave-valor (MMKV)** e **tipos TypeScript** que espelham os contratos da API.

### Armazenamento local
- **MMKV** (`src/store/storage.ts`): store `default-storage`, usado pelo Zustand `persist`. Guarda token de API e dados do usuário. **Sem `encryptionKey`** (ver §7).
- **Cache de vídeo** (`helpers/startupVideoCache.ts`) via `expo-file-system`.

### "Entidades" (tipos TS — `src/types/`)
`User`, `Address`, `Store` (revenda), `Product`, `Payment`, `Cart`/`CartProduct`, `Coupon`/`VerifiedCoupon`, `Order`/`OrderPix`, `CardInfoPayload`, `Policy`, `Root`. Refletem o schema do backend.

### Procedures / Triggers / Views / Índices
**Não aplicável** — não há banco relacional no app. A modelagem de dados real está na API `api-app-gc` e no ERP (ver relatórios respectivos).

### Mapa conceitual (estado local do app)
```
            ┌─────────────── appStore (persistido em MMKV) ───────────────┐
            │ user · apiToken · config(permissions,termsAccepted)          │
            │ loginData(name,phone) · onlineTries · notificationsId        │
            └──────────────────────────────────────────────────────────────┘

            ┌─────────────── flashStore (memória, NÃO persistido) ─────────┐
            │ store(revenda) · payment · cart(products,total,coupon)        │
            │ cardInfo(card_number,card_cvv,exp,holder) · pixOrder          │
            │ pendingOrder · rebuyOrder · evaluateOrderId                   │
            └──────────────────────────────────────────────────────────────┘
                              │ (contratos via Services)
                              ▼
                   API api-app-gc  ◄──► Firebase(Auth/FCM) ◄──► Google Maps
```

### Observações
- **Redundância de configuração de cartão**: `cardInfo` default repetido em `flashStore` (init + `clearStore`, linhas 135 e 254).
- **Tipos `any` em pontos de pagamento/serviço** (`setCardInfo: (cardInfo: any)`, vários services com `({ data }: any)`) — perda de type-safety em áreas sensíveis.

---

## 5. Fluxos de Negócio

### Principais processos
1. **Autenticação OTP** (`login.tsx`→`sms.tsx`): validação local de nome/telefone → Firebase `signInWithPhoneNumber` → confirmação do código → obtenção de token da API → resolução do cliente.
2. **Descoberta de revenda/catálogo**: seleção de loja e produtos por forma de pagamento (`calculateTotal` aplica preço por `payment.productPrices`).
3. **Carrinho e cupom**: `addToCart`/`removeFromCart`; `applyDiscount` (percentual ou fixo).
4. **Checkout**: PIX (`pix.tsx` + polling `IsPixPaid`) ou **cartão online** (`OnlinePayment.tsx`).
5. **Rastreamento**: `track.tsx` consome posição/itinerário via API + mapa.
6. **Pós-venda**: histórico, avaliação, recompra (`setRebuyOrder`).

### Fluxos críticos
- **Pagamento** (PIX e cartão) — financeiro e PCI-sensível.
- **Autenticação OTP** — porta de entrada; depende do Firebase e do `app_key` da API.
- **Cálculo de total/desconto** (`flashStore.calculateTotal`/`applyDiscount`) — regra de negócio **no cliente** (ver duplicação abaixo).

### Regras duplicadas / em múltiplos locais
- **Cálculo de preço/desconto no cliente** (`flashStore.ts:52-120`): o app recalcula totais e aplica cupom localmente. Como o backend também precisa validar valores, há **regra de negócio duplicada cliente↔servidor** — risco de divergência/manipulação se o servidor confiar no valor enviado **[Necessita Validação no backend]**.
- **Tentativas de pagamento online** (`onlineTries` em `appStore`) — controle de tentativas no cliente (burlável).
- **Definição default de `cardInfo`** repetida (`flashStore` init e `clearStore`).

---

## 6. Estrutura do Código

### Organização
- **Excelente para um app mobile**: roteamento file-based, Atomic Design, serviços isolados, hooks reutilizáveis, tipos centralizados, paths `@/*`. ~**9.645 linhas** em 79 arquivos TS/TSX.

### Qualidade / complexidade
- **Boa**: TypeScript `strict: true`, React Query para servidor, separação clara de responsabilidades, Prettier configurado.
- **Pontos fracos**:
  - Uso de `any` em áreas sensíveis (services e `setCardInfo`).
  - **19 `console.log/warn/error`** no código (incluindo `console.error("cc"/"bb"/"aa", error)` em `sms.tsx:56,71,94`) — logs de debug genéricos em produção.
  - Lógica de fluxo de auth concentrada em `sms.tsx` (encadeamento de 3 mutations com callbacks aninhados).

### Duplicação / código morto / comentado
- **Blocos comentados de configuração de ambiente** em `constants/app.ts:90-104` (env local/homolog/prod comentados) — **troca de ambiente por edição manual de comentário** em vez de variável de build/EAS.
- `// products.set(...)` comentado em `flashStore.ts:173`.
- Baixo volume de código morto no geral.

### Classificação de qualidade por área
| Área | Classificação |
| --- | --- |
| Organização/estrutura | **Excelente** |
| Tipagem (TS strict) | **Bom** (manchado por `any` em pagamento) |
| Camada de serviços | **Bom** |
| Gestão de estado | **Bom** |
| Configuração/segredos | **Ruim** (hardcoded — §7) |
| Logs/debug | **Regular** (console.* + comentários de env) |
| Testes | **Crítico** (nenhum teste encontrado) |
| **Geral** | **Bom** (com correções pontuais de segurança/config) |

---

## 7. Segurança

> Escopo: segurança do **cliente mobile**. Algumas exposições derivam de decisões do backend (ver relatório `api-app-gc`).

### 🔴 CRÍTICA

| # | Vulnerabilidade | Evidência | Impacto |
| --- | --- | --- | --- |
| S1 | **`app_key` da API hardcoded no app** | `constants/app.ts:108` `app_key: "40c20d46182c497aa5147242b91c6923d6a6258e"`. Esse valor é enviado em `getToken?app_key=` (`user.service.ts:28`) e o backend o compara com `sha1(APP_KEY)` para emitir token de **usuário fixo** | Qualquer pessoa que **decompile o APK/IPA** (ou intercepte o tráfego) extrai a `app_key` e **gera tokens válidos da API** → acesso indevido aos endpoints. Vulnerabilidade **encadeada** com a do backend |
| S2 | **Tráfego HTTP não-criptografado permitido** | `app.json`: iOS `NSAllowsArbitraryLoads: true` (linha 28) e Android `allowHttp: true` (linha 52) | Permite conexões **sem TLS** → interceptação de token, dados pessoais e **dados de cartão** (man-in-the-middle). Crítico por haver pagamento |

### 🟠 ALTA

| # | Vulnerabilidade | Evidência | Impacto |
| --- | --- | --- | --- |
| S3 | **Token de API e dados do usuário em storage não criptografado** | `store/storage.ts` cria `MMKV` **sem `encryptionKey`**; `appStore` persiste `apiToken`/`user` nele | Em dispositivo comprometido/rooted, o **Bearer token** e dados pessoais são legíveis em claro |
| S4 | **Dados de cartão (PAN/CVV) trafegam pela API própria** | `OnlinePayment.tsx` coleta `card_number`/`card_cvv`/validade; enviados via `OrderService.CreateOrder` (`flashStore.cardInfo`) | **Risco PCI-DSS**: cartão passando pelo backend próprio (sem evidência de tokenização/gateway PCI no cliente). **[Necessita Validação de como o backend trata]** |
| S5 | **Google Maps API key hardcoded e versionada** | `app.json:19,48` e `constants/app.ts:109` (`AIzaSyDygo66KV3BCnznA_vVG4s63JXpk8Qd0d8`) | Uso indevido/custos se a key não tiver restrições de aplicativo/API |

### 🟡 MÉDIA

| # | Item | Evidência |
| --- | --- | --- |
| S6 | Arquivos de configuração Firebase versionados | `google-services.json`, `GoogleService-Info.plist` no repo (contêm identificadores/keys do Firebase — exposição moderada; comum, mas ideal restringir) |
| S7 | `console.error/log` (19) podem vazar payloads/erros | `sms.tsx`, services |
| S8 | Validação de identidade **só no cliente** + `app_key` única | a autorização real por usuário depende do backend; o app não amarra o token ao cliente autenticado por OTP |
| S9 | Controle de tentativas de pagamento no cliente (`onlineTries`) | `appStore` — burlável |

### 🟢 BAIXA / Pontos positivos
- ✅ **Dados de cartão NÃO são persistidos** — `flashStore` é **in-memory** (não usa MMKV). Reduz risco PCI no dispositivo.
- ✅ **OTP por SMS (Firebase)** é um fator de autenticação razoável para o usuário final.
- ✅ TypeScript `strict`; uso de HTTPS na URL de produção (apesar de S2 permitir downgrade).
- ✅ `.gitignore` cobre chaves nativas (`*.jks`, `*.p12`, `*.key`, `*.mobileprovision`, `*.pem`).

---

## 8. Performance

| Item | Evidência | Observação |
| --- | --- | --- |
| **React Query** para cache de servidor | 20 usos de `useQuery/useMutation` | ✅ Reduz requests redundantes |
| **FastImage** + cache de vídeo | `react-native-fast-image`, `startupVideoCache.ts` | ✅ Otimização de mídia |
| **useDebounce** em inputs (cartão, busca) | `hooks/useDebounce.ts`, `OnlinePayment.tsx:47` | ✅ Evita reprocessamento |
| **Polling de PIX** | `IsPixPaid` em `pix.tsx` | ⚠️ Verificar intervalo/cancelamento para não drenar bateria/rede **[Necessita Validação]** |
| **Recálculo de carrinho** a cada add/remove | `flashStore.calculateTotal` itera todos os produtos | Impacto baixo (carrinho pequeno) |
| **MMKV** como storage | `storage.ts` | ✅ Storage rápido (melhor que AsyncStorage) |
| Escalabilidade | App cliente — escala no backend | Sem gargalo próprio relevante |

Conclusão: **performance adequada**; sem gargalos estruturais no cliente.

---

## 9. Débito Técnico

| Débito | Impacto | Evidência |
| --- | --- | --- |
| `app_key`/Maps key hardcoded no app | **Crítico** | `constants/app.ts`, `app.json` |
| HTTP arbitrário habilitado (ATS off / allowHttp) | **Crítico** | `app.json:28,52` |
| MMKV sem criptografia (token em claro) | **Alto** | `storage.ts` |
| Ausência de testes automatizados | **Alto** | nenhum arquivo de teste |
| Regra de preço/desconto duplicada no cliente | **Médio** | `flashStore.ts` |
| `any` em fluxos de pagamento/serviços | **Médio** | `setCardInfo`, services |
| Troca de ambiente por comentário | **Médio** | `constants/app.ts:90-104` |
| `console.*` em produção | **Baixo/Médio** | 19 ocorrências |
| Dependência git (`react-native-ratings` fork) | **Médio** | `package.json:45` — risco de supply chain/manutenção |
| Zustand em RC | **Baixo** | `package.json:50` |
| Config Firebase versionada | **Baixo** | repo |

---

## 10. Riscos da Aplicação

### Operacionais
- **Dependência da API `api-app-gc` e do Firebase**: indisponibilidade de qualquer um bloqueia login/pedido.
- **Troca de ambiente manual** (comentários): risco de publicar app apontando para ambiente errado.

### De negócio
- **Comprometimento da `app_key`** (extraível do binário) → abuso da API.
- **Reputação/financeiro**: falhas de pagamento ou interceptação de cartão (S2/S4).

### Tecnológicos
- Dependência de fork git e libs de manutenção reduzida.
- Zustand em versão RC.

### De segurança
- Segredos no binário, HTTP permitido, token em storage não criptografado (§7).

### Dependências críticas (SPOF)
- API `api-app-gc`. Firebase (Auth/FCM). Google Maps/Places. Gateway de pagamento (no backend).

---

## 11. Estratégia de Modernização

> **Premissa: manter a stack principal (Expo/React Native/TypeScript).**

### Curto Prazo (0–3 meses) — Segurança e configuração
- **Desabilitar HTTP arbitrário** (S2): remover `NSAllowsArbitraryLoads`/`allowHttp`, forçar **TLS** (HTTPS only) — crítico por haver pagamento.
- **Tirar segredos do binário/repo** (S1, S5): mover `api_url`, `app_key`, `gap_key` para **variáveis de build (EAS Secrets / `expo-constants` extra)**; **rotacionar** as chaves expostas; **restringir** a Google Maps key por app/SHA/bundle. (Observar que `app_key` em app público é mitigável, mas a defesa real é no backend — ver relatório `api-app-gc`.)
- **Criptografar o MMKV** (S3): instanciar `MMKV` com `encryptionKey` derivada de Keychain/Keystore.
- Substituir troca de ambiente por **build profiles do EAS** (eliminar comentários em `constants/app.ts`).
- Remover/condicionar `console.*` em produção (Babel transform `transform-remove-console`).

### Médio Prazo (3–9 meses) — Robustez
- **Introduzir testes** (Jest + React Native Testing Library) cobrindo fluxos de auth, carrinho/desconto e checkout — hoje inexistentes.
- **Eliminar `any`** nos fluxos de pagamento/serviços; tipar contratos da API (gerar tipos a partir do backend).
- **Validar valores no servidor** (não confiar no total/desconto calculado no cliente) — coordenar com o backend.
- Substituir a **dependência git** `react-native-ratings` por versão publicada/fork interno auditado.
- Tratar PCI: confirmar que cartão usa **gateway tokenizado**; se não, migrar coleta para SDK PCI-compliant.

### Longo Prazo (9–18 meses) — Evolução
- Migrar Zustand para versão **estável** quando disponível; manter Expo SDK atualizado (cadência semestral).
- Adicionar **observabilidade** (Sentry/Crashlytics) e **feature flags**.
- Avaliar **deep-linking/Universal Links** e otimização de cold start.

---

## 12. Estimativa de Complexidade

| Área | Complexidade |
| --- | --- |
| Arquitetura | **Baixa/Média** — app bem estruturado, padrões claros |
| Banco de Dados | **Baixa** — sem BD próprio (apenas MMKV/tipos) |
| Backend | **N/A** (este é o cliente; complexidade está em `api-app-gc`/ERP) |
| Frontend (mobile) | **Média** — 79 arquivos, fluxos de pagamento/rastreamento |
| Segurança | **Média/Alta** — segredos no binário, HTTP, storage não cifrado, PCI |
| Modernização | **Baixa/Média** — stack atual; correções são pontuais (config/segurança/testes) |

---

## 13. Resumo Executivo

### Estado geral
O `app-gas-em-casa` é o **aplicativo mobile do consumidor final**, construído em **stack moderna e bem arquitetada** (Expo SDK 53, React Native 0.79, React 19, TypeScript strict, Expo Router, Atomic Design, React Query, Zustand). É **o componente mais saudável e recente** do ecossistema analisado — boa organização, baixa dívida estrutural e performance adequada. Os problemas são **pontuais e majoritariamente de segurança/configuração**, corrigíveis em curto prazo.

### Principais problemas encontrados
1. **Segredos hardcoded no binário/repo** — `app_key` da API e Google Maps key (`constants/app.ts`, `app.json`).
2. **HTTP não-criptografado habilitado** (`NSAllowsArbitraryLoads`/`allowHttp`) — grave por haver pagamento.
3. **Token de API em MMKV não criptografado**.
4. **Cartão de crédito trafega pela API própria** — atenção a PCI (mitigado por não persistir em disco).
5. **Ausência de testes** e troca de ambiente por comentário.

### Principais riscos
- **Interceptação de dados/cartão** (HTTP + segredos no binário).
- **Abuso da API** via `app_key` extraível — risco que se conecta diretamente à fragilidade de autenticação do backend `api-app-gc`.

### Potencial de modernização
**Muito alto e de baixo custo.** A base é moderna, tipada e modular; não há reescrita necessária. O trabalho concentra-se em **hardening de segurança/configuração** e **introdução de testes** — esforço pequeno frente ao valor.

### Prioridades recomendadas
1. **(Imediato)** Forçar TLS (remover HTTP arbitrário); mover segredos para EAS Secrets e rotacioná-los; restringir a Maps key.
2. **(Curto)** Criptografar o MMKV; remover `console.*`; build profiles para ambientes.
3. **(Médio)** Testes automatizados; tipar pagamento/serviços; validar valores no servidor; confirmar conformidade PCI do fluxo de cartão.
4. **(Longo)** Estabilizar dependências (Zustand, fork git), observabilidade e atualização contínua do Expo.

---

### Apêndice — Evidências quantitativas coletadas
- Arquivos TS/TSX: **79** · LOC `src/`: **~9.645** · Telas (Expo Router): **25**
- Services: **5** (user/order/product/store/address) · Stores Zustand: **2** (appStore persistido, flashStore in-memory)
- `useQuery/useMutation`: **20** · `console.*`: **19** · Testes: **0**
- Stack: Expo **53.0.20** · RN **0.79.5** · React **19** · TS **5.1** strict · Firebase **22.4** · React Query **5**
- Segredos hardcoded: `app_key` (`constants/app.ts:108`), Google Maps key (`app.json:19,48` + `constants/app.ts:109`)
- Rede: `NSAllowsArbitraryLoads: true` (iOS) · `allowHttp: true` (Android) — `app.json`
- Storage: MMKV **sem encryptionKey** (`store/storage.ts`)

> Itens **[Necessita Validação]** (tratamento PCI do cartão no backend, intervalo de polling do PIX, dependências circulares, validação de valores no servidor) requerem acesso ao backend ou execução/instrumentação para confirmação definitiva.
