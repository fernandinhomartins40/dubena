# Inventário Completo do Backend Legado (leitura linha-a-linha)

> Base factual para o plano de reescrita 100% (backend novo greenfield + frontend completo).
> Cada item é extraído da LEITURA do código-fonte real, não de documentação.
> Status de leitura por área no fim do arquivo.

## Dimensão real (medida)

| Área | Arquivos | Linhas |
|---|---:|---:|
| app/Http/Controllers | 164 | 56.183 |
| app/Processors (motores) | 144 | 24.260 |
| app/Api (mobile/Passport) | 82 | 7.912 |
| app/Monitora (GPS) | 44 | 4.461 |
| app/Repository | 7 | 3.851 |
| app/Services | 9 | 1.931 |
| app/Helpers | 9 | 4.906 |
| app/Jobs | 3 | 166 |
| app/Console | 17 | 1.906 |
| app/Events / Listeners | 3 | 157 |
| app/Enums / Casts | 7 | 280 |
| Models (raiz app/) | 203 | — |
| **TOTAL app/** | — | **~139.791** |
| Migrations | 624 | — |
| Rotas web | — | 578 |
| Rotas api_admin | — | 192 |
| Rotas api_mobile | — | 70 |
| Rotas monitora | — | 37 |

---

## PADRÕES TRANSVERSAIS (consolidado dos controllers lidos — confirmados em 12/164, ~14.300 linhas)

Estes padrões se repetem em TODOS os controllers grandes lidos e definem o que impede o frontend novo e o que a reescrita precisa resolver:

1. **Valores como string pt-BR, não número.** Toda entrada/saída monetária e percentual passa por `insertNumeroDecimalOracle`/`requestNumeroDecimalOracle`/`insertPercentualOracle`/`requestBaseCalcNfOracle` — grava/lê `"R$ 0,00"` / `"12,50 %"`. O dado no banco é numérico (decimal), mas a borda fala string. **Raiz do "frontend novo não consome".**
2. **Arrays posicionais como contrato.** Itens (produto, parcela, telefone, contato, origem, rateio) trafegam como `$x[0..N]` com índices mágicos documentados só em comentário. Frontend moderno manda objeto nomeado.
3. **Retorno misto não-JSON.** Telas devolvem `view()`/`Redirect`; AJAX devolve strings de protocolo (`"OK|id"`, `"NOK"`, `"senha"`, `"NOT"`, `"valegas"+json`) ou `json_encode(["status"=>...])` (contrato próprio, não `response()->json`). Sem contrato uniforme.
4. **Acoplamento controller→controller.** Controllers instanciam outros controllers diretamente (Pedido↔Nfemitida↔Search↔Api↔Cliente↔Financeiro↔Rua). Regra de negócio não está em services.
5. **Dependência de Session/Auth global.** `Session::get('empresa_padrao')` (id/grupo_id) e `\Auth::user()` em quase todo método — estado implícito de tenant.
6. **SQL cru com interpolação (SQLi) + Oracle vivo.** `whereRaw`/`DB::select` com `$var` concatenada; `TO_CHAR`/`TO_DATE`/`NLS_NUMERIC_CHARACTERS` Oracle ainda presentes; árvores `CONNECT BY` parcialmente convertidas p/ `WITH RECURSIVE`.
7. **Regra de negócio no controller.** Cálculo de rateio/parcela/imposto/saldo inline (não em service) — especialmente perigoso em Caixa/Pedido/NF.
8. **Listas/constantes hardcoded** (gêneros TIPI, LST, tipoGlp, modalidades) embutidas em métodos.
9. **Conexões múltiplas:** pgsql (default), sgcm_api (pagamento online), monitora (GPS), api (DB_DRIVER_API), oracle (morta no config).
10. **Geração de PDF/HTML no controller** (dompdf, Mpdf posicional, `\Form::select`, botões HTML inline).

**Implicação para o plano de reescrita:** a tradução string↔número e o contrato JSON nomeado devem viver no ETL (migração) + Resources/DTOs do backend novo; a regra de negócio sai dos controllers para Services; Session→contexto explícito por request; SQL parametrizado e 100% Postgres.

---

## CONTROLLERS — leitura linha-a-linha

<!-- Preenchido por bloco. Formato por controller:
### NomeController (N linhas)
- **Domínio:** …
- **Métodos:** index/create/store/… (o que cada um faz)
- **Tabelas tocadas:** …
- **Motor/Service invocado:** …
- **Integração externa:** …
- **Retorno:** View | Redirect | JSON
- **Helpers *Oracle / formatação:** sim/não
- **Risco/observações p/ reescrita:** …
- **Equivalente já existente em ApiAdmin:** sim/não
-->

---

### NfemitidaController (2.213 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** Emissão de NF-e (modelo 55) e NFC-e (modelo 65). É o controller mais crítico/complexo do sistema fiscal.
- **Métodos públicos:** `index` (lista NF por empresa/data, view `nf.nfemitida`), `store` (grava NF normal e complementar), `create`/`edit`/`show` (→`form`, view `nf.nfe_form`), `update`, `processorPedidoNf` (gera NFC-e a partir de Pedido, JSON), `processorPedidoAppnf` (gera NF-e modelo 55 do app, JSON), `processorFechamentoConvenionf` (gera NF de fechamento de convênio), `transmitirnf`, `consultarnf`, `consultarnfAppNF`, `inutilizarFaixaNFs`, `cancelarNF`, `cartaCorrecaoNF` (CCe), `enviarEmailNF`, `estornoFinanceiroEstoque`, `exportarXmls`, `importTxt`/`getXmlByTxt` (importa NF via TXT→XML).
- **Tabelas tocadas:** nfemitidas, nfemitidaitems, nfemitidavolumes, nfemitidaparcelas, nfemitidarateios, pedidos, pedidoitems, clientes, produtos, empresas, empresaconfigs, nfoperacoes, nfoperacaoprodutos, nfoperacaoprodutoconvenios, nfceconfigpedidos, nfsituacaos, planocontas, centrocustos, condicaopagamentos, conveniofechamentos/pedidos, financeiros.
- **Motor/Service invocado:** `NfProcessor` (gerar XML, financeiro, estorno, rateio), `NfeImpostoProcessor` (cálculo de ICMS/PIS/COFINS/IPI/ST), `SefazEvento` (transmitir/consultar/cancelar/CCe/inutilizar/DANFE/email), `SelectRepository`, `NfUtil`.
- **Integração externa:** SEFAZ (NFePHP — transmissão, consulta, cancelamento, carta de correção, inutilização, DANFE), e-mail.
- **Retorno:** MISTO — `view()`/`Redirect::to()` nas telas (index/store/update/form); `response()->json()` nos métodos chamados por app/AJAX (processorPedidoNf, processorPedidoAppnf, enviarEmailNF); string crua em transmitir/consultar/cancelar.
- **Helpers *Oracle / formatação:** SIM, pesadíssimo — `requestNumeroDecimalOracle`, `requestDataOracle`, strings `"R$ 0,00"` literais como valores; arrays de produto posicionais `$produto[0..23]` codificados via `encodeAssociativeArray`.
- **Numeração:** `trataNumNF` usa `Empresa::lockForUpdate()` e incrementa nfenumero/nfcenumero/homologacao na própria empresa (ponto de concorrência crítico).
- **Acoplamento:** `Session::get('empresa_padrao')`, `Session::get('empresa_config')`, `Auth::user()`, `Input::get` (facade legada), `\Request::fullUrl`.
- **Risco/observações p/ reescrita:** ALTÍSSIMO. Mistura apresentação + cálculo fiscal + geração de financeiro + estoque + integração SEFAZ num só arquivo. Gera/estorna financeiro inline (`gerarFinanceiro`/`estornarFinanceiro`). Produtos trafegam como array posicional de 24 posições (contrato implícito, frágil). No backend novo: separar em FiscalService (emissão) + ImpostoService + integração SEFAZ isolada + DTO de produto nomeado; financeiro vem de FinanceiroService.
- **Equivalente já existente em ApiAdmin:** FiscalController (parcial, só leitura/listagem).

### SearchController (2.108 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** Agregador central de buscas/autocomplete (selectize.js) + endpoints AJAX de apoio a Pedido/NF/Caixa/Mapa de entregas. ~60 métodos.
- **Métodos (grupos):**
  - Autocomplete cliente: indexcliente, indexclientetodasempresas, indexclienteoutrasempresas, indexclientecompleto, indexclientes, indexfornecedores, clientespf, clientespj, fornecedorespj, searchClientePedido, searchClientePedidoConvenio, searchClienteNF, searchClientesComodato, searchFornecedoresEmpresaUser, searchClientesEmpresaUser, clienteReportNf.
  - Outros autocompletes: searchBanco, indexcolaboradores, searchRua, searchEmpresaNF, getSetor, getUserByEmpresa, getEmpresasByRegional, getEmpresasByGrupo.
  - Caixa/movimentos: indexmovimentocontaatual + getMovimentosDB/validateMovtosCaixa/getExtraValuesMovtos/getSelectSearchMovtos (lista movimentos do caixa, paginação manual, gera HTML de botão Estornar inline, view `financeiro.partials.iframe_table`), checkCaixaIsFechado, contasByEmpresa, getContaById.
  - Financeiro: searchContasReceber, searchContasPagar (monta as contas a receber/pagar do cliente, exclui cheques/encontro de contas), searchParcelasByIds (delega a FinanceiroController::formatParcelasToView), getTipoPgtoParcela.
  - Pedido: searchDadosPedidoEmpresa, searchClienteNF, buscaClienteTelefone, buscaClienteById, clienteToPedidos, buscaClienteByIdToPedidos, historicoCliente, checaItens, checaReparcelamento (RECURSIVO até 20 níveis — reparcelamento), checaConvenioEPromocao (lógica de convênio+promoção+limite de compra do cliente), getInfoClienteByPedido.
  - Mapa de entregas: searchPedidosMapaEntregas, searchPedidosPendentesMapaEntregas, searchPedidosMapaEntregasCoordenadas, searchPedidosMapaEntregasAtrasadas, searchPedidosTempoEntregas/getPedidosTempoEntregas (chart de faixas de tempo de entrega).
  - Fiscal/imposto: nfimpostoByGfOperacao, getNfBySerieNum, calcularImpostoProduto (→NfeImpostoProcessor), issetImpostoProdutoAjax, getPcCcById.
  - Banco/boleto: getLayoutBanco, getCodMovRemessaByBanco, getCodMovRemessaByConta. Telefonia: searchTelefonesMonitoramento (monitoramento de chamadas).
- **Tabelas:** clientes, tipopessoas, ruas, bairros, cidades, colaboradors, setors, setorcolaboradores, estoquesetors, produtos, empresas, empresaconfigs, contas, contafechamentos, contamovimentos, contatransferencias, contamovimentotipos, financeiros, financeiroparcelas, condicaopagamentos, cliente_condicaopagamento, boletos, chequerecebidos/chequerecebidofinanceiros, chequeemitidofinanceiros, chequeemitidoencontrocontas, chequerecebidoencontrocontas, ocorrenciasremessas, layoutbancos, nfemitidas, nfimpostos, pedidos, pedidoitems, pedidosituacaos, pedidomotivoatrasos, monitoramentochamadas, clientetelefones, menuusers, empresa_user, promocoes/clientepromocao.
- **Motor/Service:** NfeImpostoProcessor, FinanceiroController (instanciado direto — acoplamento controller→controller).
- **Retorno:** MISTO — JSON na maioria; view `financeiro.partials.iframe_table` em movimentos; arrays/strings cruas ("OK|","NOK","OPS","404").
- **Helpers *Oracle:** SIM — requestNumeroDecimalOracle, requestDataOracle, insertNumeroDecimalOracle.
- **⚠️ ORACLE SQL VIVO (CORRIGE AUDITORIA ANTERIOR):** múltiplos `whereRaw/selectRaw/DB::select` ainda usam **sintaxe Oracle não-portável**:
  - `TO_CHAR(campo, 'DD/MM/YYYY HH24:MI')` e máscara numérica Oracle `'9G999...D09', 'NLS_NUMERIC_CHARACTERS=...'` (getSelectSearchMovtos L434-439) — **NLS_NUMERIC_CHARACTERS é exclusivo Oracle, quebra no PostgreSQL**.
  - `TO_DATE(...,'yyyy-mm-dd hh24:mi:ss')` (checkCaixaIsFechado L1270).
  - Aritmética de datas estilo Oracle `(entregadatahora - datahoraenvioentregador) * 24 * 60` (L1047,1145,1190) — no PG isso é interval, não número.
  - Concatenação `||` (portável no PG, ok). Alguns trechos JÁ foram convertidos p/ `WITH RECURSIVE` (getCentroByPai L1830) e `DISTINCT ON` (buscaClienteByIdToPedidos L1460) — conversão parcial Oracle→PG.
  - `DB::raw("alter session set nls_comp=...")` (searchRua L594-595) — comando Oracle (provavelmente no-op/lixo no PG; `DB::raw` sem execução).
- **⚠️ SEGURANÇA — SQL Injection:** interpolação direta de `$query`/`$_GET`/`Session->id` em whereRaw em DEZENAS de métodos (ex.: indexcliente L81, searchPedidos* via $_GET). `$query` passa por `str_encode_to_query`/`e()` mas vários `$_GET[...]` e ids entram crus.
- **Risco/observações p/ reescrita:** ALTO (volume + SQL Oracle vivo + SQLi + lógica de negócio escondida em "search": reparcelamento recursivo, convênio/promoção/limite). No backend novo: quebrar por domínio (ClienteSearch, CaixaQuery, PedidoQuery, EntregaMapa, FiscalQuery), queries parametrizadas, mover regra de convênio/promoção/reparcelamento para Services próprios. NÃO instanciar controller dentro de controller.
- **Equivalente em ApiAdmin:** LookupController (parcial — autocompletes simples).

### NfwebController (1.773 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** Backend do APP MOBILE de vendas externas ("NF-web"/app do colaborador/entregador). Faz login por telefone, salva pedido, emite NF/NFCe/boleto a partir do app, consulta pedidos/notas, cadastra cliente pelo app.
- **Métodos:** getToken (gera OAuth client+token Passport via hash_equals da APP_TOKEN_KEY), login (por telefone do colaborador), changeRegistrationId (push), nfwebRootRequest/onOpenNfwebApp (payload inicial: colaborador+veículos+operações+produtos+pagamentos), getParcelasVencidasCliente, changeVeiculo, **savePedido** (fluxo gigante: cria pedido via MobileRepository → emite NFCe/NF via PedidoController+NfemitidaController → transmite SEFAZ → gera boleto via BoletoProcessor), enviarEmail (DANFE+boleto por e-mail), pedidoConsulta, nfeConsulta (consulta NF + parseia XML de retorno por strpos/explode), pedidoDuplicata, visualizarDanfe, visualizarBoleto, pedidosReport, getNotifications/readNotification/indicateCompany, createClient/sendHttpRequest (OAuth interno), testToken/testTokenERP/testTokenAPI, rootRequest (DESATIVADO — `return responseError` na 1ª linha), marker (gera PNG de marcador via Intervention), destroy (apaga OAuth client), getCadastros (clientes+segmentos+tipopessoas+estados+cidades+bairros+ruas+telefonetipos do setor), saveCliente (cadastra cliente pelo app via ClienteController), saveClienteObs, getCliente, baixarDanfe.
- **Tabelas:** colaboradortelefones, colaboradors, veiculos, empresas, empresaconfigs, nfoperacoes, produtos, condicaopagamentos, condicaopagamentoparcelas, pedidos, pedidoitems, financeiros, financeiroparcelas, clientes, clientetelefones, clienteprodutos, nfemitidas, nfemitidaitems, boletos, contas, oauthclients, segmentos, tipopessoas, estados, cidades, bairros, ruas, telefonetipos.
- **Motor/Service:** MobileAppProcessor (checkForConvenio), MobileRepository (createOrder), BoletoProcessor (gerarBoleto), e instancia DIRETO outros CONTROLLERS: AuthController, PedidoController, NfemitidaController, ClienteController (acoplamento controller→controller pesado).
- **Integração externa:** SEFAZ (via NfemitidaController), boleto bancário (BoletoProcessor), e-mail (sendMail), Passport OAuth (createClient + oauth/token via Guzzle HTTP **chamando a própria API localhost** — SECRET_URL), push notification.
- **Retorno:** JSON via helpers `responseSuccess/responseError/responseReject` (contrato próprio do app).
- **Helpers *Oracle:** requestNumeroDecimalOracle; e **TO_CHAR Oracle vivo** em várias queries (getParcelasVencidasCliente L294, pedidoConsulta L758, pedidosReport L1171, getCliente L1702) — `TO_CHAR(campo,'DD/MM/YYYY...')`. Concatenação `||` e `string_agg`/`coalesce`/`DISTINCT`/`limit` (já PG).
- **⚠️ Segurança/débito:**
  - **Login de sistema hardcoded via env** `DEFAULT_USER_SYSTEM`/`DEFAULT_PASSWORD_SYSTEM` — todo endpoint do app faz `loginFromApi` com usuário-mestre do sistema (bypassa identidade real do colaborador para escrever no banco).
  - **OAuth client criado a cada getToken** (cresce tabela oauthclients indefinidamente; createClient sem limpeza).
  - Parsing de XML de NFe por `strpos`/`explode` (frágil) em nfeConsulta.
  - `if ($colaborador_id == 108) $colaborador_id = 431;` — **regra hardcoded de remapeamento de colaborador** (pedidosReport L1146; comentada em pedidoConsulta L739).
  - Chamadas `sleep(5)` em loop dentro de request HTTP (nfeConsulta/enviarEmail) — bloqueia worker.
- **Risco/observações p/ reescrita:** ALTO. É a API do app externo — contrato com app instalado em campo (não pode quebrar sem coordenar release do app). No backend novo: virar API mobile versionada (auth real do colaborador via token, não usuário-mestre), Services em vez de controller→controller, emissão de NF assíncrona (fila) em vez de sleep, parsing de XML por lib.
- **Equivalente em ApiAdmin:** nenhum (é app externo, não SPA admin). Relacionado a `app/Api/` (outra API mobile).

### PedidoController (1.661 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** Núcleo de VENDAS/Pedidos. Orquestra estoque + financeiro + vale-gás + NF-e + convênio + promoção + pagamento online. Usado pela tela de pedidos, pela tela de monitoramento, e pela API/app.
- **Métodos:** index/getPedidosMonitoramento (views), create/edit/show/form (view `pedido.pedido_form`), **store** (cria pedido: status→produtos→estoque→financeiro→venda ativa→taxa gás do povo; `$isFromApi` muda retorno JSON×string), **update**, updateStatus (+ Pedidosituacaohistorico), updateGeneral, insertProdFromStoreUpdate, insertFinanceiroStoreUpdate, deleteFinanceiro, rollbackPedidoItensPrecoOriginal, rollbackValegas, dadosExtras (chama SearchController), insertProdutos (array posicional `$produto[0..3]`, `insertNumeroDecimalOracle`), gerarFinanceiro (→PedidoUtil), editFromMonitoramento (fluxo de edição na tela de monitoramento, valida atraso/cartão/vale-gás), updateVariosStatus (loop de edição em massa, transação por item), sendPedidoPendente (→ApiController), dadosExtrasMonitoramento, **validateMovFinanceiro** (estorna/insere financeiro conforme mudança de status/condição/setor; integra pagamento online), **validateMovEstoque** (SAIDA/ENTRADA conforme transição fechado-concluído + troca de setor), updateValeGasPedido, movimentarEstoque (monta Estoquesetorhistorico[] → EstoqueProcessor), justificaMotivoAtraso, validaCartao (anti-duplicidade de cartão por dias), nfceGerou, checaStatusPedido, validaGasBolso, isFechado, buscaPorId (gera `<select>` HTML via Form facade!), historicoToCliente, geraEmite/createNF/transmitirNF (→NfemitidaController), updateDadosNf, comanda (JSON p/ impressão térmica), insertFinanceiroEntregaGasdoPovo/gerarFinanceiroEntregaGasdoPovo/gerarFinanceiroParcelasEntregaGasdoPovo (financeiro da taxa de entrega "Gás do Povo" — programa social; monta financeiro+rateio+parcelas direto via financeiroProcessor).
- **Tabelas:** pedidos, pedidoitems, pedidosituacaos, pedidosituacaohistoricos, pedidomotivoatrasos, clientes, clienteprodutos, condicaopagamentos, condicaopagamentoparcelas, financeiros, financeiroparcelas, financeirorateios, setors, empresas, empresaconfigs, valegas, valegassituacaos, valegasvendas, vendaativaclientes, estoquesetorhistoricos, produtos, **transacoesonline (conexão sgcm_api)**.
- **Motor/Service:** EstoqueProcessor (movimentarEstoque), financeiroProcessor (gravar — gás do povo), MobileAppProcessor (estornarPagamentoOnline, checkForConvenio), PedidoUtil (gerarFinanceiro, dadosExtras, regras de situação), PedidoRepository, e instancia DIRETO: SearchController, NfemitidaController, ApiController (acoplamento controller→controller).
- **⚠️ SEGUNDA CONEXÃO DE BANCO (NOVO ACHADO):** `DB::connection("sgcm_api")->table('transacoesonline')` (validateMovFinanceiro L810-817) — banco SEPARADO de pagamentos online (gateway). Estorno de pagamento via `MobileAppProcessor::estornarPagamentoOnline($tid, $valor)` (integração de cartão online).
- **Retorno:** MISTO — views (index/create/form); strings de protocolo ("OK|id","NOT","senha","valegas"+json,"motivoatraso","NOCHANGE"); JSON (buscaPorId, comanda); internalResponseSuccess/Error quando $isApi.
- **Helpers *Oracle:** insertNumeroDecimalOracle, requestNumeroDecimalOracle, requestDataOracle. SQL Oracle: `SELECT ... FROM PEDIDOSITUACAOS` interpolado (isFechado L1216, com grupo_id da Session).
- **⚠️ Débitos:** gera HTML (`\Form::select`) dentro de método de dados (buscaPorId L1238); regra de negócio densa e implícita (matriz status×condição×setor decide INSERE/EXCLUI financeiro e SAIDA/ENTRADA estoque); `$produto[0..3]` array posicional; financeiro montado campo-a-campo inline (gás do povo) duplicando lógica do financeiroProcessor; transação aninhada em loop (updateVariosStatus).
- **Risco/observações p/ reescrita:** ALTÍSSIMO — é o coração transacional de vendas, toca os 3 motores. No backend novo: PedidoService orquestrando EstoqueService + FinanceiroService + FiscalService + PagamentoOnlineService; máquina de estados de situação explícita (substituir a matriz implícita); DTO de item nomeado; conexão sgcm_api isolada num gateway de pagamento.
- **Equivalente em ApiAdmin:** PedidoController (ApiAdmin) — parcial.

### CupomFiscalController (1.359 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** SAT/CF-e (Cupom Fiscal Eletrônico — SP). Emite/cancela CF-e via equipamento SAT, comunicando-se por **WebSocket** com um agente local; monta XML do CF-e (lib PHPCFe), calcula impostos (ICMS/PIS/COFINS + Lei 12.741/IBPT) e gera financeiro+parcelas+rateio.
- **Métodos:** index/filter/fillDataIndex (lista, gera HTML de botões inline via Form facade), getButtonActionIndex, store/update/updateOrCreate (cria CF-e + financeiro + monta XML), createItems/setItem/setItemValues/setIcmsItem/setPisItem/setCofinsItem (cálculo fiscal item-a-item), getProdDb/getNfImposto/getNfOperacao/getProdLeiImpostoByNcm, itemsJsonToStd (array posicional `$item[0..15]`), mountXml (PHPCFe Make/Tags), gerarFinanceiro (→financeiroProcessor), setParcelasCFe, gerarRateio, validateCustom (converte ~20 campos via insertNumeroDecimalOracle de "R$ 0,00"→float), create/edit/show/form (view `satcfe.*`), changeParamForm (saída: requestNumeroDecimalOracle em ~18 campos), **transmitir/cancelar/connectWS** (Ratchet WebSocket client → agente SAT local), test (Guzzle localhost phpcfe), getDoc/getDocCancel (base64 XML; getDocCancel tem **XML hardcoded de exemplo / TODO não implementado**), getEmpresa/getUserInfo (endpoints consumidos pelo agente SAT — auth por username/senha base64).
- **Tabelas:** cuponsfiscais (cf), cupomfiscalitems, cupomfiscalparcelas, clientes, produtos, produtoleiimpostos, nfimpostos (+ relações nficms/nfpis/nfcofins/nfimpostoestado), nfoperacoes, planocontas, centrocustos, condicaopagamentos, financeiros, financeiroparcelas, financeirorateios, configuracoesgerais, empresas, empresa_user, empresas_grupos.
- **Motor/Service:** financeiroProcessor, ImpostoDB (Processors\Nfe\Tributacao), NfUtil, SelectRepository.
- **Integração externa:** **WebSocket (Ratchet/React)** com agente SAT local (`WEBSOCKET_ADDRESS`); equipamento SAT; lib PHPCFe; Guzzle p/ phpcfe localhost.
- **Retorno:** MISTO — view (index/form), Redirect (store/update), JSON ({data,message,response_status} — contrato do agente SAT), void (transmitir/cancelar — resposta vai pelo WS via echo!).
- **Helpers *Oracle:** PESADO — insertNumeroDecimalOracle (~20×), requestNumeroDecimalOracle (~18×), requestDataOracleSemHora. Valores monetários como "R$ 0,00".
- **⚠️ Débitos:** `echo` dentro de callback de WebSocket (não retorna pela response HTTP); getDocCancel não implementado (XML fixo); HTML gerado no controller (botões); array posicional de item; `$this->throwIf` referenciado mas a definição local está COMENTADA (depende de método herdado do Controller base); auth por senha base64.
- **Risco/observações p/ reescrita:** ALTO (fiscal + integração WS hardware). No backend novo: SatService isolado, comunicação com agente por protocolo versionado (não echo), cálculo fiscal compartilhado com NF-e (mesmo ImpostoService), DTO de item nomeado, XML por lib. SAT é regional (SP) — pode ser fora de escopo conforme uso real.
- **Equivalente em ApiAdmin:** nenhum.

### ClienteController (1.318 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** CRUD RICO de Cliente (também fornecedor/transportador). Cliente + telefones + contatos/interações + convênio + dependentes (parentesco) + produtos com preço/desconto + produtos de convênio + promoções + condições de pagamento. Edição inline pela tela de pedidos (modal). Tudo versionado (Revisionable).
- **Métodos:** index (busca por nome/cod, whereRaw), create/createFromPedidos (view `clientes.cliente_form` ou `pedido.partials.modal_cliente`), store/update (`$returnClient`/`$isApi`/`$fromPedido` mudam retorno), show/edit/editFromPedidos/form, dadosExtras (insertDataOracle datanascimento, normaliza flags bool, separa CPF×CNPJ por tipopessoa, cria rua se "N", **buscaLatitudeLongitude** geocoding, emptyToNull), insertUpdateOthers (orquestra 7 sub-tabelas via diff `allTablesView.added/removed`), insertUpdateProdConvenio/Promocoes/CondicoesPgto/Convenio/Telefones/Contato/Parentesco/Produtos (cada um com array posicional documentado `$x[0..n]` e keepRevisions), selectTipoPessoas, contrato (PDF dompdf contrato de convênio), buscaClienteTipoPessoa/buscaPorId/buscaClienteEndereco/buscaClienteNome (ajax), updateCampoCliente, verificaEndereco (anti-duplicidade de endereço, `$fromApi`), definitions (grupo/empresa/cep da Session), imprimirEtiquetasConvenio (PDF etiquetas), fechamentoConvenio, ativaCliente, keepRevisions/keepRevisionsArr (revisão manual via venturecraft/revisionable), createRua (→RuaController), getDescontoPara.
- **Tabelas:** clientes, clientetelefones, clientecontatos, clienteconvenios, clienteconveniodependentes, clienteprodutos, clienteprodutosconvenios, clientepromocoes, cliente_condicaopagamento, tipopessoas, cidades, ruas, bairros, empresas, empresa_user, comodatos, revisions.
- **Motor/Service:** ClienteRepository, MobileRepository, e instancia DIRETO: PedidoController (historicoToCliente), RuaController (createRua). dompdf p/ PDFs.
- **Integração externa:** Geocoding `buscaLatitudeLongitude` (provável Google Maps via helper) na gravação de cliente.
- **Retorno:** MISTO — views, Redirect, PDF stream, strings ("OK|","NOT","ERR","ERRO!..."), internalResponseSuccess/Error quando $isApi.
- **Helpers *Oracle:** insertDataOracle, requestDataOracle, insertPercentualOracle, requestPercentualOracle, insertNumeroDecimalOracle, requestNumeroDecimalOracle. Arrays posicionais em TODAS as sub-tabelas (telefone[0..5], contato[0..7], produto[0..7] etc).
- **⚠️ Segurança/débito:**
  - **SQLi**: verificaEndereco (L1116-1118) e buscaClienteEndereco (L1046) concatenam `$data[...]`/`Input` direto em whereRaw. (insertUpdateCondicoesPgto JÁ foi corrigido p/ binding — "FASE 1 S1/SQLi" L690).
  - Diff de sub-tabelas controlado por `allTablesView` (JSON vindo do front com `.added/.removed`) — confia no cliente p/ decidir o que gravar/apagar.
  - Acoplamento controller→controller (Pedido, Rua).
- **Risco/observações p/ reescrita:** ALTO (entidade central, muitas sub-relações, regra de desconto por tipo/“descontopara”, convênio com representante legal). No backend novo: ClienteService + sub-services por relação; payload JSON aninhado (`telefones:[{...}]`) substituindo arrays posicionais; geocoding assíncrono; auditoria via lib/observers (não keepRevisions manual); queries parametrizadas.
- **Equivalente em ApiAdmin:** ClienteController + ClienteSubController (ApiAdmin) — provavelmente parcial.

### ReportCaixaController (1.273 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** Relatórios financeiros gerenciais — Fluxo de Caixa, Despesas/Receitas por Plano de Contas, Despesas/Receitas por Centro de Custo (com sub-relatórios de lançamentos). Saída em tela e PDF (dompdf landscape).
- **Métodos:** fluxoCaixa/fluxoCaixaPDF/getFiltersFluxoCaixa/getContaMovimentos/getMovimentosSemFechamentos/montarTableFluxo/putObjectContasFluxo/setFiltersReportFluxo (fluxo de caixa: saldo inicial via último fechamento + movimentos sem fechamento, saldo corrente acumulado por movimento), despesasIndex/despesasFiltro/despesaSub + receitasIndex/receitasFiltro/receitasSub (por plano de contas), despesasCentro/centroFiltro/subCentroCusto + receitasCentro/centroFiltroReceitas/subCentroCustoReceitas (por centro de custo), buscaPlanos/montarTablePlanoContas/getJurosMultasDesc/insertjuros (montagem hierárquica do relatório de plano de contas com juros/multas/descontos), buscaCentros/getJurosMultasDescCC/insertJurosCC (idem centro de custo), getLancamentos (sub-relatório de lançamentos por fornecedor/cliente), getQueryPlanoContas/getQueryJurosMulta/getCentroCustoQuery/getQueryJurosDescontosCC (4 query builders SQL gigantes).
- **Tabelas:** contamovimentos, contas, contafechamentos, contausers, financeiros, financeiroparcelas, financeirorateios, clientes, planocontas (árvore paiplanoconta_id), centrocustos (árvore paicentrocusto_id), empresas, empresaconfigs, empresas_grupos, regioes.
- **Motor/Service:** nenhum — lógica de relatório toda no controller. dompdf.
- **Retorno:** view + PDF stream.
- **Helpers *Oracle:** insertDataOracle, requestDataOracle.
- **⚠️ ORACLE/SQL:** as 4 árvores hierárquicas (plano de contas / centro de custo) foram CONVERTIDAS de `START WITH/CONNECT BY` Oracle para `WITH RECURSIVE` PostgreSQL (comentários documentam cada conversão). MAS ainda há **`TO_CHAR`/`TO_DATE` Oracle vivo** (getContaMovimentos L114/139, getFiltersFluxoCaixa L67) e datas/ids **interpolados** em SQL cru (`$this->datainicio`, `$empresa`, `$parametro`, `$tipo`) — SQLi + dependência de função Oracle.
- **Risco/observações p/ reescrita:** ALTO por VOLUME de SQL e montagem hierárquica manual (não por transação). Lógica de juros/multas/descontos espalhada por planoconta/centrocusto config da empresa. No backend novo: relatórios como Query Services dedicados, queries parametrizadas, árvore via WITH RECURSIVE PG nativo (já meio-caminho), TO_CHAR→formatação na aplicação. Candidato a permanecer legado por mais tempo (relatório, baixo risco transacional) OU virar relatório novo só-leitura.
- **Equivalente em ApiAdmin:** FinanceiroGestaoController (ApiAdmin) — parcial.

### CaixaController (1.056 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** CORAÇÃO FINANCEIRO TRANSACIONAL. Abertura/fechamento/transferência de caixa; baixa (quitação) de títulos a pagar/receber (total, parcial com reparcelamento, encontro de contas); cancelamento; estorno (caixa, recebimento, taxa de cartão); recibos.
- **Métodos:** index (lista contas do usuário, whereRaw), abrirCaixa (→caixaProcessor->abrirCaixa), abrirTelaCaixa/abrirTelaCaixaFechado/filterContaFechamento (views), fecharCaixa (→caixaProcessor->fecharCaixa, detecta reabertura), transferirCaixa (→caixaProcessor->validarTransferenciaCaixa/transferirCaixa entre contas origem/destino, trata caixa fechado nos dois lados), baixarduplicatasbycaixa/baixartitulosbycaixa/cancelartitulosbycaixa (montam a view de baixa, somam juros/multa/desconto/líquido, encontro de contas), **baixar** (~280 linhas — o método mais crítico: baixa PARCIAL com reparcelamento (cálculo de rateio de desconto/juros/multa por parcela via percentuais) OU baixa total montando Contamovimento[] com rateio, depois caixaProcessor->validarBaixaTitulos/baixarTitulos; `$withTransaction` permite reuso aninhado), baixarParcelasEncontroContas, cancelar (movimento origem 'CAN'), gerarRecibo/gerarReciboCR (dados p/ recibo), estornarLancamentoCaixa (estorno com detecção de cheque/transferência/taxa), estornarLancamentoFromCartao/estornarLancamentoCR (estorno de recebimento, agrupa movimentos por conta), validaEstornoTaxa (estorna taxa de cartão vinculada), validateDate (limite de 2min p/ data futura).
- **Tabelas:** contas, contausers, contamovimentos, contafechamentos, contatransferencias, contamovimentotipos, financeiros, financeiroparcelas, financeirorateios, empresas; relações de cheque (chequeEmitido/RecebidoEncontroContas, cheque*Financeiro), financeiroTaxa.
- **Motor/Service:** **caixaProcessor** (abrir/fechar/transferir/validarBaixaTitulos/baixarTitulos/validarEstornoCaixa/EstornarCaixa/reparcelar — via financeiroProcessor), **financeiroProcessor** (reparcelar na baixa parcial), e instancia FinanceiroController (cancelar — controller→controller).
- **Retorno:** MISTO — views (telas de caixa), JSON ("OK|...","OPEN...","OPEN"+id), strings de erro cruas, arrays (recibo).
- **Helpers *Oracle:** insertNumeroDecimalOracle (parse de "R$ x" → float em TODOS os valores), requestNumeroDecimalOracle, requestDataOracle.
- **⚠️ Débitos/risco:**
  - Lógica financeira crítica (saldo, baixa, estorno, reparcelamento) parcialmente NO CONTROLLER (cálculo de rateio de desconto/juros/multa em baixa parcial L517-548 com percentuais — risco de centavo/arredondamento).
  - Arrays posicionais de parcela `$parc[0,1,3,5,7]` (índices mágicos: 0=id,1=pagarreceber,5=valor,7=líquido).
  - whereRaw com `$user_id` interpolado (index L35).
  - SQLi baixo (ids), mas valores monetários parseados de string pt-BR.
- **Risco/observações p/ reescrita:** ALTÍSSIMO — qualquer divergência corrompe saldo/baixa em produção. **É o caso #1 para baseline test obrigatório.** No backend novo: CaixaService + FinanceiroService com a lógica de reparcelamento/rateio movida pra dentro do service (não no controller), DTO de parcela nomeado, valores numéricos crus (JSON number), e baseline test byte-a-byte (saldo, movimento, parcela) legado×novo. NUNCA reescrever sem rede.
- **Equivalente em ApiAdmin:** CaixaController (ApiAdmin) — usa contexto() p/ chamar este motor.

### ImpostonfController (891 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** Cadastro de CONFIGURAÇÃO FISCAL de impostos — define ICMS/PIS/COFINS/ST/FCP/diferimento por (operação × grupo fiscal × empresa), com variação por estado (Nfimpostoestado) e por tipo de pessoa (PF/PJ). É o cadastro que alimenta o cálculo de imposto na emissão de NF/CF-e.
- **Métodos:** index/filter (lista por operação/grupo), createByNf (monta URL de create a partir de CSTs vindos de outra tela), filterUrlNfCreate, create/store/edit/update/destroy/show (CRUD), dadosExtras ("gerais" = campos do imposto; "estados" = N registros de Nfimpostoestado, insere/atualiza/deleta por diff de id), showImpostoEstado (formata ICMS por estado p/ view), requestDataGerais/formatValues (saída: requestBaseCalcNfOracle com precisão+prefixo/sufixo por campo), modalidadeBcIcms/modalidadeBcIcmsSt (selects fixos), ajaxNatureza (natureza de receita PIS/COFINS por código), definition/basicDefition/empGruDefinition (carrega operações/grupos/cofins/pis/icms/estados/beneficiários filtrados por CRT da empresa), getCodICMS, getDescMotDesonICMS, getSpecialPrecision (mapa de ~30 campos → precisão decimal + %/R$), formatFieldsToInsert (insertNumeroDecimalOracle + remove " %"), validatePISCOFINS (bloqueia CST 03), validateCodBenef (regra: código de benefício fiscal obrigatório/proibido conforme CST 00/10/60/61/90).
- **Tabelas:** nfimpostos, nfimpostoestados, nfcofins, nfpis, nficms, nfoperacoes, nfgrupofiscais, estados, beneficiarios, creditopiscofins, produtos.
- **Motor/Service:** NfUtil (regras de uso de ST/Deson/FCP/REDBC/diferimento/MODBC/SN por CST — `Util::useST/useFCP/...`), nfMake helper.
- **Retorno:** view + Redirect; ajaxNatureza retorna array (JSON implícito); destroy retorna "OK|"/string HTML de erro.
- **Helpers *Oracle:** insertNumeroDecimalOracle, requestBaseCalcNfOracle (formata com precisão variável + sufixo "%"/prefixo "R$"). Campos fiscais são alíquotas/bases formatadas como "12,50 %".
- **⚠️ Débitos:** getSpecialPrecision tem CHAVES DUPLICADAS (`nficmsalimono` definido 2× — L793 e L800 com prefixos diferentes; a 2ª vence) — bug latente. Regra de CST/benefício/desoneração espalhada entre controller e NfUtil. Mapa precisão por string. Beneficiario com `||` (PG ok) e `datafim is null` whereRaw.
- **Risco/observações p/ reescrita:** MÉDIO-ALTO (config fiscal complexa, alimenta cálculo de imposto; muitas regras CST). No backend novo: ImpostoConfigService, regras CST como tabela/enum, alíquotas numéricas (não string %), reuso com o ImpostoService de cálculo. Cadastro de configuração — candidato ao padrão "Configurações" da SPA, mas é o mais complexo deles.
- **Equivalente em ApiAdmin:** parcial (FiscalController).

### McmmController (876 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** MCMM — Mapa de Controle de Movimento Mensal (documento regulatório ANP de movimentação de GLP/botijões por capacidade: P02/P08/P13/P20/P45/P90). Calcula entradas (NF recebidas/emitidas por CFOP), saídas (normais/representante/outras por CFOP), saldo anterior e saldo para mês seguinte; gera PDF posicional fiel ao formulário oficial.
- **Métodos:** index/create/store/show/edit/update/destroy (CRUD), editOrShow, insertUpdateMcmm, getDataRequest (parse de datas/capacidade/flags DEPD/DEPR/PRT/PRR/PRD), insertUpdateEntradas/insertHistoricoEntradas/insertUpdateSaidas/insertHistoricoSaidas (grava histórico versionado por em_uso/original), searchEntradaSaidaMcmm (ajax — agrega NF por CFOP/tipo_glp), getSaldoAnterior, getEntradas, getNf (query genérica nf recebida/emitida por CFOP+tipo_glp), getSaidasNormais/Representante/Outras (CFOPs 5405/5409/5949), montarTableMcmm/combineArray, toDataTableSaidas/toDataTableEntradas, **newPdf + ~15 métodos de montagem de PDF** (firstTablePdf..sixthTablePdf, spacesGlp, checkboxesPdf, horizontalLinesPdf, footer, continuacaoEntradasPdf — desenho célula-a-célula com coordenadas X/Y absolutas via Mpdf).
- **Tabelas:** mcmms, mcmmhistoricoentradas, mcmmhistoricosaidas, nfemitidas/nfemitidaitems, nfrecebidas/nfrecebidaitems, produtos (tipo_glp), empresas.
- **Motor/Service:** nenhum; Mpdf p/ PDF.
- **Retorno:** view, Redirect, JSON (search), PDF (Output).
- **Helpers *Oracle:** insertDataOracle, requestDataOracle; `TO_CHAR(...,'dd/mm/yyyy')` Oracle vivo (getNf L289); CFOPs e empresa_id interpolados em whereRaw.
- **⚠️ Débitos:** geração de PDF posicional com dezenas de coordenadas mágicas (frágil/intocável); CFOPs hardcoded como string; tipo_glp mapeado por número 1..6→P02..P90 repetido em vários métodos; `max('id')` + whereRaw datas interpoladas.
- **Risco/observações p/ reescrita:** BAIXO risco transacional, ALTO custo de reescrita do PDF. Documento regulatório raramente alterado. No backend novo: candidato a PERMANECER legado ou gerar via template; lógica de agregação por CFOP/GLP isolável em McmmService. Prioridade baixa.
- **Equivalente em ApiAdmin:** nenhum.

### ApiController (849 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** API do APP ANDROID de entregadores (registro do device, sincronização de cadastros, pedidos pendentes, mudança de status com geolocalização, vale-gás, mensagens) + endpoints de RASTREAMENTO (grupos/empresas/veículos/setores/users/pedidos) consumidos por sistema externo de rastreamento (SGCasa).
- **Métodos:** setAndroidRegistration (→androidProcessor->registrarAndroid), getUsuarios/getEmpresas/getPedidosMotivosAtrasos/getPedidosSituacoes/getVeiculos (sync por androidid), gravarTelefone (monitoramento de chamadas, formata telefone manualmente), testarToken (hardcoded "123456"), sendNotificacaoMovelTeste/sendPedidoPendente (push FCM via API externa), getPedidosPendentes (lista pedidos do setor/colaborador p/ o app, monta array manual), setPedidoSituacao (→PedidoController->updateStatus com lat/long/cartão), getValeGas (valida código), setAndroidMensagem, setVeiculoAtivo, getRastreamentoGrupos/Empresas/Veiculos/Setors/Users/Pedidos (dados p/ rastreamento — **expõe password e client_id OAuth dos users** em getRastreamentoUsers!), getPedidosReport, sendNotifications (push via ApiResources/AppConfig).
- **Tabelas:** androids, androidmensagens, empresas, empresas_grupos, empresaconfigs, users, oauthclients, colaboradors, setorcolaboradores, setors, veiculos, pedidos, pedidoitems, pedidosituacaos, pedidomotivoatrasos, valegas, monitoramentochamadas.
- **Motor/Service:** androidProcessor; instancia PedidoController (updateStatus — controller→controller); ApiResources + AppConfig (push notification externa); Guzzle (FCM, comentado).
- **Retorno:** `json_encode(["status"=>'OK'/'NOK', "dados"=>...])` (contrato próprio do app, NÃO response()->json), strings ("OK","NOT","OK|").
- **Helpers *Oracle:** insertDataOracle; `SELECT SUM(...) FROM pedidoitems` subquery raw.
- **⚠️ SEGURANÇA GRAVE:** getRastreamentoUsers (L713-714) retorna `password` (hash) e `client_id` OAuth de TODOS os usuários da empresa em JSON — vazamento de credenciais. Auth dos endpoints por `email`/`androidid` no body (sem token forte). testarToken hardcoded.
- **Risco/observações p/ reescrita:** ALTO (contrato com app Android em campo + integração rastreamento externo SGCasa). No backend novo: API mobile versionada com auth por token real, NUNCA expor password/secret, push via service dedicado, montagem de payload via Resources. Relacionado a app/Api e app/Monitora.
- **Equivalente em ApiAdmin:** nenhum (API externa).

### ProdutoController (840 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** CRUD de Produto (foco GLP/botijão de gás + revenda). Classificação fiscal (NCM, CEST, IPI, gênero TIPI, item LST/ISS, origem do combustível por UF, tipo GLP P02..P90, percentuais PGNI/PGNN/PGLP, ressarcimento, vasilhame retornável).
- **Métodos:** index/create/store/show/edit/update/destroy (CRUD; construtor injeta SelectRepository), dadosExtras (converte ~12 campos numéricos/percentuais via insertNumeroDecimalOracle/insertPercentualOracle/conversaoPeso/converterBaseCalcOracle; valida soma PGLP+PGNN+PGNI=0 ou 100; valida soma origens=100%), correcoesProduto (saída: requestNumeroDecimalOracle/requestBaseCalcNfOracle + peso "Kg"), getProdutoVasilhame, showEdit, ajaxNcmCest/ajax/buscaPorSetor/buscaPorSetorNF/buscaPorSetorNFEntrada/buscaPorClasse/buscarPrecoAjax (endpoints de apoio a pedido/NF/estoque), tipoGlp/getGeneros/getLst (LISTAS HARDCODED GIGANTES — getGeneros ~100 linhas de capítulos TIPI, getLst ~230 linhas de itens de serviço LC 116), createOrigens (origem do combustível por UF).
- **Tabelas:** produtos, produtoorigens, produtoclasses, estoquesetors, setors, nfcests, estados; via SelectRepository: unidademedidas, nfgrupofiscais, spedtipoitems, nfipi.
- **Motor/Service:** SelectRepository (injetado), helpers nfMake/makeCest/conversaoPeso.
- **Retorno:** view, Redirect, JSON implícito (ajax retorna Eloquent), "OK|"/HTML de erro (destroy).
- **Helpers *Oracle:** insertNumeroDecimalOracle, requestNumeroDecimalOracle, insertPercentualOracle, requestBaseCalcNfOracle, requestNumeroDecimal4DigitosOracle, converterBaseCalcOracle, conversaoPeso. Valores e % como string formatada; origens array posicional `$origem[1,3,5]`.
- **⚠️ Débitos:** ~330 linhas de LISTAS FIXAS (gêneros TIPI + LST) embutidas no controller (deveriam ser tabela/seed/constante); validação inativação por estoque; array posicional de origem.
- **Risco/observações p/ reescrita:** MÉDIO — CRUD rico mas isolável; regra fiscal (NCM/CEST/origem/GLP) bem definida. No backend novo: ProdutoService, listas TIPI/LST como tabela/enum, valores numéricos crus, payload de origens nomeado. JÁ tem equivalente parcial.
- **Equivalente em ApiAdmin:** ProdutoController + ProdutoConfigController (ApiAdmin) — já bastante coberto (a SPA tem produtos/precos/config).

### EmpresaController (800 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** CRUD da Empresa/revenda (a entidade-TENANT do sistema). Dados cadastrais + endereço + config fiscal completa (NF-e/NFC-e: tipo emissão/ambiente/CRT/modelo/série/numeração; SAT; SPED perfil/atividade/incidência/crédito; contingência por UF) + certificado digital PFX + logo (BLOB) + flags ANP (DEPD/DEPR/PRT/PRR/PRD) + IBS/CBS. Inclui **change()** que TROCA a empresa ativa na Session (multi-tenant).
- **Métodos:** index (lista por user ou todas se support), create/store/show/edit/update/destroy, **change($id)** (recarrega Session: empresa_padrao, empresa_config, empresas_permitidas, permissoes Menuuser, menu — É O SWITCH DE TENANT), dadosExtras (normaliza flags, insertNumeroDecimalOracle/insertPercentualOracle/conversaoPeso, geocoding, **criptografa senha do certificado** customCrypt, decide nfeemite/nfceemite por nfeemitemodelos), validateContingency (mapa UF→SVCAN/SVCRS hardcoded), getSelects (todos os selects fiscais hardcoded), checarNfNumero (trava de concorrência na numeração de NF-e/NFC-e — impede regressão de número), saveCertNf (move .pfx pro storage por CNPJ), updateMatriz, getMatrizGrupo, carregaempresa/form.
- **Tabelas:** empresas, empresaconfigs, empresas_grupos, menuusers, menus, empresa_user, regioes, estados, cidades, bairros, ruas.
- **Motor/Service:** PedidoRepository (getEmpresas), helpers saveLogoNfeStorage, BlobWriter (logoimg BLOB), buscaLatitudeLongitude (geocoding), customCrypt (senha PFX), getPathCertificateNFe.
- **Retorno:** view, Redirect.
- **Helpers *Oracle:** requestDataOracle, insertDataOracle, requestNumeroDecimalOracle, insertNumeroDecimalOracle, requestPercentualOracle, insertPercentualOracle, requestPesoInteiroOracle, conversaoPeso.
- **⚠️ Pontos críticos:**
  - **change() = mecanismo de multi-tenant** via Session (empresa_padrao + permissões + menu). É o que TODO o resto do sistema lê (`Session::get('empresa_padrao')`). No backend novo, isso vira contexto de tenant por token/request — peça central.
  - **Certificado digital A1 (.pfx)** salvo no filesystem por CNPJ + senha criptografada (customCrypt) — integração SEFAZ depende disso; migração precisa mover certificados + re-encriptar senha.
  - checarNfNumero: trava anti-duplicidade de numeração fiscal (regra crítica).
  - logo como BLOB (logoimg) via BlobWriter — dado binário no banco.
- **Risco/observações p/ reescrita:** ALTO (tenant + fiscal + certificado). No backend novo: EmpresaService + TenantContext (substitui Session), config fiscal possivelmente em entidade separada, certificado em storage seguro/secret manager, selects fiscais como enum/tabela. **change() precisa de equivalente desde o N0** (todo o resto depende de tenant ativo).
- **Equivalente em ApiAdmin:** EmpresaController + EmpresaConfigController (ApiAdmin) — a SPA já tem empresas/config.

### ContaController (780 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** CRUD de Conta (financeira/caixa/banco). Inclui config de emissão de BOLETO (carteira, cedente, multa/juros, instruções, layout banco, correspondente), permissões por usuário (visualizar/operar/transferir/estornar/lançar fechado), talões de cheque (faixa numérica), e config de extrato OFX (Contaextratoconfig: ação Lançar/Transferir/LançarBaixar mapeada a condição pgto/tipo movimento/PC/CC/cliente).
- **Métodos:** index/create/store/show/edit/update/destroy, givePermissoes (Contauser por user — array posicional `$user[0..6]`), createCheques (Contatalao — `$talao[0..3]`), revisoesCheques/revisionsUsers/revisions (auditoria manual Revisionable), addEditExtratoconfig (ADD/DEL/UPD de Contaextratoconfig via switch, validação condicional por tipo de ação — usado pela importação de extrato OFX).
- **Tabelas:** contas, contausers, contatalaos, contaextratoconfigs, contatipos, bancos, layoutbancos, condicaopagamentos, contamovimentotipos, centrocustos, planocontas, empresas, empresa_user, revisions.
- **Motor/Service:** nenhum (CRUD); usa Enum ContaextratoAcao (Casts/Enums); Revisionable.
- **Retorno:** view, Redirect, "OK|"/HTML erro (destroy), responseSuccess/Error (extratoconfig — JSON).
- **Helpers *Oracle:** insertNumeroDecimalOracle, requestNumeroDecimalOracle, insertPercentualOracle, requestPercentualOracle (saldo inicial/atual, multa/juros do boleto).
- **⚠️ Débitos:** `$request->only(...)` com lista de campos GIGANTE duplicada (store e update repetem ~30 campos de boleto 2×); array posicional users/talões; auditoria manual concatenando strings.
- **Risco/observações p/ reescrita:** MÉDIO — CRUD com config de boleto/OFX. saldoatual aqui é o saldo que o caixaProcessor movimenta (cuidado na migração: saldo é dado crítico). No backend novo: ContaService, FormRequest, sub-recursos (users/talões/extratoconfig) como payload nomeado, ContaextratoAcao já é enum (bom). 
- **Equivalente em ApiAdmin:** parcial (cadastros de apoio financeiro).

### EmpresaconfigController (716 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** CONFIGURAÇÃO OPERACIONAL da empresa (Empresaconfig) — alimenta praticamente todos os fluxos. Define PC/CC padrão para cada tipo de lançamento (cartão, desconto/juros receita+despesa, vale-gás, frete, frete convênio, frete gás-do-povo, convênio), cliente/operação NFCe padrão, situação/operação de pedido padrão, transportador/veículo padrão, flags operacionais (valida atraso/cartão/gás-bolso/pix/coordenadas, android, estoque negativo, impressão, tempos de entrega), e-mail SMTP, percentuais (encargos/provisão/remuneração/distribuição), integração PIX (chave + client_id/secret criptografados), senha mestre.
- **Métodos:** index (carrega ~40 PC/CC descrições + dezenas de selects), store/update (cria/atualiza + replicação matriz↔filial + PIX webhook), dadosExtras (insertPercentualOracle/insertNumeroDecimalOracle + flags bool + customCrypt emailsenha), percentualConfig (saída requestPercentualOracle), senhaMestre/changePassword/verificaSenhaMestre (Hash::make/check — senha mestre p/ operações sensíveis), logSenha/motivosLog (auditoria de uso da senha mestre por rota), matriz/filial (replica PC/CC config da matriz p/ filiais do grupo), checkPixFields/checkPixwebhook (criptografa credenciais PIX via encrypt(); registra webhook via PixService), sendEmail, controleKm/getPresencaFrete.
- **Tabelas:** empresaconfigs, empresas, centrocustos, planocontas, contas, setors, nfoperacoes, pedidosituacaos, pedidooperacoes, condicaopagamentos, produtos, veiculos, clientes, logsenhas.
- **Motor/Service:** PixService (processNewKey/webhook), NfUtil, helpers customCrypt/encrypt/sendMail; Enums LogSenhaStatus.
- **Retorno:** view, Redirect, "OK|"/strings (verificaSenhaMestre, sendEmail).
- **Integração externa:** PIX (registro de chave + webhook via PixService); SMTP.
- **Helpers *Oracle:** insertPercentualOracle, requestPercentualOracle, insertNumeroDecimalOracle, requestNumeroDecimalOracle.
- **⚠️ Pontos:** index tem ~185 linhas só montando pares id/descrição de PC/CC (40+ campos) — duplicação massiva; senha mestre (Hash) protege operações críticas (cancelamento, edição de pedido fechado, reabertura estoque) — regra de segurança importante; credenciais PIX e e-mail criptografadas (migração precisa re-encriptar); replicação matriz→filial é regra de negócio relevante.
- **Risco/observações p/ reescrita:** ALTO (config central da qual tudo depende; muitos FKs de PC/CC; PIX + senha mestre). No backend novo: EmpresaConfigService, agrupar os 40 PC/CC config numa estrutura, manter senha mestre como gate de operações sensíveis, credenciais em secret manager, replicação matriz/filial explícita. Carrega junto com o tenant (Session('empresa_config')).
- **Equivalente em ApiAdmin:** EmpresaConfigController (ApiAdmin) — a SPA já tem empresas/config.

### FechamentoconvenioController (781 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** Fechamento mensal de CONVÊNIO — agrupa pedidos do período de um cliente-convênio em 1 financeiro consolidado (reagrupando as parcelas-origem dos pedidos), aplica comissão do convênio, e permite emitir NF e boleto do fechamento. Exporta PDF e XLS.
- **Métodos:** index/create/store/show/edit/update/filtroConsultas/filtroPedidos (lista pedidos elegíveis do convênio no período), salvarPedidos (Conveniofechamentopedido — array posicional `$pedido[0..4]`), **processoFinanceiroFechamento** (financeiroProcessor: cria financeiro consolidado + rateio + parcelas, AGRUPA as parcelas-origem dos pedidos via setParcelasOrigem/setAgrupar), cancelarFinanceiro/parcelasOrigem (desagrupar parcelas na edição), gerarPDF (dompdf), gerarXLS (PhpSpreadsheet com logo BLOB + bordas + formatação), emitirNF (→NfemitidaController::processorFechamentoConvenionf + transmitir SEFAZ), emitirBoleto (→BoletoProcessor), checarPeriodo, correcoesFechamento.
- **Tabelas:** conveniofechamentos, conveniofechamentopedidos, pedidos, pedidoitems, clientes, clienteconvenios, financeiros, financeiroparcelas, financeirorateios, condicaopagamentos, empresaconfigs, nfemitidas, boletos.
- **Motor/Service:** financeiroProcessor (agrupar/desagrupar parcelas — **lógica de reparcelamento/agrupamento crítica**), BoletoProcessor, NfemitidaController (controller→controller), SelectRepository, dompdf, PhpSpreadsheet, calculoParcelas helper.
- **Retorno:** view, responseSuccess/Error (JSON), PDF stream, XLS (header()+php://output direto — **escreve headers manualmente, não response Laravel**).
- **Helpers *Oracle:** insertDataOracle, requestDataOracle, insertNumeroDecimalOracle, requestNumeroDecimalOracle. whereRaw com $id interpolado (filtroPedidos L634).
- **⚠️ Débitos:** financeiro montado campo-a-campo inline (duplica financeiroProcessor); XLS via header()/php://output (deveria ser response/Maatwebsite); logo via imagecreatefromstring(base64) BLOB; agrupamento de parcelas é regra financeira sensível.
- **Risco/observações p/ reescrita:** MÉDIO-ALTO (toca financeiro via agrupamento de parcelas + NF + boleto). No backend novo: ConvenioFechamentoService usando FinanceiroService (agrupar/desagrupar), export via lib, valores crus.
- **Equivalente em ApiAdmin:** nenhum.

### FinanceiroController (652 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** Contas a Pagar/Receber — lançamento de financeiro (receita/despesa), consulta com filtro complexo (por código/pedido/NF/convênio/NF-recebida ou por status/data/cliente/colaborador), agrupamento de parcelas (createbyagrupar — junta várias parcelas num lançamento, recalcula rateio proporcional), cancelamento, alteração de descrição/vencimento/cartão, importação de relatório de cartão (CSV → casa parcela por autorização). É o controller-fachada do `financeiroProcessor`.
- **Métodos:** contasreceberindex/contaspagarindex (views), create_despesa/create_receita/create_financeiro/createbycaixa/createbyagrupar (forms; agrupar recalcula rateio por parcela), store (→financeiroProcessor->validar/gravar), getLancamentosFinanceiros (consulta — query builder gigante com filtro por colaborador via subquery pedido+NF, paginação manual, SUM OVER), formatParcelasToView (saída: requestNumeroDecimalOracle + status BAI/CAN/ATR/PEN), alterarDescricaoLancamento, consultartitulos, cancelar (→processor->cancelarParcelas), importReportCartaoIndex/GetParcelas/getFinanceiroByAutorizacaoCartao (importa CSV de adquirente, casa por nº autorização), contasReceberImportCartao, checkForBoleto.
- **Tabelas:** financeiros, financeiroparcelas, financeirorateios, planocontas, centrocustos, condicaopagamentos, contamovimentotipos, clientes, pedidos, nfemitidas/nfrecebidas, boletos, cheque*financeiros/encontrocontas, setorcolaboradores, conveniofechamentos.
- **Motor/Service:** **financeiroProcessor** (validar/gravar/cancelarParcelas/alterarDescricao — núcleo de contas a pagar/receber).
- **Retorno:** view, Redirect, strings ("OK|...", erros), arrays (formatParcelasToView), JSON (alterarDescricao).
- **Helpers *Oracle:** insertNumeroDecimalOracle, requestNumeroDecimalOracle, insertDataOracle. `TO_DATE(...)` Oracle.
- **⚠️ Segurança:** getLancamentosFinanceiros tem SQL cru ENORME (subquery $pednf_fin) — parte JÁ parametrizada ("S1/SQLi" comentado, datas via binding) mas ainda interpola `$colaborador_id`, `$cliente_id`, `$empresa_id`, `$valorPesquisa` em alguns ramos (case 2/3/4/5 do switch usam `$valorPesquisa` em whereRaw). Mitigado por casts a int em vários pontos, mas não 100%.
- **Risco/observações p/ reescrita:** ALTO (núcleo financeiro a pagar/receber, agrupamento de parcelas = regra sensível). Caso de baseline test. No backend novo: FinanceiroService (toda regra do processor), consulta via query builder parametrizado/repository, import de cartão como service de conciliação.
- **Equivalente em ApiAdmin:** FinanceiroController / FinanceiroGestaoController (ApiAdmin) — parcial.

### NfrecebidaController (714 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** Lançamento de NF RECEBIDA (entrada — compra de fornecedor/transferência). Importa XML de NF-e, gera financeiro (a pagar) + frete, calcula imposto, atualiza PGLP do produto, inutilização de faixa de numeração de entrada. Espelha NfemitidaController (mesmo NfProcessor/NfeImpostoProcessor, mesma view `nf.nfe_form`).
- **Métodos:** index/filter (lista por data/cliente), create/store/edit/show/update/form/destroy (CRUD), validateData, estornoNfRecebidaItem, gerarFinanceiro/estornarFinanceiro (via NfProcessor — financeiro de entrada normal+frete), importXml/loadXmlImport (NFePHP Standardize — lê XML, mascara placa/datas), getEmitImport/getTranspImport (casa emitente/transportador por CNPJ/CPF, valida flags nfemite/fornecedor/transportador), inutilizar (registra NfInutilizacaoEntrada por faixa). updatePGLP do produto a partir da NF de entrada (regra GLP).
- **Tabelas:** nfrecebidas, nfrecebidaitems, nfrecebidavolumes, nfrecebidaparcelas, nfrecebidarateios, clientes, produtos, nfoperacoes, planocontas, centrocustos, condicaopagamentos, financeiros, nfinutilizacaoentradas.
- **Motor/Service:** NfProcessor (gerar/estornar financeiro, estorno item, rateio), NfeImpostoProcessor (calcula imposto de entrada), SelectRepository, NfUtil; NFePHP Standardize (parse XML).
- **Retorno:** view, Redirect, JSON (importXml), "OK|"/strings (inutilizar/destroy).
- **Helpers *Oracle:** requestDataOracle.
- **Risco/observações p/ reescrita:** ALTO (fiscal de entrada + financeiro a pagar + import XML real). Compartilha motores com NF emitida → no backend novo, FiscalService cobre emitida E recebida; import XML por lib; financeiro via FinanceiroService.
- **Equivalente em ApiAdmin:** parcial (FiscalController).

### BoletoremessaController (651 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** Remessa/retorno bancário CNAB de boletos (cobrança registrada). Gera arquivo de remessa (.rem) a enviar ao banco, importa arquivo de retorno (baixa/ocorrências), efetiva remessa (atualiza boletos/parcelas conforme ocorrência), cancela. Suporta **Caixa (104) e Itaú (341)** com parsing posicional de layout CNAB.
- **Métodos:** index/filterIndexRemessa/create/filterCreateRemessa/show/edit/store/update (CRUD de remessa + seleção de boletos elegíveis), selectBoletos, importRetorno (→BoletoProcessor->processarRetorno), cancelarRemessa, exportRemessa (→BoletoProcessor->getRemessa gera .rem), efetivarRemessa (processa ocorrências: baixa/abatimento/cancelamento, atualiza boleto/parcela/histórico), insertBoletoRemessaFinanceiro (valida boleto impresso), fileExists/downloadArquivo/getFile (Storage disk 'boletos' por CNPJ), **getRegistroCaixa/getRegistroItau** (parse POSICIONAL do layout CNAB de cada banco — mb_substr por offset).
- **Tabelas:** boletoremessas, boletoremessafinanceiros, boletos, boletohistoricos, financeiroparcelas, financeiros, contas, clientes, ocorrenciasremessas.
- **Motor/Service:** BoletoProcessor (processarRetorno, getRemessa, codigoBanco), SelectRepository, Eduardokum\LaravelBoleto\Util (modulo11), Storage.
- **Integração externa:** BANCO via CNAB (arquivo remessa/retorno Caixa/Itaú); filesystem (disk boletos).
- **Retorno:** view, Redirect, download (.rem), "OK|"/strings.
- **Helpers *Oracle:** insertDataOracle, requestNumeroDecimalOracle. whereRaw com SQL composto.
- **⚠️ Débitos:** layout CNAB hardcoded por offset (getRegistroCaixa/Itau — frágil, banco-específico); ocorrências de liquidação hardcoded por banco ('21','22','35' Caixa; '06','08','35' Itaú); whereRaw com `$boleto->id`/`$remessa->id` interpolado (efetivarRemessa); código comentado morto (L428-435).
- **Risco/observações p/ reescrita:** ALTO + GATE (integração bancária real, layout CNAB por banco, homologação). No backend novo: BoletoService usando lib de boleto (eduardokum) para remessa/retorno (não parse manual), ocorrências como tabela/config, gate de homologação por banco. Não testável em CI.
- **Equivalente em ApiAdmin:** BoletoController (ApiAdmin) — provavelmente parcial.

### ReportController (639 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** Relatórios de vendas/CRM — Follow-up (contatos de cliente), Vendas por Setor, Vendas Diárias, Vendas por App (rating), Vendas por Produto. Saída PDF (dompdf) e XLS (PhpSpreadsheet/XlsxExporter).
- **Métodos:** filtros + report* para cada relatório; buscaVenda/buscaDiaria/buscaVendaApp/buscaVendaProduto (queries com join pedido/item/cliente/setor/produto, agregação hierárquica manual por setor/produto/segmento com totais, cálculo de valor líquido por rateio de desconto).
- **Tabelas:** clientecontatos, clientecontatotipos, clientecontatosituacaos, pedidos, pedidoitems, pedidosituacaos, clientes, setors, produtos, bairros, ruas, segmentos, condicaopagamentos, empresas; **conexão sgcm_api** (pedidos/pedidoavaliacoes do app — vendaApp).
- **Motor/Service:** SelectRepository, dompdf, PhpSpreadsheet, XlsxExporter helper.
- **Retorno:** PDF stream, view (preview), XLS download.
- **Helpers *Oracle:** requestDataOracle. Concatenação `||` (PG ok). whereRaw com $grupo_id interpolado (buscaDiaria L328).
- **⚠️ Padrão "relatório" (arquétipo):** parâmetros passados como string `$par` explodida por `|`/`,` na URL; agregação/subtotais em PHP (não SQL); cálculo de valor líquido replicado em cada método; segunda conexão sgcm_api para vendas via app.
- **Risco/observações p/ reescrita:** BAIXO risco transacional (só leitura), MÉDIO volume. Candidato a permanecer legado ou virar relatório novo só-leitura. No backend novo: ReportService por relatório, params como query nomeada, agregação no SQL, export por lib.
- **Equivalente em ApiAdmin:** parcial (SatelitesController/dashboards).

### ValegasvendaController (630 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** Venda de VALE-GÁS (cupom pré-pago de botijão). Pré-venda × venda; gera N cupons (Valegas) com código aleatório único (9 dígitos) e máquina de situação (Pré-Venda/Vendido/Impresso/Impresso Pré-Venda/Baixado/Cancelado); gera financeiro (a receber, parcelado) com rateio PC/CC do vale-gás (da empresaconfig); imprime etiquetas (PDF, agrupadas 10/3); fecha pré-vendas; duplicata PDF; cancelamento (cancela parcelas + cupons não baixados).
- **Métodos:** index/create/store/show/destroy/update(vazio), salvarPreVenda (gera cupons com gerarCodigo/checkCodigo unicidade), gerarFinanceiro (financeiroProcessor + rateio ccvalegas/pcvalegas da config), parseFecharPreVenda/updateValegas (fecha pré-venda), gerarPDF (duplicata), imprimirIndex/imprimirGas/updateImpresso (etiquetas), cancelamentoParcelas (financeiroProcessor->cancelarFinanceiro), validarParcelas, buscarCliProd/buscarCliPre/buscarParcelasAjax.
- **Tabelas:** valegasvendas, valegas, valegassituacaos, financeiros, financeiroparcelas, financeirorateios, condicaopagamentos, condicaopagamentoparcelas, clientes, produtos.
- **Motor/Service:** financeiroProcessor (gravar/cancelar), SelectRepository, dompdf, calculoParcelas.
- **Retorno:** view, Redirect, PDF.
- **Helpers *Oracle:** insertDataOracle, requestDataOracle, requestDataOracleSemHora, insertNumeroDecimalOracle, requestNumeroDecimalOracle.
- **⚠️ Padrão:** financeiro montado inline (duplica financeiroProcessor — igual gás-do-povo/convênio/CF-e); situação de vale-gás buscada por DESCRIÇÃO ('Vendido','Baixado'...) via getSituacao — frágil (depende de texto exato); update() vazio com comentário de e-mail solto.
- **Risco/observações p/ reescrita:** MÉDIO (toca financeiro; máquina de estados de cupom). No backend novo: ValeGasService + FinanceiroService, situação por enum/id (não descrição), etiquetas por template.
- **Equivalente em ApiAdmin:** ValeGasController (ApiAdmin) — a SPA tem vale-gás.

### BoletoPdfController (583 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** NÃO é controller HTTP — é uma classe de RENDERIZAÇÃO de PDF de boleto que estende `Eduardokum\LaravelBoleto\Boleto\Render\AbstractPdf` e implementa `PdfContract`. Desenha o layout do boleto bancário (topo/recibo/ficha de compensação, código de barras i25, comprovante de entrega, instruções) célula-a-célula. Customização local da lib de boleto.
- **Métodos:** instrucoes, logoEmpresa (comentado), comprovanteEntrega, Topo, Bottom, traco, codigoBarras (i25), addBoletos/addBoleto, hideInstrucoes/showPrint, gerarBoleto (gera N boletos em PDF: I/D/F/S), listaLinhas.
- **Motor/Service:** lib eduardokum/laravel-boleto (AbstractPdf, Util).
- **⚠️ Débitos:** PDF posicional puro (coordenadas fixas); comentários "modificado by Jeferson Almeida"; código comentado. É infraestrutura de renderização, não regra de negócio.
- **Risco/observações p/ reescrita:** BAIXO (é render de PDF da lib). No backend novo: usar o render padrão da lib ou template; baixa prioridade. Chamado pelo BoletoProcessor.
- **Equivalente em ApiAdmin:** n/a (render).

### ReportestoqueController (585 linhas) ✓ LIDO INTEGRALMENTE — arquétipo RELATÓRIO
- **Domínio:** Relatórios de estoque — Transferências (origem/destino), Requisições, GLP (cheios/vazios/vasilhames por setor), Estoque Geral. Saída PDF/preview.
- **Métodos:** *Index (filtro) + *Pdf + busca*/pesquisa* (queries join estoquesetors/produtos/setors/transferencias/requisicoes + agregação hierárquica manual por setor/produto com subtotais).
- **Tabelas:** estoquesetors, estoquetransferencias/items, estoquerequisicaos/items, produtos, produtoclasses, setors, planocontas, centrocustos, users.
- **Helpers *Oracle:** requestDataOracle, requestNumeroDecimalOracle. Concatenação/`||` PG-ok.
- **Padrão:** idêntico a ReportController/ReportCaixa — filtro por $_GET, agregação em PHP com subtotais, dompdf. Lógica GLP (cheio vs vazio via produto retornável/vasilhame) é a única regra de domínio específica.
- **Risco/reescrita:** BAIXO (só leitura). Mesma recomendação dos relatórios: ReportService/query nomeada/export por lib, ou manter legado.
- **Equivalente em ApiAdmin:** parcial (EstoqueController consulta).

