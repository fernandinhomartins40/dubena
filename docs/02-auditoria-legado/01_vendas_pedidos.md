# PRD FIDEDIGNO (linha-a-linha) — Vendas / Pedidos · D01

> Lidas 100% das linhas de TODO o domínio (≈4.900 linhas):
> Pedido(1661), Caixa(1053), Vendaativa(633), Promotor(546),
> Vendasmensaisgestao(512), Promocao(263), Atualizarprecos(207),
> Pedidosituacao(162), Pedidooperacao(151), VendaAtivaOcorrenciaTipos(130),
> Pedidomotivoatraso(127).
> (Caixa fica no limite D01/D04 — engine financeiro acionado pela venda; documentado aqui.)

- **Status:** ✅ pronto (fiel)
- **Criticidade:** 🔴🔴 (núcleo: pedido = estoque + financeiro + NF + vale-gás + monitor)
- **Decisão:** **REFATORAR** Pedido/Caixa (Service+testes) · **REESCREVER** o resto

---

## 1. O que cada peça FAZ (verificado)
- **PedidoController (1661):** o coração do ERP. CRUD de pedido + **máquina de estados**
  (pendente→em entrega→concluído/cancelado) que orquestra, conforme `pedidoOp`
  (movimentaestoque/movimentafinanceiro) e `empresaconfig`:
  - **estoque** (`validateMovEstoque`/`movimentarEstoque`: SAIDA ao concluir, ENTRADA ao
    estornar/cancelar, estorno+reinsere ao trocar setor) via EstoqueProcessor;
  - **financeiro** (`validateMovFinanceiro`: INSERE/EXCLUI financeiro+parcelas+rateios,
    bloqueia estorno de parcela baixada/cheque/boleto, gás do povo) via financeiroProcessor;
  - **vale-gás** (`updateValeGasPedido`/`rollbackValegas`), **NF-e/NFC-e**
    (`geraEmite`/`createNF`/`transmitirNF`/`updateDadosNf`), **pagamento online**
    (`validateMovFinanceiro` consulta `sgcm_api.transacoesonline` e estorna via
    MobileAppProcessor), **comanda** (impressão térmica), **monitoramento**
    (`editFromMonitoramento`/`updateVariosStatus`). Usa Repository + Util + PedidoRequest.
- **CaixaController (1053):** engine de **caixa/tesouraria** — abrir/fechar/reabrir/
  transferir caixa; **baixar títulos** (parcial com rateio, encontro de contas),
  cancelar, estornar (com regras de cheque), recibos. Tudo via caixaProcessor/
  financeiroProcessor. Autorização granular (receber/pagar/estornar/cancelar/igualdade).
- **Vendaativa (633):** campanhas de venda ativa — 3 filtros (endereço / por compra /
  média de giro) que varrem clientes+pedidos; gera ocorrências; vincula pedido.
- **Promotor (546):** venda porta-a-porta — busca/cadastra cliente (reusa
  ClienteController) e registra visita (ausente/com venda).
- **Vendasmensaisgestao (512):** dashboard meta×realizado (GLP P13) + mapa de entregas +
  **geração de PowerPoint** (PhpPresentation) com gráficos por setor.
- **Promocao (263):** promoção por período (prêmio a cada N pedidos).
- **Atualizarprecos (207):** atualização de preço em massa por filtro (produto/segmento/
  tipo/setor), opção de alterar preço-base do produto.
- **Pedidosituacao (162):** cadastra a **máquina de estados** (flags fechadoconcluido,
  entregacancelada, entregapendente, valegas, etc.) — config crítica lida pelo Pedido.
- **Pedidooperacao (151):** define convenio/disk/gasbolso/pdv/vendadireta +
  movimentaestoque/movimentafinanceiro — config crítica.
- **VendaAtivaOcorrenciaTipos / Pedidomotivoatraso:** cadastros de apoio.

> Regra real a preservar (ATIVO MAIS CARO DO SISTEMA): a orquestração atômica
> estoque+financeiro+NF+vale-gás na transição de estado do pedido, e o estorno
> condicionado (parcela baixada / cheque / boleto / pagamento online / troca de setor).

---

## 2. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA

### 🔴 QUEBRADO em produção (Postgres ou typo)
- **Atualizarprecos::updateStatementOracle:176-189** — usa **`UPDATE (subquery) SET`**
  (sintaxe **Oracle**) executado via `DB::statement`. **Postgres não suporta** UPDATE de
  subquery (precisa `UPDATE ... FROM`). ⇒ **atualização de preços em massa por filtro está
  QUEBRADA no Postgres.** Escapou da varredura (é POST de ação, não index).
