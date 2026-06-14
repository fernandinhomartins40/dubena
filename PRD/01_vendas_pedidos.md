# PRD — Vendas / Pedidos  ·  D01

- **Status:** ✅ pronto
- **Criticidade:** 🔴🟠 (núcleo transacional: pedido vira estoque + financeiro + NF)
- **Decisão:** **REFATORAR** (não reescrever do zero — concentra regra crítica) — ver §8

---

## 1. Escopo
- **Controllers:** `Pedido` (1661 — **o maior do sistema**, 50 funções),
  `Vendaativa` (633), `Promotor` (546), `Promocao` (263), `Atualizarprecos` (207),
  `Pedidosituacao` (162), `Pedidooperacao` (151), `VendaAtivaOcorrenciaTipos` (130),
  `Pedidomotivoatraso` (127).
- **Processors:** `EstoqueProcessor`, `financeiroProcessor`, `MobileAppProcessor`.
- **Tabelas:** `pedidos`, `pedidoitems`, `pedidosituacaos`, `pedidooperacaos`,
  `pedidomotivoatrasos`, `vendaativas`, `promocaos`, `promotorvendas`,
  + grava em `estoquesetorhistoricos`, `financeiros`/`financeiroparcelas`/
  `financeirorateios`, `valegas`.

## 2. O que o módulo FAZ (regra crítica)
- **Criar/editar pedido**: cliente + itens + entrega (setor/rua/bairro) + condição
  de pagamento + operação fiscal.
- **Ao confirmar/fechar**, dispara em cascata (config-dependente):
  - **Movimenta estoque** (EstoqueProcessor: SAIDA dos itens).
  - **Movimenta financeiro** (financeiroProcessor: gera financeiro + parcelas +
    rateios conforme condição de pagamento).
  - **Vale-gás** (se aplicável): baixa/gera vale.
  - Pode **gerar NF-e/NFC-e** (liga ao D02).
- **Situação do pedido** (pendente→em entrega→concluído/cancelado): máquina de
  estados que controla estoque/financeiro (cancelar estorna).
- **Monitoramento**: pedido alimenta o mapa de entregas (D14).
- **Venda ativa / promotor / promoção**: campanhas e acompanhamento de venda.

## 3. Como FAZ hoje
- Controller **gigante** que injeta os 3 processors no construtor
  (`setMovimentaEstoqueFinanceiro`) conforme `empresaconfig`.
- Lógica de movimentação delegada aos Processors (bom), mas a **orquestração**
  (o que dispara o quê, em que ordem, com que estorno) está no controller.
- `store`/`update`/`updateStatus` concentram a máquina de estados.

## 4. Gambiarras / dívida técnica
- [ ] **God controller** (1661 linhas, 50 métodos): orquestração transacional inteira
      num lugar; difícil de testar isoladamente.
- [ ] Mistura de responsabilidades: pedido + NF + vale-gás + status + monitoramento.
- [ ] Acoplamento config-dependente (`movimentaEstoque`/`movimentaFinanceiro` por
      empresaconfig) espalhado.
- [ ] Risco de consistência: estoque + financeiro + NF deveriam ser **transação
      atômica**; verificar se há `DB::transaction` cobrindo o fluxo todo.

## 5. Riscos de tocar
- **MÁXIMO.** É onde dinheiro + estoque + fiscal se encontram. Erro aqui = venda
  sem baixar estoque, parcela errada, NF divergente, estorno que não estorna.
- Reescrever do zero = reimplementar a máquina de estados + os 3 fluxos + estornos.
  **Altíssimo risco sem baseline robusto.**

## 6. Estado de compatibilidade Postgres
- ✅ index/create validados (200). `getAllSetoresAllowedUser` (whereIn vazio) e
  aliases já corrigidos na Fase Postgres.

## 7. Visão REESCRITA/REFATORADA (Laravel 12)
- **Não reescrever do zero.** Refatorar para:
  - **Service de Pedido** + Actions por transição de estado (CriarPedido,
    ConfirmarPedido, CancelarPedido) com `DB::transaction` garantindo atomicidade
    estoque+financeiro+NF.
  - Manter EstoqueProcessor/financeiroProcessor (já isolam regra), robustecendo.
  - Eventos de domínio (PedidoConfirmado → movimenta estoque/financeiro/NF/mapa)
    para desacoplar.
  - UI moderna por cima (tela de venda) consumindo os Services.
- Preservar contratos usados pelo app mobile (MobileAppProcessor / D13).

## 8. DECISÃO e justificativa
- **Decisão: REFATORAR** (faseado), não reescrever do zero.
- **Por quê:** concentra a regra mais crítica e cara de reproduzir (estoque +
  financeiro + fiscal + estornos). A regra existente é o ativo; o problema é a
  organização (God controller). Refatorar para Services/Actions + transação +
  testes preserva a regra e moderniza a estrutura. UI nova vem por cima.
- **Pré-requisitos (BLOQUEANTES):** baseline fiscal/financeiro (Frente D) — sem ele,
  qualquer mudança aqui é cega; D04 (financeiro) e D06 (estoque) refatorados antes;
  D02 (NF-e) alinhado.
- **Esforço:** ALTO. É o módulo mais caro e arriscado.
- **Ordem:** **um dos ÚLTIMOS** a migrar; só com baseline + estoque/financeiro/NF
  já sólidos. UI de venda nova pode vir antes (consumindo o legado refatorado).
