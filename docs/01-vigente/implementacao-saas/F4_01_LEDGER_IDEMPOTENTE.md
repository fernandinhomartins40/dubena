# F4-01 — O ledger de estoque não duplica quando reprocessado

Data: 2026-08-31 (America/Sao_Paulo)

## O que já existia, e estava certo

A tarefa pede um *"movimento imutável com tenant, empresa, local origem/destino,
item, quantidade, unidade, evento causal, ator e idempotency key"*.

**`estoquehistorico` já é esse ledger**, e um bom:

| Pedido | Já existia |
|---|---|
| quantidade | assinada (+entrada / −saída) |
| evento causal | `origem` + `origem_id` |
| ator | `user_id` |
| local | `setor_id` (tipado desde F3-06) |
| — | `saldo_resultante` (o saldo logo após o movimento) |

Faltavam duas coisas, e uma delas é o gate da fase.

## 1. `tenant_account_id` existia — vazia

A migration `000300` (F1) acrescentou a coluna junto com dezenas de outras
tabelas, e **nada a preenchia**.

É o mesmo achado do F2-06 nas trilhas de auditoria: coluna vazia não responde
pergunta nenhuma, e é **pior que coluna ausente porque parece resolvida**. A
migration faz o backfill; quem passa a preencher nas linhas novas é o
`EstoqueService`.

## 2. Idempotência: o gate diz "rerun não duplica"

A proteção existia, mas **por caso de uso**: o pedido tem a flag
`estoque_movimentado`. Transferência, acerto e carga do franqueado não tinham
nada — reprocessar um job, ou o operador clicando duas vezes, gravava o
movimento de novo.

E movimento de estoque duplicado **não dá erro**: dá um saldo que não bate,
descoberto no inventário, quando ninguém mais liga o sintoma à causa.

### As três decisões

**A chave é opcional.** Exigi-la de todos os chamadores num único lote quebraria
os que ainda não a informam. O que a coluna garante é que **quem informa uma
chave nunca duplica** — e isso é verificável, ao contrário de "todo mundo vai
lembrar de passar".

**A garantia é do banco, não do código.** Índice único **parcial**
(`WHERE chave_idempotencia IS NOT NULL`): sem o `WHERE`, todas as linhas sem
chave colidiriam entre si, e são a maioria. Verificado em PostgreSQL — o segundo
insert com a mesma chave é recusado pelo banco, e duas linhas sem chave convivem.

A consulta no serviço vem *antes* do lock e só evita a exceção no caso comum;
uma corrida entre duas chamadas simultâneas ainda bate no índice, que é onde a
garantia mora.

**A chave é escopada por empresa.** Duas revendas podem legitimamente usar o
mesmo identificador de origem — o número do pedido reinicia por empresa. Uma
unicidade global faria a segunda revenda **perder o movimento**, que é pior que
duplicá-lo.

## Um detalhe que só o teste pegou

Acrescentei o parâmetro em `movimentar()` e esqueci de repassá-lo nos atalhos
`entrada()` e `saida()` — que é por onde quase todo mundo chama. A chave chegava
ao serviço e morria ali.

## Verificação

| Portão | Resultado |
|---|---|
| Testes focais | 6 (`LedgerIdempotenteTest`) |
| Suíte integral | **1528 passes / 4753 assertions** |
| Migrations em PostgreSQL real | 156, sem erro |
| `RlsCoberturaTest` (PostgreSQL real) | 6/6 |
| Índice parcial | criado e **verificado no banco** |
| Rollback → reaplicação | OK, preserva a coluna da `000300` |
| Pint | aprovado |

## O que fica aberto da F4

- **F4-02 (projeção de saldo)**: `estoquesaldos` é a projeção e já é atualizada
  na mesma transação do movimento. Falta o **recálculo** e a comparação que
  detecta divergência sem ajustá-la silenciosamente;
- os chamadores ainda **não passam a chave**. A infraestrutura está pronta e
  testada; adotar em cada porta (transferência, acerto, carga) é o passo
  seguinte, e cada uma precisa decidir qual é a sua chave natural;
- F4-03 a F4-07.
