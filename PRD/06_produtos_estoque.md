# PRD — Produtos / Estoque  ·  D06

- **Status:** ✅ pronto
- **Criticidade:** 🟠 estoque (saldo/movimentação) · **Produto** toca 🔴 (tributação p/ NF-e)
- **Decisão:** **REESCREVER** Produto (UI) + cadastros · **REFATORAR** motor de estoque (ver §8)

---

## 1. Escopo
- **Controllers:** `Produto` (840), `Estoquerequisicao` (454),
  `EstoqueTransferencias` (437), `Testeestoque` (343), `Estoquefisico` (269),
  `Estoquesetor` (211), `Inventario` (205), `Estoquesetoracerto` (170),
  `Produtoclasse` (138), `Tipocombustivel` (128), `Unidademedida` (127).
- **Núcleo:** `app/Processors/EstoqueProcessor.php` (535) — motor de movimentação.
- **Tabelas:** `produtos`, `produtoclasses`, `estoquesetors`,
  `estoquesetorhistoricos`, `estoquefechamentos`/`estoquefechamentosetors`,
  `estoquerequisicaos`, `estoquetransferencias`, `inventarios`, `unidademedidas`,
  `tipocombustivels`.

## 2. O que o módulo FAZ
- **Produto** 🔴(parcial): cadastro com preço de venda, **dados de tributação**
  (NCM/CEST, grupo fiscal, tipo_glp, peso) — usados pela NF-e (D02). Liga produto a
  imposto/operação fiscal.
- **Estoque por setor**: saldo de cada produto por setor; **movimentação**
  ENTRADA/SAIDA registrada em `estoquesetorhistoricos` (kardex).
- **EstoqueProcessor**: valida e aplica movimentação (não deixa SAIDA sem saldo,
  salvo estoque negativo permitido), mantém saldo, lida com fechamento de estoque.
- **Requisição / Transferência / Inventário**: movimentos entre setores e ajuste
  por contagem.

## 3. Como FAZ hoje
- Produto: controller grande mas limpo (FormRequest, 0 SQL cru); muito campo fiscal.
- Estoque: lógica de saldo/movimentação centralizada no **EstoqueProcessor**
  (bom — já é um "service"), invocado por Pedido/NF/requisição/transferência.
- Fechamento de estoque com data (usa funções de data já traduzidas p/ Postgres).

## 4. Gambiarras / dívida técnica
- [ ] `EstoqueProcessor` é um service de fato, mas com erros acumulados em
      `addError`/`getErrors` (controle de erro por array, não exceções) — frágil.
- [ ] Bastante código comentado em `EstoquefisicoController` (limpeza).
- [ ] `Testeestoque` (343 linhas) parece tela de teste/diagnóstico em produção —
      avaliar se é código que deveria existir no sistema final.
- [ ] Cálculo de saldo depende de varrer histórico — verificar performance com volume.

## 5. Riscos de tocar
- **Estoque = 🟠**: saldo errado afeta venda (não deixa vender / vende sem ter) e
  custo. O motor (EstoqueProcessor) é invocado pelo Pedido — acoplamento crítico.
- **Produto = 🔴 parcial**: os campos fiscais alimentam a NF-e. Mudar como
  NCM/CEST/grupo fiscal são lidos afeta cálculo de imposto → baseline.

## 6. Estado de compatibilidade Postgres
- ✅ Validado na varredura (200). Fechamento de estoque (datas) traduzido na Fase 3.
- Movimentacao/relatórios de estoque com SQL já convertido (to_date::date etc.).

## 7. Visão REESCRITA (Laravel 12)
- **Produto**: reescrever UI (cadastro com abas: comercial, fiscal, estoque);
  manter schema fiscal; FormRequest/Resource/Policy.
- **Motor de estoque**: manter o conceito do EstoqueProcessor, mas reescrever como
  **Service com exceções + transações + testes** (movimentação é dinheiro/operação).
  Eventos de domínio (EstoqueMovimentado) para desacoplar de Pedido.
- Cadastros (classe, unidade, tipo combustível) → recursos limpos.
- `Testeestoque`: decidir se vira ferramenta de diagnóstico ou some.

## 8. DECISÃO e justificativa
- **Produto + cadastros → REESCREVER** (UI nova, schema fiscal preservado).
- **Motor de estoque → REFATORAR** para Service testável (não reescrever a regra do
  zero — ela está concentrada e funciona; melhorar robustez/erros/testes).
- **Por quê:** estoque correto é pré-condição de venda; a regra existente é valiosa.
  Produto tem face fiscal → preservar com baseline.
- **Pré-requisitos:** baseline de estoque/produto (movimentação dá o mesmo saldo);
  alinhar com D01 (Pedido usa o motor) e D02 (NF-e usa tributação do produto).
- **Esforço:** médio (Produto) + médio-alto (motor com testes).
- **Ordem:** depois de cadastros base; junto/antes de D01 (Pedido depende do estoque).
