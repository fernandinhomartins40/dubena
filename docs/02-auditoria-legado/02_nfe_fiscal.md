# PRD FIDEDIGNO (linha-a-linha) — NF-e / NFC-e / SAT / Tributação · D02

> Lidas 100% (núcleo) / caracterizadas (periféricos) das ~8.000 linhas do domínio:
> **lidos integralmente:** Nfemitida(2213), Nfrecebida(714), Impostonf(891 — matriz
> tributária, 1-540 + helpers), NfOperacao(243), Nficms(100), Nfgrupofiscal(69).
> **caracterizados (topo + arquitetura):** Nfweb(1763 — na verdade é API mobile, ver D13),
> CupomFiscal(1359 — SAT/CF-e via PHPCFe+Ratchet).
> **CRUD-padrão de tabela tributária (confirmado pelo Nficms):** Nfpis(106), Nfcofins(108),
> Nfipi(92), Nfcst(102), Nfclasstrib(118), Nfsituacao(103), ConfigNfcePedido(54).
> A regra fiscal pesada vive nos Processors: `Nfe\NfProcessor`, `Nfe\Tributacao\
> NfeImpostoProcessor`, `Nfe\Tools\SefazEvento` (NFePHP) — documentados pelos pontos de chamada.

- **Status:** ✅ pronto (fiel — núcleo lido, periféricos caracterizados)
- **Criticidade:** 🔴🔴🔴 (fiscal: emite documento legal à SEFAZ; erro = multa/autuação)
- **Decisão:** **REFATORAR** (NÃO reescrever) — é o código MAIS maduro do sistema

---

## 1. O que cada peça FAZ (verificado)
- **NfemitidaController (2213) — o controller MAIS bem arquitetado do ERP:** emite NF-e (55)
  e NFC-e (65) via **NFePHP**. Fluxos: tela (store/update), **NFC-e a partir do pedido**
  (`processorPedidoNf`), **NF-e a partir do pedido pelo app** (`processorPedidoAppnf`),
  **NF do fechamento de convênio** (`processorFechamentoConvenionf`), **NF complementar**
  (`getRequestComplementar`). SEFAZ: transmitir/consultar/DANFE/CCe/cancelar/inutilizar
  faixa/enviar email (via `SefazEvento`). **Numeração com `lockForUpdate`** (sem
  duplicidade), ambiente produção/homologação. Gera financeiro (NF + frete separados) e
  estorna. Cálculo de imposto delegado ao `NfeImpostoProcessor`.
- **NfrecebidaController (714):** entrada/lançamento de documento — **importa XML** de NF-e
  do fornecedor (NFePHP `Standardize`), casa emitente/transportador por CNPJ/CPF, gera
  financeiro (pagar), inutilização de faixa, calcula imposto. `tipolancamento==1` →
  **atualiza % de GLP do produto** (`Produto::updatePGLP`, regra fiscal do GLP).
- **ImpostonfController (891):** **matriz de tributação** por grupo fiscal × operação ×
  estado (PF/PJ): ICMS/ST/PIS/COFINS/FCP/desoneração/diferimento/cód. benefício/Simples
  Nacional/crédito PIS-COFINS. Whitelist via `getFillable()`. Alimenta o NfeImpostoProcessor.
- **NfOperacaoController (243):** **operação fiscal** (CFOP, movimenta estoque/financeiro,
  usa SAT, aparece em qual tela, mapeia produto→operação por app/convênio).
- **CupomFiscalController (1359):** **SAT / CF-e** (PHPCFe + WebSocket Ratchet p/ o
  equipamento SAT); gera financeiro.
- **Tabelas tributárias** (Nficms/Nfpis/Nfcofins/Nfipi/Nfcst/Nfclasstrib/Nfsituacao/
  Nfgrupofiscal): CRUD de referência. **ConfigNfcePedido:** mapeia operação×grupo fiscal
  p/ NFC-e por pedido.
- **NfwebController (1763):** apesar do nome, é **API mobile** (getToken/login/pedido/
  cliente do app) — **pertence ao D13**; documentado lá.

> Regra real a preservar: TODA a cadeia fiscal (cálculo de imposto, geração/assinatura/
> transmissão de XML, numeração sem duplicidade, DANFE, CCe, cancelamento, inutilização,
> import de XML, % GLP) é legalmente obrigatória. **É o ativo de maior risco regulatório.**

---

## 2. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA

### 🔴 QUEBRADO / debug em produção
- **NfemitidaController::getXmlByTxt:1629-1641 — `dd($e)` + `dump()` + `dd($e->getTrace
  AsString())` em PRODUÇÃO** (3×) no import de XML por TXT: qualquer erro **mata a
  requisição e expõe o stack trace**. Resíduo de debug. **Remover.**

### 🔴 Segurança (chave previsível)
- **NfwebController::getToken:87 — `Input::get('app_key') !== sha1(env('APP_KEY'))`**: a
  app_key é **`sha1(APP_KEY)`** (previsível/derivável). A Fase 1 corrigiu isso no
  monitora/api (APP_TOKEN_KEY), mas **AQUI no ctrl-web (Nfweb) permanece**. (É D13, mas
  anotado: mesma falha do `'secret'` HMAC do D11.) `encodeSecret` usado p/ oauth client.