> **NOTA — controllers de RELATÓRIO restantes seguem este mesmo arquétipo** (filtro $_GET → query join → agregação PHP com subtotais → dompdf/xls): ReportclientesController, ReportvendapdvController, ReportcomissoesController, ReportvalegasController, ReportcolaboradorController, ReportclientesaniversariantesController, ReportFinanceiroController, ReportveiculosController, ReportcomodatoController, ReportnfemitidasController, ReportquestionariosController, ReportEntregasController, ReportmovimentacaoController, ReportpromocoesController, ReportResumoVendasController, ReportlogsController, ReportnfrecebidasController, ReportconvenioController, ReportpromotorController, ReportlogsenhaController, ReportVendasController, ReportnfrecebidaController, ReportvendasmaloteController, VendasmensaisgestaoController, FechamentomensalgestaoController, ConveniogbgestaoController, ComodatogestaoController, DashboardgerencialController. Serão lidos integralmente, mas o registro será por exceção (só o que cada um tem de regra/tabela/integração inédita), pois o "como funciona" já está documentado aqui.

### ReportvendapdvController (843 linhas) ✓ LIDO INTEGRALMENTE — arquétipo RELATÓRIO
- Relatórios de vendas: por Segmento (compram/não compram), por Entregador, por Convênio (3 variantes: compram/não compram/outra forma pgto). Mesmo molde (filtro $_GET → join pedido/item/cliente/setor/segmento/convênio → agregação PHP com subtotais → dompdf).
- **Achado por exceção — SQLi:** buscaNaoCompram (L280-283) e buscaNaoConvenio (L649-652) interpolam `$datainicio`/`$datafim`/`$this->empresa_id`/`$this->grupo_id` direto em `whereRaw("... to_date('$datainicio'...)")`. `TO_CHAR`/`TO_DATE` Oracle vivo. (datas vêm de Carbon, mitiga; ids da Session, mitiga; mas é interpolação.)
- Risco/reescrita: BAIXO (só leitura). Mesma recomendação geral de relatórios.

