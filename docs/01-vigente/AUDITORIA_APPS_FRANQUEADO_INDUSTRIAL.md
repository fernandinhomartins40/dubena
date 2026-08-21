# Auditoria — app do franqueado e app do industrial

Leitura de código de `legado-movelapp/` e `legado-nfweb/` feita em **2026-08-20**,
com a finalidade de acoplar as duas operações ao ecossistema Gás em Casa.

> **Correção de premissa.** O levantamento partiu de "MovelApp = app do
> franqueado". O código **não sustenta** isso: nenhum dos dois apps conhece o
> conceito de franqueado, e é o MovelApp — não o NFWEB — que tem o perfil mais
> próximo de *entrega*. Ver "O que o código diz" abaixo. Isso não invalida a
> direção do projeto; muda **onde** o vínculo é decidido: no backend, nunca no app.

---

## O que o código diz

| | MovelApp | NFWEB |
|---|---|---|
| Pilha | Android Java nativo, 53 classes, v1.24 | React Native 0.61.5 |
| Última alteração | 12/11/2025 (flavio) | 17/07/2025 |
| Recebe pedido pronto | **sim** (`getPedidosPendentes`) | não |
| Cria pedido do zero | sim (`exportPedidoTask`) | **sim** (`savePedido`) |
| Edita preço/desconto | **sim, sem limite** (ver abaixo) | **sim, campo livre** |
| Emite/imprime nota | imprime DANFE e boleto (ESC/POS) | imprime via JAR proprietário |
| Cadastra cliente | não | **sim** (`saveCliente`) |
| Consulta financeira do cliente | não | **sim** (`getParcelasVencidasCliente`) |
| Opera sem rede | **sim** — SQLite de 8 tabelas | não |
| Vale-gás | sim (`getValeGas`) | não |

**Leitura.** O MovelApp é **operação de rota**: recebe a carga do dia, entrega,
imprime o documento e presta contas — e faz isso offline, porque rota tem
sombra de sinal. O NFWEB é **venda consultiva**: chega no cliente, cadastra,
consulta o que ele deve, monta o pedido e fecha. São dois momentos diferentes
do negócio, não duas versões do mesmo app.

Nenhum dos dois decide vínculo empregatício. Quem é funcionário e quem é
franqueado está no cadastro do backend — o app só apresenta o que o usuário
pode fazer. **Isso é exatamente o que viabiliza unificar os dois num app só
com níveis de acesso.**

---

## O achado que motiva a central de vendas

`PedidoFragment2.java:80`

```java
txtPreco.setEnabled(pedidoId.equals("-1"));
```

Em pedido novo, **o campo de preço fica livre para digitação**. Não há teto, não
há faixa por produto, não há aprovação. O mesmo vale para o NFWEB, onde
`desconto` é um `TextInput` comum (`Pedido.js:800`).

Busca por alçada de desconto (`desconto_max`, `limite_desconto`, `alcada`,
`aprova.*desconto`):

- no legado `ctrl-web/`: **nada**
- no `erp-novo/`: **nada**

Ou seja: hoje **qualquer vendedor ou franqueado pode zerar a margem de um pedido
sem que ninguém aprove**. Não é uma regra a portar — é um controle que nunca
existiu e precisa ser construído. É a justificativa mais forte para a central de
vendas.

---

## O que o ecossistema novo já tem (e serve)

| Peça | Onde | Serve para |
|---|---|---|
| Papel por token (Sanctum abilities) | `app/Http/Middleware/AppRole.php` | base pronta para novos perfis; aceita `role:franqueado` sem reescrita |
| Venda em campo | `missao/venda`, `missao/vale-gas` | o que o NFWEB faz ao fechar pedido |
| Cadastro de cliente em campo | `missao/clientes` | `saveCliente` do NFWEB |
| Operação de entrega completa | `entregador/*` (rota, aceitar, recusar, ocorrência, concluir, jornada) | o que o MovelApp faz na rota |
| **Comissão com variante de app** | `colaborador_comissoes` (`percentual_app`, `empresa_valor_app`) | remuneração diferenciada por canal |
| **Repasse** (tipo 2) | `ComissaoService::calcularItem` | "valor que sobra após a empresa reter um fixo por unidade" — **é o modelo de franquia** |
| Acerto financeiro | `Domain/Caixa/MaloteService.php` | prestação de contas do que ele recebeu |
| Central com posição e carga | `Domain/Logistica/CentralService.php` | base da central de vendas |
| Emissão de NF-e completa | `Domain/Fiscal/` (XML, DANFE, SPED) | o que o industrial precisa |