### 🟠 Bugs funcionais
- **Nfemitida::getRequestComplementar:257 — `$nfRef->emitnomefantasi`** (typo, falta o "a"):
  na NF complementar o nome fantasia do emitente fica **null** (campo `emitnomefantasia`).
- **Nfrecebida::loadXmlImport:698-699 — `format("d/m/Y h:i")`** usa **`h` (12h)** em vez de
  `H` (24h) → hora de emissão da NF importada pode ficar errada (am/pm).
- **Nfgrupofiscal::store:28/update:43 — unique do `descricao` aponta `grupo_id` mas passa
  `empresa_padrao->id`** (id da empresa no lugar do grupo_id) → valida unicidade no escopo
  errado (mesmo bug do Tiporecessos/D08).
- **`whereRaw` com grupo_id da sessão** em Nfemitida::getEmpresaUser:1605 (risco menor).

### 🟡 Dívida estrutural
- **God controller + duplicação massiva:** os 4 fluxos de geração de NF (tela/pedido-NFCe/
  pedido-app-NFe/convênio) repetem ~70 linhas de montagem de `$data[...]` quase idênticas
  → extrair um **NfBuilder/DTO** (alto ganho, baixo risco semântico).
- **Controller↔controller:** Nfemitida ↔ Pedido/Fechamentoconvenio; Nfweb ↔ Nfemitida/
  Cliente; CupomFiscal usa financeiroProcessor. Forte acoplamento.
- **Tabelas tributárias sem transação** no store/update (Nficms/Nfgrupofiscal/...);
  **Nficms::all()** sem escopo; **NfOperacao store/update sem transação** (grava operação +
  N produtos). **destroy retornando HTML `<br/>`** em todos — padrão do projeto.
- **Stack fiscal EOL acoplada:** NFePHP/PHPCFe/Ratchet/laravelcollective Form — upgrade do
  framework (6→8/12) depende de compatibilizar essas libs (já mapeado no projeto).
- `DB::begintransaction()` minúsculo (Impostonf/NfOperacao) — inconsistente.

### ✅ O que está BOM (NÃO regredir — é a referência do projeto)
- **Nfemitida/Nfrecebida/Impostonf** são os controllers **mais maduros**: FormRequests
  (NfRequest/NfImpostoRequest/NfOperacaoRequest), authorize granular (view/create/update/
  **especial**/igualdade), transações, **lockForUpdate na numeração**, Revisionable
  (revisionItems/generateRateioLog), Processors isolando a regra fiscal, NFePHP/PHPCFe
  (libs oficiais), import/validação de XML, whitelist `getFillable()`. **Sem SQLi** no
  núcleo (Eloquent + `e()` no Input de filtro). Compatibilidade PG já tratada.

## 3. Especificação do REFATORADO (Laravel 12)
- **NÃO reescrever do zero** — a regra fiscal é cara, validada com a SEFAZ e correta.
- **Refatorar Nfemitida/Nfrecebida** extraindo um **NfBuilder** (DTO único que monta o
  `$data` fiscal a partir de Pedido/Convênio/Tela/App — elimina a duplicação 4×) +
  **Actions** (EmitirNF, TransmitirNF, CancelarNF, CCe, Inutilizar, ImportarXML). Manter
  NFePHP/PHPCFe (libs oficiais) — upgrade controlado junto do framework.
- **Tabelas tributárias + NfOperacao + ConfigNfcePedido → REESCREVER UI** (recursos limpos,
  transação, escopo por empresa/grupo correto).
- **Impostonf** → manter a matriz; modernizar UI (form gigante → wizard por imposto);
  serviço de tributação testável (já existe NfeImpostoProcessor — robustecer + testes).
- **SAT/CF-e** → isolar a comunicação WebSocket (Ratchet) num serviço.

## 4. DECISÃO
- **Decisão: REFATORAR** (é o módulo mais maduro — preservar a regra, modernizar estrutura/UI).
- **Quick wins aplicáveis JÁ:**
  (a) **remover `dd()`/`dump()` do getXmlByTxt** (dump-and-die + leak em produção);
  (b) **`emitnomefantasi`→`emitnomefantasia`** (NF complementar sem nome fantasia);
  (c) **`h`→`H`** no loadXmlImport do Nfrecebida (hora de emissão);
  (d) corrigir `grupo_id` no unique do Nfgrupofiscal;
  (e) (D13) trocar `sha1(APP_KEY)` por APP_TOKEN_KEY no getToken do Nfweb.
- **Pré-requisitos (BLOQUEANTES):** baseline fiscal (caracterização com NFs reais
  homologadas) ANTES de qualquer refactor; compatibilizar NFePHP/PHPCFe com o framework
  alvo; alinhar com D01 (pedido→NF), D04 (NF→financeiro), D06 (NF→estoque), D03 (SPED).
- **Esforço:** ALTO (núcleo fiscal) — mas com NfBuilder o refactor é incremental e seguro.
- **Ordem:** depois de estoque/financeiro sólidos; junto/antes de D03 (SPED lê as NFs).