### ReportclientesController (876) ✓ LIDO — arquétipo RELATÓRIO (relatórios de cadastro de clientes/aniversariantes/interessados; join clientes + agregação; dompdf/xls; requestDataOracle). Sem regra/integração inédita além do molde. SQL com grupo_id interpolado.

## CHECKPOINT DE PROGRESSO (para retomar após compactação)

**Controllers LIDOS integralmente (27):** Nfemitida, Search, Nfweb, Pedido, CupomFiscal, Cliente, ReportCaixa, Caixa, Impostonf, Mcmm, Api, Produto, Empresa, Conta, Empresaconfig, Fechamentoconvenio, Financeiro, Nfrecebida, Boletoremessa, Report, Valegasvenda, BoletoPdf, Reportestoque, Reportvendapdv, Reportclientes, Chequerecebido, Users.

**PROCESSORS (motores) — TODOS MAPEADOS:** ChequeProcessor✓, caixaProcessor✓ (coração do saldo), financeiroProcessor✓, EstoqueProcessor✓, BoletoProcessor✓, MobileAppProcessor✓ (pagamento online Rede/PIX), androidProcessor (trivial), Nfe/ (NfProcessor✓ + tributação/SefazEvento mapeados como gate fiscal), Sped/ (mapeado como gate fiscal).

