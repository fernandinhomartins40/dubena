# F3-01 (primeira peça) — Os papéis da pessoa ganham vigência

Data: 2026-08-31 (America/Sao_Paulo)

## O escopo, dito antes

A tarefa F3-01 pede três coisas:

> criar papéis **com vigência**, identidade e regimes separados; endereço único
> normalizado com município IBGE e histórico; preservar texto derivado somente
> quando consumidor exigir.

**Este lote entrega a primeira.** O endereço fica aberto: hoje o texto de
`clientes.endereco` ainda é a fonte em seis pontos de leitura — incluindo cupom
fiscal e contrato de comodato — e trocar isso alcança documento que vai para o
cliente.

## O que estava furado

`clientes` tem três booleanos paralelos: `cliente`, `fornecedor`,
`transportador`. Eles respondem **"é?"** e não conseguem responder **"era,
quando?"**.

Dois custos concretos:

- **um fornecedor que deixou de fornecer não tem como sair da lista sem apagar o
  histórico.** Desmarcar o booleano faz parecer que ele nunca forneceu, e as
  notas de entrada antigas passam a apontar para alguém que "não é fornecedor";
- **não há filtro por papel em consulta nenhuma.** O lookup
  `clientes-fornecedores` e o `clientes` apontam para a mesma tabela sem
  distinção — ao lançar uma nota de entrada, o operador escolhe entre todos os
  cadastros, inclusive quem nunca forneceu nada.

## A correção

`cliente_papeis`: um papel por linha, com `inicio` e `fim`. Mesma troca que
F3-05 fez com o canal — dimensão em vez de booleanos paralelos. Papel novo
(representante, prestador) passa a ser uma linha na enum, não uma migration.

Marcar **abre** vigência; desmarcar **encerra** a vigente com a data de hoje. A
linha não é apagada, e é essa a diferença que a tabela existe para fazer.

## Os booleanos NÃO foram removidos

Eles continuam sendo escritos e lidos, e `ClienteService::sincronizarPapeis()`
mantém as duas fontes coerentes.

Removê-los no mesmo lote deixaria a leitura sem fonte antes de o consumo migrar —
`ClienteResource`, `ClienteRequest` e o ETL ainda dependem deles. E migration
destrutiva não viaja junto com feature (regra do repositório).

O caminho é: a tabela existe e é preenchida → o consumo migra → só então as
colunas saem, em migration própria.

Por isso `temPapel()` lê da tabela **com o booleano como fallback**: um cadastro
criado por um caminho ainda não migrado (o ETL) tem só o booleano, e não pode
desaparecer da lista por causa disso. Há teste para esse caso.

## Dois erros meus, no caminho

**1. `fim >= hoje` no escopo de vigentes.** Encerrar um papel com a data de hoje
é o caso comum ("deixou de ser fornecedor agora"), e com `>=` ele continuaria
vigente até a virada do dia.

**2. E `where` em vez de `whereDate`.** O valor gravado chega como
`"2026-08-31 00:00:00"` e, comparado como **string** com `"2026-08-31"`, sai
maior. É exatamente a armadilha do `whereBetween` em coluna datetime registrada
no `CLAUDE.md` — e eu a repeti mesmo tendo lido o arquivo.

Os dois só apareceram porque o teste cobria o encerramento **no mesmo dia**, que
é o caso real.

## Verificação

| Portão | Resultado |
|---|---|
| Testes focais | 7 (`PapelDaPessoaTest`) |
| Migrations em PostgreSQL real | 155, sem erro |
| `RlsCoberturaTest` (PostgreSQL real) | 6/6 — **360 asserções** (eram 358: a tabela nova entrou na cobertura sozinha) |
| Policy canônica aplicada | `app_tenant_can_read/operate`, FORCE RLS |
| Conversão com massa real | 4 casos, todos corretos |
| Rollback → reaplicação | OK |
| Suíte integral | ver ESTADO_ATUAL |
| Pint | aprovado |

A conversão foi conferida em Postgres com quatro cadastros — só cliente, só
fornecedor, os três papéis, e nenhum. O último é o que mais importa: um cadastro
sem papel algum **não** ganhou papel por presunção.

## O que fica aberto da F3-01

- **endereço único normalizado** (a segunda parte da tarefa) — seis pontos de
  leitura ainda usam o texto, incluindo documentos que vão ao cliente;
- **os lookups ainda não filtram por papel.** A tabela existe e responde a
  pergunta, mas `clientes-fornecedores` continua devolvendo todos — o
  `LookupController` é genérico por tabela e não tem filtro por coluna (mesma
  limitação registrada em F3-06);
- **remover os booleanos**, quando o consumo tiver migrado.
