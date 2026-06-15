# PRD FIDEDIGNO (linha-a-linha) — Produtos / Estoque · D06

> Lidas 100% das linhas dos controllers + processor do domínio (≈4.300 linhas):
> Produto(840), EstoqueProcessor(535 — Processor), Estoquerequisicao(454),
> EstoqueTransferencias(437 · ~175 efetivas + XLS comentado), Testeestoque(343),
> Estoquefisico(269), Estoquesetor(211), Inventario(205), Estoquesetoracerto(170),
> Produtoclasse(138), Tipocombustivel(128), Unidademedida(127).

- **Status:** ✅ pronto (fiel)
- **Criticidade:** 🔴 (estoque vira NF-e/SPED/financeiro; Produto é fortemente fiscal)
- **Decisão:** **REFATORAR** núcleo de estoque (motor) · **REESCREVER** cadastros e telas

---

## 1. O que cada peça FAZ (verificado)
- **ProdutoController (840):** CRUD do produto, **fortemente fiscal** (NCM, CEST, IPI,
  gênero, LST, origem, **origem GLP**, grupo fiscal). Maior parte são arrays estáticos
  de referência (`getGeneros`/`getLst`/origens). Regras reais: **soma % GLP = 100 ou 0**
  (189-192), **soma origens = 100%** (212), **não inativa produto com estoque** (146-152).
  Usa **ProdutoRequest** (FormRequest), **transações**, authorize+igualdade. Limpo (sem
  `$_GET`/whereRaw interpolado). `join('produtoclasses as class', ...)` (60,301).
- **EstoqueProcessor (535) — MOTOR de estoque (service):** movimentação ENTRADA/SAIDA por
  setor×produto, **saldo**, **custo médio** (`updateCustoMedio`), validação de estoque
  negativo (`empresaconfig->permiteestoquenegativo`), **fechamento/reabertura** por
  período/setor, estoque físico → efetivação. Trata erro por **array `addError`/`getErrors`**
  (não exceptions). Usa DB::beginTransaction extensivamente. É código real, não gambiarra.
- **Estoquerequisicao (454):** requisição/baixa de estoque (saída) por plano de conta/
  centro de custo; PDF; `destroy` = cancelamento que **estorna** (ENTRADA reversa). XLS
  comentado (195-349).
- **EstoqueTransferencias (437):** transfere produto entre setores (SAIDA origem +
  ENTRADA destino via Processor); PDF. ~226-401 são XLS comentado (morto). edit/update/
  destroy vazios.
- **Estoquefisico (269):** inventário físico por setor → ao efetivar, gera histórico de
  acerto e movimenta o Processor; monta `<input>` HTML no controller.
- **Estoquesetor (211):** consulta saldo por setor/produto; **fecharEstoque/abrirEstoque**
  (chama Processor); helpers de consulta de quantidade c/ regra de negativar.
- **Inventario (205):** inventário p/ **SPED** (produtos nfepermite, valor/quantidade);
  CRUD com itens.
- **Estoquesetoracerto (170):** acerto pontual de saldo (nova qtde × antiga → diferença
  vira ENTRADA/SAIDA no Processor).
- **Produtoclasse (138):** classe do produto (B/G/V/R/O — GLP/Vasilha/Brinde/Ressarc/Outros).
- **Tipocombustivel (128) / Unidademedida (127):** cadastros simples por grupo.
- **Testeestoque (343):** ⚠️ **controller de TESTE/DEBUG** (ver bug 🔴 abaixo).

> Regra real a preservar: o **motor de estoque** (saldo, custo médio, estoque negativo,
> fechamento/reabertura, efetivação do físico, estorno no cancelamento) é a fonte do
> estoque que alimenta NF-e/SPED/financeiro. **Não pode regredir.**

---

## 2. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA

### 🔴 QUEBRADO / PERIGOSO em produção
- **TesteestoqueController (todo)** — controller de DEBUG exposto: `index`/`store`
  **SEM authorize**, movimenta o motor de estoque com dados **hardcoded**
  (setor_id=1, produto_id=1, entidade='Nfrecebida', entidade_id=`rand(1,100)`, qtdes
  10/10/2), `fecharEstoque`/`abrirEstoque` em data fixa **2017-02-23**, e `uml()` gera
  diagrama do framework via Graphviz. Qualquer usuário autenticado pode **corromper o
  estoque real**. **Remover o controller + auditar/remover a rota.**
- **InventarioController::store:70 e destroy:164 — `catch (Exception $ex)` usando `$e`**:
  a variável capturada é `$ex` mas o corpo usa `$e->getMessage()`/`$e->getLine()` →
  no erro real lança **"Undefined variable $e"** (fatal). Gravar/excluir inventário
  quebra em qualquer falha. **Corrigir o nome da variável.**
- **EstoqueProcessor::efetivarEstoquefisico:497** — `$estoquesetorhistorico->empresa_id =
  $estoquefisico->id;` — atribui o **id** do estoque físico ao campo **empresa_id**
  (deveria ser `$estoquefisico->empresa_id`). Bug real de dados na efetivação do físico.

### 🟠 Bugs funcionais
- **InventarioController::store:73 / update:148** — `if(!isset($inserido) && !$inserido)`:
  condição **invertida**/frágil (acessa var possivelmente indefinida) — mesmo padrão do
  EmpresaconfigController:419 (D10).
- **InventarioController::getErrors:32** — `implode($this->errors, ', ')` com **ordem de
  argumentos legada** (deprecada no PHP 7.4, **removida no 8**) → warning/erro futuro.
- **EstoquesetoracertoController:132-137** — `catch(ValidationException)` colocado **depois**
  de `catch(\Exception)` → o segundo é **inalcançável** (genérico captura primeiro). Além
  disso `ValidationException` sem import.
- **EstoquesetorController:115 / EstoqueTransferencias:101** — `catch(ValidationException)`
  sem import → catch morto.
- **EstoqueProcessor::processaHistoricosPrimeiroFechamento:335** — `if ($estoquesetor
  historico->produto_id == 104) $x = $x + 1;` — **código de debug esquecido** (produto_id
  104 hardcoded, `$x` nunca usado). Inócuo, mas sujo. **Remover.**

### 🔴 Segurança (input não parametrizado)
- **EstoquefisicoController::buscaEstoqueSetor:98** — `whereRaw("datahoracompetencia >=
  to_date('$datacompetencia', ...)")` interpola `$datacompetencia` (vem da rota/AJAX) →
  **SQLi**. **Parametrizar.**
- **EstoqueProcessor::isSetorestoqueFechadoData:162** — `whereRaw` com `TO_DATE`
  interpolando `$datahoracompetencia` (dado interno; risco menor, mas deveria ser binding).
- **`$_GET` lido direto:** EstoquesetorController:71 (`filterColumns`), EstoquesetoracertoController:50,
  EstoqueTransferencias:31 (filtros de data, depois passam por insertDataOracle).

### 🟡 Dívida estrutural
- **HTML montado no backend:** EstoquefisicoController:114,134 monta `<input type=checkbox>`
  como dado retornado (mesma família do menu/D11). EstoqueProcessor/setor retornam
  strings HTML `<br/>` cruas em rotas AJAX (UX/contrato ruim).
- **Contratos de retorno inconsistentes:** vários `destroy`/store de estoque retornam
  **string** (`"OK|"`, `implode(erros)`, ou `echo "<br/>..."`) em vez de redirect/JSON.
  Ex.: Estoquerequisicao::destroy:443 faz **`echo`** direto no controller.
- **Blocos XLS gigantes comentados** (EstoqueTransferencias 226-401; Estoquerequisicao
  195-349) — lixo a remover.
- **Cadastros pequenos sem transação** no store/update (Produtoclasse, Tipocombustivel,
  Unidademedida) — padrão do projeto.