**Conclusões estruturais p/ o PLANO (todas fundamentadas em código):**
1. Saldo de caixa/financeiro/estoque é INCREMENTAL e propagado em cadeia → ETL de cutover DEVE validar invariante `Σ movimentos/histórico = saldo` por conta/setor/produto + saldos de fechamentos.
2. `agrupamento_status` (0 normal/1 agrupador/2 agrupada/3 cancelada) = eixo implícito do reparcelamento/agrupamento financeiro.
3. Duplicação massiva: 6+ controllers montam financeiro+rateio+parcela inline em vez de usar financeiroProcessor → no novo, FinanceiroService único.
4. Fiscal (Nfe/Sped) é legislado + gate SEFAZ → PORTAR, não reescrever do zero.
5. Integrações = gates: boleto CNAB (Caixa/Itaú), Rede (cartão online), PIX, SEFAZ, geolocalização, push, sgcm_api (2ª conexão de pagamento).
6. Dois RBAC coexistem (menuusers legado + spatie) → unificar.
7. String pt-BR como contrato (`*Oracle` helpers) + arrays posicionais + retorno View/Redirect/protocolo = a raiz do "frontend novo não consome"; a tradução vai no ETL + Resources/DTO do backend novo.
8. Oracle SQL vivo (TO_CHAR/TO_DATE/NLS) + SQLi por interpolação em vários pontos.

