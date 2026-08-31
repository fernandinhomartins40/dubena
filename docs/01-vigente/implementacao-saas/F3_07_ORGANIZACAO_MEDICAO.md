# F3-07 — Medição: não há duas hierarquias concorrentes

Data: 2026-08-31 (America/Sao_Paulo)

## O que a tarefa pede

> **F3-07 — Organização:** uma hierarquia ativa dentro do tenant; desativar a
> concorrente somente após medir consumidores e converter dados.

A tarefa manda **medir antes de desativar**. Este documento é a medição — e a
conclusão é que **não há o que desativar**.

## O que eu procurei, e o que achei

Parti da hipótese de que `regioes` (antiga) competia com
`unidades/departamentos/setores_org` (A3). Está errado:

| Tabela | O que é | Consumidores |
|---|---|---|
| `unidades` → `departamentos` → `setores_org` | árvore **organizacional** | `role_user` (escopo do papel), `PolicyEvaluator`, `EstruturaController` |
| `regioes` | região **geográfica** de entrega | `Empresa`, `EmpresaRequest`, `RegiaoController` |
| `setores` | local de **estoque** (F3-06) | saldos, pedidos, comodato, comissão |

São três coisas com nomes parecidos e propósitos diferentes. Não são
concorrentes.

### Dois falsos positivos que investiguei

- **`colaborador_comissoes.setor_id`** aponta para `setores` (estoque), e cheguei
  a ler isso como "colaborador lotado num depósito". Não é: o próprio comentário
  da migration diz `(colaborador × produto × setor × condição)` — é regra de
  comissão **por local de estoque**, que é legítimo;
- **`cargos`** poderia ter sido duplicada pela A3. A migration diz
  explicitamente que não foi: *"a tabela `cargos` JÁ EXISTE (RH, grupo-scoped).
  Em vez de duplicá-la, só acrescentamos `role_id`"*.

Ambos os casos são o oposto do defeito que a tarefa combate — são decisões
corretas já tomadas.

## O que a árvore serve hoje

Exclusivamente o **RBAC**: `role_user` guarda a partir de qual nó o papel vale, e
`herda_filhos` diz se desce para os filhos (semântica fechada em F2-02A).

## O que eu deliberadamente NÃO fiz

O colaborador não tem lotação na árvore — não existe
`colaboradores.unidade_id`/`departamento_id`. Poderia parecer uma lacuna.

**Não é, e acrescentar seria inventar requisito.** A árvore foi criada para
escopar permissão, e é isso que ela faz. Nenhum relatório, tela ou regra pede
"em qual departamento o colaborador trabalha", e uma coluna sem consumidor é
exatamente o tipo de peso morto que esta transformação está removendo.

Se a lotação vier a ser necessária — folha por centro de custo, por exemplo — o
lugar dela já está pronto e a decisão será do dono.

## Conclusão

**F3-07 já está satisfeita.** Há uma hierarquia organizacional ativa por tenant,
sem concorrente. O gate da tarefa ("desativar a concorrente após medir") não se
aplica porque a concorrente não existe.

Registro a medição para que a próxima pessoa não refaça a investigação — e para
que, se alguém introduzir uma segunda árvore, este documento sirva de referência
do que existia antes.
