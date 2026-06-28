# CHECKLIST DE PARIDADE — App Legado → ERP-NOVO (`app/v1`)

> **Diretriz do cliente:** a modernização **NÃO pode reduzir capacidades**. Toda função do app legado deve existir no ERP-NOVO antes de o app novo ir a produção.
> **Estratégia:** paridade no servidor primeiro; depois religar o app.
> **Fonte:** `ctrl-web/routes/api_mobile.php` (superfície legada) cruzado com o que o app realmente consome (auditoria F0/F2) e o `app/v1` atual.

Legenda: ✅ pronto · 🟡 parcial · ❌ falta · ⛔ não se aplica (plumbing do legado eliminado no monólito)

---

## 1. Autenticação / sessão
| Legado | Capacidade | ERP-NOVO | Status | Fase |
|---|---|---|---|---|
| `getToken?app_key` | Token | `POST app/v1/cliente/login` (Firebase) | ✅ | F1 |
| `client/setPushToken` | Registrar push | `POST app/v1/devices` | ✅ | F1 |
| `logout` | Logout | `POST app/v1/logout` | ✅ | N10 |
| `testToken*` | Diagnóstico | — | ⛔ | — |

## 2. Cliente / perfil
| Legado | Capacidade | ERP-NOVO | Status | Fase |
|---|---|---|---|---|
| `client/get`, `v2/client/getById` | Dados do cliente | derivar do token | 🟡 (falta `GET app/v1/perfil`) | F3b |
| `client/create` | Cadastro de cliente pelo app | — | ❌ | F3b |
| `client/update` | Editar cliente | — | ❌ | F3b |
| `client/updatePhone` | Trocar telefone | — | ❌ | F3b |
| `client/delete` | Excluir conta | — | ❌ | F3b |

## 3. Endereços
| Legado | Capacidade | ERP-NOVO | Status | Fase |
|---|---|---|---|---|
| `address/getStandard` | Endereço padrão | `GET app/v1/perfil/endereco` | 🟡 (inline único) | F3 |
| `address/getAll` | **Múltiplos** endereços | — | ❌ | F3b (precisa tabela) |
| `address/create/update/delete` | CRUD endereço | `PUT app/v1/perfil/endereco` (1 só) | 🟡 | F3b |
| `address/makeFavorite` | Favoritar endereço | — | ❌ | F3b |
| `getAddressFromLatLng` | Geocode reverso | (app usa Google direto) | ✅ | — |

## 4. Revenda / catálogo / preço
| Legado | Capacidade | ERP-NOVO | Status | Fase |
|---|---|---|---|---|
| `reseller/get` | Dados da revenda | empresa do token | 🟡 | F3b |
| `reseller/isGpAllowed` | Gás do Povo permitido | `GET app/v1/config` | ✅ | F3 |
| `product/get` (`getToOrder`) | Produtos | `GET app/v1/produtos` | ✅ | F2/F3 |
| `v2/order/root` / `app/init` | Abertura (produtos+pagamento) | `GET app/v1/init` | ✅ | F3 |
| `payment/get` | **Formas de pagamento com preço por forma** | `init.condicoes` + `produto_condicao_precos` | ✅ | **F3c** |
| `price/get` | Preços por forma de pagamento | `POST app/v1/carrinho/cotacao` (por `condicao_id`) | ✅ | F3c |
| `coupons/verify`, `coupons/get`, `payment/coupon` | Cupom | `GET app/v1/cupom` + cotação | ✅ | F3 |
| `holiday/*` | Feriados (afeta agendamento) | — | ❌ | F3b |
| `polygons/*` | Polígonos de entrega | geofence (admin/F0 satélites) | 🟡 | F3b |

## 5. Pedido
| Legado | Capacidade | ERP-NOVO | Status | Fase |
|---|---|---|---|---|
| `order/create` | Criar pedido | `POST app/v1/pedidos` | ✅ | N10 |
| `order/history` | Histórico | `GET app/v1/pedidos` | ✅ | N10 |
| `order/track`, `getLastestStatus` | Acompanhar | `GET app/v1/pedidos/{id}` | ✅ | N10 |
| `order/getItems` | Itens do pedido | embutido no acompanhar/histórico | ✅ | N10 |
| `order/evaluate` | Avaliar | `POST app/v1/pedidos/{id}/avaliar` | ✅ | N10 |
| `order/cancel` | Cancelar | `POST app/v1/pedidos/{id}/cancelar` | ✅ | N10 |
| `order/update` | Editar pedido | (PedidoService.atualizar) | 🟡 (sem rota app) | F3b |