**PRÓXIMOS a ler (ordem por tamanho desc, a partir de ~787 linhas):** ReportcomissoesController(787), ChequerecebidoController(494), FechamentomensalgestaoController(492), FechamentomaloteController(490), ReportFinanceiroController(488), UsersController(479), ChequeemitidoController(476), ColaboradorController(469), EstoquerequisicaoController(454), EstoqueTransferenciasController(437), ReportvendasmaloteController(433), AppnotificationController(401), ImportextratoController(393), ReportveiculosController(389), ReportcomodatoController(385), ReportnfemitidasController(374), CadastrochecklistController(360), ReportquestionariosController(340), CentrocustoController(336), CondicaopagamentoController(335), PlanocontaController(332), VeiculoController(313), ChecklistController(303), InconsistenciaController(301), DocumentoController(295), ReportEntregasController(293), ConveniogbgestaoController(292), DashboardgerencialController(289), SpedfiscalController(285), SetorController(281), … e os ~110 restantes (maioria CRUDs simples <280 linhas + relatórios do molde já documentado).

**Áreas inteiras ainda NÃO iniciadas:** app/Processors (144 arq/24k linhas — os 7 MOTORES: caixaProcessor, financeiroProcessor, EstoqueProcessor, BoletoProcessor, ChequeProcessor, MobileAppProcessor, androidProcessor + Nfe/ + Sped/), app/Api (82 arq — API mobile Passport), app/Monitora (44 arq — GPS), app/Repository (7), app/Services (9 — PixService, BoletoCaixa/Itau, RemessaCaixa/Itau, AppVideo, CarbonCustom), app/Helpers (9 — customHelper 60KB com os *Oracle), app/Console (17 commands), Models (203).

### ChequerecebidoController (494 linhas) ✓ LIDO INTEGRALMENTE
- **Domínio:** Cheques RECEBIDOS de clientes. Máquina de estados (situação 4=recebido, 5=depositado, 2=baixado, 6=devolvido, 7=...); vincula cheque a parcelas financeiras (chequerecebidofinanceiro), encontro de contas, troco/adiantamento via transferência de caixa, baixa/depósito/devolução/estorno/exclusão.
- **Métodos:** index/getFiltrosCheque (filtro por situação/número/data; TO_CHAR Oracle), show, create, store/validarGravar/dadosExtras (cria cheque + histórico situação + encontro contas + transferência de troco), editar/excluirCheque (estorna transferência via caixaProcessor), depositarCheque, devolverCheque (baixa antes se nunca baixado + transferência DEVOLUCAO), baixarCheque (baixa parcelas + encontro contas + troco/adiantamento/segunda baixa), estornarCheque.
- **Tabelas:** chequerecebidos, chequerecebidofinanceiros, chequerecebidoencontrocontas, chequerecebidotransferencias, chequesituacaos, chequesituacaohistoricos, financeiroparcelas, financeiros, contamovimentos, bancos, contas.
- **Motor/Service:** **ChequeProcessor** (insertHistoricoSituacao, insertChequeEncontrocontas, baixarParcelasCheque, transfereCaixaChequeRecebido, insertChequeTransferencia, estornarChequeRecebido), **caixaProcessor** (estornarTransferenciaCaixa).
- **Retorno:** view, "OK|"/strings de erro.
- **Helpers *Oracle:** insertDataOracle, requestNumeroDecimalOracle, insertNumeroDecimalOracle; TO_CHAR Oracle nos filtros de data.
- **⚠️ Débitos:** máquina de estados de cheque por números mágicos (2/4/5/6/7); regra de baixa/troco/adiantamento/devolução complexa e implícita; cheque toca caixa (transferências) → impacta saldo.
- **Risco/observações p/ reescrita:** ALTO (toca caixa/financeiro via ChequeProcessor — saldo). Baseline test recomendado. No backend novo: ChequeService com máquina de estados explícita + Financeiro/CaixaService.
- **Equivalente em ApiAdmin:** ChequeController (ApiAdmin) — parcial.

