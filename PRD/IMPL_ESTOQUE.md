# PRD DE IMPLEMENTAÇÃO — Estoque · auditado

> Auditado: EstoqueProcessor (motor, CARACTERIZADO na F1) + controllers
> Estoquesetor/Transferencias/Requisicao/Setoracerto/Inventario/Estoquefisico + Reportestoque.
> Motor a PRESERVAR (testes golden da F1); telas a reescrever com UX moderna.

## 1. MOTOR — EstoqueProcessor (NÃO regredir; cobrir por teste antes de mexer)
- `movimentarEstoque(arrayEstoquesetorhistorico):512` — ENTRADA/SAÍDA por setor+produto; cria/atualiza
  saldo (estoquesetors, estoqueprodutos), grava histórico (estoquesetorhistoricos); respeita
  `permiteestoquenegativo` (bloqueia SAÍDA sem saldo). **Núcleo de toda movimentação.**
- `fecharEstoque(Estoquefechamento):169` — consolida saldo por setor+produto em estoquefechamentosetors.
- `abrirEstoque(Estoquefechamento):432` — reabre período.
- `efetivarEstoquefisico(Estoquefisico):461` — ajusta sistema→físico (gera SAÍDA/ENTRADA da diferença),
  marca efetivado=1 (F0 corrigiu bug que gravava id no empresa_id; não regredir).
- Custo médio ponderado: `Produto::updateCustoMedio` (caracterizado F1).

## 2. TELAS / CONTROLLERS (ações a preservar)
- **Estoquesetor** — saldos por setor/produto (consulta + ajustes).
- **EstoqueTransferencias** — index/create/store/show/edit/update/destroy: transferir entre setores
  (gera SAÍDA origem + ENTRADA destino atômico).
- **Estoquerequisicao** — requisição de produtos (show + fluxo).
- **Estoquesetoracerto** — acerto de estoque do setor.
- **Inventario** — index/create/store/show/edit/update/destroy: contagem/ajuste de inventário.
  (F0: catch usava $e errado — corrigido; manter.)
- **Estoquefisico** — index/create/edit/show/store/update + `buscaEstoqueSetor(setor,datacompetencia)`:
  registrar estoque físico e efetivar (chama efetivarEstoquefisico).
- **Reportestoque** — relatórios (→ Central de Relatórios).

## 3. REORGANIZAÇÃO / UX (MAPA_NAVEGACAO_ALVO)
**De-para:** Estoques (Saldos/Requisição/Transferência/Acerto/Inventário/Fechamento) → **1 página
Estoque com abas**. **Visão nova:** saldo por setor×produto em tempo real, com preview do efeito de
cada movimento (antes/depois); timeline de movimentações do produto. Relatórios de estoque → Central
de Relatórios.

## 4. API ADMIN + Service
- Extrair EstoqueProcessor como Domain Service testável (já caracterizado). API admin:
  /estoque/saldos, /estoque/transferencias (CRUD), /estoque/requisicoes, /estoque/inventarios,
  /estoque/fisico (+ efetivar), /estoque/fechamento (fechar/abrir). Todas com transação atômica.
- Lookups: setores, produtos (reusar).

## 5. DoD
1. Motor preservado com baseline de teste verde (movimentar/fechar/abrir/efetivar/custo médio).
2. Todas as telas (transferência/requisição/acerto/inventário/físico/fechamento/saldos) na página
   Estoque com abas; ações por linha; preview de saldo.
3. permiteestoquenegativo respeitado; não-inativar produto com saldo (ver IMPL_PRODUTO).
4. Testes (motor + endpoints) + suíte verde.