A estrutura de comissão é a descoberta mais útil: `colaborador_comissoes` já
permite regra **por produto, setor, condição de pagamento e vigência**, com
variante para pedido de app e com modelo de **repasse**. Remunerar franqueado
não exige mudança de schema.

---

## O que falta de verdade

Em ordem de dificuldade:

1. **Impressão térmica** — não existe nada no ecossistema novo. Os dois apps
   imprimem no cliente. O MovelApp usa ESC/POS por Bluetooth (padrão aberto,
   replicável); o NFWEB usa `NfePrinterLib.jar`, **biblioteca proprietária** do
   fabricante (impressora "Leopardo Pro Max"). Depende de decisão sobre o parque
   de hardware — ver Pendências.

2. **Operação offline** — o MovelApp tem SQLite com 8 tabelas (`tbl_pedidos`,
   `tbl_pedidos_itens`, `tbl_veiculos`, `tbl_situacoes`, `tbl_motivos_atrasos`,
   `tbl_empresa`, `tbl_usuarios`, `tbl_config`) e funciona sem rede. O
   `app-entregador` **não tem AsyncStorage nem fila de sincronização**: o
   "offline-first" citado em `src/helpers/realtime.ts` é apenas queda para
   polling quando o WebSocket cai. Para quem trabalha em rota, é diferença real.

3. **Alçada de desconto** — inexistente nos dois sistemas (acima).

4. **Vínculo franqueado** — o conceito não existe no `erp-novo` (busca por
   "franqueado" não retorna nada). Precisa de modelagem: tipo de vínculo,
   regra de remuneração associada, e o que muda na operação.

5. **Estoque em poder do franqueado** — não existe estoque por veículo/entregador
   no ERP novo (`carga`, em `CentralService`, é *número de pedidos atribuídos*,
   não botijão físico). Num modelo de franquia isso pesa: o franqueado tem
   mercadoria consigo e precisa prestar contas dela, não só do dinheiro.

6. **Consulta financeira do cliente em campo** — `getParcelasVencidasCliente` e
   `pedidoDuplicata` não têm equivalente no app novo.

7. **DANFE e boleto no app** — existem no ERP novo, mas só em rota admin; não
   expostos ao `app/v1`. Provavelmente o item mais barato da lista.

---

## Pendências com o cliente

1. **Quantas impressoras existem em campo e quais modelos.** Decide entre
   integrar o JAR proprietário, padronizar em ESC/POS genérico, ou trocar o
   parque. É o item que trava o cronograma dos outros.
2. **Como o franqueado é remunerado hoje** — percentual sobre venda, repasse
   fixo por botijão, ou misto? A estrutura suporta os três, mas a regra precisa
   vir do negócio.
3. **O franqueado compra a mercadoria ou a leva em consignação?** Muda se é
   estoque dele ou da empresa, e muda o fiscal.
4. **Qual o teto de desconto por perfil** — e quem aprova o que passa disso.

---

## Riscos herdados (segurança)

| O quê | Onde |
|---|---|
| **Keystore de produção versionado** | `movelapp.jks`, no SVN (não copiado para cá) |
| Chave Google API dentro do APK | `legado-nfweb/src/helper/Constants.js:22` |
| Chaves Firebase | `google-services.json` dos dois (fora do commit) |
| WebSocket sem TLS, `app_key` na query | `ws://adm.gasemcasa.com.br:8092` |
| `targetSdk 28` — abaixo do mínimo do Google Play | `legado-movelapp/app/build.gradle` |

O keystore é o mais grave: quem alcança o repositório assina APK como se fosse
o cliente.

---

## Limite desta auditoria

Foram lidos estrutura, rotas, telas, schema local e os pontos de decisão citados
(preço, desconto, sincronização, impressão). **Não** foram lidas as ~5 mil linhas
de regra de negócio dos dois apps. Onde este documento diz "existe equivalente",
está afirmando que a *capacidade* existe — não que a *regra* seja idêntica.
Condições de pagamento, tabelas de preço por segmento e cálculo de comissão
precisam de conferência linha a linha antes de qualquer corte do app legado.