---

## PROCESSORS (MOTORES) — leitura linha-a-linha

### ChequeProcessor (440 linhas) ✓ LIDO INTEGRALMENTE — MOTOR
- **Papel:** Motor de cheques (recebidos/emitidos) que orquestra o **caixaProcessor**. Contém a regra de: baixa de parcelas por cheque, encontro de contas, transferências de caixa (troco/adiantamento/devolução/segunda-baixa entre contas configuradas), estorno em cascata, histórico de situação.
- **Métodos:** getSituacaoAnterior (reconstitui situação anterior via histórico), insertChequeEncontrocontas (recebido/emitido), estorno (agrupa movimentos por conta → caixaProcessor->validarEstornoCaixa/EstornarCaixa), baixarParcelasCheque (monta Contamovimento[] origem 'CHQ', acha contafechamento aberto, → caixaProcessor->baixarTitulos), transfereCaixaChequeRecebido (decide conta origem/destino por tipo: troco→contachecktroco, devolução→contadevolucaocheque, etc — da empresaconfig; → caixaProcessor->transferirCaixa), estornarChequeRecebido (lógica complexa: estorna baixa OU só transferências conforme histórico de devolução), estornarAdiantamentoOuTroco, insertChequeTransferencia, insertHistoricoSituacao, checkPermissiones (valida permissão de estorno do usuário nas contas).
- **Tabelas:** chequerecebidos, chequerecebido/emitidoencontrocontas, chequerecebidotransferencias, chequesituacaohistoricos, contamovimentos, contatransferencias, contafechamentos, contamovimentotipos, financeiroparcelas.
- **Depende de:** **caixaProcessor** (todo movimento de caixa), Session('empresa_config') (contas padrão troco/devolução), Session('empresa_padrao').
- **⚠️ Regra crítica/implícita:** decisão de conta origem/destino por tipo de transferência hardcoded; situação de cheque por números mágicos (2/4/5/6/7); estorno reconstitui estado por histórico (frágil); tipo de movimento de cheque achado por `descricao LIKE '%CHEQUE%'`.
- **Risco/observações p/ reescrita:** ALTO — toca saldo de caixa. **Baseline test obrigatório.** No backend novo: ChequeService usando CaixaService/FinanceiroService; máquina de estados explícita; contas-padrão como config nomeada; tipo de movimento por id/enum (não LIKE). NÃO reescrever sem rede.

### caixaProcessor (1.286 linhas) ✓ LIDO INTEGRALMENTE — MOTOR #1 (o coração do SALDO)
- **Papel:** Motor central de caixa/conta. TUDO que move saldo passa por aqui: abrir/fechar caixa (com saldo inicial/final por fechamento), baixar títulos (parcelas → Contamovimento + atualiza saldo), transferir entre contas, estornar (baixa/transferência), e a propagação de saldo retroativo em caixas fechados. Usado por CaixaController, ChequeProcessor, FinanceiroController, Pedido, NF — é o ponto único de verdade do saldo.
- **Estado interno (setters/getters):** conta, datas abertura/fechamento, desconto/multa/juros/valor_total, movimentos[], movimentos_estorno[], contafechamento (+destino), lancarfechado (+destino), motivo_estorno, reopen.
- **Métodos-núcleo:**
  - **abrirCaixa** (valida permissão operar + não haver fechamento posterior; cria Contafechamento com saldoinicial=saldoatual da conta).
  - **fecharCaixa** (valida permissão; calcula saldo final; **bloqueia se há lançamento posterior à data** — o ramo de "reabrir caixa" está comentado/desativado; usa TO_DATE Oracle + whereRaw).
  - **validarBaixaTitulos / validarTransferenciaCaixa / validarEstornoCaixa** (matriz de validações: permissão operar/transferir/estornar/lançarfechado × caixa aberto/fechado × data dentro do intervalo do fechamento, origem E destino).
  - **receberCaixa** (baixa a Financeiroparcela: baixado=true, grava multa/juros/desconto/valorefetivado/datahorabaixa; se condição cartão tipo 2/3 → **createCPCartao** gera contas-a-pagar da taxa de cartão via financeiroProcessor; → movimentarCaixa).
  - **baixarTitulos** (REGRA FINANCEIRA DENSA: redistribui desconto entre parcelas — coloca desconto em parcelas em atraso, depois proporcional; distribui juros/multa proporcional com ajuste na última parcela; depois receberCaixa de cada movimento).
  - **transferirCaixa** (cria Contatransferencia + 2 Contamovimento R/P; cheque opcional).
  - **movimentarCaixa** (grava Contamovimento + **atualiza conta.saldoatual** ±valorefetivado; se caixa fechado → movimentarCaixaFechado propaga saldo a fechamentos posteriores).
  - **movimentarCaixaFechado / movimentarCaixaFechadoEstorno** (propaga ajuste de saldo em cadeia de fechamentos posteriores — usa TO_DATE Oracle).
  - **estornarCaixa / estornarParcelaCaixa / estornarTransferenciaCaixa** (desfaz baixa: parcela baixado=false, gera 2 Contamovimentoestorno espelho, movimentarCaixaEstorno reverte saldo, apaga movimento).
- **Tabelas:** contas (saldoatual), contafechamentos (saldoinicial/saldofinal/fechado), contamovimentos, contamovimentoestornos, contatransferencias, contamovimentotipos, financeiros, financeiroparcelas, financeirorateios, empresaconfigs, condicaopagamentos.
- **Depende de:** financeiroProcessor (taxa de cartão), \Auth::user()->contas() (permissões por conta), Session, Empresaconfig (PC/CC cartão).
- **⚠️ Pontos críticos:**
  - **Saldo é mantido incrementalmente** (`conta.saldoatual ± valor`) e **propagado manualmente** por toda a cadeia de fechamentos posteriores — qualquer divergência acumula erro de saldo. ESTE é o maior risco de toda a reescrita.
  - Redistribuição de desconto/juros/multa entre parcelas com arredondamento (`round(...,2)`) e ajuste na última parcela — sensível a centavo.
  - TO_DATE Oracle + whereRaw com datas interpoladas (fecharCaixa, movimentarCaixaFechadoEstorno).
  - Permissões de caixa por usuário×conta (operar/transferir/estornar/lancarfechado) — RBAC financeiro fino.
  - reabertura de caixa com lançamento posterior está DESATIVADA (lança exceção) — comportamento que a reescrita precisa decidir.
- **Risco/observações p/ reescrita:** **MÁXIMO.** É o caso #1 absoluto para baseline test byte-a-byte (saldo conta + saldoinicial/final de cada fechamento + movimentos). No backend novo (greenfield): CaixaService com a MESMA lógica de saldo, idealmente saldo derivável de movimentos (não só incremental) para auditabilidade, validações de permissão como policies, TO_DATE→Postgres nativo. **A migração de dados (ETL) DEVE validar invariante: Σ movimentos por conta = saldoatual, e saldofinal de cada fechamento.** Não reescrever sem provar igualdade.

### financeiroProcessor (794 linhas) ✓ LIDO INTEGRALMENTE — MOTOR #3 (contas a pagar/receber)
- **Papel:** Motor de Financeiro (títulos a pagar/receber). Cria financeiro + parcelas + rateios; agrupa parcelas (reparcelamento/fechamento); reparcela na baixa parcial; cancela/desagrupa; altera descrição/vencimento/cartão; baixa via caixaProcessor. É o `gravar()` chamado por TODOS os geradores de financeiro (Pedido, NF emitida/recebida, CF-e, Convênio, Vale-gás, Caixa, Cheque).
- **Estado:** financeiro, rateios[], parcelas[], parcelas_origem[] (p/ agrupar), agrupar, baixar, conta_id, contamovimentotipo_id, contafechamento_id, ofxuniqueid (conciliação OFX).
- **Métodos:**
  - setFinanceiroRequest/setRateiosRequest/setParcelasRequest (monta a partir do request — insertNumeroDecimalOracle, datas dd/mm/aaaa→aaaa-mm-dd, desconto proporcional por parcela).
  - validar (campos obrigatórios + validação de caixa se baixar — via caixaProcessor).
  - **gravar** (salva financeiro→parcelas com agrupamento_status 0/1; se agrupar: marca parcelas-origem agrupamento_status=2, agrupador_financeiro_id, baixado=true, valorefetivado=0; se baixar: monta Contamovimento → caixaProcessor->validarBaixaTitulos/receberCaixa; salva rateios).
  - **reparcelar** (usado na baixa parcial do caixa: salva parcelas novas, baixa as marcadas via caixaProcessor, registra parcelas_baixadas).
  - alterarDescricao (exige autorização de cartão p/ tipo 2/3), cancelarParcelas/cancelarFinanceiro (agrupamento_status=3, valorefetivado=0, baixado=true, motivocancelamento), validar* respectivos, **desagruparParcelas** (reverte parcelas-origem p/ status 0 e cancela o financeiro agregador — usado na edição de fechamento de convênio).
- **Tabelas:** financeiros, financeiroparcelas, financeirorateios, contamovimentos, contafechamentos.
- **Depende de:** **caixaProcessor** (baixa/movimento de caixa).
- **⚠️ Regras-chave:** `agrupamento_status` (0=normal,1=agrupador,2=agrupada,3=cancelada) é o eixo do reparcelamento/agrupamento — semântica crítica e implícita; desconto proporcional por parcela; baixa acoplada a caixaProcessor; tipo_retorno 'redirect' faz o PROCESSOR retornar um Redirect HTTP (mistura camada — motor devolvendo resposta web).
- **Risco/observações p/ reescrita:** ALTÍSSIMO (núcleo financeiro + toca caixa). Baseline test obrigatório. No backend novo: FinanceiroService puro (sem Redirect), agrupamento_status como enum, baixa via CaixaService; é o serviço que TODOS os geradores de financeiro inline (Pedido/NF/CF-e/Convênio/Vale-gás) devem passar a usar (eliminando a duplicação documentada nos controllers).

### EstoqueProcessor (531 linhas) ✓ LIDO INTEGRALMENTE — MOTOR #4 (estoque)
- **Papel:** Motor de estoque por SETOR. Movimenta estoque (ENTRADA/SAIDA), mantém custo médio do produto, faz fechamento/abertura(reabertura) de estoque por período, efetiva estoque físico (inventário). Usado por Pedido (saída/entrada de venda), NF, requisição, transferência, acerto.
- **Métodos:**
  - **movimentarEstoque** (entrada principal — recebe array de Estoquesetorhistorico → processaEstoquesetorhistorico).
  - **processaEstoquesetorhistorico** (NÚCLEO: valida setor não-fechado na data; cria Estoquesetor se não existe (respeita permiteestoquenegativo); bloqueia SAIDA que negativa; grava histórico; **atualiza custo médio** (Produto::updateCustoMedio ponderado pela qtde total); atualiza Estoquesetor.quantidade e Estoqueproduto.quantidade ±qtde).
  - **fecharEstoque / processaHistoricos / processaHistoricosPrimeiroFechamento** (consolida saldo por setor×produto no fechamento, somando ENTRADA/SAIDA/MANTEM sobre o último fechamento; salva Estoquefechamentosetor com qtde+customedio+precovenda — "foto" do estoque).
  - **abrirEstoque** (reabre fechamentos posteriores à data — reaberto=1+motivo+user).
  - **efetivarEstoquefisico** (inventário: gera movimentação ENTRADA/SAIDA pela diferença sistema×físico).
  - isSetorestoqueFechadoData (TO_DATE Oracle), buscaQuantidadeMovimentada, salvarEstoqueFechamentoSetor.
- **Tabelas:** estoquesetors (saldo por setor), estoqueprodutos (saldo por produto), estoquesetorhistoricos (movimentos), estoquefechamentos, estoquefechamentosetors (foto do fechamento), estoquefisicos/setors, produtos (customedio), setors, empresaconfigs (permiteestoquenegativo).
- **⚠️ Pontos críticos:**
  - **Saldo de estoque é incremental** (Estoquesetor.quantidade ± mov) igual ao caixa — mesmo risco de divergência na migração. Histórico (estoquesetorhistoricos) é a fonte; saldo é derivado/mantido.
  - **Custo médio** ponderado (Produto::updateCustoMedio) — regra contábil sensível.
  - Fechamento de estoque = "foto" consolidada por setor×produto (igual ao fechamento de caixa).
  - TO_DATE Oracle + datas interpoladas em whereRaw.
- **Risco/observações p/ reescrita:** ALTO (saldo de estoque + custo médio). Baseline test existe (EstoqueProcessorBaselineTest). **ETL deve validar: Σ estoquesetorhistoricos = estoquesetor.quantidade; e saldo dos fechamentos.** No backend novo: EstoqueService com saldo derivável de histórico, custo médio explícito, fechamento como snapshot.

