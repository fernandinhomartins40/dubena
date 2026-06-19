# PRD DE IMPLEMENTAÇÃO — Financeiro / Tesouraria · auditado

> Auditado: Processors financeiro/caixa/cheque/boleto (motor, parte caracterizada na F1) +
> controllers Financeiro/Caixa/Cheque*/Boleto*/Conta/Pix/Fechamentos. O domínio com MAIS telas
> dispersas no legado (12+) — grande oportunidade de consolidação.

## 1. MOTOR — Processors (PRESERVAR; cobrir com teste antes de refatorar)
- **financeiroProcessor**: setParcelasRequest (rateio de desconto por parcela), gravar (com/sem baixa),
  rateios (centro/plano de conta 100%), parcelas, encontro de contas. CARACTERIZADO F1.
- **caixaProcessor(conta_id)**: abrir/fechar/reabrir caixa, baixar título (parcial + rateio),
  movimentar, saldo, fechamento; autorização granular (operar/estornar/transferir). CARACTERIZADO F1.
- **ChequeProcessor / BoletoProcessor**: ciclo de cheque (emitido/recebido, devolução) e boleto
  (remessa/retorno).

## 2. TELAS / CONTROLLERS (todas — paridade; ver de-para)
Financeiro (lançamentos + filtro getLancamentosFinanceiros), Caixa (1056 ln), Chequeemitido,
Chequerecebido, Boleto/BoletoPdf/Boletoremessa, Conta, Contamovimentotipo, Pix, Descontocheque,
Fechamentomalote, Fechamentomensalgestao, Planoconta, Conciliacao, ImportExtrato, ImportReportCartao,
Contasreceber, Contaspagar, financeiro.createReceita/createDespesa.

## 3. 🔴 RESÍDUOS A CORRIGIR (auditados)
- **SQLi de filtro** em `FinanceiroController::getLancamentosFinanceiros:355-365` — `$valorPesquisa`
  interpolado em whereRaw. PARAMETRIZAR (bindings). Prioridade segurança.
- `case 4;` (ponto-e-vírgula) no switch de tipo de pesquisa (`:361`) — case malformado.
- Caixa: `wwhere`/recibo CR já corrigidos na F0 (não regredir).

## 4. REORGANIZAÇÃO / UX (MAPA_NAVEGACAO_ALVO) — consolidar 12+ telas
| Telas legadas | Página-alvo |
|---|---|
| Contas a Receber + Contas a Pagar + Lançamento Receita + Lançamento Despesa + filtro | **Lançamentos** (1 página, filtros por tipo/status/período/origem) |
| Caixas + Fechamento de Malotes | **Caixa/Tesouraria** (abrir/fechar/baixar com preview de saldo) |
| Cheques Emitidos + Recebidos + Desconto de cheque | **Cheques** (abas) |
| Boletos + Remessas/Retornos + Baixa do PIX | **Boletos/PIX** (abas) |
| Conciliação + Import Extrato + Import Cartão | **Conciliação** |
| Plano de Contas + Centro de Custo + Contamovimentotipo + Layout banco | **Config Financeiro** (árvore plano/centro) |
| Fechamento Mensal Gerencial | **Fechamento Mensal** (DRE/Balanço) |
**Visão nova:** Lançamentos num lugar com filtros tipados (fim do "abrir 4 telas"); Caixa com preview
do efeito da baixa no saldo (antes/depois) e rateio visual; extrato consolidado.

## 5. API ADMIN + Service
- Manter Processors como Domain Services testáveis. API: /financeiro/lancamentos (filtros
  PARAMETRIZADOS — corrige SQLi), /caixa (abrir/fechar/baixar), /cheques, /boletos, /pix,
  /conciliacao, /plano-contas (árvore), /centro-custos, /fechamento-mensal.

## 6. DoD
1. Motor preservado com baseline verde (parcelas/gravar/baixar/rateio/caixa).
2. SQLi do filtro fechado (bindings); case malformado corrigido.
3. As 12+ telas consolidadas nas páginas acima (de-para 100%; nada perdido).
4. Caixa com preview de saldo; Lançamentos com filtros tipados.
5. Testes (motor + endpoints) + suíte verde.