- **`->insert()` em massa** (Inventario::criarItems) bypassa eventos/casts.
- **Comentários `//dd(...)`** soltos (Estoquerequisicao:26, Estoquesetoracerto:60,
  Tipocombustivel:102) — lixo inócuo.
- **Algoritmo de fechamento (EstoqueProcessor::processaHistoricos)** usa varredura
  ordenada com last_setor/last_produto — funciona, mas frágil (depende de ordenação).

### ✅ O que está BOM (não regredir)
- **ProdutoController**: ProdutoRequest + transações + authorize/igualdade; regras
  fiscais reais (soma GLP, soma origens, não inativa com estoque); **limpo de SQLi**.
- **EstoqueProcessor**: motor coeso com transações, custo médio, validação de negativo,
  fechamento/reabertura — **lógica de negócio valiosa a preservar**.
- **Estorno no cancelamento** (Estoquerequisicao::destroy) e **acerto** (setoracerto) —
  regras corretas.
- Quase todos os controllers têm authorize `view/create/update/delete` + `igualdade`
  (exceção grave: **Testeestoque**).

## 3. Especificação do REFAT/REESCRITO (Laravel 12) — baseada no código real
- **Motor de estoque → REFATORAR como Service/Domain** testável: `MovimentoEstoque`,
  `CustoMedio`, `FechamentoEstoque`, `EstoqueFisico` — preservando exatamente as regras
  (saldo, custo médio, estoque negativo por config, fechamento/reabertura, estorno).
  Trocar array de erros por exceptions/Result tipado. **Cobrir com testes** (é 🔴).
- **Produto → REESCREVER UI** (abas: dados / fiscal NCM-CEST-IPI / origens-GLP / preços);
  manter schema fiscal e as regras de soma (GLP/origens) e no-inativa-com-estoque.
  Mover arrays de referência (gêneros/LST) para config/enum.
- **Telas transacionais (requisição/transferência/físico/acerto/inventário) →
  REESCREVER** como recursos limpos (FormRequest/Resource/Policy), retornos JSON
  consistentes (sem HTML no back), bindings (sem whereRaw interpolado), **com transação**.
- **Cadastros (Produtoclasse/Tipocombustivel/Unidademedida) → REESCREVER** em lote
  (vitrine do padrão novo, com transação).
- **Limpeza:** **deletar TesteestoqueController** + rota; remover blocos XLS comentados;
  remover debug `produto_id==104`; remover `//dd`.

## 4. DECISÃO
- **Motor de estoque → REFATORAR** (Service + testes; efeito fiscal/financeiro — só
  migrar com baseline e paridade comprovada).
- **Produto + telas transacionais + cadastros → REESCREVER** (UI moderna; baixo risco
  nos cadastros, médio nas telas transacionais).
- **Quick wins de segurança/compat aplicáveis JÁ (não dependem da reescrita):**
  (a) **deletar/blindar TesteestoqueController** (corrompe estoque real) — prioridade;
  (b) corrigir `$ex`→`$e` em Inventario::store/destroy (gravar/excluir quebrado);
  (c) corrigir `empresa_id` em EstoqueProcessor::efetivarEstoquefisico:497;
  (d) parametrizar `$datacompetencia` em Estoquefisico::buscaEstoqueSetor (SQLi);
  (e) `implode` com ordem correta em Inventario::getErrors (PHP 8);
  (f) reordenar/importar catches de ValidationException (setoracerto/setor/transferências);
  (g) remover debug `produto_id==104` no Processor.
- **Pré-requisitos:** D11 (navegação nova); **baseline do motor de estoque** antes de
  refatorar; alinhar com D01 (vendas baixam estoque), D02 (NF-e), D03 (SPED/Inventario),
  D04 (custo médio → financeiro).
- **Esforço:** motor = médio-alto (testes); Produto = médio; telas = médio; cadastros = baixo.
- **Ordem:** cadastros cedo; Produto junto/depois de D05; motor de estoque com baseline
  antes de D01/D02/D03.
