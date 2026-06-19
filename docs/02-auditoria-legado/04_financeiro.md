# PRD FIDEDIGNO (linha-a-linha) — Financeiro / Tesouraria · D04

> Lidas 100% das linhas do núcleo do domínio (≈5.500 linhas):
> Fechamentoconvenio(781), Conta(780), Financeiro(638), Fechamentomensalgestao(518),
> Chequerecebido(494), Chequeemitido(476), Fechamentomalote(467), Planoconta(324),
> Centrocusto(324), Boleto(268), Metavenda(190), Contamovimentotipo(109).
> (Caixa(1053) lido e documentado no D01 — engine de tesouraria acionado pela venda.)
> Boletos de geração de arquivo (BoletoPdf 583, Boletoremessa 651 = CNAB/PDF) e os
> Processors (financeiroProcessor/caixaProcessor/ChequeProcessor/BoletoProcessor) são a
> camada de regra; documentados pelos pontos de chamada.

- **Status:** ✅ pronto (fiel — núcleo)
- **Criticidade:** 🔴🔴 (é o dinheiro: parcelas, baixa, estorno, cheque, boleto, DRE/Balanço)
- **Decisão:** **REFATORAR** núcleo (Processors+Services+testes) · **REESCREVER** cadastros/UI

---

## 1. O que cada peça FAZ (verificado)
- **FinanceiroController (638):** contas a pagar/receber — criar lançamento (simples/
  complexo/por caixa/por agrupar), **consulta central de parcelas** (`getLancamentos
  Financeiros`: filtros por status/data/cliente/colaborador, paginação, totais via
  `SUM() over()`), alterar descrição/vencimento, cancelar, importar relatório de cartão
  (CSV da operadora → casa por autorização). Delega a regra ao **financeiroProcessor**.
- **ContaController (780):** CRUD de **conta/caixa bancária** — talões de cheque,
  permissões por usuário (ver/operar/transferir/estornar/lançar fechado), config de
  **boleto** (layout×banco, carteira, multa/juros, instruções), **conciliação de
  extrato OFX** (`addEditExtratoconfig`: ações Lançar/Transferir/LançarBaixar). Usa
  **Enum** (ContaextratoAcao) e **Revisionable** (auditoria de users/talões).
- **Chequeemitido (476) / Chequerecebido (494):** máquina de estados de **cheque**
  (situações 1-7: emitido/baixado/inutilizado/recebido/depositado/devolvido), talão
  sequencial, encontro de contas, troco/adiantamento, baixa/estorno/devolução. Delega
  ao **ChequeProcessor**.
- **Fechamentoconvenio (781):** agrupa pedidos do período de um conveniado num único
  **financeiro com parcelas** (`calculoParcelas`), aplica comissão do convênio, desagrupa
  ao editar, emite NF e boleto do fechamento. PDF/XLS. Delega ao **financeiroProcessor**.
- **Fechamentomalote (467):** fecha em lote pedidos de um setor/colaborador no período →
  atualiza status (via PedidoController) e **baixa no caixa malote** (via CaixaController).
- **Fechamentomensalgestao (518):** **DRE + Balanço Patrimonial** gerencial (Excel/PDF +
  envio por email à diretoria). Cálculo nos Repositories Dre/Balanco (WITH RECURSIVE).
- **Planoconta (324) / Centrocusto (324):** plano de contas / centro de custo
  **hierárquicos por código** (3 dígitos/nível), naturezaSPED, finalizador. Revisionable.
- **Boleto (268):** geração/ocorrências/cancelamento de boleto (delega BoletoProcessor).
- **Metavenda (190):** metas de venda por setor/produto/mês. **Contamovimentotipo (109):**
  tipo de movimento de caixa (cartão/cheque/convênio/vale-gás).

> Regra real a preservar (DINHEIRO): geração/estorno de financeiro+parcelas+rateios,
> baixa parcial com rateio, máquina de cheque, agrupamento (convênio/malote), DRE/Balanço,
> plano de contas hierárquico. Reproduzir errado = caixa divergente, parcela duplicada,
> estorno que não estorna.

---

## 2. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA

