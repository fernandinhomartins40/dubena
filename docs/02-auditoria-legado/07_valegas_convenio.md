# PRD FIDEDIGNO (linha-a-linha) — Vale-Gás / Convênio / Condição de Pagamento · D07

> Lidas 100% das linhas do domínio (≈1.700 linhas):
> Valegasvenda(626), Condicaopagamento(335), Conveniogbgestao(287),
> Valegascancelar(54), Valegasbaixar(45), Valegasconsulta(35).
> (Fechamentoconvenio(781) lido e documentado no D04 — agrupa pedidos do conveniado
> em financeiro; pertence aos dois domínios.)

- **Status:** ✅ pronto (fiel)
- **Criticidade:** 🔴 (vira financeiro; Condicaopagamento é config central de TODO o ERP)
- **Decisão:** **REFATORAR** Valegasvenda/Condicaopagamento (efeito financeiro) ·
  **REESCREVER** vale-gás baixar/cancelar/consulta + dashboard convênio

---

## 1. O que cada peça FAZ (verificado)
- **Condicaopagamento (335):** **config central** lida por Pedido/Financeiro/Caixa/
  Cheque/Boleto/Convênio/Vale-gás. 6 tipos: 0=à vista, 1=à prazo (N parcelas com %),
  2=cartão à vista (taxa), 3=cartão à prazo (gera 1 condição por nº de parcelas na faixa
  min-max), 4=convênio, 5=vale-gás. Define `nfc_tpag` (tipo pagamento NF-e), envio
  automático de NF pelo app (`enviaappnf`/`appnfceauto`), contamovimentotipo. Usa
  CondicaoPagamentoRequest, authorize+igualdade, transações.
- **Valegasvenda (626):** núcleo do **vale-gás** — vende (gera N códigos `Valegas` via
  `random_int`), pré-venda → impressão de etiquetas (PDF 10×3) → fechamento → **financeiro**
  (parcelas via `calculoParcelas` + rateio no cc/pc do vale-gás). Cancelamento estorna o
  financeiro. Máquina de situações (Pré-Venda/Vendido/Impresso/Impresso Pré-Venda/Baixado/
  Cancelado). Duplicata PDF. Delega ao financeiroProcessor.
- **Valegasbaixar (45) / Valegascancelar (54):** baixa/cancela vale-gás por código.
- **Valegasconsulta (35):** consulta vale-gás por código/situação/cliente.
- **Conveniogbgestao (287):** dashboard gerencial **convênio + vale-gás** (gráficos
  meta×realizado por produto/mês/cliente; tabelas por conveniado).

> Regra real a preservar: vale-gás gera financeiro (parcelas/rateio) e tem máquina de
> situações própria; Condicaopagamento define COMO todo lançamento vira parcela
> (à vista/prazo/cartão/convênio/vale-gás) — mexer errado quebra o ecossistema inteiro.

---

## 2. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA

### 🔴 Bug real (geração de código)
- **Valegasvenda::gerarCodigo:470-478 — recursão sem retorno + coluna errada:**
  `if ($this->checkCodigo($numero)) $this->gerarCodigo();` gera novo número mas **não
  retorna nem reatribui** → em colisão devolve o `$numero` **duplicado**. E
  `checkCodigo:482` verifica `prevendasequencia`, mas o código gerado é salvo em `codigo`
  → **checa a coluna ERRADA** → colisão de código de vale-gás possível (vale duplicado).

### 🔴 Segurança (SQLi)
- **Conveniogbgestao::getDataTabelaConvenio:197 / getDataTabelaValegas:274** —
  `UPPER(produtos.descricao) like UPPER('%".$produto."%')` interpola **`$produto`**
  (`\Input::get("produto")`) → **SQL injection** no dashboard. `$diainicial`/`$diafinal`
  (derivados de `$mes` do Input) em `to_date('$diainicial',...)`. **Parametrizar.**
- **Condicaopagamento::validateTpag:326** — whereRaw com grupo_id da sessão (risco menor).
- **`$_GET`/`\Input` direto:** Valegasconsulta (codigo/situacao/cliente — via binding ok),
  Conveniogbgestao (produto/mes/tipo). Valegasvenda usa `request()->get`.

### 🟠 Bugs funcionais
- **Conveniogbgestao::getDataChartConvenioClientes:138 — `empresas.id = 2` HARDCODED:**
  o gráfico de clientes do convênio filtra a **empresa 2 fixa** em vez da empresa logada
  (`Session::get('empresa_padrao')->id`) → dados errados p/ qualquer empresa ≠ 2.