- **Vendaativa — `rownum <= 2` (Oracle) em 4 lugares** (show:113, filtroEndereco:238,
  filtroCompra:325, filtroMediaGiro:414) dentro de `string_agg` → **quebra no Postgres**.
  São telas de filtro/AJAX → não pegas pela varredura.
- **Caixa::baixarduplicatasbycaixa:277 — `->wwhere('ativo', true)`** (typo de `where`):
  método inexistente → **fatal** ao baixar duplicatas por caixa.
- **Caixa::gerarReciboCR:837** — usa colunas `pagar_receber`/`data_pagamento` (snake) onde
  o schema usa `pagarreceber`/`datahorabaixa` → coluna inexistente → recibo CR quebrado.
- **Atualizarprecos::getErrors:35** — `implode(', ', $this->error)` (propriedade certa é
  **`$this->errors`**) → "Undefined property" (só no caminho de erro).

### 🔴 Segurança (SQLi)
- **Atualizarprecos::updateCliente:144-148 + updateStatementOracle** — interpola
  `segmento_id`/`tipopessoa_id`/`setor_id`/`variacao`/ids direto em SQL de **escrita** via
  `DB::statement`, **sem sanitização**. SQLi grave em comando de UPDATE em massa.
- **Vendaativa** — `$_GET` direto e interpolado: filtros (cidade/bairro/rua/setor/
  segmento/datas) montam `whereRaw("ultima.datacompra < to_date('$datanaocompra',...)")`
  (294,386) e `whereRaw("grupo_id = $this->grupo_id and ...")` (97,208) → SQLi.
- **Promotor** — todos os AJAX de busca interpolam input em `whereRaw ... LIKE '%$var%'`:
  getClienteByNome:261, getStreet:282, getNeighborhood:302, getClientByStreet:319
  (rua_id/numero/cidade_id crus). Sanitização parcial (`str_encode_to_query`/`intVal`),
  não substitui binding.
- **Vendasmensaisgestao::getDataChartVenda\*** — SQL gigante com `$ano`/`$setor->id`/
  Session interpolados em `DB::select` (ano/setor vêm de Input) → SQLi. (Já traduzido
  p/ PG: generate_series/date_trunc — só falta parametrizar.)
- **Pedido::isFechado:1216** — `$selectSituacao` SQL cru + `whereRaw("...in
  ($selectSituacao)")`; `$_GET['ids']` json_decode direto. **validaCartao:1144** e
  **validaGasBolso:1190** lêem `$_POST` direto.

### 🟠 Bugs funcionais
- **Promocao::update:179** — string de validação **sem `|`** entre `max:255` e
  `unique:...` → vira `'max:255unique:...'`: a regra unique **nunca é aplicada** no update.
- **Promocao::verificarPeriodoPromocoes:117-140** — 4 condições de sobreposição com a
  1ª==4ª (126==132) e a 3ª logicamente impossível: lógica de período furada (mesmo
  padrão D05/D08/D10).
- **Promocao::destroy:231** — `count($clientePromocao)` sobre `->first()` (objeto, não
  coleção) → checagem de vínculo frágil.
- **Vendaativa::update:149** — `where()->whereNotNull()->orWhere()->whereNotNull()`
  **sem agrupar OR** → precedência AND/OR furada na checagem "filtro gerou pedido/ocorr.".
- **Atualizarprecos::store** e **Pedido (várias rotas internas)** — `store` do
  Atualizarprecos **sem authorize** (index/create/show têm).
- **Pedidosituacao::dadosExtras:100-105** — cases "7"/"8" setam
  `pedidorecebidomovel`/`pedidolidomovel` que **não foram zerados** no topo (os outros 7
  foram) → inconsistência de flags no save.
- **Pedido::createNF:1316** — `$pedido->update(['nfcegerou'..,'nfce_id'..])` está
  **comentado**; o vínculo depende de `nfceGerou` chamado pelo front → risco de NF gerada
  sem vínculo se o front falhar.

### 🟡 Dívida estrutural
- **God controllers**: Pedido (1661/50 métodos) e Caixa (1053) concentram orquestração
  transacional inteira; **controller chamando controller** (Pedido→Nfemitida/Api/Search;
  Caixa→Financeiro/Nfemitida; Promotor→Cliente) — acoplamento forte e difícil de testar.