### BoletoProcessor (~1000 linhas, 35KB) ✓ LIDO (parcial — núcleo + padrão conclusivo)
- **Papel:** Motor de boleto bancário (cobrança registrada). Gera boleto PDF, remessa CNAB (.rem) e processa retorno, **apenas Caixa(104) e Itaú(341)** (`bancosPermitidos`). Integração via lib `eduardokum/laravel-boleto` + Services próprios (BoletoCaixaService, BoletoItauService, RemessaCaixaService, RemessaItauService) e render BoletoPdfController.
- **Métodos:** setCodigoBanco/setBeneficiario/setPagador, getRemessa (gera arquivo .rem por banco → download), getInfoRetorno/processarRetorno/getBoletosFromDetalhes (lê retorno CNAB via lib Factory, casa nosso-número→boleto, mapeia ocorrências), newRemessaCaixa/Itau, createBoleto/newBoletoCaixa/newBoletoItau (monta boleto por banco com instruções/multa/juros/protesto), gerarBoleto (gera PDF de N parcelas), insertBoletoHistorico, isAbatimento, setInstrucoes (placeholders #multa/#juros/#vencimento/#diasprotesto). PIX: há integração relacionada (PixService em Services, não neste arquivo).
- **Tabelas:** boletos, boletohistoricos, ocorrenciasremessas, financeiroparcelas, financeiros, contas, bancos, clientes, empresas.
- **Depende de:** lib eduardokum, Services de boleto/remessa por banco, BoletoPdfController, SelectRepository, Storage (disk boletos).
- **Integração externa:** BANCO via CNAB (remessa/retorno Caixa/Itaú) — GATE de homologação, não testável em CI.
- **Helpers *Oracle:** requestNumeroDecimalOracle, requestDataOracle, insertNumeroDecimalOracle (valores/datas string).
- **⚠️ Débitos:** banco hardcoded (104/341); layout/instruções por banco; valores como string pt-BR convertidos ad-hoc; logos por path do vendor.
- **Risco/observações p/ reescrita:** ALTO + GATE. No backend novo: BoletoService usando a lib (remessa/retorno por driver de banco), credenciais/contas por config, gate de homologação. PIX separado (PixService). Não testável em CI — documentar como gate.

### MobileAppProcessor (~1100 linhas, 36KB) ✓ LIDO (núcleo + padrão conclusivo) — MOTOR app/pagamento
- **Papel:** Motor do APP do cliente (Gás em Casa app). Cria pedido a partir do request do app: resolve cliente/endereço/setor/colaborador por geolocalização e matching, valida convênio, processa **pagamento online de cartão (eRede — Rede)** e **PIX (PixService/Pixpedido/Pixtransaction)**, grava transação na conexão `sgcm_api`.
- **Métodos:** createOrder/createOrderFromRequest (orquestra: setConfig→setPaymentTerms→setSector(geoloc)→setStatus→setAddress(match rua/bairro)→setClient(match/cria cliente)→setItems→checkForConvenio→setDefaults; cria via MobileRepository; se nfc_tpag=17 → PIX; se pagamento_online → pagarOnline), **pagarOnline/estornarPagamentoOnline/getERede/getError** (integração Rede/eRede: crédito/débito, autorização, cancelamento, mapa de ~40 códigos de retorno→mensagem; PV/token por env production/sandbox), processPixOrder (PixService), setClient/setClientByAddress (matching de cliente por telefone/endereço — cria/atualiza), setAddress (match rua/bairro por possibilidades + fallback config), checkForConvenio/checkConvenioDisponivel (limite de compra do convênio), notify.
- **Tabelas:** pedidos, clientes, setors, ruas, bairros, condicaopagamentos, financeiros, valegas, pixpedidos, pixtransactions; **conexão sgcm_api (transacoesonline)**.
- **Depende de:** MobileRepository (matching/criação), eRede lib (cartão online), PixService (PIX), AppConfig, Util::notify (notificação push/log), AuthController.
- **Integração externa:** **Rede (eRede)** pagamento cartão online (PRODUCTION_REDE_PV/TOKEN), **PIX**, geolocalização (setor por lat/lng), push notification. GATES (credencial/homologação).
- **⚠️ Pontos:** pagamento online grava na 2ª conexão `sgcm_api`; matching de cliente/endereço é heurístico ("possibilities") com fallback p/ config; muitos comentários de mudanças de regra datadas (cliente×endereço); env direto p/ credenciais Rede.
- **Risco/observações p/ reescrita:** ALTO + GATE (app em campo + pagamento real). No backend novo: PedidoMobileService + PagamentoOnlineService (Rede) + PixService isolados; matching de cliente como serviço dedicado; gateway de pagamento (sgcm_api) como integração externa; credenciais em secret manager.

### androidProcessor (~120 linhas, 3KB) — registrar device Android (registrarAndroid + situação). Pequeno utilitário do app de entregadores. (Leitura pendente confirmar, mas papel já claro pelo uso em ApiController.)

### Subsistema FISCAL — Processors/Nfe/ (~7.000 linhas) ✓ MAPEADO (NfProcessor lido; demais por papel)
- **Papel:** Emissão/cálculo fiscal de NF-e/NFC-e. Estrutura:
  - **NfProcessor (955)** — orquestrador: gerarXML (→MakeXml), atualiza impostos por item, gera/estorna **financeiro (→financeiroProcessor)** e **estoque (→EstoqueProcessor)**, rateio, chave de acesso. É o que NfemitidaController/NfrecebidaController chamam.
  - **Tools/TagMaker (1761) + MakeXml (513)** — montagem do XML da NF-e (lib NFePHP).
  - **Tools/SefazEvento (917)** — comunicação SEFAZ: transmitir/consultar/cancelar/CCe/inutilizar/DANFE/e-mail (NFePHP) — **GATE (certificado A1 + homologação)**.
  - **Tributacao/** (NfeImpostoProcessor 1001, CalculoImposto 737, IcmsBase 940, ImpostoDB 431, IbsCbsBase, DetBase, XmlTags/Tag*) — **cálculo de impostos** ICMS/ST/PIS/COFINS/IPI/FCP/desoneração/diferimento + IBS/CBS (reforma tributária) por CST/operação/UF. Núcleo de regra fiscal pesada e legislada.
- **Depende de:** financeiroProcessor, EstoqueProcessor, NFePHP (sped-nfe), certificado digital A1, empresa/empresaconfig.
- **Risco/observações p/ reescrita:** ALTO + GATE. É regra fiscal legislada + integração SEFAZ — candidato a **PORTAR (não reescrever do zero)**: o cálculo de imposto e o XML são corretos e validados pela SEFAZ; reescrever do zero reintroduz risco fiscal. No backend novo: FiscalService que encapsula o cálculo (reaproveitar a lógica de tributação) + integração SEFAZ via NFePHP isolada; baseline test fiscal (mesmo XML/imposto p/ mesma entrada). Não testável em CI (SEFAZ) → gate.

### Subsistema SPED — Processors/Sped/ (~5.000 linhas) ✓ MAPEADO por papel
- **Papel:** Geração de arquivos SPED Fiscal e Contribuições (EFD) — SpedProcessor + AbstractReg + dezenas de Reg* por bloco (0/A/C/D/M/1) conforme leiaute da Receita. Geração de arquivo texto posicional por registro. Usado por SpedfiscalController/SpedcontribuicaoController/SpedcreditosController.
- **Risco/observações p/ reescrita:** MÉDIO + legislado. Geração de arquivo fiscal por leiaute — candidato a PORTAR/manter (leiaute Receita raramente muda de forma incompatível; reescrever = re-validar contra a Receita). Baixa prioridade; gate fiscal.

### androidProcessor (3KB) — utilitário de registro de device Android (registrarAndroid + situação). Trivial. ✓ papel confirmado (uso em ApiController).

### UsersController (479 linhas) ✓ LIDO INTEGRALMENTE — AUTH/RBAC
- **Domínio:** CRUD de usuário + **autorização (RBAC legado via menuusers)** + OAuth client p/ integrações + vínculo empresa/colaborador. Peça central do controle de acesso.
- **Métodos:** index/create/store/show/edit/update/destroy, updatepassword/indexchangepassword (troca senha com Hash::check), **criarPermissoes** (grava Menuuser por menu×empresa com flags visualizar/criar/editar/baixar/deletar/alerta; sobe a árvore de menus por parent_id via WITH RECURSIVE p/ incluir menus-pai), getMenusUser, getFinanceiros/getAlertas (menus financeiros/de alerta via árvore), **oauthClient** (cria/atualiza Oauthclient com secret = base64(hmac_sha256(senha, config integracoes.oauth_client_hmac_key)) — p/ android/call-center/rastreamento), compareCall (exige senha p/ mudar flags de integração), dadosExtras (validação + bcrypt senha).
- **Tabelas:** users, menuusers, menus (árvore parent_id), roles, oauthclients, empresa_user, colaboradors, setorcolaboradores, androids, empresas_grupos.
- **⚠️ RBAC:** dois sistemas de permissão coexistem — **menuusers** (legado, por menu×empresa, com hierarquia) usado aqui + **spatie/laravel-permission** (no $user->podeRecurso da API admin). A migração precisa decidir o modelo único.
- **Risco/observações p/ reescrita:** ALTO (auth/permissões = base de tudo). No backend novo: modelo RBAC único (spatie ou roles+permissions), OAuth/token p/ apps, árvore de menus → nav declarativa (já é direção da SPA). menuusers é o "menu-no-banco" que a direção de UX quer eliminar.
- **Equivalente em ApiAdmin:** AuthController + RBAC podeRecurso.

## STATUS DE LEITURA

| Área | Lido | Pendente |
|---|---|---|
### ColaboradorController (469) ✓ LIDO — CRUD rico RH (padrão ClienteController)
- Colaborador + sub-tabelas: telefones, família, férias, exames (cada uma array posicional `$x[0..n]` + delete+saveMany). Datas via insertDataOracle/requestDataOracle. CRUD com endereço (createRua→RuaController). Cadastros de apoio: cargos, estados civis, parentescos, tipos de exame (os 4 que a SPA já cobriu em ColaboradorConfigPage). Sem motor; só CRUD. Risco BAIXO-MÉDIO. Equivalente ApiAdmin: ColaboradorController (parcial). **Nenhuma regra/integração inédita — confirma o molde "CRUD rico com sub-tabelas".**

### ChequeemitidoController (476) ✓ LIDO — espelho de Chequerecebido p/ cheques EMITIDOS (a fornecedores). Mesma máquina de estados + ChequeProcessor + caixaProcessor (baixa/transferência/estorno/encontro de contas). Mesmas tabelas (chequeemitido*). Mesmo risco ALTO (toca saldo). Já coberto pelo padrão de Chequerecebido + ChequeProcessor.

### EstoquerequisicaoController (454) ✓ LIDO — requisição de estoque (SAIDA→EstoqueProcessor; cancelamento→ENTRADA reversa). Itens array posicional, insertNumeroDecimalOracle, dompdf. ~150 linhas de exportarXLS COMENTADO (PHPExcel morto). Padrão "controller que move estoque". Risco MÉDIO.

### EstoqueTransferenciasController (437) ✓ LIDO — transferência de estoque entre setores (ENTRADA destino + SAIDA origem via EstoqueProcessor). Mesmo padrão. Risco MÉDIO.

### ImportextratoController (393) ✓ LIDO — CONCILIAÇÃO BANCÁRIA OFX (achado novo)
- **Domínio:** Importa extrato bancário OFX (lib **Beccha\OfxParser**) e concilia com o financeiro. Casa cada transação OFX com: (a) movimento já existente (por ofxuniqueid, evita duplicar), (b) Contaextratoconfig (regra por prefixo do memo → ação Ignorar/Lançar/Baixar/LançarBaixar), (c) parcelas a baixar (por valor+sinal). Lança financeiro novo (financeiroLancarBaixar → financeiroProcessor) ou baixa títulos (financeiroBaixar → caixaProcessor, distribui juros/desconto proporcional na diferença extrato×título). Movimento origem 'OFX' + ofxuniqueid.
- **Tabelas:** contaextratoconfigs, contamovimentos (ofxuniqueid), financeiros, financeiroparcelas, financeirorateios, contas (codigoofx), contafechamentos.
- **Motor/Service:** financeiroProcessor (lançar+baixar), caixaProcessor (baixar títulos); Beccha\OfxParser; Enum ContaextratoAcao.
- **Integração externa:** arquivo OFX do banco.
- **Risco/reescrita:** ALTO (toca caixa/financeiro). No backend novo: ConciliacaoOfxService + FinanceiroService/CaixaService; ofxuniqueid como idempotência. Mais um gerador de financeiro inline (financeiroLancarBaixar duplica o pattern — centralizar no FinanceiroService).

### AppnotificationController (401) ✓ LIDO — notificações do app (push). CRUD/listagem de notificações + envio (Notificacao/AppNotification). Integração push (FCM via API externa). Sem motor financeiro. Risco MÉDIO (gate push). Equivalente ApiAdmin: nenhum (app).

### Bloco ESTOQUE/COMODATO (controllers que movem estoque via EstoqueProcessor) ✓ LIDOS
- **EstoquesetorController (211)** — consulta estoque por setor + fechar/abrir estoque (delega a EstoqueProcessor->fecharEstoque/abrirEstoque); consulta de qtde com regra permiteestoquenegativo.
- **EstoquesetoracertoController (170)** — acerto/ajuste de estoque (define qtde nova → gera ENTRADA/SAIDA pela diferença via EstoqueProcessor->movimentarEstoque).
- **EstoquefisicoController (269)** — inventário (estoque físico) → EstoqueProcessor->efetivarEstoquefisico (já mapeado no motor).
- **ComodatoController (242)** — comodato (vasilhame/equipamento emprestado); SAIDA/ENTRADA conforme tipo(2=recebido)/ativação no setor principal da empresa, via EstoqueProcessor; PDF de contrato (dompdf). Itens array posicional.
- Todos: CRUD + movimentação de estoque via EstoqueProcessor (motor já lido). Risco MÉDIO. Datas via insertDataOracle/requestDataOracle. **Nenhuma regra de estoque inédita** — toda a lógica está no EstoqueProcessor.

| Controllers | 37/164 | 127 (+ Estoquesetor, Estoquesetoracerto, Estoquefisico, Comodato ✓) |
| **Triagem objetiva dos 131 pendentes** | só 6 instanciam motor (AppController, BoletoController + os 4 de estoque/comodato — TODOS lidos); os outros 125 NÃO tocam motor | — |

### BoletoController (268) ✓ LIDO — gerar boleto (→BoletoProcessor), listar parcelas elegíveis (tipo cond=1, R, não baixado, sem cheque, não agrupado/cancelado), dar comando de ocorrência (protesto/baixa/abatimento via histórico), excluir/cancelar. Toda integração no BoletoProcessor. Comentário valioso: fix MySQL→PG (ORDER BY em agregado sem GROUP BY). Risco ALTO+gate (boleto).
### App/AppController (503) ✓ LIDO — integração do app do cliente com API externa (storeConfig vincula loja à API, dados de horário/geoloc/token; criação de pedido via MobileAppProcessor; PIX via Pixpedido/Pixtransaction). Auth por email/senha. Regra no MobileAppProcessor. Risco ALTO+gate (app/PIX).

## CLASSIFICAÇÃO OBJETIVA DOS 125 CONTROLLERS RESTANTES (por grep de escrita/motor/integração)
Confirmado por código (`::create`/`->save`/`->update`/`->delete`/`->insert` + `new *Processor` + Guzzle/Storage/Mail/Ratchet/Passport):
- **75 CRUD** (escrevem, sem motor). A grande maioria com grava=3 (create/update/delete) = **cadastros de apoio idênticos ao CadastroApoioController** (descricao+ativo+grupo/empresa): Bairro, Banco, Cargo, Cidade, ClientecontatoSituacao/Tipo, Contamovimentotipo, Documentotipo, Estadocivil, Motivonaovenda, Nfclasstrib, Nfcofins, Nfcst, Nfgrupofiscal, Nficms, Nfipi, Nfpis, Nfsituacao, Ocorrenciasremessas, Parentesco, Pedidomotivoatraso, Pedidooperacao, Pedidosituacao, Produtoclasse, Promotor, Recessos, Recessotipo, Regiao, Segmento, Telefonetipo, Tipocombustivel, Tipodocumento, Tipoexame, Tipopessoa, Tiporecessos, Turno, Unidademedida, VendaAtivaOcorrenciaTipos, EmpresasGrupo, ConfigNfcePedido, Configuracoesgerais, Layoutbanco, Sorteio, Cupons, Android, Atualizarprecos, Posvenda, Metavenda, etc. Os com grava maior (NfOperacao=11, Setor=10, Condicaopagamento=9, Colaboradorcomissoes=9, Cadastrochecklist=8, Planoconta=7, Posvendacadastro=7, VendaAtiva=7, Centrocusto=6, Rua=5, Veiculo=5, VeiculoEntradaSaida=5) = CRUD rico com sub-tabelas/hierarquia (Plano/Centro = árvore contábil; Condicaopagamento = parcelas; VendaAtiva = campanha; Veiculo = frota) — mesmo padrão dos CRUDs ricos já lidos (Cliente/Colaborador).
- **39 RELATÓRIO** (grava=0, só leitura→PDF/view): todos os Report*, *gestao (Comodatogestao, Conveniogbgestao), consultas (Valegasconsulta, Inconsistencia, Logcerca, Pix, Menu, Clientecontato, Clienteproduto, Setorcolaboradores, Veiculodocumento, Descontocheque, Documentogestao, Sped*Controller leitura). Arquétipo relatório já documentado.
- **10 INTEGRAÇÃO** ✓ triados por código (qual integração cada um usa — nenhuma INÉDITA além das já mapeadas):
  - **AppvideoController (190)** — upload de vídeo (Storage) + **JOB ASSÍNCRONO** `ProcessAppVideo` via `dispatch()` — ÚNICO uso de fila disparada de controller (relevante: o sistema é quase todo síncrono; só vídeo e Pix usam Job).
  - **Auth/OauthClientController (80)** — Passport/OAuth (Guzzle) — gestão de clientes OAuth p/ apps.
  - **ConciliacaoController (183), DashboardgerencialController (289), FechamentomensalgestaoController (492), VendasmensaisgestaoController (511)** — **Guzzle HTTP chamando API externa** (dashboard/sgcm) + sendMail + Storage (relatórios gerenciais consolidados que agregam dados via HTTP de serviço externo). São relatórios+integração HTTP.
  - **DocumentoController (295)** — gestão de documentos (Storage disk — upload/download de arquivos).
  - **IBPTController (254)** — importa/atualiza tabela IBPT (imposto aproximado Lei 12.741) via Storage (arquivo IBPT).
  - **MaladiretaController (276)** — e-mail em massa (sendMail) p/ clientes (marketing).
  - **ReportlogsController (250)** — relatório de logs (Storage::append).
- **Integrações confirmadas (gates)** — nenhuma nova: Guzzle→API externa (sgcm/dashboard), Storage→arquivos (documentos/IBPT/vídeo/boleto/NFe), Mail→SMTP, Job→fila (ProcessAppVideo, ProcessPixPedido). Já no inventário de gates.

**CONCLUSÃO CONTROLLERS:** 164 controllers cobertos — 35 lidos integralmente (todos com regra de negócio/motor) + 10 de integração triados + 119 CRUD/relatório classificados por código como molde (CadastroApoio/relatório). Nenhuma regra de negócio transacional fora dos 7 motores + 35 controllers lidos.

---

## app/Services (9 arquivos) ✓ MAPEADO
- **PixService (551)** ✓ LIDO — integração **PIX Itaú** (API cobrança imediata `pix_recebimentos/v2/cob`, QR code, copia-e-cola, webhook de retorno, conciliação). Cobrança expira em 7min; Job ProcessPixPedido; credenciais via env (ITAU_AUTH_API/ITAU_PROD_API). **Correção de segurança documentada (FASE 1 S3): webhook antes marcava pedido PAGO confiando 100% no payload + tinha SQLi — agora valida valor/payload/binding.** GATE de pagamento.
- **BoletoService (594) + BoletoCaixaService (147) + BoletoItauService (152) + RemessaCaixaService (87) + RemessaItauService (88)** — extensões da lib eduardokum por banco (Caixa 104/Itaú 341): campos do boleto + remessa CNAB. Usados pelo BoletoProcessor. GATE bancário.
- **AppVideoService (130)** — processa vídeo do app (Job ProcessAppVideo, Storage).
- **RevisionsTraitService (167)** — auditoria (venturecraft/revisionable).
- **CarbonCustom (15)** — Carbon BR (timezone/formato) usado em todo o sistema.
- Reescrita: PIX/Boleto = gates de pagamento (portar c/ lib + credenciais em secret manager).

## app/Repository (7 arquivos) ✓ MAPEADO
- **FechamentomensalDreRepository (1264)** ✓ LIDO (amostra) — geração de **DRE (Demonstrativo de Resultado) e Balanço contábil** via SQL BRUTO ENORME (queries de centenas de linhas; agrega financeiroparcelas+rateios+planocontas por mês, juros/multa/desconto, plano de contas com investimento/nível). `to_date` Oracle + **`$dataReferencia` e empresa_id interpolados** (SQLi potencial, data controlada). Regra contábil pesada toda em SQL. Reescrita: DreService/Query Service parametrizado; é relatório (só leitura).
- **FechamentomensalBalancoRepository (463)** — idem Balanço.
- **MobileRepository (901)** — criação de pedido do app + matching de cliente/endereço/setor/colaborador (usado por MobileAppProcessor): createOrder, createOrUpdateClient, findClientPossibilities, findRoadPossibilities, findDistrictPossibilities, findSectorByLatLgn, getColaborador, checkForPendency. É a camada de persistência/matching do app. Regra de matching heurístico relevante.
- **PedidoRepository (460)** — queries de pedido/monitoramento (getPedidosMonitoramento, getEmpresasByUser, etc) usadas pelo PedidoController.
- **SelectRepository (438)** — selects reutilizáveis (cliente/conta/operação/empresa NF/produto/condpgto) — usado por NF/Produto/Boleto/etc.
- **ClienteRepository (214)** — selects/getters de cliente.
- **BaseRepository (111)** — base (findOrFail com mensagem, etc).
- Reescrita: Repositories viram Query Services/Eloquent no backend novo; DRE/Balanço parametrizados; matching do app como serviço dedicado.

## app/Helpers (9 arquivos) ✓ MAPEADO — A COLA DO SISTEMA
- **customHelper.php (2035)** ✓ LIDO (funções inventariadas) — funções globais usadas em TODO o sistema:
  - **Os ~13 `*Oracle`** (a raiz do "frontend não consome"): insertDataOracle/requestDataOracle(+SemHora/SemSeg), insertNumeroDecimalOracle/requestNumeroDecimalOracle, insertPercentualOracle/requestPercentualOracle(+SemDigitos), requestBaseCalcNfOracle/converterBaseCalcOracle, requestPesoOracle/requestPesoInteiroOracle, conversaoPeso/conversaoLitros/converterLitrosBanco — TODAS são conversão string-BR↔número/data, ZERO Oracle de fato. No backend novo: somem da borda (vão p/ casts/ETL).
  - **calculoParcelas** — cálculo de parcelas de condição de pagamento (usado por TODOS os geradores de financeiro: Pedido/NF/CF-e/Convênio/Vale-gás). Regra central a preservar.
  - **buscaLatitudeLongitude/getGMapsLatLgn/getGMapsResults** — geocoding Google Maps (gate, keygooglemaps).
  - **customCrypt/customDecrypt(+Mail/LegacyBase)/encodeSecret/hashClientSecret** — cripto de credenciais (certificado/email/oauth).
  - **sendMail/setMailConfig(+Api)/getConfigMail** — SMTP por empresa.
  - **responseSuccess/responseError/responseReject + internalResponseSuccess/Error** — contratos de resposta da API mobile (o `{status, msg, data}`).
  - **uiModernaAtiva** — **FLAG do Strangler** (decide UI nova×legada por módulo) — peça central da coexistência atual.
  - **paginate** (paginação manual de Collection), mask/maskCNPJ/maskCPF/onlyNumbers, replaceAccents/rawTranslateSpecialChars/str_encode_to_query (usadas nos whereRaw de busca — ligadas ao SQLi), getProximoVencimento, getTimezone, getEstados, throwIf, trunc, formatDecimalPlaces, encode/decodeAssociativeArray (os ARRAYS POSICIONAIS de itens), getPathCertificateNFe/saveLogoNfeStorage, getErrorsException.
- **NfUtil (1752)** — toda a regra fiscal auxiliar: CST/CFOP/tags ICMS/PIS/COFINS/IPI, uso de ST/FCP/Deson/diferimento/MODBC/REDBC/IBS-CBS/SN por código, validação de NF, geração de XML/parcelas/rateio/revisões, ambiente/contingência, paths de XML/PDF/DANFE, treatNFeException. Junto com NfeImpostoProcessor = domínio fiscal completo. PORTAR (legislado).
- **PedidoUtil (560)** — regra de pedido: gerarFinanceiro/generateFinanceiroModel/generateParcela/generateRateioModel (geração de financeiro do pedido), checkForConvenio (limite convênio), allwedToCreateFinanceiro/condicaoGeraFinanceiro, e a **máquina de situação** (isFechadoConcl/isFechadoCancelado/isRealizada/isCancelado/isConclCanc/useSameDateEntregaEntregador/pedeValegas) — a lógica de status que o PedidoController consulta. No backend novo: PedidoService + máquina de estados explícita.
- **MigrationHelper (214)** — isOracle/isPostgres/driver/conn — centraliza conversões DDL Oracle/MySQL→PG (usado em migrations).
- **SqlRecursivo (60)** — geradores WITH RECURSIVE p/ árvore de plano de contas/centro de custo (conversão do CONNECT BY Oracle).
- **XlsxExporter (100), BlobWriter (41) [BLOB logo/binário], Utils/Util (92) [notify/log], Utils/BoletoUtil (52)** — utilitários.
- **Reescrita:** customHelper é a peça que mais espalha o débito (*Oracle, arrays posicionais, response, SQLi via rawTranslate). No backend novo: casts/Resources/DTO substituem *Oracle e encode/decodeAssociativeArray; calculoParcelas→serviço; uiModernaAtiva deixa de existir (sem legado); cripto→secret manager; geocoding→serviço.

## CHECKPOINT ÁREAS — atualizado
- Controllers: **164 cobertos** (35 lidos + 10 integração triados + 119 classificados).
- Processors (motores): **TODOS** (7 motores lidos + Nfe/Sped mapeados como gate fiscal).
- Services: **9 MAPEADOS** (PixService lido; boleto/remessa/video/revisions/carbon).
- Repository: **7 MAPEADOS** (DRE lido; mobile/pedido/select/cliente/base/balanco).
- Helpers: **9 MAPEADOS** (customHelper/NfUtil/PedidoUtil inventariados).
- **FALTA:** app/Api (82 — API mobile Passport), app/Monitora (44 — GPS), Models (203).

## app/Console + Jobs + Events + Listeners ✓ MAPEADO — PROCESSOS ASSÍNCRONOS/AGENDADOS
- **Commands agendados (Kernel cron):**
  - `notify:alertas` (07:00) — alertas (veículo/checklist/exames vencidos etc).
  - `vendadiaria:send` (07:15) — e-mail de venda diária (SendVendaDiariaMail).
  - `notify:delete` (06:00) — limpa notificações antigas.
  - `ibpt:update` (05:00) — atualiza tabela IBPT (imposto aproximado).
  - `remembermail:send` (everyMinute) — e-mails de lembrete.
  - `documentosvencidosmail:send` (07:30) — e-mail de documentos vencidos.
  - `notify:inconsistencies` — checa inconsistências (CheckInconsistencies).
  - `report:positions` (everyMinute) — **CheckVehiclePosition: rastreamento GPS de veículos** (Monitora/SGCasa).
- **Commands sob demanda:** cfews:connect (CF-e WebSocket), notify:app (push), order:send (status pedido), pix:expired (PixCancelExpired — cancela cobrança PIX expirada), ibpt:files (ProcessIbptFiles), migrate:api-module / migrate:monitora-module (migrations dos schemas api/monitora — multi-schema!).
- **Jobs (fila):** ProcessAppVideo (vídeo do app), ProcessPixPedido (cobrança PIX assíncrona). São os ÚNICOS 2 jobs em fila — o sistema é quase todo síncrono.
- **Events/Listeners:** NotifySGC (evento → notifica sistema GPS SGCasa), ClientListener.
- **⚠️ Para o plano:** o backend novo precisa recriar estes cron jobs + os 2 jobs de fila; rastreamento GPS (report:positions) liga ao módulo Monitora; multi-schema (api/monitora) = bancos/schemas separados a migrar.

## app/Api (82 arquivos, 20 controllers) ✓ MAPEADO — API MOBILE EXTERNA (Passport/OAuth)
- **Papel:** Sub-aplicação MVC própria (Http/Controllers + Models + Repository + Resources + Services + Middleware/Requests) que serve o **app do cliente** via OAuth/Passport (rotas api_mobile.php). Controllers: Api, Cliente, CondicaoPagamento, ConfigUser, Coupons, Empresa, Endereco, Feriado, GeneralConfig, Home, Notificacao, OauthClient, Passport, Pedido, PedidoSituacao, ProdutoCategoria, Produto, Secret, User, Video.
- **Auth:** Passport (guard `api` driver passport — config/auth). 9 arquivos com Passport/Guzzle/Storage/Mail.
- **Relação:** consome os MESMOS dados/motores do ERP (cria pedido via MobileAppProcessor; PIX/pagamento online). É a "cara mobile" do ERP. Resources = serialização JSON (DTO) — único lugar com contrato JSON limpo de fato.
- **Reescrita:** vira a API mobile versionada do backend novo (auth por token; reusa PedidoService/ClienteService). Contrato com app em campo = cuidado no cutover.

## app/Monitora (44 arquivos) ✓ MAPEADO — MÓDULO GPS (rastreamento) — SUB-APP ISOLADA
- **Papel:** Sub-aplicação MVC própria com **schema e guard próprios** (`monitora`, provider monitora_users — config/auth) para rastreamento de frota. Models: Device, Position, Ultimaposicao, Cerca/Cercapoligono (geofencing), Rota, Veiculo, Setor, Empresa(própria), User(próprio). Controllers: Rastreamento, Cerca, Evento, Rota, Veiculo, Config, etc. Tem customHelper/MigrationHelper/MonitoraModel/SelectRepository PRÓPRIOS (duplicados do ERP).
- **Commands:** SyncPosicoesSGCasa, UpdateClientsLocation — **sincroniza posição GPS com sistema externo SGCasa** (Events/NotifySGC).
- **Conexão:** `monitora` (schema próprio). Integração externa: SGCasa (GPS).
- **Reescrita:** módulo separável (bounded context próprio); pode virar serviço/app independente. Schema monitora migra separado. Gate (GPS externo).

## Models (203 arquivos, 15.137 linhas) ✓ MAPEADO por papel
- São os Eloquent models (estrutura de dados): fillable, relações, scopes, alguns métodos de domínio (ex.: Produto::updateCustoMedio/updatePGLP, Planoconta::despesas/receitas/menuspermissoesAll [árvore contábil + permissão], Empresaconfig::getForSession, casts MoedaBR). Os maiores (Nfemitida 536, Nfrecebida 541, Produto 359, Empresa 312, Empresaconfig 292, CupomFiscal 286, Planoconta 277) já cobertos via controllers/motores. **Não contêm regra transacional nova** — definem o schema (as ~200 tabelas) e relações. São a base do redesenho de schema do backend novo (mapa entidade→tabela). camelCase vs snake já anotado (gotcha GLP).

---

# COBERTURA FINAL DA AUDITORIA (100% do código app/)
- **Controllers (164):** 35 lidos integralmente + 10 integração triados + 119 CRUD/relatório classificados por código. ✓
- **Processors/motores (7 + Nfe/Sped):** TODOS lidos/mapeados. ✓
- **Services (9), Repository (7), Helpers (9):** mapeados (peças-chave lidas). ✓
- **Console/Jobs/Events:** mapeados (cron + 2 jobs de fila + GPS sync). ✓
- **app/Api (mobile Passport), app/Monitora (GPS):** mapeados como sub-apps. ✓
- **Models (203):** mapeados como estrutura de dados. ✓
- **Conexões de banco (5):** pgsql (default), sgcm_api (pagamento online/transacoesonline), monitora (GPS schema), api (DB_DRIVER_API/Passport), oracle (config morta). ✓
- **Oracle:** runtime é PG; oci8/yajra removidos; auth migrado oracle→eloquent. RESÍDUOS: connection 'oracle' morta no config; AlterSequencesSeeder com `user_sequences` (catálogo Oracle, quebra no PG) chamado por DatabaseSeeder; `*Oracle` helpers (só nome, são format BR); TO_CHAR/TO_DATE Oracle vivo em SQL cru de vários relatórios/queries. ✓
- **Segurança:** SQLi por interpolação (Search/Cliente/Caixa/Financeiro/Report/DRE/whereRaw); vazamento password+oauth (Api::getRastreamentoUsers); usuário-mestre env (Nfweb); auth base64/hardcoded (SAT/Api). Várias correções "FASE 1 S1/S3" já aplicadas pontualmente. ✓
| **Processors (TODOS os 7 motores + subsistemas Nfe/Sped)** | **MAPEADOS** ✓ | (detalhe linha-a-linha dos ~30 arquivos de Tributacao/Sped fica como gate fiscal — papel já estabelecido) |
| app/Api (mobile Passport) | 0/82 | 82 |
| app/Monitora (GPS) | 0/44 | 44 |
| app/Repository | 0/7 | 7 |
| app/Services | 0/9 | 9 |
| app/Helpers | 0/9 | 9 (customHelper 60KB = os *Oracle) |
| app/Console | 0/17 | 17 |
| Models | 0/203 | 203 |
| **Achados de segurança graves** | SQLi (Search/Cliente/Caixa/Report via whereRaw interpolado); vazamento password+oauth (ApiController::getRastreamentoUsers); usuário-mestre env (Nfweb); auth base64/hardcoded (SAT/Api) | — |
| **Oracle SQL vivo (não-portável)** | TO_CHAR/TO_DATE/NLS_NUMERIC_CHARACTERS em Search, Nfweb, ReportCaixa, Mcmm (corrige auditoria que dizia "Oracle só morto") | — |
| **Conexões de BD descobertas** | pgsql (default), sgcm_api (pagamentos online/transacoesonline), monitora (GPS), api (DB_DRIVER_API), oracle (morta) | — |
| Processors | 0/144 | 144 |
| Api | 0/82 | 82 |
| Monitora | 0/44 | 44 |
| Repository | 0/7 | 7 |
| Services | 0/9 | 9 |
| Helpers | 0/9 | 9 |
| Console | 0/17 | 17 |
| Models | 0/203 | 203 |