- **Valegasvenda::update():131 VAZIO** com email pessoal no comentário
  (`encape02@hotmail.com`) — edição de venda de vale-gás não implementada + lixo.
- **Valegasvenda::salvarPreVenda:330 — `Valegas::max('prevendasequencia')` sem filtro de
  empresa/grupo** → sequência de pré-venda **global** entre empresas (problema multi-tenant).
- **Valegasbaixar::store SEM authorize** (e index) — baixa vale-gás sem checagem de
  permissão nem igualdade (Valegascancelar tem authorize+igualdade — inconsistente).
- **Valegasbaixar/cancelar** — `foreach ($valegas as $item) $valegas_id = $item['id'];`
  para pegar o id do último (deveria ser `->first()`) — gambiarra.
- **Condicaopagamento::store:81/update:275** — `$percentualvalor !== null` sempre true
  (já checado `!== ''`) → ramo `(100 - $percetualValorTotal)` é morto; fechamento de %
  de parcela frágil.
- **Conveniogbgestao: getDashboard/getConvenioValegasMes/index SEM authorize** —
  relatório gerencial sensível exposto (depende do bypass AJAX do D11).

### 🟡 Dívida estrutural
- **Conveniogbgestao = SQL gigante em string** (`DB::select` com Session/`$produto`/`$mes`
  interpolados); hardcodes `tipo_glp IN (3,4,5) AND PESOLIQUIDO IN (13,20,45)` (P13/20/45)
  embutidos. CONNECT BY **já traduzido** (generate_series/WITH RECURSIVE/window) — só falta
  parametrizar o input.
- **Valegasvenda** monta etiquetas (loop 10×3) e usa `DB::begintransaction()` minúsculo
  (inconsistente). HTML/loops de apresentação no controller.
- `destroy` HTML `<br/>` (Condicaopagamento); catches com `$e->getLine()` exposto.

### ✅ O que está BOM (NÃO regredir)
- **Condicaopagamento** é maduro: 6 tipos modelados, FormRequest, transações, authorize+
  igualdade — config central correta. **Valegasvenda** delega financeiro ao Processor,
  valida cc/pc na config, estorna no cancelamento, authorize+igualdade+transações.
  Conveniogbgestao com SQL já portado p/ PG (só inseguro no input).

## 3. Especificação do REFAT/REESCRITO (Laravel 12)
- **Condicaopagamento → REFATORAR**: modelar os 6 tipos como value objects/strategy
  (cálculo de parcelas testável); manter `nfc_tpag`/envio-app; é config central → baseline.
- **Valegasvenda → REFATORAR** como Service de Vale-Gás (geração de código única e
  correta, máquina de situações, financeiro, etiquetas como view/job); efeito financeiro.
- **Valegasbaixar/cancelar/consulta → REESCREVER** unificados (1 recurso com ações +
  authorize/igualdade em todas).
- **Conveniogbgestao → REESCREVER** como Query Services parametrizados (bindings; sem
  `empresas.id=2` hardcoded; empresa/produto/mês como parâmetro seguro); hardcodes de GLP
  em config.

## 4. DECISÃO
- **Condicaopagamento + Valegasvenda → REFATORAR** (efeito financeiro; baseline).
- **Vale-gás baixar/cancelar/consulta + Conveniogbgestao → REESCREVER.**
- **Quick wins aplicáveis JÁ:**
  (a) corrigir **gerarCodigo** do vale-gás (recursão sem retorno + coluna `codigo` vs
     `prevendasequencia`) — risco de vale duplicado;
  (b) parametrizar `$produto`/`$mes` do Conveniogbgestao (SQLi);
  (c) trocar **`empresas.id = 2` hardcoded** por empresa da sessão;
  (d) authorize no Valegasbaixar (e nos dashboards do Conveniogbgestao/malote);
  (e) `prevendasequencia` por empresa/grupo (não global).
- **Pré-requisitos:** D04 (financeiro) e Condicaopagamento estáveis; baseline; alinhar
  com D01 (vale-gás no pedido), D05 (convênio do cliente), Fechamentoconvenio (D04).
- **Esforço:** Condicaopagamento médio (config central); Valegasvenda médio; demais baixo.
- **Ordem:** junto com D04 (compartilham financeiro/convênio); Condicaopagamento cedo
  (config base de Pedido/Financeiro).
