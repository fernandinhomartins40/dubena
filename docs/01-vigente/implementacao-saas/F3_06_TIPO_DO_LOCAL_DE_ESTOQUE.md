# F3-06 — O local de estoque diz que espécie de lugar é

Data: 2026-08-31 (America/Sao_Paulo)

## O que existia

`setores` tinha `descricao` e `ativo`. Mais nada.

Isso faz três coisas de naturezas diferentes conviverem na mesma lista, sem
distinção:

- o **depósito** da revenda, que é um endereço físico;
- o estoque **em poder de um franqueado**, criado automaticamente como
  `'Em poder de '.$colaborador->nome` na primeira carga
  (`CargaFranqueadoService`);
- o que estiver **a bordo de um veículo**, que se move.

A consequência aparece no seletor de "onde lançar a entrada": o operador vê
"Em poder de João" ao lado de "Depósito central" e pode escolher qualquer um.

**O lançamento errado não dá erro.** Dá um saldo que não bate, descoberto no
inventário — quando ninguém mais liga uma coisa à outra.

E há a consequência de produto: sem tipo, a pergunta *"quanto do meu estoque
está fora do depósito?"* não é expressável.

## A correção

`tipo` no setor, com quatro valores, e dois predicados que o código consulta em
vez de repetir a regra:

| | `aceitaEntradaDireta()` | `eCustodia()` |
|---|---|---|
| `DEPOSITO` | sim | não |
| `LOJA` | sim | não |
| `CUSTODIA_PESSOA` | **não** | sim |
| `VEICULO` | **não** | sim |

## Onde a restrição fica, e por quê

**Na porta HTTP, não no `EstoqueService`.**

O que está com uma pessoa ou num veículo chegou lá por um movimento — carga do
franqueado, transferência. Esses caminhos usam o mesmo `EstoqueService::entrada()`
internamente, e bloqueá-lo ali travaria justamente os fluxos legítimos.

O que se recusa é a **entrada manual**: lançar mercadoria direto num local de
custódia cria estoque do nada num lugar que deveria ter recebido de algum outro.

Há teste dos dois lados: a entrada manual é recusada (422), e a transferência
para custódia continua funcionando.

## A conversão é a mais segura desta fase

Nas outras conversões da F3 (F3-02, F3-04A) a heurística lia texto que um humano
digitou, e por isso deixava o ambíguo sem classificar. Aqui é diferente: o
prefixo `"Em poder de "` é **escrito pelo próprio código**, e existe uma
evidência estrutural melhor ainda — `colaboradores.setor_estoque_id`.

Por isso a ordem é: **vínculo primeiro, prefixo só para os órfãos** (colaborador
removido, setor com saldo ainda lá — deixá-lo como depósito faria mercadoria em
poder de terceiro parecer estoque próprio).

Verificado em PostgreSQL com massa que reproduz o cenário:

| Descrição | Resultado | Por quê |
|---|---|---|
| `Deposito central` | DEPOSITO | — |
| `Em poder de Joao` | CUSTODIA_PESSOA | prefixo |
| `EM PODER DE Maria` | CUSTODIA_PESSOA | prefixo (case-insensitive) |
| `Loja centro` | DEPOSITO | loja precisa ser declarada |
| **`Deposito 2`** | **CUSTODIA_PESSOA** | **vínculo estrutural** |

A última linha é a que prova o desenho: o nome não tem a assinatura, mas um
colaborador aponta para ele. Uma conversão só por texto teria errado esse caso.

Todo o resto vira `DEPOSITO`, que é o default conservador: um depósito a mais na
lista de armazéns é um erro visível e corrigível; um depósito classificado como
custódia sumiria dos lançamentos.

## Verificação

| Portão | Resultado |
|---|---|
| Testes focais | 8 (`TipoLocalEstoqueTest`) |
| Suíte integral | **1483 passes / 4663 assertions** |
| Migrations em PostgreSQL real | 151, sem erro |
| `RlsCoberturaTest` (PostgreSQL real) | 6/6 |
| Rollback → reaplicação | OK |
| Pint | aprovado |

## O que não foi feito, e por quê

O lookup `/lookups/setores` continua devolvendo todos os setores. Ele é genérico
por tabela, sem filtro por coluna, e é usado tanto por seletores de lançamento
(onde custódia não deveria aparecer) quanto pela transferência (onde custódia é
destino válido). Separar os dois exige mudar a mecânica do lookup.

Não fiz agora porque **a tela de entrada manual ainda não existe na SPA** — a
proteção efetiva está na API, que é por onde a requisição passa. Quando a tela
for construída, o seletor deve usar `Setor::armazens()`, que já existe.