- **HTML/Form no backend**: Pedido::buscaPorId:1238 monta `\Form::select`. Vendaativa
  monta objetos de endereço concatenados em SQL.
- **Cálculo financeiro/giro em PHP** (Caixa::baixar rateio parcial; Vendaativa::
  filtroMediaGiro loops aninhados) — correto mas frágil/pesado; deveria ser Service+testes.
- **Cross-connection no controller**: Pedido lê `sgcm_api.transacoesonline`;
  Vendasmensais lê `monitora.cercas` direto no controller.
- **Cadastros sem transação** (situacao/operacao/motivoatraso/ocorrenciatipos) e
  **destroy retornando HTML `<br/>`** — padrão do projeto. `//dd` comentados soltos.
- **Hardcodes**: Vendasmensais `tipo_glp IN (3) AND PESOLIQUIDO IN (13)` (P13) embutido.

### ✅ O que está BOM (NÃO regredir)
- **Pedido** é o controller **mais maduro** do sistema: Repository, Util, PedidoRequest,
  `DB::transaction` cobrindo store/update, máquina de estados completa, estorno
  condicionado (parcela baixada/cheque/boleto/online), suporte API+monitoramento.
- **Caixa**: autorização granular, transações, regras de cheque no estorno, rateio.
- Promotor/Promocao/Vendaativa: authorize+igualdade+transações nos fluxos principais;
  Promotor reusa ClienteRequest/Repository; Vendasmensais já traduzido p/ PG.
- Pedidosituacao/Pedidooperacao: máquina de estados/operação bem modelada (config real).

## 3. Especificação do REFAT/REESCRITO (Laravel 12)
- **Pedido → REFATORAR** em **Domain/Service + Actions por transição**
  (CriarPedido, ConfirmarPedido, CancelarPedido, TrocarSetor) com `DB::transaction`
  garantindo atomicidade estoque+financeiro+NF+vale-gás; **eventos de domínio**
  (PedidoConfirmado → estoque/financeiro/NF/mapa) p/ desacoplar; manter contratos do app
  mobile (D13). UI de venda nova por cima. **Cobrir com testes** (é o ativo mais caro).
- **Caixa → REFATORAR** como Service de tesouraria (baixa/estorno/transferência/rateio)
  testável; corrigir `wwhere` e colunas snake do recibo.
- **Atualizarprecos → REESCREVER já** (UPDATE...FROM no PG + bindings; é SQLi + quebrado).
- **Vendaativa/Promotor/Vendasmensais → REESCREVER** como Query Services parametrizados
  (sem rownum/$_GET/whereRaw interpolado); manter regras (giro, meta×realizado, visita).
- **Promocao + cadastros (situacao/operacao/motivoatraso/ocorrenciatipos) → REESCREVER**
  limpos (FormRequest/Policy/transação); corrigir `|` da validação e a sobreposição.

## 4. DECISÃO
- **Pedido + Caixa → REFATORAR** (Service+Actions+testes; baseline obrigatório).
- **Atualizarprecos → REESCREVER com urgência** (quebrado no PG + SQLi em massa).
- **Vendaativa/Promotor/Vendasmensais/Promocao/cadastros → REESCREVER.**
- **Quick wins aplicáveis JÁ (segurança/compat, antes da reescrita):**
  (a) **Atualizarprecos**: UPDATE...FROM no PG + bindings + authorize no store + `$this->errors`;
  (b) **rownum→limit** nos 4 pontos da Vendaativa;
  (c) **`wwhere`→`where`** e colunas do recibo CR no Caixa;
  (d) parametrizar whereRaw/`$_GET`/`$_POST` (Vendaativa/Promotor/Vendasmensais/Pedido);
  (e) `|` da validação unique no Promocao::update; corrigir sobreposição de período;
  (f) zerar flags pedidorecebidomovel/lidomovel no Pedidosituacao.
- **Pré-requisitos (BLOQUEANTES):** baseline fiscal/financeiro; D04 (financeiro) e D06
  (estoque) refatorados ANTES; D02 (NF-e) alinhado; D11 (navegação).
- **Esforço:** MÁXIMO (Pedido+Caixa). Atualizarprecos baixo (mas urgente). Demais médio.
- **Ordem:** **um dos ÚLTIMOS** (Pedido/Caixa) — só com baseline + estoque/financeiro/NF
  sólidos. Atualizarprecos pode ser corrigido já (quick win isolado).
