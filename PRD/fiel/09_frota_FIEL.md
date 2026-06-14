# PRD FIDEDIGNO (linha-a-linha) — Frota / Veículos · D09

> Lidas 100% das 1.139 linhas dos 7 controllers: Veiculo(313),
> Veiculoentradasaida(247), Veiculotrocaoleo(190), Veiculoabastecimento(149),
> Veiculotipo(144), Veiculopneu(110), Veiculodocumento(86).

- **Status:** ✅ pronto (fiel)
- **Criticidade:** 🟡 (apoio operacional; não fiscal)
- **Decisão:** **REESCREVER** (CRUDs; baixo risco) — mas com correções de compat antes

---

## 1. O que cada controller FAZ (verificado)
- **VeiculoController (313):** CRUD do veículo (placa, tipo, combustível, km, pneus,
  troca de óleo, alertas). Vincula **setores** (sync many-to-many) e grava
  **documentos do veículo** (Veiculodocumento, com vencimento/alerta) no próprio
  store/update. `buscarVeiculoAjax` p/ telas de manutenção.
- **Veiculoabastecimento (149):** registra abastecimento (km anterior/atual, litros,
  média de consumo); se `telacontrolakm`, **atualiza km/motorista do veículo**
  (`updateOthers`); destroy faz **rollback do km** (subtrai km rodado).
- **Veiculotrocaoleo (190):** registra troca de óleo; `updateVeiculo` atualiza
  `kmultimatrocaoleo`/motorista; destroy faz `rollbackOleo` (volta ao penúltimo
  registro). `getTrocas` (AJAX) busca últimos dados do veículo.
- **Veiculoentradasaida (247):** entrada/saída do veículo vinculando **pedidos**
  (entregas) carregados; atualiza km; destroy faz rollback do km. AJAX p/ buscar
  pedidos por período/setor (`ajaxBuscaPedidoSetor`) e dados do veículo.
- **Veiculopneu (110):** registra troca de pneu (km, valor, medida, vida útil, alerta).
- **Veiculotipo (144):** CRUD do tipo de veículo (por grupo).
- **Veiculodocumento (86):** **100% VAZIO** (scaffold morto).

> Regra de negócio real: abastecimento/óleo/pneu/entrada-saída **mantêm o estado de
> km do veículo** e os históricos com alertas de manutenção. O destroy precisa
> estornar o km — lógica que precisa ser preservada.

---

## 2. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA

### 🔴 Compatibilidade Postgres (QUEBRADO em runtime)
- **Veiculotrocaoleo::getTrocas (123-132)** — `where rownum <= 1` (Oracle). **rownum
  não existe no Postgres** → query quebra. É AJAX (disparado ao selecionar veículo na
  tela de troca de óleo) → não pego pela varredura de index. **Corrigir: order by ...
  limit 1.**
- **Veiculotrocaoleo::rollbackOleo (161-164)** — `select * from ($query) where rnk = 1
  limit 1`: a subquery no FROM **sem alias** → Postgres "subquery in FROM must have an
  alias". Quebra ao deletar troca de óleo. **Corrigir: dar alias à subquery.**

### 🔴 Segurança / catch quebrado
- **`catch (Exception $ex)` SEM `use Exception`** em:
  Veiculoabastecimento:64, Veiculopneu:57, Veiculoentradasaida::salvarPedidos:149.
  No namespace `App\Http\Controllers` referencia classe inexistente → se uma exceção
  for lançada no try, o catch dá **fatal "class not found"** em vez de tratar. (Veiculo
  e Veiculotrocaoleo importam corretamente.) **Corrigir: usar `\Exception` ou importar.**
- **Veiculopneu:59 usa `Redirect` sem import** — mesmo método do catch quebrado.
- **Veiculotrocaoleo::getTrocas:121 e Veiculoentradasaida::ajaxBuscaPedidoSetor:185**
  lêem **`$_GET` direto**; getTrocas interpola `$veiculo_id`/`$empresa_id` no SQL
  (vêm de GET/sessão) → **SQLi** no `$_GET["veiculo"]`.

### 🟠 Bugs funcionais
- **VeiculoController:124,263** — `catch (ValidationException $e)` sem import: classe
  inexistente; só funciona porque o segundo catch (`\Exception`) cobre. Primeiro catch
  é morto.
- **Veiculoentradasaida::salvarPedidos** retorna um `Redirect` de método privado que o
  chamador (store:71-79) trata como booleano → fluxo de erro confuso/potencialmente
  quebrado.
- **VeiculodocumentoController 100% vazio** — scaffold morto (deletar; documentos do
  veículo são gravados pelo VeiculoController).
- **Veiculotipo:15-21** — array `$tipos` hardcoded (Carro/Caminhão...) exibido no index
  mas o cadastro usa `descricao` livre → dado mock possivelmente sem uso real.

### 🟡 Dívida estrutural
- Cálculo de consumo/km e estorno espalhados nos controllers (deveriam ser Service).
- `explode('||')` p/ lista de setores (VeiculoController usa flag $first — OK;
  diferente do bug count-1 do Setor/D10).
- `to_char(...)` para formatar data no index (já Postgres-OK).

### ✅ O que está BOM
- CRUDs com DB::transaction + autorização `view/create/update/delete` + `igualdade`
  por dono em todos.
- Veiculo: sync de setores + documentos com transação; validação de placa única por
  empresa.
- Estorno de km no destroy (abastecimento/óleo/entrada-saída) — regra correta.

## 3. Especificação do REESCRITO (Laravel 12)
- Recurso `Veiculo` + sub-recursos (abastecimento, óleo, pneu, entrada/saída,
  documento) com FormRequest/Resource/Policy.
- **Service de Manutenção/Km**: centraliza cálculo de consumo + atualização e estorno
  de km (hoje duplicado em 3 controllers) — testável.
- **Alertas de manutenção/documento por vencimento** como feature (job + notificação)
  — os dados (alerta/kmalertaantes/vencimento) já existem.
- UI: ficha do veículo com timeline de manutenção + gráfico de consumo.
- Preservar vínculo `veiculoerp_id` ↔ módulo Monitora (D14) e o vínculo
  entrada/saída ↔ Pedido (D01).
- Eliminar VeiculodocumentoController vazio.

## 4. DECISÃO
- **Decisão: REESCREVER** (baixo risco, não fiscal, alto ganho de UX).
- **Quick wins de compat/segurança aplicáveis JÁ (não dependem da reescrita):**
  (a) `rownum<=1`→`order by ... limit 1` em getTrocas;
  (b) alias na subquery de rollbackOleo;
  (c) `\Exception`/import nos catches quebrados (abastecimento/pneu/entradasaida);
  (d) parametrizar `$_GET["veiculo"]` em getTrocas (SQLi);
  (e) deletar VeiculodocumentoController vazio.
- **Pré-requisitos:** D11 (navegação nova); definir Service de Km/Manutenção.
- **Esforço:** baixo-médio.
- **Ordem:** após cadastros de apoio do D10; antes dos transacionais pesados.
