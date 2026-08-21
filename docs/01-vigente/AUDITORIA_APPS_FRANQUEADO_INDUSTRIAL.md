# Auditoria — apps de campo e a central de vendas

Leitura de código de **quatro** sistemas, feita em 2026-08-20/21 com o objetivo
de acoplar as operações de franqueado e industrial ao ecossistema Gás em Casa:

| Sistema | O que é |
|---|---|
| `legado-movelapp/` | app de rota (Android nativo, 53 classes) |
| `legado-nfweb/` | app de venda consultiva (React Native) |
| `app-entregador/` | app novo do entregador (Expo/RN) |
| `erp-novo/` | backend + SPA administrativa |

> Fonte: código. Onde este documento cita comportamento, há arquivo e linha.

---

## 1. O modelo de negócio (do cliente) e o que o código mostra

O cliente descreve **dois tipos de entregador** (funcionário CLT e franqueado sem
vínculo, remunerado por comissão/repasse) e **vendedores industriais** (vendem
para empresa e indústria, emitem nota, dão desconto).

E descreve uma regra decisiva: **o franqueado não fecha o pedido.** Quando ele vai
vender, quem cria, fatura e aprova o desconto é a central de vendas (o antigo call
center) — resta confirmar se ele *solicita pelo app* ou pede por WhatsApp.

**Correção de premissa registrada.** A auditoria anterior partiu de "MovelApp =
app do franqueado". O código não sustenta: nenhum dos dois apps legados conhece
vínculo empregatício, e é o MovelApp — não o NFWEB — que tem perfil de *entrega*.
Isso **não invalida** a direção de unificar num app com níveis de acesso; ao
contrário, é o que a torna viável, porque o vínculo já é decidido no backend.

---

## 2. O que cada app faz, lado a lado

| | MovelApp (rota) | NFWEB (venda) | app-entregador (novo) |
|---|---|---|---|
| Recebe pedido pronto | sim (`getPedidosPendentes`) | não | sim (`entregador/pedidos`) |
| Cria pedido em campo | sim (`exportPedidoTask`) | sim (`savePedido`) | sim (`missao/venda`) |
| **Edita preço** | **sim, livre** | **sim, livre** | **não — servidor decide** |
| Cadastra cliente | não | sim (`saveCliente`) | sim (`missao/clientes`) |
| Pendência financeira do cliente | não | sim (`getParcelasVencidasCliente`) | **não** |
| Emite/imprime documento | DANFE + boleto (ESC/POS BT) | via JAR proprietário | **não imprime** |
| Opera sem rede | **sim** (SQLite, 8 tabelas) | não | **não** |
| Vale-gás | sim (`getValeGas`) | não | sim (`missao/vale-gas`) |

### 2.1 O achado que justifica a central de vendas

`legado-movelapp/app/src/main/java/br/inf/qti/movelapp/PedidoFragment2.java:80`

    txtPreco.setEnabled(pedidoId.equals("-1"));

Em pedido **novo**, o campo de preço fica livre para digitação. Sem teto, sem
faixa por produto, sem aprovação. No NFWEB o desconto é um `TextInput` comum
(`legado-nfweb/src/pages/Pedido.js:800`).

Busca por alçada (`desconto_max`, `limite_desconto`, `alcada`, `aprova.*desconto`):
**nada em `ctrl-web/`, nada em `erp-novo/`**.

Hoje, no legado, qualquer vendedor ou franqueado zera a margem sem que ninguém
aprove. **Não é regra a portar — é controle que nunca existiu.**

### 2.2 O contraste que resolve metade do problema

O ERP novo **já é fail-closed em preço** na venda de campo:

- `AppMissaoController::venderGas` (linha 155) aceita apenas `produto_id` e
  `quantidade` — **não há campo de preço nem de desconto** no contrato da rota.
- `VendaCampoService.php:71` — *"SEM preco_unitario: o PedidoService usa o
  preco_venda do produto"*.

Consequência prática: **hoje o franqueado não conseguiria dar desconto pelo app
novo nem se quisesse.** O caminho para permitir desconto controlado é acrescentar
a alçada, não remover a trava — o desenho já está do lado certo.

---

## 3. O que o `erp-novo` já tem (e é mais do que parece)

### 3.1 A "central" que existe é de logística, não de vendas

`Domain/Logistica/CentralService.php` (226 linhas) faz: `filaDistribuicao`,
`entregadores`, `atribuir`, `redistribuir`, `bloquearEntregador`, `priorizar`,
`reagendar`, `cargaPorEntregador`. O `CentralController` expõe isso em
`central/*` sob a permissão `logistica.view` / `logistica.distribuir`.

**Não cria pedido, não fatura, não aprova desconto, não faz pós-venda.** A
central de vendas do cliente é um módulo novo — irmão deste, não uma extensão.

Atenção a um falso amigo: `cargaPorEntregador` é **número de pedidos atribuídos**,
não estoque físico no veículo.

### 3.2 Autorização: a fundação já está pronta

| Peça | Onde | Serve para |
|---|---|---|
| RBAC por chave `modulo.acao` | `Concerns/AutorizaPorPermissao.php:28` | permissões da central |
| **ABAC com limite/ownership/horário** | `Domain/Acesso/PolicyEvaluator.php:163-165` | **alçada de aprovação** |
| `avaliarLimite` genérico | `PolicyEvaluator.php:171` | compara campo do recurso contra `valor_max` |
| Papel por token (Sanctum ability) | `Middleware/AppRole.php:16` | aceita `role:franqueado` sem reescrita |

O comentário em `AutorizaPorPermissao.php:38` já cita o caso de uso literal:
*"aprovar pedido até um limite"*. O motor de alçada **existe**; falta a política
cadastrada e o ponto de chamada no fluxo de desconto.

