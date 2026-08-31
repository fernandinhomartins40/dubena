# F3-05 — O pedido registra por qual porta entrou

Data: 2026-08-31 (America/Sao_Paulo)

## O que faltava

Quatro caminhos criam pedido:

| Porta | Canal |
|---|---|
| painel admin | `INTERNO` (balcão ou telefone) |
| app do consumidor | `APP_CLIENTE` |
| app do entregador | `CAMPO` |
| central de vendas | `CENTRAL` |

E **no banco os quatro ficavam idênticos.** Nenhuma coluna dizia de onde o
pedido veio.

Perguntas que a revenda faz e o sistema não respondia:

- *"quanto do meu faturamento já vem do app?"* — que é exatamente a decisão de
  investir ou não no canal digital;
- *"o ticket do telefone é maior que o do balcão?"*;
- *"esse pedido veio de onde?"*, quando algo deu errado nele.

## Dimensão, e não booleanos paralelos

A tarefa é explícita: *"substituir booleanos paralelos e colunas `*_app` por
dimensões/relacionamentos extensíveis"*.

O legado respondia parte disso com um booleano por canal. Dois problemas: eles
permitem **estados impossíveis** (dois verdadeiros ao mesmo tempo — o pedido veio
do app *e* do balcão?) e exigem uma **coluna nova a cada canal** que se
acrescenta. Um enum numa coluna resolve os dois.

## `DESCONHECIDO` em vez de adivinhação

Os pedidos existentes ficam `DESCONHECIDO`. **Não há conversão retroativa, e isso
é deliberado.**

Seria possível adivinhar: pedido com `entregador_user_id` e sem
`atendente_user_id` *provavelmente* veio do campo. Mas "provavelmente" num dado
que vira **relatório de faturamento por canal** é pior que "não sei" — o gráfico
ficaria bonito e errado, e ninguém saberia de onde veio a linha.

`DESCONHECIDO` é honesto: a fatia sem origem aparece no relatório, encolhe
sozinha conforme pedidos novos entram, e a decisão se baseia no que foi medido de
verdade.

É o mesmo princípio das outras conversões da F3 — o ambíguo fica sem classificar
—, levado ao caso em que **tudo** é ambíguo.

## O guardião

Declarar o canal em quatro lugares e depender de alguém lembrar do quinto é como
o `DESCONHECIDO` volta a crescer: silenciosamente, até o relatório deixar de
fechar.

`test_toda_porta_que_cria_pedido_declara_o_canal` varre quem injeta
`PedidoService` e chama `criar`, e exige que declare um canal. O que ele **não**
faz é adivinhar qual — essa é a decisão de quem escreve a porta.

Precisou de duas iterações para não virar ruído: a primeira versão pegava
qualquer `$service->criar(...)`, e a segunda ainda acusava o `FinanceiroService`,
que só **cita** `PedidoService` num comentário e tem o próprio método `criar`. A
versão final exige o tipo injetado e uma chamada que não seja `$this->criar`.

Verificado que detecta: removi o canal do `CentralVendasService` de propósito e
ele apontou o arquivo.

## Verificação

| Portão | Resultado |
|---|---|
| Testes focais | 6 (`CanalDeVendaTest`) |
| Migrations em PostgreSQL real | 153, sem erro |
| `RlsCoberturaTest` (PostgreSQL real) | 6/6 |
| Rollback → reaplicação | OK |
| Suíte integral | ver ESTADO_ATUAL |
| Pint | aprovado |

## O que fica aberto

- **relatório por canal na tela.** O dado existe e é consultável, mas ninguém o
  vê ainda — e era justamente a pergunta que motivou a tarefa;
- a ponte do app legado (`PonteMovelAppController`) não cria pedido diretamente,
  então `LEGADO` está no enum sem uso hoje. Fica declarado para quando a ponte
  passar a criar;
- `envia_app_nf` no produto continua sendo um flag booleano de canal — é pequeno
  e isolado, mas é do mesmo tipo que esta tarefa combate.
