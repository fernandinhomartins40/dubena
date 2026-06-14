# PRD — Vale-Gás / Convênio / Comodato / MCMM  ·  D07

- **Status:** ✅ pronto
- **Criticidade:** 🟠🔴 (fechamentos geram financeiro; MCMM é registro fiscal de combustível)
- **Decisão:** **REFATORAR** fechamentos/MCMM · **REESCREVER** consultas/cadastros simples

---

## 1. Escopo
- **Controllers:** `Mcmm` (876), `Fechamentoconvenio` (781), `Valegasvenda` (626),
  `Fechamentomalote` (467), `Conveniogbgestao` (287), `Comodato` (241),
  `Comodatogestao` (211), `Valegascancelar` (54), `Valegasbaixar` (45),
  `Valegasconsulta` (35).
- **Tabelas:** `valegas`, `valegasvendas`, `valegassituacaos`, `convenios`/
  `clienteconvenios`, `comodatos`/`comodatoitems`, `mcmm`/`mcmmhistorico*`,
  fechamentos (malote/convênio).

## 2. O que o módulo FAZ
- **Vale-gás**: venda/baixa/cancelamento/consulta de vales (pré-pago de gás);
  máquina de situação (vendido/impresso/baixado/cancelado/pré-venda).
- **Convênio** 🔴(parcial): cliente corporativo compra fiado; **fechamento de
  convênio gera financeiro** (`processoFinanceiroFechamento` → financeiro/parcelas/
  rateios) = cobrança real. Gestão (`Conveniogbgestao`) tem dashboards.
- **Comodato**: equipamento (botijão/tanque) emprestado ao cliente; controle de
  giro/vencimento; gestão de comodatos ativos/vencidos.
- **MCMM** 🔴: livro de Movimentação Mensal (entradas/saídas/saldo) — registro do
  setor de combustíveis (face fiscal/regulatória ANP).
- **Fechamento de malote**: agrupa pedidos/parcelas para fechamento financeiro.

## 3. Como FAZ hoje
- Fechamentos usam `financeiroProcessor` (gera financeiro) — regra concentrada.
- MCMM calcula saldo anterior + entradas/saídas por período (datas).
- Conveniogbgestao/Comodatogestao tinham CONNECT BY/generate_series (traduzidos).

## 4. Gambiarras / dívida técnica
- [ ] `Mcmm` (876) e `Fechamentoconvenio` (781) grandes; lógica de fechamento +
      financeiro no controller (deveria ser Service).
- [ ] `McmmController:238` tinha `to_date('$dataInicio')` 1-arg (corrigido p/ ::date).
- [ ] Conveniogbgestao tinha geradores de série Oracle (traduzidos p/ generate_series).
- [ ] Vale-gás: máquina de situação por strings/ids dispersos (poderia ser enum/estado).

## 5. Riscos de tocar
- **Fechamentos = 🔴**: geram cobrança real (financeiro). Erro = cobrar errado o
  conveniado. Precisa baseline.
- **MCMM = 🔴**: registro fiscal de combustível (ANP); valor/saldo errado = problema
  regulatório.
- Comodato/vale-gás consulta/cadastro: risco menor.

## 6. Estado de compatibilidade Postgres
- ✅ Validado; funções Oracle (CONNECT BY/to_date/listagg) traduzidas e validadas
  por sintaxe — **valor depende de baseline + dados reais**.

## 7. Visão REESCRITA (Laravel 12)
- **Vale-gás**: máquina de estados explícita (enum + transições), Service de
  emissão/baixa; UI moderna; consultas/cancelamento simples → reescrever.
- **Convênio/Fechamento**: Service de fechamento (gera financeiro) testável +
  baseline; gestão com dashboards modernos.
- **Comodato**: recurso com controle de giro/vencimento + alertas.
- **MCMM**: Service de apuração mensal testável; relatório regulatório.

## 8. DECISÃO e justificativa
- **Vale-gás (consulta/baixa/cancelar) + Comodato cadastro → REESCREVER** (risco menor).
- **Fechamento de convênio/malote + MCMM → REFATORAR** com Service + baseline
  (geram financeiro / registro fiscal).
- **Por quê:** específico do negócio de gás, com partes que viram dinheiro/registro
  regulatório (preservar regra) e partes simples (modernizar livre).
- **Pré-requisitos:** baseline financeiro (fechamentos) e de MCMM; D04 (financeiro)
  e D05 (convênio do cliente) alinhados.
- **Esforço:** médio-alto (fechamentos/MCMM); baixo (consultas/cadastros).
- **Ordem:** consultas/cadastros cedo; fechamentos/MCMM junto/depois do D04.
