# PRD FIDEDIGNO (linha-a-linha) — Clientes / CRM · D05

> Lidas 100% das 2.948 linhas dos 10 controllers: Cliente(1318), Maladireta(274),
> Posvendacadastro(263), Posvenda(208), Tipopessoa(127), Segmento(126),
> Clientecontatotipo(130), Clientecontatosituacao(129), Clientecontato(87 vazio),
> Clienteproduto(15).

- **Status:** ✅ pronto (fiel)
- **Criticidade:** 🟠 (cadastro central; convênio/limite têm efeito financeiro)
- **Decisão:** **REESCREVER** (faseado) — com correções de compat/segurança antes

---

## 1. O que cada controller FAZ (verificado)
- **Cliente (1318):** CRUD de cliente PF/PJ + 7 agregados (telefones, contatos/
  interações CRM, parentesco/dependentes de convênio, produtos com preço/desconto,
  promoções, condições de pagamento, produtos de convênio). **Convênio** com
  representante legal, limite, comissão, dia de fechamento/vencimento. Gera
  **contrato** e **etiquetas** (PDF). Usa **Revisionable** (auditoria de mudanças).
  Suporta API e edição via modal de pedido (`fromPedido`).
- **Maladireta (274):** filtra clientes (aniversariantes / por compra / que não
  compram) por setor/cidade/bairro/rua; exporta CSV; envia email; gera etiquetas.
- **Posvendacadastro (263):** builder de formulário de pós-venda (perguntas/respostas).
- **Posvenda (208):** preenche pesquisa de pós-venda por pedido; filtra pedidos.
- **Tipopessoa/Segmento/Clientecontatotipo/situacao:** CRUD de apoio (por grupo).
- **Clientecontato (87):** 100% VAZIO (interações gravadas pelo ClienteController).
- **Clienteproduto (15):** só `buscaPrecos`.

> Regra real a preservar: convênio (limite/representante/dependentes/comissão) tem
> efeito financeiro; **Revisionable** audita mudanças (importante p/ CRM/convênio).

---

## 2. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA

### 🔴 QUEBRADO em produção
- **PosvendaController::store:108 — `DB::rdollback();`** (typo de "rollback"): método
  inexistente → no erro do store, em vez de rollback, lança fatal "Method rdollback
  does not exist". Pesquisa de pós-venda quebra em qualquer falha.
- **ROWNUM (Oracle) em queries não-index** — quebram no Postgres:
  - `Maladireta::searchClientes:151` — `ROWNUM <= 2` dentro de string_agg.
  - `Posvenda::filtroPedididos:165` e `create:54` — `rownum <= 2` / whereRaw.
  Escaparam da tradução da Fase Postgres (estavam em DB::raw de telas AJAX/filtro).

### 🔴 Segurança (SQLi)
- **ClienteController** whereRaw interpolando input:
  - `verificaEndereco:1116-1118` — cidade_id/bairro_id/rua_id/**numero/complemento**
    do request interpolados → SQLi.
  - `index:66`, `buscaClienteEndereco:1046,1052` — nome/complemento (sanitização
    parcial `str_encode_to_query`).
- **Posvenda::filtroPedididos:139** — `whereRaw("grupo_id = $this->grupo_id and
  entregafinalizada = 1 or fechadoconcluido = 1 or ...")`: interpola + **precedência
  AND/OR sem parênteses** → traz situações de OUTROS grupos (bug lógico + risco).
- **Maladireta::etiquetas:222 / deleteTempFileCSV** — `$_GET` direto; `unlink` recebe
  filename do usuário (path traversal potencial).

### 🟠 Bugs funcionais
- **Cliente::updateCampoCliente:1092** — `if(isset($cliente->cliente))` quase sempre
  true; `Cliente::find($id)` sem checar null (erro antes do try). Lógica frágil.
- **Cliente::ativaCliente:1232** — sem authorize, sem null-check.
- **Cliente::keepRevisionsArr:1285** — concatena " 00:00:00" em qualquer campo cujo
  nome contenha "data" (frágil p/ datafechamento etc.).
- **Maladireta::searchClientes:104-126** — $anoInicio/$mesInicio podem ficar
  indefinidas em certos caminhos (notice/erro).
- **Posvendacadastro::verificarPeriodo:231 / Posvenda** — 5 condições de sobreposição
  com 2 idênticas (copy-paste; mesmo padrão do D08).
- **Posvenda usa Session('link') + $_SERVER['REQUEST_URI']** p/ guardar URL de retorno
  (frágil).

### 🟡 Dívida estrutural
- **ClienteController = God controller (1318):** store/update orquestram 7 agregados
  via `insertUpdateOthers`, controlado por flags JSON `alltables` do front (added/
  removed) — forte acoplamento view↔controller.
- **Maladireta::csv:175** — fopen no diretório de trabalho com nome = empresa_id.csv
  (colisão concorrente).
- `$_GET` direto em Maladireta/Posvenda/Posvendacadastro.
- **Clientecontato 100% vazio** (6º scaffold morto do projeto).

### ✅ O que está BOM
- ClienteController usa **ClienteRequest** (FormRequest), **transações**, e
  **Revisionable** (auditoria) — maduro p/ o tamanho. A Fase 1 corrigiu o SQLi do
  `insertUpdateCondicoesPgto:697` (bindings + intval). Autorização view/create/update/
  delete + igualdade. Cadastros de apoio limpos.

## 3. Especificação do REESCRITO (Laravel 12)
- **Cliente** → quebrar em recurso + sub-recursos (Endereço, Telefone, Contato,
  Produto, Convênio, Dependente, Promoção, CondPgto), cada um FormRequest/Resource/
  Policy. **Service de Convênio** (limite/representante/dependentes/comissão) testável.
  Manter auditoria (Laravel Auditing). UI ficha 360º.
- **Maladireta/Posvenda** → Query Services parametrizados (sem rownum/$_GET/whereRaw
  interpolado); export CSV via serviço seguro (storage, nome único).
- **Cadastros de apoio** → recursos limpos.
- **Limpeza:** deletar Clientecontato vazio.

## 4. DECISÃO
- **Decisão: REESCREVER (faseado)** — cadastro base + endereços/telefones/contatos
  primeiro; convênio/comissão com baseline (efeito financeiro).
- **Quick wins aplicáveis JÁ:**
  (a) **`DB::rdollback`→`DB::rollBack`** (Posvenda store quebrado);
  (b) **rownum→limit** em Maladireta:151 / Posvenda:165,54 (quebrados no PG);
  (c) parametrizar whereRaw de verificaEndereco (SQLi) + parênteses no whereRaw de
     filtroPedididos:139 (bug lógico de grupo);
  (d) deletar Clientecontato vazio.
- **Pré-requisitos:** D11; baseline p/ convênio; mapear uso de cliente por
  Pedido/NF-e/Financeiro antes de mexer no schema.
- **Esforço:** médio-alto (Cliente é grande); cadastros de apoio baixo.
- **Ordem:** cadastros de apoio cedo; Cliente core depois de D06; convênio junto/
  depois de D04.
