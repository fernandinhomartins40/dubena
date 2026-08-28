# F1-13 — Demais grafos pai-filho e FKs (item 4 do gate)

Data: 2026-08-28 (America/Sao_Paulo)

## A medição que reduziu o problema

O item 4 pedia "cobrir os demais relacionamentos pai-filho/FKs fora dos grafos já
protegidos". A pergunta parecia grande. Medida por `pg_constraint` num PostgreSQL
com a cadeia completa aplicada:

| | |
|---|---|
| FKs entre duas tabelas com chave SaaS | **175** |
| ...com `empresa_id` no próprio filho | **168** |
| ...sem `empresa_id` no filho | **7** |

As 168 **não precisam de trigger**. A policy canônica valida a própria linha na
escrita (`app_tenant_can_operate(tenant_account_id, empresa_id)`), então apontar
para um pai de outro tenant não ajuda ninguém: a linha filha já teria que
satisfazer o grant para existir. Instalar 168 triggers seria custo e risco sem
ganho de isolamento.

Das 7 restantes, 6 são grafos já cobertos pelo protetor documental — incluindo
os dois pivots adicionados no microlote anterior.

**Sobrava exatamente uma:** `sorteio_numeros.cliente_id`.

## O furo

`SorteioNumero` herda a chave de tenant do `sorteios` pai, e isso está correto.
Mas `cliente_id` nunca era conferido contra ela. `clientes` é COMPANY e tem
policy canônica — então o número de sorteio de um tenant podia ficar amarrado a
um cliente de outro.

Provado em PostgreSQL antes da correção:

```
RESULTADO: numero do tenant 901 aponta para cliente do tenant 902
```

## A correção

Trigger `sorteio_numeros_cliente_tenant`, no mesmo padrão do guarda de FK
financeira já existente. Depois dela, no mesmo banco:

| Caso | Resultado |
|---|---|
| `cliente_id` de outro tenant (INSERT) | **recusado** — `23514` |
| `cliente_id` de outro tenant (UPDATE) | **recusado** — o gatilho cobre `UPDATE OF` |
| `cliente_id` do mesmo tenant | aceito |
| `cliente_id` nulo (número sem dono) | aceito |

O último caso é deliberado: `cliente_id` é nullable de propósito — número emitido
antes de ter dono. Recusá-lo quebraria o fluxo legítimo do sorteio.

A única compatibilidade permitida é cliente ainda sem chave SaaS, para não travar
dados anteriores à conversão.

## Evidência

- Cadeia completa (**138 migrations**) aplicada do zero em PostgreSQL 16.
- `RlsCoberturaTest` com role `erp_app` e `--fail-on-skipped`: **6 testes / 354
  assertions, zero skip**.
- Os quatro casos da tabela acima executados no banco, com o cruzamento provado
  **antes** e recusado **depois**.
- Suíte integral: **1.333 passes, 4.207 assertions, 8 skips, zero falhas**.
- Pint aprovado.

## O que isto NÃO conclui

Do gate F1 restam o item 5 (registro de rollback/snapshot de grants) e o item 1
(execução em homologação com a role de runtime, que depende de deploy). Os itens
2, 3 e 4 estão fechados. `erp-novo/perda.sql` segue pré-existente e intocado.