## 6. Pagamento online (cartão) — **F5**
| Legado | Capacidade | ERP-NOVO | Status | Fase |
|---|---|---|---|---|
| `order/create` c/ `pagamento:cardInfo` | Cobrança no cartão | `POST app/v1/pedidos/{id}/pagar` (token) | 🟡 falta tokenização no app + fluxo | **F5** |

## 7. PIX — **F4**
| Legado | Capacidade | ERP-NOVO | Status | Fase |
|---|---|---|---|---|
| `order/create` (PIX) → `pix` inline | Gerar cobrança PIX + QR/copia-cola | — (infra PIX existe no admin, acoplada a parcela) | ❌ | **F4** |
| `order/ispaid/{id}` | Status do PIX | — | ❌ | **F4** |
| `order/pixpaid`, `order/expired` | Webhook/expiração | `PixWebhookController`, `PixExpirar` (admin) | 🟡 reusar p/ pedido | **F4** |

## 8. Notificações / outros
| Legado | Capacidade | ERP-NOVO | Status | Fase |
|---|---|---|---|---|
| `video/get`, `video/sync` | Vídeo de abertura | `GET app/v1/config` (video) | 🟡 (sem upload) | F3 |
| `sendNotification*` | Disparo de push (admin) | `PushService` | 🟡 (sem rotas app) | F7 |
| `*/getToLink`, `*/link`, `*/migrate`, `linkFrom` | **Bridge ERP↔API legado** | — | ⛔ eliminado no monólito | — |

---

## PLANO DE PARIDADE (servidor primeiro)

- **F3 (em curso):** cotação ✅, config ✅, endereço inline ✅ (servidor). Pendências F3 abaixo.
- **F3b — Cadastros/perfil:** `GET app/v1/perfil`, cadastro/edição/exclusão de cliente, **múltiplos endereços** (nova tabela `cliente_enderecos` + favorito), feriados, reseller info, polígonos. Editar pedido.
- **F3c — Formas de pagamento:** decisão de modelo — portar `payment/get` com **preço por forma** (o legado tinha preço diferente por forma de pagamento) ou consolidar em `condicoes`. **Precisa validação:** o ERP-NOVO tem preço por forma de pagamento? (checar `ProdutoPreco`/`CondicaoPagamento`).
- **F4 — PIX por pedido:** `POST app/v1/pedidos/{id}/pix` + `GET .../pix/status`, reusando `PixService` (desacoplar de `financeiroparcela_id`), webhook/expiração.
- **F5 — Cartão tokenizado:** fluxo completo (`pagar` já recebe token), SDK de tokenização no app, tentativas/limite no servidor.
- **F7 — Notificações:** disparos e push acionável.

> **Próximo passo de investigação (antes de codar F3c):** confirmar no ERP-NOVO se há **preço por forma de pagamento** (o legado tinha — `price/get`), pois isso muda a cotação. Ver `ProdutoPreco`, `CondicaoPagamento`, e como `PedidoService` precifica.

### RESULTADO DA INVESTIGAÇÃO F3c (2026-06-27)
**Confirmado: o legado precifica por forma de pagamento.** `CondPgtoImportacaoRepository::getPrices` retorna `(condicaopagamento_id, produto_id, valor)` — o MESMO produto tem preço diferente por condição (ex.: dinheiro × crédito). É o que alimenta o `Payment.productPrices` do app.

**Lacuna no ERP-NOVO:** não existe preço por (produto × condição). `PedidoService` precifica só por `produto.preco_venda` (com override `preco_unitario`, que inclusive é um risco — cliente poderia mandar preço). Há `ClientePreco` (preço por cliente), mas não por condição de pagamento.

**Decisão necessária (afeta o core do ERP, não só o app):** criar tabela `produto_condicao_precos` (produto_id × condicao_id → valor, escopo empresa) e fazer a **cotação e a criação do pedido** precificarem pela condição selecionada; e **remover o override `preco_unitario`** do caminho do app (anti-fraude). Isso é a F3c — requer migration + ajuste no `CotacaoMobileService`, `PedidoService`/`PedidoMobileService` e testes.

### CHECKPOINT (commitado)
Servidor F3 (núcleo): `CotacaoMobileService` (preço server-side, hoje por `preco_venda`/`preco_gasdopovo`), `GET app/v1/config`, `GET/PUT app/v1/perfil/endereco`, `clienteDoUsuario` derivando do token (fecha IDOR). 31 testes mobile verdes. **Pendente para paridade total:** F3b (cadastros/múltiplos endereços), F3c (preço por condição), F4 (PIX), F5 (cartão).