### 3.3 Tempo real: infraestrutura pronta

Cinco eventos de broadcast: `PedidoEntrouNaFila`, `PedidoAtribuido`,
`PedidoStatusAtualizado`, `EntregadorPosicaoAtualizada`, `PixConfirmado`.

E o canal já existe: `empresa.{empresaId}.central` (`routes/channels.php`), com
autorização por tenant. **A central de vendas tem por onde receber evento em
tempo real sem infraestrutura nova.**

### 3.4 O pedido já nasce numa fila

`PedidoService::criar` (linha 38): se o pedido nasce PENDENTE e sem entregador,
dispara `PedidoEntrouNaFila` + `AtribuirPedidoJob`. **É exatamente o encaixe da
"solicitação do franqueado"**: uma solicitação é um pedido que nasce num estado
anterior ao PENDENTE, e a central o promove.

### 3.5 Comissão: serve para franquia sem mudar schema

`colaborador_comissoes` (migration `2026_06_22_000200_create_rh_tables.php:67`):

- `tipo_comissao`: 1=percentual, **2=repasse** — *"valor que fica para a empresa"*.
  **É o modelo de franquia.**
- `percentual_app` / `empresa_valor_app` — variante para pedido do app.
- Regra por `produto_id`, `setor_id`, `condicaopagamento_id`, com `data_inicio`/`data_fim`.
- `comissao_excecoes` por segmento.

`Domain/Rh/ComissaoService::calcularItem` já resolve os dois tipos.

### 3.6 Outras peças aproveitáveis

| Peça | Onde |
|---|---|
| Pós-venda (CRUD) | `CrmController::posVendaIndex/Salvar`, rotas `pos-vendas` |
| Acerto financeiro do entregador | `Domain/Caixa/MaloteService.php` |
| NF-e completa (XML, DANFE, SPED) | `Domain/Fiscal/` |
| Missões (atribuir, auditar, evidência, adiamento) | `MissaoController`, rotas `missoes/*` |
| Ocorrência e conclusão de entrega | `AppEntregadorController` |

---

## 4. Faturamento: não existe como ação

O `PedidoController` admin não tem `faturar`. O faturamento acontece por
`mudarSituacao` para uma situação cujo `efeito` é CONCLUIDO — e é a máquina de
estados (`EfeitoPedido`) que baixa estoque e gera financeiro.

Para a central "faturar" um pedido de franqueado, o caminho é uma transição de
situação autorizada, não um método novo. Isso **preserva** a idempotência que já
existe (`estoque_movimentado`).

---

## 5. Lacunas reais, por dificuldade

1. **Impressão térmica** — inexistente no ecossistema novo. MovelApp usa ESC/POS
   por Bluetooth (padrão aberto); NFWEB usa `NfePrinterLib.jar`, **proprietário**.
   Bloqueado pela pendência do parque de impressoras.
2. **Operação offline** — MovelApp tem SQLite de 8 tabelas e funciona sem rede.
   O `app-entregador` **não tem AsyncStorage nem fila**: o "offline-first" citado
   em `src/helpers/realtime.ts:15` é apenas queda para polling quando o WebSocket
   cai. Para rota com sombra de sinal, é diferença operacional real.
3. **Alçada de desconto** — o motor existe (`PolicyEvaluator`), falta a política e
   o ponto de chamada. `PedidoService::recalcularTotais` (linha 185) hoje **soma o
   desconto dos itens sem verificar nada**.
4. **Fluxo de solicitação → aprovação** — não existe. É o coração da central de vendas.
5. **Vínculo franqueado** — o conceito não existe no `erp-novo` (busca por
   "franqueado": zero ocorrências).
6. **Estoque em poder do franqueado** — não há estoque por veículo/entregador.
7. **Pendência financeira do cliente no app** — `getParcelasVencidasCliente` do
   NFWEB não tem equivalente.
8. **DANFE/boleto no app** — existem no ERP, só em rota admin. O item mais barato.

---

## 6. Pendências com o cliente

1. **O franqueado solicita pelo app ou por WhatsApp?** Muda se a fase de
   solicitação precisa de tela no app ou só de painel na central. (O cliente não
   recordava.)
2. **Quantas impressoras e quais modelos** — decide entre JAR proprietário,
   ESC/POS genérico ou troca de parque. Bloqueia a fase de impressão.
3. **Como o franqueado é remunerado** — percentual, repasse por unidade, ou misto.
4. **Consignação ou compra?** Muda estoque e fiscal.
5. **Teto de desconto por perfil** e quem aprova acima disso.

---

## 7. Riscos herdados

| O quê | Onde |
|---|---|
| **Keystore de produção versionado** | `movelapp.jks` no SVN (não copiado) |
| Chave Google API dentro do APK | `legado-nfweb/src/helper/Constants.js:22` |
| Chaves Firebase | `google-services.json` (fora do commit) |
| WebSocket sem TLS, `app_key` na query | `ws://adm.gasemcasa.com.br:8092` |
| `targetSdk 28` — abaixo do mínimo da Play Store | `legado-movelapp/app/build.gradle` |

---

## 8. Limite desta auditoria

Foram lidos: estrutura, rotas, contratos de entrada, schema local dos apps, e os
pontos de decisão citados com arquivo e linha. **Não** foram lidas as ~5 mil linhas
de regra de negócio dos dois legados. Onde se afirma "existe equivalente", trata-se
de *capacidade*, não de *regra idêntica* — tabela de preço por segmento, condições
de pagamento e cálculo de comissão exigem conferência linha a linha antes de
desligar qualquer app.