### 🔴 QUEBRADO no Postgres
- **Fechamentomalote::getParcelasStore:454-459 — CONNECT BY (Oracle) NÃO TRADUZIDO!**
  `CONNECT_BY_ROOT`, `LEVEL`, `CONNECT BY NOCYCLE PRIOR ... START WITH` em `DB::select`.
  **Postgres não tem CONNECT BY** → o `store` do fechamento de malote **quebra no PG**.
  ⚠️ Contradiz a afirmação anterior de "100% CONNECT BY traduzido no ctrl-web" — este
  escapou (é no fluxo de gravação do malote, não numa tela varrida). **Traduzir p/
  WITH RECURSIVE.**
- **Planoconta + Centrocusto `isUsed`/`isUsedByConfig`/`isUsedByNF` — `WHERE ROWNUM <= 1`
  (Oracle) + subquery `SELECT id FROM (subSel)` SEM ALIAS**: as subqueries internas já
  têm `limit 1`, mas o wrapper externo usa `ROWNUM` e a subquery não tem alias →
  **quebra no PG** ("subquery must have alias" + ROWNUM inexistente). ⇒ checagem de
  "plano/centro em uso" quebrada → cadastro de filho/edição falham. **Quick win.**

### 🔴 Bugs reais (typo/variável)
- **Chequerecebido::dadosExtras:264 — `$dada['banco_id'] = $data['banco_id_erro'];`**
  (typo **`$dada`**): cria variável-fantasma; o `banco_id` real **nunca entra** no `$data`
  que vai pro `Chequerecebido::create` → cheque gravado sem banco (ou viola NOT NULL).
- **Cheque{emitido,recebido}: assinatura com typehint depois de opcional** —
  `editar($acao,$id,$data=null, Chequeemitido $cheq)` (emitido:335) e
  `baixarCheque(Request,$id,$devolucao=false, Chequerecebido $cheq)` (recebido:407): o
  parâmetro com type-hint **após** parâmetro opcional impede o route-model-binding/DI de
  resolver; e `editar` chama `baixarCheque($id,$data)` com argumentos no slot errado.
- **Centrocusto::update:169 — `$cc_descricao = $centrocusto->first()->descricao`** onde
  `$centrocusto` é um Model (`findOrFail`), não Collection → `->first()` em Model é
  inesperado (deveria ser `$centrocusto2->first()`).

### 🔴 Segurança
- **Financeiro::getLancamentosFinanceiros — SQLi** na tela financeira central: bloco
  `$colaborador_id` monta `$rawSetor`/`$pednf_fin` (UNION ALL) interpolando
  `$colaborador_id`/`$cliente_id`/`$empresa_id`/`$pagarreceber` em `whereRaw`; e o
  `switch tipoPesquisa` interpola `$valorPesquisa` (`documento = '$valorPesquisa'`).
  **Parametrizar.**
- **Planoconta/Centrocusto isUsed\*** interpolam `$pc_id`/`$cc_id` em SQL cru (derivados
  internos; risco menor, mas SQL cru). **Planoconta::hasEmpresaEmitSped:189** whereRaw
  com grupo_id da sessão.
- **`$_GET` direto** amplamente (Financeiro, Boleto, Chequeemitido/recebido,
  Fechamentoconvenio, Metavenda, Fechamentomensalgestao via `\Input`). Boleto::index:70
  reflete `$_GET` na URL paginada (risco XSS se a view não escapar).
- **Authorize ausente em relatórios/fluxos sensíveis:** Fechamentomalote
  (getPedidos/store/getParcelas) e **Fechamentomensalgestao (DRE/Balanço:
  getDashboard/export/getDetalhes/getCentroCustos)** **sem `authorize()`** — dependem só
  do middleware auth + AuthorizeCustom (que tem o **bypass de AJAX** do D11). ⇒ DRE/
  Balanço/malote acessíveis via AJAX sem permissão específica.

### 🟠 Bugs funcionais
- **Fechamentomensalgestao::sendMailApi:119 — `$data["sender_name"]`**: variável `$data`
  **nunca definida** nesse método → notice (cai no else por isset). **sendMail:91** tem
  `return responseSuccess();` **duplicado** (linha morta).
- **Contamovimentotipo::dadosExtras:59-60** — `$data['grupo_id'] = ...` **duplicado**.
- **ContaController::addEditExtratoconfig:776** — `DB::rollback()` no catch **sem
  `DB::beginTransaction()`** correspondente (rollback no-op/warning).
- **Fechamentoconvenio::processoFinanceiroFechamento** retorna `true`/string mas o
  chamador testa `if ($financeiro_id)` (contrato bool/string/id confuso).
