# PRD — Financeiro / Caixa / PIX / Boleto / Cheque  ·  D04

- **Status:** ✅ pronto
- **Criticidade:** 🔴 (tudo move dinheiro: baixa de título, caixa, PIX, boleto, conciliação)
- **Decisão:** **REFATORAR** (regra financeira crítica; modernizar estrutura/UI, preservar lógica)

---

## 1. Escopo
- **Controllers:** `Caixa` (1053), `Conta` (780), `Boletoremessa` (651),
  `Financeiro` (638), `BoletoPdf` (583), `Chequerecebido` (494), `Chequeemitido` (476),
  `Importextrato` (393), `Condicaopagamento` (335), `Planoconta` (324),
  `Centrocusto` (324), `Boleto` (268), `Pix` (auditado Fase 1), `Conciliacao`,
  `Contamovimentotipo`, `Layoutbanco`, `Descontocheque`.
- **Processor:** `financeiroProcessor`, `caixaProcessor`.
- **Tabelas:** `financeiros`/`financeiroparcelas`/`financeirorateios`, `contas`/
  `contamovimentos`/`contamovimentotipos`, `boletos`/`boletoremessas`,
  `pixtransactions`, `cheque*`, `condicaopagamentos`, `planocontas`, `centrocustos`,
  `layoutbancos`.

## 2. O que o módulo FAZ (regra crítica)
- **Caixa**: baixa/cancelamento de títulos (duplicatas), recebimento por caixa,
  encontro de contas; gera movimento de conta; recibo. Usa **transação**
  (`baixar($request, $withTransaction)`).
- **Financeiro (contas a pagar/receber)**: títulos, parcelas, rateios por
  plano de conta/centro de custo; baixa/estorno.
- **PIX** ✅(Fase 1): cobrança + webhook com token + confere valor pago==cobrado.
- **Boleto**: geração, remessa/retorno (CNAB), PDF.
- **Cheque** (emitido/recebido, desconto): controle e baixa.
- **Conciliação / Importação de extrato**: bate lançamentos com extrato bancário.
- **Plano de contas / Centro de custo**: classificação contábil (árvore — usada
  nos relatórios DRE/Balanço via WITH RECURSIVE).
- **Condição de pagamento**: define como o pedido gera parcelas.

## 3. Como FAZ hoje
- Regra concentrada em `financeiroProcessor`/`caixaProcessor` + controllers grandes.
- Caixa usa transação na baixa (bom). Boleto remessa/retorno = CNAB (formato banco).
- `whereRaw` em Financeiro (7), Boletoremessa (3), Caixa (1) — datas/filtros.

## 4. Gambiarras / dívida técnica
- [ ] Controllers grandes (Caixa 1053, Conta 780) com orquestração financeira embutida.
- [ ] `whereRaw` interpolado a triar (Frente C) — Financeiro/Boletoremessa.
- [ ] Boletoremessa tinha bug de data sem espaço (`'00:00:00'`) — corrigido.
- [ ] Lógica de baixa/estorno espalhada entre Caixa, Financeiro e processors.

## 5. Riscos de tocar
- **MÁXIMO** (junto com D01/D02). Baixa errada = título sumido/duplicado; rateio
  errado = contabilidade errada; remessa CNAB errada = banco rejeita; PIX/boleto
  = dinheiro real. Plano de contas alimenta DRE/Balanço (fiscal-gerencial).

## 6. Estado de compatibilidade Postgres
- ✅ Validado (200) incl. report.contaspagar/receber/fluxocaixa. PIX auditado.
- 🟡 whereRaw a parametrizar (Frente C). Plano de contas (CONNECT BY) traduzido p/
  WITH RECURSIVE — **valor depende de baseline + dados**.

## 7. Visão REESCRITA/REFATORADA (Laravel 12)
- **Não reescrever a regra do zero.** Refatorar para Services/Actions:
  BaixarTitulo, EstornarTitulo, ConciliarExtrato, GerarRemessa — com transação e
  testes; manter financeiroProcessor como base.
- PIX/boleto: manter integrações (CNAB/PSP), encapsular em gateways testáveis.
- Plano de contas/centro de custo: árvore com WITH RECURSIVE (já feito) + UI.
- UI moderna: caixa/contas a pagar-receber com UX de fintech.

## 8. DECISÃO e justificativa
- **Decisão: REFATORAR** (faseado), preservando a regra financeira.
- **Por quê:** dinheiro real, integrações bancárias (CNAB/PIX), e a regra é o ativo.
  Reescrever do zero arriscaria conciliação/baixa/remessa — caro de reproduzir.
- **Pré-requisitos (BLOQUEANTES):** baseline financeiro (Frente D); parametrizar
  whereRaw (Frente C); plano de contas validado por valor (DRE/Balanço).
- **Esforço:** ALTO.
- **Ordem:** entre os últimos; antes do D01 fechar (Pedido usa o financeiro) mas
  depois do baseline. Cadastros (condição pgto, plano conta, centro custo) podem
  ter UI nova cedo.
