# PRD DE IMPLEMENTAÇÃO — Vendas / Pedidos / Caixa · auditado (ÚLTIMO a implementar)

> O ATIVO MAIS CARO. Auditado: PedidoController (1661), CaixaController (1056), PedidoRequest,
> Vendaativa/Promotor/Promocao/Atualizarprecos, Pedidoitem, Repository/Util/Processors.
> Decisão: REFATORAR Pedido/Caixa em Domain/Actions + testes; só implementar com cadastros+
> motores (estoque/financeiro)+fiscal SÓLIDOS. Risco MÁXIMO.

## 1. PEDIDO — máquina de estados + orquestração ATÔMICA (preservar — ver 01_vendas_pedidos.md)
- Transições conforme `pedidooperacao` (movimentaestoque/movimentafinanceiro) e `pedidosituacao`
  (flags: fechadoconcluido/entregacancelada/entregapendente/valegas/...). Ao concluir/cancelar/trocar
  setor, orquestra ATÔMICO (DB::transaction): ESTOQUE (saída/entrada/estorno+reinsere via
  EstoqueProcessor), FINANCEIRO (insere/exclui financeiro+parcelas+rateios via financeiroProcessor),
  VALE-GÁS, NF-e/NFC-e (geraEmite/createNF/transmitirNF), PAGAMENTO ONLINE (transacoesonline +
  MobileAppProcessor), COMANDA (impressão térmica), MONITORAMENTO.
- **Estorno CONDICIONADO**: bloqueia se parcela baixada / cheque / boleto / pagamento online.
- `pedidos` (46 col): cliente_id, entrega(rua/bairro/cidade/numero/lat/long), atendenteuser_id,
  condicaopagamento_id, pedidooperacao_id, pedidosituacao_id, datahora, valorvenda, etc.
- `pedidoitems`: produto_id, quantidade, precovendaunitario, precovendatotal, customedio.
- **Validação (PedidoRequest):** cliente_id, entreganumero, condicaopagamento_id, pedidooperacao_id,
  pedidosituacao_id, valorvenda, entregasetor_id, colaborador_id (required).
- ~50 métodos: index, create, store, edit, update, geraEmite/createNF/transmitirNF/updateDadosNf,
  updateValeGasPedido/rollbackValegas, editFromMonitoramento/updateVariosStatus, validaCartao/
  validaGasBolso, historicoCliente, etc. Contratos com o APP MOBILE (D13) — PRESERVAR.

## 2. CAIXA — tesouraria (preservar; já parte em IMPL_FINANCEIRO)
- abrir/fechar/reabrir/transferir caixa; baixar títulos (parcial+rateio, encontro de contas);
  cancelar/estornar (regras de cheque); recibos. Autorização granular (receber/pagar/estornar/
  cancelar/igualdade). via caixaProcessor/financeiroProcessor.

## 3. SATÉLITES DE VENDAS
- **Vendaativa** (campanhas: 3 filtros endereço/compra/giro → ocorrências). **Promotor** (porta-a-porta,
  reusa Cliente). **Promocao** (prêmio a cada N pedidos). **Atualizarprecos** (massa — REESCREVER:
  UPDATE…FROM já corrigido F0, mas confirmar bindings + authorize no store + $this->errors).
- **Vendasmensaisgestao** (meta×realizado + PowerPoint) → relatório/dashboard.

## 4. REORGANIZAÇÃO / UX (MAPA_NAVEGACAO_ALVO)
| Telas legadas | Página-alvo |
|---|---|
| pedido.index + monitoramento + venda ativa | **Pedidos** — painel/Kanban por status + ficha (jornada: cliente→itens c/ preço/estoque em tempo real→pagamento→confirmação) |
| caixa.index + malotes | **Caixa/Tesouraria** (em Financeiro) |
| atualizarprecos | **Produtos → ação Atualizar preços** (preview) |
| promover (promotor) | **Vendas → Promover** |
| promocao | **Clientes → Promoções** / Vendas → Promoções |
**Visão nova:** ciclo do pedido visível (Kanban); venda guiada por etapas com feedback em tempo real
(estoque/preço/vale-gás), em vez de um formulário gigante.

## 5. ARQUITETURA-ALVO (refatoração)
- Domain/Actions por transição: CriarPedido, ConfirmarPedido, CancelarPedido, TrocarSetor — cada uma
  `DB::transaction` + eventos de domínio (PedidoConfirmado → estoque/financeiro/NF/mapa). Controller/UI
  finos. Cobrir com TESTES (baseline obrigatório antes de mexer). Manter API mobile.

## 6. DoD
1. Máquina de estados + orquestração atômica preservada e COBERTA POR TESTES (estoque+financeiro+
   NF+vale-gás na transição; estorno condicionado).
2. Pedidos como painel/Kanban + ficha com jornada; Caixa com preview.
3. Atualizarprecos seguro (bindings/authorize/$this->errors).
4. Contratos do app mobile intactos.
5. Suíte verde; emissão fiscal validada (depende de IMPL_FISCAL/SEFAZ).

> ORDEM: implementar por ÚLTIMO, após cadastros + estoque + financeiro + fiscal prontos e testados.