- **catches frágeis/expostos:** Chequerecebido/emitido fazem `echo`/`return
  $e->getMessage().Line.File` (info leak de path/stack); `catch(ValidationException)` sem
  import em Metavenda/outros (morto).

### 🟡 Dívida estrutural
- **God controllers** (Conta store/update duplicam blocos `$request->only` enormes;
  Financeiro::getLancamentos é gigante) e **controller↔controller** (Fechamentomalote→
  Pedido+Caixa; Fechamentoconvenio→Nfemitida+Boleto; Chequerecebido→caixaProcessor).
- **HTML montado no backend** (Metavenda::store monta `<tr>`); **logo em base64**
  (Fechamentoconvenio/Fechamentomensal) em vez de bytea.
- **Excel via Maatwebsite 2.1 + PHPExcel legado** (Fechamentomensal) — stack EOL.
- **Cadastros sem transação** (Contamovimentotipo) e **destroy HTML `<br/>`** — padrão.
- **Algoritmo de código hierárquico** (planoContaAjax/centroCustoAjax) ilegível (muitos
  if/else aninhados) — funciona, mas frágil.

### ✅ O que está BOM (NÃO regredir)
- **ContaController** é dos mais maduros: Enum, Revisionable manual, transações,
  authorize+igualdade, validação de boleto. **Financeiro/Cheque/Boleto** delegam a regra
  a Processors (boa separação). Estorno bloqueia parcela baixada/cheque/boleto. Boleto::
  index já corrigido p/ PG (zera orders no agregado). DRE/Balanço via Repositories.
- Plano de contas/centro de custo: hierarquia por código + naturezaSPED + Revisionable.

## 3. Especificação do REFAT/REESCRITO (Laravel 12)
- **Núcleo financeiro → REFATORAR** os Processors em **Domain Services testáveis**:
  `LancamentoFinanceiro`, `BaixaParcela` (parcial/rateio), `EstornoFinanceiro`,
  `Cheque` (emitido/recebido + estados), `Boleto` (CNAB/ocorrências),
  `FechamentoConvenio`, `FechamentoMalote`, `DRE`, `Balanco`. Exceptions/Result tipado em
  vez de string `OK|`/`implode(erros)`. **Cobrir com testes** (é o dinheiro).
- **Consulta de parcelas (getLancamentos) → Query Service** com bindings (sem SQLi),
  paginação real, sem UNION cru interpolado.
- **Conta/Plano/Centro/Contamovimentotipo/Metavenda → REESCREVER UI** (recursos limpos,
  Policy, transação); plano/centro com serviço de hierarquia testável + detecção de uso
  por SQLSTATE (sem ROWNUM).
- **DRE/Balanço → migrar Excel** p/ PhpSpreadsheet/laravel-excel atual; manter as regras
  dos Repositories.

## 4. DECISÃO
- **Núcleo (Processors/cheque/boleto/convênio/malote/DRE) → REFATORAR** (Service+testes;
  baseline financeiro obrigatório).
- **Conta/Plano/Centro/Metavenda/Contamovimentotipo → REESCREVER** (UI + limpeza).
- **Quick wins aplicáveis JÁ (compat/segurança):**
  (a) **CONNECT BY → WITH RECURSIVE** em Fechamentomalote::getParcelasStore (quebrado no PG);
  (b) **ROWNUM/subquery-sem-alias** em Planoconta/Centrocusto isUsed\* (quebrado no PG);
  (c) **`$dada`→`$data`** no banco_id do Chequerecebido (cheque sem banco);
  (d) corrigir assinaturas typehint-após-opcional nos cheques;
  (e) parametrizar SQLi do getLancamentosFinanceiros;
  (f) **adicionar authorize** em Fechamentomalote e Fechamentomensalgestao (DRE/Balanço);
  (g) `$centrocusto2` em Centrocusto::update:169; remover `responseSuccess` duplicado e
     `$data` indefinido no sendMailApi; remover grupo_id duplicado no Contamovimentotipo.
- **Pré-requisitos:** baseline financeiro (Frente D) ANTES de tocar; alinhar com D01
  (pedido gera financeiro), D02 (NF gera financeiro), D07 (convênio/vale-gás), D06
  (custo médio).
- **Esforço:** ALTO (núcleo financeiro + DRE/Balanço). Cadastros baixo.
- **Ordem:** depois de D06; junto/antes de D01 (Pedido/Caixa dependem do financeiro
  sólido). Quick wins de compat (CONNECT BY/ROWNUM/$dada) podem ir já.
