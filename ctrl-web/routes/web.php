<?php

/*
  |--------------------------------------------------------------------------
  | Web Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register all of the routes for an application.
  | It's a breeze. Simply tell Laravel the URIs it should respond to
  | and give it the controller to call when that URI is requested.
  |
 */

use Illuminate\Http\Request;

Route::get('/', function () {
    // Raiz redireciona para o login (antes mostrava a página "welcome" padrão
    // do Laravel). Usuários autenticados serão levados ao /home pelo fluxo de auth.
    return redirect('/login');
});

////Rotas antigas

Route::group(['middleware' => ['web']], function () {
    //  Route::resource('/empresas_grupo_form', ['middleware' => 'auth', 'as' => 'empresas_grupo_form', 'uses' => 'EmpresasGrupoController@form']);
});
Route::get('/login', ['as' => 'login', 'uses' => 'AuthController@login']);
Route::post('/handleLogin', ['as' => 'handleLogin', 'uses' => 'AuthController@handleLogin']);
Route::get('/logout', ['as' => 'logout', 'uses' => 'AuthController@logout']);
Route::get('/changepassword', ['middleware' => 'auth', 'as' => 'changepassword', 'uses' => 'UsersController@indexchangepassword']);
Route::patch('/updatepassword/{id}', ['middleware' => 'auth', 'as' => 'user.updatepassword', 'uses' => 'UsersController@updatepassword']);

Route::group(['middleware' => ['auth', 'pode'], 'where' => ['id' => '[0-9]+']], function () { //,'pode'
    Route::get('/home', ['as' => 'home', 'uses' => 'UsersController@home']);

    //Menu Cadastro
    //Androids
    Route::resource('android', 'AndroidController');
    Route::get('getAndroidData/{id}', 'AndroidController@getAndroidData');
    Route::get('testAndroidNotify/{id}', 'AndroidController@testAndroidNotify');

    //Configurações Gerais
    Route::get('configuracoesGerais', ['as' => 'configuracoesGerais.index', 'uses' => 'ConfiguracoesgeraisController@index']);
    Route::get('monitoramento.acesso', ['as' => 'monitoramento.acesso', 'uses' => 'ConfiguracoesgeraisController@acessoMonitoramento']);
    Route::post('configuracoesGerais.store', ['as' => 'configuracoesGerais.store', 'uses' => 'ConfiguracoesgeraisController@store']);

    //Empresas
    Route::resource('empresa', 'EmpresaController');
    Route::get('/empresachange/{id}', ['middleware' => 'auth', 'as' => 'empresa.change', 'uses' => 'EmpresaController@change']);
    Route::get('empresa/carregaempresa/{id}', 'EmpresaController@carregaempresa');
    Route::get('empresa/getMatrizGrupo/{id}', 'EmpresaController@getMatrizGrupo');

    //Grupos de Empresas
    Route::resource('empresas_grupo', 'EmpresasGrupoController');

    //Layout Cobranças
    Route::resource('layoutbancos', 'LayoutbancoController');

    //Ocorrencia de Remessas
    Route::resource('ocorrenciasremessas', 'OcorrenciasremessasController');

    //Regiao
    Route::resource('regiao', 'RegiaoController');

    //Usuários
    Route::resource('user', 'UsersController');

    //Promotores de Vendas
    Route::resource('promover', 'PromotorController');
    Route::get('promotor.getClienteByNome', 'PromotorController@getClienteByNome');
    Route::get('promotor.getStreet', 'PromotorController@getStreet');
    Route::get('promotor.getNeighborhood', 'PromotorController@getNeighborhood');
    Route::get('promotor.getClientByStreet', 'PromotorController@getClientByStreet');
    Route::get('promotor.getHistoricoCliente/{cliente_id}/{limit}', 'PromotorController@getHistoricoCliente');

    //Roles
    Route::resource('roles', 'RoleController');
    Route::get('definirtipos', ['as' => 'definir.index', 'uses' => 'RoleController@definicao']);
    Route::post('definirstore', ['as' => 'definir.store', 'uses' => 'RoleController@definicaoStore']);

    //Clientes
    Route::resource('cliente', 'ClienteController');
    //Ajax Clientes
    Route::get('cliente/ativacliente/{id}', ['as' => 'ajax.ativacliente', 'uses' => 'ClienteController@ativaCliente']);
    Route::get('/cliente/comodato/{tipo}', ['as' => 'ajax.comodato', 'uses' => 'ClienteController@entradaSaida']);
    Route::get('/cliente/comodato/show/{id}', ['as' => 'ajax.comodatoshow', 'uses' => 'ClienteController@exibeDadosCliente']);
    Route::get('cliente/buscaporid/{id}', ['as' => 'ajax.buscaporid', 'uses' => 'ClienteController@buscaPorId']);
    Route::get('api/buscaClientePorId/{id}', 'SearchController@buscaClienteById');
    Route::get('/pedido/geraEmite/{id}', ['as' => 'ajax.pedido/geraEmite/{id}', 'uses' => 'PedidoController@geraEmite']);
    Route::post('/pedido/updateDadosNf/{id}', ['as' => 'ajax.pedido/updateDadosNf/{id}', 'uses' => 'PedidoController@updateDadosNf']);
    //clientetelefone ajax
    Route::get('clienteendereco/buscaclienteendereco', ['as' => 'ajax.buscatelefone', 'uses' => 'ClienteController@buscaClienteEndereco']);
    //clientetelefone ajax
    Route::get('updatecampocliente/{id}', ['as' => 'ajax.updatecampocliente', 'uses' => 'ClienteController@updateCampoCliente']);
    //contrato convênio
    Route::get('cliente/contrato/{id}', ['as' => 'cliente.contrato', 'uses' => 'ClienteController@contrato']);
    //etiquetas convenio
    Route::get('cliente/etiquetas/{apartir}/{id}', ['as' => 'cliente.pdf', 'uses' => 'ClienteController@imprimirEtiquetasConvenio']);
    Route::get('/clienteconveniado/{id}', ['as' => 'ajax.clienteconveniado', 'uses' => 'ClienteController@fechamentoConvenio']);
    //Ajax Pedidos
    Route::get('cliente.createFromPedidos/{empresa_id}', ['as' => 'cliente.createFromPedidos', 'uses' => 'ClienteController@createFromPedidos']);
    Route::get('cliente.editFromPedidos/{id}', ['as' => 'cliente.editFromPedidos', 'uses' => 'ClienteController@editFromPedidos']);

    //promoções
    Route::resource('promocao', 'PromocaoController');
    //ajax promocoes
    Route::get('buscaPromocaoAjax/{id}', ['as' => 'ajax.buscapromocao', 'uses' => 'PromocaoController@buscaPromocaoAjax']);

    //Segmento
    Route::resource('segmento', 'SegmentoController');

    //Situação de Contatos
    Route::resource('clientecontatosituacao', 'ClientecontatosituacaoController');

    //Tipo de Contatos
    Route::resource('clientecontatotipo', 'ClientecontatotipoController');

    //Tipo de Pessoa
    Route::resource('tipopessoa', 'TipopessoaController');

    //Tipo de Telefones
    Route::resource('telefonetipo', 'TelefonetipoController');

    //Cargos
    Route::resource('cargo', 'CargoController');

    //Colaborador
    Route::resource('colaborador', 'ColaboradorController');
    //Ajax Colaboradores
    Route::get('colaborador/buscaColaboradorPorSetor/{setor_id}', ['as' => 'ajax.colaboradorsetor', 'uses' => 'ColaboradorController@buscaPorSetor']);

    //Estado Civil
    Route::resource('estadocivil', 'EstadocivilController');

    //Parentesco
    Route::resource('parentesco', 'ParentescoController');

    //Tipos de Exame
    Route::resource('tipoexame', 'TipoexameController');

    //Banco
    Route::resource('banco', 'BancoController');

    //Centro de Custos
    Route::resource('centrocusto', 'CentrocustoController');
    //Centrocusto Ajax
    Route::get('centrocustoajax/{id}/{planocaontaspai_id}', ['as' => 'ajax.centrocusto', 'uses' => 'CentrocustoController@centroCustoAjax']);
    Route::get('centrocusto/carregacentrocustoid/{id}', ['as' => 'ajax.centrocustoporid', 'uses' => 'CentrocustoController@centroCustoPorIdAjax']);

    //Condições de Pagamento
    Route::resource('condicaopagamento', 'CondicaopagamentoController');

    //Conta
    Route::resource('conta', 'ContaController');
    Route::post('conta.extratoconfig', ['as' => 'conta.extratoconfig', 'uses' => 'ContaController@addEditExtratoconfig']);

    //Planoconta
    Route::resource('planoconta', 'PlanocontaController');
    //Planoconta Ajax
    Route::get('planocontaajax/{id}/{planocontaeditando_id}', ['as' => 'ajax.planoconta', 'uses' => 'PlanocontaController@planoContaAjax']);
    Route::get('planoconta/carregaplanocontaid/{id}', ['as' => 'ajax.planocontaporid', 'uses' => 'PlanocontaController@planoContaPorIdAjax']);
    Route::get('api/getPcCcById', ['as' => 'ajax.getPcCcById', 'uses' => 'SearchController@getPcCcById']);

    //Tipos de Movimentos de Contas
    Route::resource('contamovimentotipo', 'ContamovimentotipoController');

    //Configurações Pedidos NFC-e
    Route::get('confgNfcePedido', ['as' => 'confgNfcePedido.index', 'uses' => 'ConfigNfcePedidoController@index']);
    Route::post('confgNfcePedido', ['as' => 'confgNfcePedido.store', 'uses' => 'ConfigNfcePedidoController@store']);

    //NFe COFINS
    Route::resource('nfcofins', "NfcofinsController");

    //NFe ICMS
    Route::resource('nficms', "NficmsController");

    //NFe IBS CST
    Route::resource('nfcst', "NfcstController");

    //NFe IBS CST
    Route::resource('nfclastrib', "NfclasstribController");

    //NFe IPI
    Route::resource('nfipi', "NfipiController");

    //NFe PIS
    Route::resource('nfpis', "NfpisController");

    //Grupo Fiscal
    Route::resource('grupofiscal', 'NfgrupofiscalController');

    //Grupo Fiscal
    Route::resource('ibpt', 'IBPTController');

    //Nfe Impostos
    Route::get('nfimposto/createByNf', 'ImpostonfController@createByNf')->name("nfimposto.createByNf");
    Route::resource('nfimposto', 'ImpostonfController');
    Route::get('nfimposto/filter/{operacao}/{grupofiscal}', ['as' => 'nfimposto.filtro', 'uses' => 'ImpostonfController@filter']);
    //Ajax Impostos
    Route::get('nfimposto/ajaxnatureza/{id}', ['as' => 'ajax.impostonatureza', 'uses' => 'ImpostonfController@ajaxNatureza']);

    //NFe operação
    Route::resource('nfoperacao', "NfOperacaoController");
    //NF Operacao Ajax
    Route::get('nfoperacao/carregaoperacaoid/{id}', ['as' => 'ajax.operacaocarregar', 'uses' => 'NfOperacaoController@carregaoperacaoid']);

    //Motivos de não venda
    Route::resource('motivonaovenda', 'MotivonaovendaController');

    //Motivo Atraso Pedido
    Route::resource('pedidomotivoatraso', 'PedidomotivoatrasoController');

    //Operações de pedidos
    Route::resource('pedidooperacao', 'PedidooperacaoController');

    //Situacoes de pedidos
    Route::resource('pedidosituacao', 'PedidosituacaoController');
    // busca os status dos pedidos
    Route::get('pedidosituacao/buscaPorEmpresa/{empresa_id}', ['as' => 'ajax.pedidosituacaoempresa', 'uses' => 'PedidosituacaoController@buscaPorEmpresa']);

    //Tipos de Ocorrencia
    Route::resource('vendaativaocorrenciatipos', 'VendaAtivaOcorrenciaTiposController');

    //Comissões
    Route::resource('colaboradorcomissoes', 'ColaboradorcomissoesController');

    //Meta de vendas
    Route::resource('metavenda', 'MetavendaController');
    // M1: esta rota POST reusava o nome 'metavenda.update' já criado pelo resource
    // (PUT/PATCH) → nome de rota DUPLICADO, que faz `php artisan route:cache` falhar
    // ("Unable to prepare route ... Another route has already been assigned name").
    // Renomeada p/ 'metavenda.updatePost' (mesma URI/ação; nenhum view referencia o nome).
    Route::post('metavenda/{id}', ['as' => 'metavenda.updatePost', 'uses' => 'MetavendaController@update']);

    //recessos
    Route::resource('recessos', 'RecessosController');

    //Tipos de recessos
    Route::resource('tiporecessos', 'TiporecessosController');

    //Empresa Bens
    Route::resource('empresabens', 'EmpresabensController');

    //Cadastro de Checklist
    Route::resource('cadastrochecklist', 'CadastrochecklistController');
    Route::get('checklist.verificarperiodo', ['as' => 'ajax.checklistverificarperiodo', 'uses' => 'CadastrochecklistController@verificarPeriodo']);

    //Cadastro de Pós-Venda
    Route::resource('posvendacadastro', 'PosvendacadastroController');
    Route::get('/posvendacadastro.verificarperiodo', ['as' => 'ajax.posvendacadastroverificarperiodo', 'uses' => 'PosvendacadastroController@verificarPeriodo']);

    //Setor
    Route::resource('setor', 'SetorController');

    //Classes de Produto
    Route::resource('produtoclasse', 'ProdutoclasseController');

    //Produto
    Route::resource('produto', 'ProdutoController');
    //clienteprecos ajax
    Route::get('clienteproduto/buscaprecosprodutos/{cliente_id}', ['as' => 'ajax.clientepreco', 'uses' => 'ClienteprodutoController@buscaPrecos']);
    Route::get('produto/ajax/{id}', ['as' => 'ajax.produtoajax', 'uses' => 'ProdutoController@ajax']);
    Route::get('produto/buscaporsetor/{id}', ['as' => 'ajax.produtoporsetor', 'uses' => 'ProdutoController@buscaporsetor']);
    Route::get('produto/buscaporclasse/{classe_id}', ['as' => 'ajax.produtoclasse', 'uses' => 'ProdutoController@buscaporclasse']);
    Route::get('produto/ajaxncmcest/{id}', ['as' => 'ajax.ncmcest', 'uses' => 'ProdutoController@ajaxncmcest']);
    Route::get('produto/buscaporsetornf/{id}', ['as' => 'ajax.produtosetornf', 'uses' => 'ProdutoController@buscaPorSetorNF']);
    Route::get('produto/buscaporsetornfentrada/{id}', ['as' => 'ajax.produtosetornfentrada', 'uses' => 'ProdutoController@buscaPorSetorNFEntrada']);
    Route::get('issetImpostoProdutoAjax', ['as' => 'ajax.issetImpostoProdutoAjax', 'uses' => 'SearchController@issetImpostoProdutoAjax']);

    //Unidades de Medida
    Route::resource('unidademedida', 'UnidademedidaController');

    //Atualizar Preços
    Route::resource('atualizarprecos', 'AtualizarprecosController');

    //Veiculos tipo de Combustivel
    Route::resource('tipocombustivel', 'TipocombustivelController');

    //Tipo Documento
    Route::resource('tipodocumento', 'TipodocumentoController');

    //Tipos Veiculos
    Route::resource('veiculotipo', 'VeiculotipoController');

    //Veiculos
    Route::resource('veiculo', 'VeiculoController');
    //ajax veiculo
    Route::get('veiculo/buscarveiculosajax/{id}', ['as' => 'ajax.buscaveiculo', 'uses' => 'VeiculoController@buscarVeiculoAjax']);

    // Envio de notificacoes ao app
    Route::resource('appnotification', 'AppnotificationController');
    Route::patch('appnotification.send/{id}', ['as' => 'appnotification.send', 'uses' => 'AppnotificationController@sendNotification']);

    // Upload de video para app
    Route::get("appvideo.index", ["as" => "appvideo.index", "uses" => "AppvideoController@index"]);
    Route::post("appvideo.store", ["as" => "appvideo.store", "uses" => "AppvideoController@store"]);
    Route::patch("appvideo.update/{id}", ["as" => "appvideo.update", "uses" => "AppvideoController@update"]);

    Route::get('cupons/gerarcodigo', ['as' => 'ajax.gerarcodigo', 'uses' => 'App\CuponsController@gerarCodigo']);
    Route::resource('cupons', 'App\CuponsController');

    // Giro de compras no app
    Route::get('appgiro.index', ['as' => 'appgiro.index', 'uses' => 'AppgiroController@index']);
    Route::get('appgiro.getGiro', ['as' => 'appgiro.getGiro', 'uses' => 'AppgiroController@getGiro']);
    Route::post('appgiro.notify', ['as' => 'appgiro.notify', 'uses' => 'AppgiroController@notifyDevices']);

    //Operações
    //Ajustes de Estoque
    Route::resource('estoquesetor', 'EstoquesetoracertoController');
    Route::get('estoquesetor/buscaajax/{setor_id}/{produto_id}', ['as' => 'ajax.prodporsetor', 'uses' => 'EstoquesetoracertoController@buscaPorSetorProduto']);

    //Consulta estoque setor
    Route::resource('consultaestoquesetor', 'EstoquesetorController');
    //Ajax
    Route::get('consultaestoquesetor/consultaEstoqueSetor/{setor}', ['as' => 'ajax.consultaestoquesetor', 'uses' => 'EstoquesetorController@consultaEstoqueSetor']);
    Route::get('consultaestoquesetor/buscaquantidadeproduto/{produto_id}/{setor_id}', ['as' => 'ajax.quantidadeproduto', 'uses' => 'EstoquesetorController@consultaQuantidadeProduto']);
    Route::get('consultaestoquesetor/buscaquantidadeprodutopermitenegativar/{produto_id}/{setor_id}/{qtdemovimentar}', ['as' => 'ajax.quantidadeprodutopermitenegativar', 'uses' => 'EstoquesetorController@consultaQuantidadeProdutoPermiteNegativar']);
    Route::get('consultaestoquesetor/buscaquantidadeprodutopermitenegativar/{produto_id}/{setor_id}/{qtdemovimentar}/{entradasaida}', ['as' => 'ajax.quantidadeprodutopermitenegativar.entradasaida', 'uses' => 'EstoquesetorController@consultaQuantidadeProdutoPermiteNegativar']);
    //Fechamento de estoque
    Route::post('consultaestoquesetor/fecharEstoque', ['as' => 'ajax.consultaestoquesetor.store', 'uses' => 'EstoquesetorController@fecharEstoque']);
    //Reabertura de estoque
    Route::post('consultaestoquesetor/abrirEstoque', ['as' => 'ajax.consultaestoquesetor.update', 'uses' => 'EstoquesetorController@abrirEstoque']);

    //Estque físico
    Route::resource('estoquefisico', 'EstoquefisicoController');
    Route::get('estoquefisico/buscaEstoqueSetor/{setor_id}/{data}', ['as' => 'ajax.buscaestoquesetor', 'uses' => 'EstoquefisicoController@buscaEstoqueSetor']);

    //Estoque requisição
    Route::resource('estoquerequisicao', 'EstoquerequisicaoController');
    Route::get('estoquerequisicao/gerarPDF/{id}', ['as' => 'estoquerequisicao.pdf', 'uses' => 'EstoquerequisicaoController@gerarPDF']);
    // Route::get('estoquerequisicao/exportarXLS/{id}', ['middleware' => 'auth', 'as' => 'estoquerequisicao', 'uses' => 'EstoquerequisicaoController@exportarXLS']);
    Route::get('estoquerequisicao/filter/{dataInicial}/{dataFinal}', ['as' => 'estoquerequisicao.filtro', 'uses' => 'EstoquerequisicaoController@filter']);

    //Transferencias de estoque
    Route::resource('estoquetransferencias', 'EstoqueTransferenciasController');
    Route::get('estoquetransferencias/gerarPDF/{id}', ['as' => 'estoquetransferencias.pdf', 'uses' => 'EstoqueTransferenciasController@gerarPDF']);
    // Route::get('estoquetransferencias/exportarXLS/{id}', ['middleware' => 'auth', 'as' => 'estoquetransferencias', 'uses' => 'EstoqueTransferenciasController@exportarXLS']);
    //NF Situação
    Route::resource('nfsituacao', "NfsituacaoController");
    //NF Emitida
    Route::resource('nfemitida', "NfemitidaController");
    //SAT CF-e
    Route::resource('satcfe', "CupomFiscalController");
    Route::post('cfe/transmitir/{id}', "CupomFiscalController@transmitir")->name('satcfe.transmitir');
    Route::post('cfe/cancelar/{id}', "CupomFiscalController@cancelar")->name('satcfe.cancelar');
    //NF Ajax
    Route::get('nfemitida/evento/transmitir', ['as' => 'nfemitida.transmitir', 'uses' => 'NfemitidaController@transmitirnf']);
    Route::get('nfemitida/evento/consultar', ['as' => 'nfemitida.consultarnf', 'uses' => 'NfemitidaController@consultarnf']);
    Route::get('nfemitida/evento/cancelar', ['as' => 'nfemitida.cancelarnf', 'uses' => 'NfemitidaController@cancelarNF']);
    Route::get('nfemitida/evento/cce', ['as' => 'nfemitida.cartacorrecaonf', 'uses' => 'NfemitidaController@cartaCorrecaoNF']);
    Route::get('nfemitida/carreganfsituacaonfid/{id}', ['as' => 'nfemitida.carregarsituacao', 'uses' => 'NfemitidaController@carreganfsituacaonfid']);
    Route::post('nfemitida/processorPedidoNf/{id}', ['as' => 'nfemitida.processarpedidonf', 'uses' => 'NfemitidaController@processorPedidoNf']);
    Route::get('nfemitida.inutilizarFaixaNFs/', ['as' => 'nfemitida.inutilizarfaixanfs', 'uses' => 'NfemitidaController@inutilizarFaixaNFs']);
    Route::get('nfemitida/exportarXmls/{ids}', ['as' => 'nfemitida.exportarxls', 'uses' => 'NfemitidaController@exportarXmls']);
    Route::get('nfemitida/enviarEmailNF/{id}', ['as' => 'nfemitida.enviaremailnf', 'uses' => 'NfemitidaController@enviarEmailNF'])->middleware('mailware');
    Route::get('nfemitida.import.txt', ['as' => 'nfemitida.import.txt', 'uses' => 'NfemitidaController@importTxt']);
    Route::post('nfemitida.import.txt', ['as' => 'nfemitida.import.txt.post', 'uses' => 'NfemitidaController@getXmlByTxt']);
    Route::get('api/getNfBySerieNum', ['as' => 'ajax.getNfBySerieNum', 'uses' => 'SearchController@getNfBySerieNum']);
    Route::post('api/calcularImpostoProduto', ['as' => 'ajax.calcularImpostoProduto', 'uses' => 'SearchController@calcularImpostoProduto']);

    //NF Recebida
    Route::resource('nfrecebida', "NfrecebidaController");
    Route::post('nfrecebida.inutilizar', ['as' => 'nfrecebida.inutilizar', 'uses' => 'NfrecebidaController@inutilizar']);
    Route::post('nfrecebida.import.xml', ['as' => 'nfrecebida.import.xml', 'uses' => 'NfrecebidaController@importXml']);
    Route::get('api/getFornecedorByCNPJ/{cnpj}', ['as' => 'ajax.getFornecedorByCNPJ', 'uses' => 'SearchController@getFornecedorByCNPJ']);

    //SPED Fiscal
    Route::resource('spedfiscal', "SpedfiscalController");
    // Route::get('spedfiscal/validasped/{datainicio}/{datafim}', ['as'=>'spedfiscal.validasped','uses'=>'SpedfiscalController@validasped']);
    Route::post('spedfiscal/gerarsped', ['as' => 'ajax.spedfiscal.gerarsped', 'uses' => 'SpedfiscalController@gerarsped']);

    //SPED Contribuições
    Route::resource('spedcontribuicao', "SpedcontribuicaoController");
    Route::post('spedcontribuicao/gerarSped', ['as' => 'ajax.spedcontribuicao.gerarSped', 'uses' => 'SpedcontribuicaoController@gerarSped']);

    //Inventario
    Route::resource('inventario', 'InventarioController');

    //Crédito
    Route::resource('spedcreditos', 'SpedcreditosController');

    Route::get('sped.dowload/{filename}', function ($filename) {
        $headers = array(
            "Content-type"        => "text/txt;charset=ISO-8859-1",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );
        return response()->download($filename, $filename, $headers)->deleteFileAfterSend(true);
    })->name('sped.dowload');

    //Veiculo Abastecimento
    Route::resource('veiculoabastecimento', 'VeiculoabastecimentoController');

    //Entrada e saida de Veiculos
    Route::resource('veiculoentradasaida', 'VeiculoentradasaidaController');
    Route::get('ajaxbuscardados/{id}', ['as' => 'ajax.buscadadosveiculo', 'uses' => 'VeiculoentradasaidaController@ajaxBuscarDadosVeiculo']);
    Route::get('ajaxpedidosetor', ['as' => 'ajax.pedidosetor', 'uses' => 'VeiculoentradasaidaController@ajaxBuscaPedidoSetor']);

    //Troca de Óleo
    Route::resource('veiculotrocaoleo', 'VeiculotrocaoleoController');
    Route::get('gettrocasdeoleo', ['as' => 'ajax.gettrocasdeoleo', 'uses' => 'VeiculotrocaoleoController@getTrocas']);

    //Troca de Pneu
    Route::resource('veiculopneu', 'VeiculopneuController');

    //Cancelar Vale Gás
    Route::resource('valegascancelar', 'ValegascancelarController');

    //Consulta Vale Gás
    Route::resource('valegasconsulta', 'ValegasconsultaController');

    //Imprimir Vale Gás
    Route::get('/vendavalegas.imprimir', ['as' => 'valegas.index', 'uses' => 'ValegasvendaController@imprimirIndex']);
    Route::post('/vendavalegas.imprimirvalegas', ['as' => 'vendavalegas.imprimirvalegas', 'uses' => 'ValegasvendaController@imprimirGas']);
    Route::post('/vendavalegas/confirmacao/{id}', ['as' => 'ajax.confirmacao', 'uses' => 'ValegasvendaController@confirmaImpressao']);

    //venda Vale Gás
    Route::resource('vendavalegas', 'ValegasvendaController');
    Route::get('vendavalegas/duplicata/{id}', 'ValegasvendaController@gerarPDF');
    Route::get('vendavalegas/destroy/{id}', ['as' => 'vendavalegas.destroyget', 'uses' => 'ValegasvendaController@destroy']);
    Route::get('vendavalegas/buscarparcelasajax/{id}', 'ValegasvendaController@buscarParcelasAjax');
    Route::get('vendavalegas/buscarnumparcela/{id}', ['as' => 'ajax.buscanumparcela', 'uses' => 'ValegasvendaController@buscarNumParcelas']);
    Route::get('vendavalegas/ajaxbuscarprevendacli/{id}', ['as' => 'ajax.buscacliprevenda', 'uses' => 'ValegasvendaController@buscarCliPre']);

    //comodatos
    Route::resource('comodato', 'ComodatoController');
    Route::get('comodato/contrato/{id}', ['as' => 'comodato.contrato', 'uses' => 'ComodatoController@contrato']);

    //Comodatos gestão
    Route::get('comodatogestao.index', ['as' => 'comodatogestao.index', 'uses' => 'ComodatogestaoController@index']);
    Route::get('comodatogestao.getSaldos', ['as' => 'comodatogestao.getSaldos', 'uses' => 'ComodatogestaoController@getSaldos']);
    Route::get('comodatogestao.getVencidos', ['as' => 'comodatogestao.getVencidos', 'uses' => 'ComodatogestaoController@getComodatosVencimento']);
    Route::get('comodatogestao.getGiro', ['as' => 'comodatogestao.getGiro', 'uses' => 'ComodatogestaoController@getGiro']);
    Route::get('comodatogestao.vencidosPdf', ['as' => 'comodatogestao.vencidosPdf', 'uses' => 'ComodatogestaoController@getComodatosVencimentoPdf']);
    Route::get('comodatogestao.giroPdf', ['as' => 'comodatogestao.giroPdf', 'uses' => 'ComodatogestaoController@getGiroPdf']);

    //fechamento de convenio
    Route::resource('fechamentoconvenio', 'FechamentoconvenioController');
    Route::get('/fechamentoconvenio/filtro/{cliente}/{datast}/{dataen}/{edit}/{id}', ['as' => 'ajax.filtropedidosconvenio', 'uses' => 'FechamentoconvenioController@filtroPedidos']);
    Route::get('fechamentoconvenio/relatorio/{id}', ['as' => 'fechamentoconvenio.pdf', 'uses' => 'FechamentoconvenioController@gerarPDF']);
    Route::get('fechamentoconvenio/relatorioxls/{id}', ['as' => 'fechamentoconvenio.xls', 'uses' => 'FechamentoconvenioController@gerarXLS']);
    Route::get('fechamentoconvenio.filtro', ['as' => 'fechamentoconvenio.filtro', 'uses' => 'FechamentoconvenioController@filtroConsultas']);
    Route::post('fechamentoconvenio.emitirNF', ['as' => 'fechamentoconvenio.emitirNF', 'uses' => 'FechamentoconvenioController@emitirNF']);
    Route::post('fechamentoconvenio.emitirBoleto', ['as' => 'fechamentoconvenio.emitirBoleto', 'uses' => 'FechamentoconvenioController@emitirBoleto']);

    //Convênio/Gás de Bolso gestão
    Route::get('conveniogbgestao.index', ['as' => 'conveniogbgestao.index', 'uses' => 'ConveniogbgestaoController@index']);
    Route::get('conveniogbgestao.getDashboard', ['as' => 'conveniogbgestao.getDashboard', 'uses' => 'ConveniogbgestaoController@getDashboard']);
    Route::get('conveniogbgestao.getConvenioValegasMes', ['as' => 'conveniogbgestao.getConvenioValegasMes', 'uses' => 'ConveniogbgestaoController@getConvenioValegasMes']);

    //Vendas Mensais gestão
    Route::get('vendasmensaisgestao.index', ['as' => 'vendasmensaisgestao.index', 'uses' => 'VendasmensaisgestaoController@index']);
    Route::get('vendasmensaisgestao.getDashboard', ['as' => 'vendasmensaisgestao.getDashboard', 'uses' => 'VendasmensaisgestaoController@getDashboard']);
    Route::post('vendasmensaisgestao.generatePowerPoint', ['as' => 'vendasmensaisgestao.generatePowerPoint', 'uses' => 'VendasmensaisgestaoController@generatePowerPoint']);
    Route::post('vendasmensaisgestao.uploadImagem', ['as' => 'vendasmensaisgestao.uploadImagem', 'uses' => 'VendasmensaisgestaoController@uploadImagem']);

    //Fechamento mensal gestão
    Route::get('fechamentomensalgestao.index', ['as' => 'fechamentomensalgestao.index', 'uses' => 'FechamentomensalgestaoController@index']);
    Route::get('fechamentomensalgestao.getDashboard', ['as' => 'fechamentomensalgestao.getDashboard', 'uses' => 'FechamentomensalgestaoController@getDashboard']);
    Route::get('fechamentomensalgestao.getDetalhes', ['as' => 'fechamentomensalgestao.getDetalhes', 'uses' => 'FechamentomensalgestaoController@getDetalhes']);
    Route::get('fechamentomensalgestao.getCentroCustos', ['as' => 'fechamentomensalgestao.getCentroCustos', 'uses' => 'FechamentomensalgestaoController@getCentroCustos']);
    Route::get('fechamentomensalgestao.sendMail', ['as' => 'fechamentomensalgestao.sendMail', 'uses' => 'FechamentomensalgestaoController@sendMail']);
    Route::get('fechamentomensalgestao.export', ['as' => 'fechamentomensalgestao.export', 'uses' => 'FechamentomensalgestaoController@export']);
    Route::get('fechamentomensalgestao.download/{fileName}', ['as' => 'fechamentomensalgestao.download', 'uses' => 'FechamentomensalgestaoController@download']);

    //Dashboard Gerencial
    Route::get('dashboardgerencial.index', ['as' => 'dashboardgerencial.index', 'uses' => 'DashboardgerencialController@index']);
    Route::get('dashboardgerencial.getDashboard', ['as' => 'dashboardgerencial.getDashboard', 'uses' => 'DashboardgerencialController@getDashboard']);
    Route::get('dashboardgerencial.getDre', ['as' => 'dashboardgerencial.getDre', 'uses' => 'DashboardgerencialController@getDre']);
    Route::get('dashboardgerencial.getDetalhes', ['as' => 'dashboardgerencial.getDetalhes', 'uses' => 'DashboardgerencialController@getDetalhes']);
    Route::get('dashboardgerencial.getCentroCustos', ['as' => 'dashboardgerencial.getCentroCustos', 'uses' => 'DashboardgerencialController@getCentroCustos']);

    //MCMM
    Route::resource('mcmm', 'McmmController');
    Route::get('mcmm.searchEntradaSaidaMcmm', ['as' => 'ajax.mcmm.searchEntradaSaidaMcmm', 'uses' => 'McmmController@searchEntradaSaidaMcmm']);
    Route::get('mcmm.print', ['as' => 'report.mcmm', 'uses' => 'McmmController@printDoc']);

    //Pedidos
    Route::resource('pedido', 'PedidoController');
    // Route::get('pedidomonitoramento', ['as'=>'ajax.getpedidomonitoramento','uses'=>'PedidoController@getMonitoramento']);
    Route::get('searchConfigNfcePedidoByOperacao/{id}', ['as' => 'ajax.searchconfignfce', 'uses' => 'ConfigNfcePedidoController@searchConfigNfcePedidoByOperacao']);
    //Muda o campo nfcegerou para 1
    Route::get('pedido/nfcegerou/{id}/{nfce_id}', ['as' => 'ajax.nfcgerou', 'uses' => 'PedidoController@nfceGerou']);
    //Historico de Pedidos
    Route::get('historicopedidocliente/{cliente_id}', ['as' => 'ajax.historicoclientepedido', 'uses' => 'PedidoController@historicoCliente']);
    Route::get('historicopedidocliente/{cliente_id}/{pedido_id}', ['as' => 'ajax.historicoclienteped', 'uses' => 'PedidoController@historicoCliente']);
    //Editar status, colaborador, setor e condicao de pagamento do pedido
    // Route::post('pedido/editFromMonitoramento/{pedido_id}', ['as'=>'ajax.editarpedidomonitoramento','uses'=>'PedidoController@editFromMonitoramento']);
    // Route::post('pedido/editFromMonitoramento/{pedido_id}/{motivoatraso}', ['as'=>'ajax.editarpedidomonitoramento','PedidoController@editFromMonitoramento']);
    Route::post('pedido/editFromMonitoramento/{pedido_id}/{motivoatraso}/{valegas}', ['as' => 'ajax.editarpedidomonitoramento', 'uses' => 'PedidoController@editFromMonitoramento']);
    // edita o status de vários pedidos de uma só vez
    // Route::post('pedido/updateVariosStatus', ['as'=>'ajax.updatevarios','uses'=>'PedidoController@updateVariosStatus']);
    // Route::post('pedido/updateVariosStatus/{motivoatraso}', ['as'=>'ajax.updatevarios','uses'=>'PedidoController@updateVariosStatus']);
    Route::post('pedido/updateVariosStatus/{motivoatraso}/{valegas}', ['as' => 'ajax.updatevarios', 'uses' => 'PedidoController@updateVariosStatus']);
    //busca Por Id
    Route::get('pedido/buscaPorId/{pedido_id}/{senhamestra}', ['as' => 'ajax.pedidobuscaporid', 'uses' => 'PedidoController@buscaPorId']);
    //justificar motivo de atraso de pedido
    Route::get('pedido/justificamotivoatraso/{pedido_id}/{motivoatraso_id}', ['as' => 'ajax.justificamotivoatraso', 'uses' => 'PedidoController@justificaMotivoAtraso']);
    //validação de cartão
    Route::post('validacartao', ['as' => 'ajax.validacartao', 'uses' => 'PedidoController@validaCartao']);
    //Pedido Ajax
    Route::get('ajaxhistorico/{cliente}/{limit}', ['as' => 'ajax.historicolimite', 'uses' => 'PedidoController@historicoToCliente']);
    Route::get('checaStatusPedido/{pedido_id}', ['as' => 'ajax.checarpedido', 'uses' => 'PedidoController@checaStatusPedido']);
    Route::post('/validaGasBolso', ['as' => 'ajax.validavalegas', 'uses' => 'PedidoController@validaGasBolso']);
    Route::get('pedidomonitoramento/getPedidos', ['as' => 'ajax.pedidomonitoramento.getPedidos', 'uses' => 'PedidoController@getPedidosMonitoramento']);
    Route::get('pedido.isFechado', ['as' => 'ajax.isFechado', 'uses' => 'PedidoController@isFechado']);
    Route::get('api/getInfoClienteByPedido/{pedido_id}', ['as' => 'ajax.api/getInfoClienteByPedido', 'uses' => 'SearchController@getInfoClienteByPedido']);

    Route::get('pedido.comanda/{id}', ['as' => 'pedido.comanda', 'uses' => 'PedidoController@comanda']);

    //Pesquisa de Checklist
    Route::resource('checklist', 'ChecklistController');
    Route::get('checklist.filtro', ['as' => 'checklist.filtro', 'uses' => 'ChecklistController@checarFiltro']);
    Route::get('checklist/impressao/{id}', ['as' => 'checklist.pdf', 'uses' => 'ChecklistController@gerarPdf']);

    //Pesquisa de Pós-Venda
    Route::resource('posvenda', 'PosvendaController');
    Route::get('posvenda.filtro', ['as' => 'posvenda.filtro', 'uses' => 'PosvendaController@filtroPedididos']);

    //Venda Ativa
    Route::resource('vendaativa', 'VendaativaController');
    Route::get('vendaativa.filtroendereco', ['as' => 'vendaativa.filtro', 'uses' => 'VendaativaController@filtroEndereco']);
    Route::get('vendaativa.filtrocompra', ['as' => 'vendaativa.filtrocompra', 'uses' => 'VendaativaController@filtroCompra']);
    Route::get('vendaativa.filtromedia', ['as' => 'vendaativa.filtromedia', 'uses' => 'VendaativaController@filtroMediaGiro']);
    Route::post('vendaativa/gerarocorrencia', ['as' => 'vendaativa.ocorrencia', 'uses' => 'VendaativaController@gerarOcorrencia']);
    Route::post('vendaativa/salvarfiltros', ['as' => 'vendaativafiltro.store', 'uses' => 'VendaativaController@salvarFiltro']);


    //Financeiro
    //importação relatório de cartão
    Route::get('importReportCartao', ['as' => 'importReportCartao.index', 'uses' => 'FinanceiroController@importReportCartaoIndex']);
    Route::post('importReportCartao', ['as' => 'importReportCartao.import', 'uses' => 'FinanceiroController@importReportCartaoGetParcelas']);
    Route::get('importReportCartao/{parcelas}', ['as' => 'importReportCartao.parcelas', 'uses' => 'FinanceiroController@contasReceberImportCartao']);

    //importação extrato bancário
    Route::get('importExtrato', ['as' => 'importExtrato.index', 'uses' => 'ImportextratoController@importExtratoIndex']);
    Route::post('importExtrato', ['as' => 'importExtrato.import', 'uses' => 'ImportextratoController@importExtrato']);
    Route::post('importExtrato.getParcelas', ['as' => 'importExtrato.getParcelas', 'uses' => 'ImportextratoController@getParcelas']);
    Route::post('importExtrato.update', ['as' => 'importExtrato.update', 'uses' => 'ImportextratoController@update']);

    //Gerar Boleto
    Route::resource('boleto', 'BoletoController');
    Route::delete('boleto/{id}', ['as' => 'boleto.destroy/{id}', 'uses' => 'BoletoController@destroy']);

    //Remessa Boleto
    Route::resource('remessa', 'BoletoremessaController');
    Route::get('remessa.retorno/retornoparcelas/{parcelas}', ['as' => 'boleto.retornoparcelas', 'uses' => 'BoletoremessaController@contasReceber']);
    Route::get('exportRemessa', 'BoletoremessaController@exportRemessa');
    Route::get('cancelarRemessa/{id}', ['as' => 'ajax.cancelarRemessa/{id}', 'uses' => 'BoletoremessaController@cancelarRemessa']);
    Route::get('efetivarRemessa', ['as' => 'ajax.efetivarRemessa', 'uses' => 'BoletoremessaController@efetivarRemessa']);
    Route::post('importRetorno', 'BoletoremessaController@importRetorno');

    //Caixas
    Route::get('/caixaindex', ['middleware' => 'auth', 'as' => 'caixa.index', 'uses' => 'CaixaController@index']);
    Route::get('/financeiro.baixartitulosbycaixa', ['as' => 'financeiro.baixartitulosbycaixa', 'uses' => 'CaixaController@baixartitulosbycaixa']);
    Route::get('/financeiro.fecharModalIframe', ['middleware' => 'auth', 'as' => 'financeiro.fecharModalIframe', 'uses' => 'CaixaController@fecharModalIframe']);
    Route::get('/financeiro.cancelartitulosbycaixa', ['middleware' => 'auth', 'as' => 'financeiro.cancelartitulosbycaixa', 'uses' => 'CaixaController@cancelartitulosbycaixa']);
    Route::get('financeiro.abrirTelaCaixa/{par}', ['middleware' => 'auth', 'as' => 'financeiro.abrirTelaCaixa', 'uses' => 'CaixaController@abrirTelaCaixa']);
    Route::get('financeiro.abrirTelaCaixaFechado/{par}', ['middleware' => 'auth', 'as' => 'financeiro.abrirTelaCaixaFechado', 'uses' => 'CaixaController@abrirTelaCaixaFechado']);
    Route::get('contafechamento/filter/{contaId}/{dataInicial}/{dataFinal}', 'CaixaController@filterContaFechamento');
    Route::post('caixa.baixar', ['middleware' => 'auth', 'as' => 'caixa.baixar', 'uses' => 'CaixaController@baixar']);
    Route::post('caixa.cancelar', ['middleware' => 'auth', 'as' => 'caixa.cancelar', 'uses' => 'CaixaController@cancelar']);
    Route::post('caixa.gerarrecibo', ['middleware' => 'auth', 'as' => 'caixa.gerarrecibo', 'uses' => 'CaixaController@gerarRecibo']);
    Route::post('caixa.gerarrecibocr', ['middleware' => 'auth', 'as' => 'caixa.gerarrecibocr', 'uses' => 'CaixaController@gerarReciboCR']);
    Route::post('api/abrirCaixa', 'CaixaController@abrirCaixa');
    Route::post('api/fecharCaixa', 'CaixaController@fecharCaixa');
    Route::post('api/transferirCaixa', 'CaixaController@transferirCaixa');
    Route::post('api/estornarLancamentoCaixa', 'CaixaController@estornarLancamentoCaixa');
    Route::post('api/estornarLancamentoCR', ['as' => 'ajax.estornarlancamentocr', 'uses' => 'CaixaController@estornarLancamentoCR']);

    //Cheque Emitido
    Route::resource('chequeemitido', 'ChequeemitidoController');
    Route::get('chequeemitido.editar/{operacao}/{id}', ['as' => 'chequeemitido.editaroperacao', 'uses' => 'ChequeemitidoController@editar']);
    Route::get('chequeemitido.editar/inutilizar/{numerocheque}/{conta_id}', ['as' => 'chequeemitido.inutilizar', 'uses' => 'ChequeemitidoController@inutilizarCheque']);
    Route::post('chequeemitido.editar/estornar/{id}', ['as' => 'chequeemitido.estornar', 'uses' => 'ChequeemitidoController@estornarCheque']);
    Route::post('chequeemitido.editar/baixar/{id}', ['as' => 'chequeemitido.baixar', 'uses' => 'ChequeemitidoController@baixarCheque']);

    //Cheque Recebido
    Route::resource('chequerecebido', 'ChequerecebidoController');
    Route::get('chequerecebido.editar/{operacao}/{id}', 'ChequerecebidoController@editar');
    Route::post('chequerecebido.depositar/{id}', 'ChequerecebidoController@depositarCheque');
    Route::post('chequerecebido.baixar/{id}', ['as' => 'chequerecebido.baixar', 'uses' => 'ChequerecebidoController@baixarCheque']);
    Route::post('chequerecebido.estornar/{id}', ['as' => 'chequerecebido.estornar', 'uses' => 'ChequerecebidoController@estornarCheque']);
    Route::post('chequerecebido.devolver/{id}', ['as' => 'chequerecebido.devolver', 'uses' => 'ChequerecebidoController@devolverCheque']);

    //Cheque Desconto
    Route::resource('descontocheque', 'DescontochequeController');

    //Contas a Receber
    Route::get('/contasreceberindex', ['middleware' => 'auth', 'as' => 'contasreceber.index', 'uses' => 'FinanceiroController@contasreceberindex']);

    //Contas a Pagar
    Route::get('/contaspagarindex', ['middleware' => 'auth', 'as' => 'contaspagar.index', 'uses' => 'FinanceiroController@contaspagarindex']);

    //Fechamento de Malotes
    Route::get("malotefechamento.index", ["as" => "malotefechamento.index", "uses" => "FechamentomaloteController@index"]);
    Route::get("malote.pedidos", ["as" => "malote.pedidos", "uses" => "FechamentomaloteController@getPedidos"]);
    Route::get("malote.getPedido/{pedido_id}", ["as" => "malote.getPedido", "uses" => "FechamentomaloteController@getPedido"]);
    Route::get("malote.getProdPedido/{pedido_id}", ["as" => "malote.getProdPedido", "uses" => "FechamentomaloteController@getProdPedido"]);
    Route::get("malote.parcelas", ["as" => "malote.parcelas", "uses" => "FechamentomaloteController@getParcelas"]);
    Route::post("malote.updatePedido/{pedido_id}", ["as" => "malote.updatePedido", "uses" => "FechamentomaloteController@updatePedido"]);
    Route::post("malote.fechar", ["as" => "malote.fechar", "uses" => "FechamentomaloteController@store"]);

    // Conciliação Contabil
    Route::get("conciliacao.index", ["as" => "conciliacao.index", "uses" => "ConciliacaoController@index"]);
    Route::get("conciliacao.get", ["as" => "conciliacao.get", "uses" => "ConciliacaoController@getFinCont"]);
    Route::get("conciliacao.getFinDet", ["as" => "conciliacao.getFinDet", "uses" => "ConciliacaoController@getFinDet"]);
    Route::get("conciliacao.getContDet", ["as" => "conciliacao.getContDet", "uses" => "ConciliacaoController@getContDet"]);

    //Lançamento de Despesa
    Route::get('/financeiro.createDespesa', ['middleware' => 'auth', 'as' => 'financeiro.createDespesa', 'uses' => 'FinanceiroController@create_despesa']);

    //Lançamento de Receita
    Route::get('/financeiro.createReceita', ['middleware' => 'auth', 'as' => 'financeiro.createReceita', 'uses' => 'FinanceiroController@create_receita']);
    //Financeiro Controller Ajax e outros
    Route::get('/financeiro.createbycaixa', ['middleware' => 'auth', 'as' => 'financeiro.createbycaixa', 'uses' => 'FinanceiroController@createbycaixa']);
    Route::post('financeiro.store', ['middleware' => 'auth', 'as' => 'financeiro.store', 'uses' => 'FinanceiroController@store']);
    Route::get('/financeiro.consultartitulos', ['middleware' => 'auth', 'as' => 'financeiro.consultartitulos', 'uses' => 'FinanceiroController@consultartitulos']);
    Route::post('financeiro.cancelar', ['middleware' => 'auth', 'as' => 'financeiro.cancelar', 'uses' => 'FinanceiroController@cancelar']);
    Route::post('/financeiro.createbyagrupar', ['middleware' => 'auth', 'as' => 'financeiro.createbyagrupar', 'uses' => 'FinanceiroController@createbyagrupar']);
    Route::get('api/getLancamentosFinanceiros', 'FinanceiroController@getLancamentosFinanceiros');
    Route::post('api/alterarDescricaoLancamento', 'FinanceiroController@alterarDescricaoLancamento');

    //Empresa Config
    Route::resource('empresaconfig', 'EmpresaconfigController');

    //Senha Mestre
    Route::get('/empresaconfig.senhamestre', ['as' => 'empresaconfig.senhamestre', 'uses' => 'EmpresaconfigController@senhaMestre']);
    //Empresa Config Ajax e outros
    Route::post('empresaconfig/changepassword', 'EmpresaconfigController@changePassword');
    Route::post('empresaconfig/verificasenhamestre', 'EmpresaconfigController@verificaSenhaMestre');
    Route::post('empresaconfig/verificasenhamestre/{empresa_id}', 'EmpresaconfigController@verificaSenhaMestre');
    Route::post('empresaconfig.email', ['as' => 'config.email', 'uses' => 'EmpresaconfigController@sendEmail'])->middleware('mailware');

    //Sorteio
    Route::resource('sorteio', 'SorteioController');
    Route::get("sorteio.getPedidos", ["as" => "sorteio.getPedidos", "uses" => "SorteioController@getPedidos"]);

    //Relatorios
    //Despesas por Tipo
    Route::get('report.despesas', ['as' => 'report.despesas', 'uses' => 'ReportCaixaController@despesasIndex']);
    Route::get('report.despesas.filtro', ['as' => 'report.despesas.filtro', 'uses' => 'ReportCaixaController@despesasFiltro']);
    Route::get('report.despesas.sub', ['as' => 'report.despesas.sub', 'uses' => 'ReportCaixaController@despesaSub']);

    //Receitas por tipo
    Route::get('report.receitas', ['as' => 'report.receitas', 'uses' => 'ReportCaixaController@receitasIndex']);
    Route::get('report.receitas.filtro', ['as' => 'report.receitas.filtro', 'uses' => 'ReportCaixaController@receitasFiltro']);
    Route::get('report.receitas.sub', ['as' => 'report.receitas.sub', 'uses' => 'ReportCaixaController@receitasSub']);

    //Centro Custos - Despesas
    Route::get('report.despesas_cc', ['as' => 'report.despesas_cc', 'uses' => 'ReportCaixaController@despesasCentro']);
    Route::get('report.despesas_cc.filtro', ['as' => 'report.despesas_cc.filtro', 'uses' => 'ReportCaixaController@centroFiltro']);
    Route::get('report.despesas_cc.sub', ['as' => 'report.despesas_cc.sub', 'uses' => 'ReportCaixaController@subCentroCusto']);

    //Centro Custos - Receitas
    Route::get('report.receitas_cc', ['as' => 'report.receitas_cc', 'uses' => 'ReportCaixaController@receitasCentro']);
    Route::get('report.receitas_cc.filtro', ['as' => 'report.receitas_cc.filtro', 'uses' => 'ReportCaixaController@centroFiltroReceitas']);
    Route::get('report.receitas_cc.sub', ['as' => 'report.receitas_cc.sub', 'uses' => 'ReportCaixaController@subCentroCustoReceitas']);

    //Mala Direta
    Route::resource('maladireta', "MaladiretaController");
    //ajax para exportar .csv de clientes
    Route::post('maladireta.csv', 'MaladiretaController@csv');
    //ajax para enviar email a clientes
    Route::post('maladireta.mail', 'MaladiretaController@sendMail')->middleware('mailware');
    //ajax para enviar email a clientes
    Route::get('maladireta.etiquetas', 'MaladiretaController@etiquetas');
    //ajax para deletar o arquivo da pasta public
    Route::get('deleteTempFileCSV/{filename}', 'MaladiretaController@deleteTempFileCSV');

    //Relatorio de Follow Up
    Route::get('filtro.filtroFollowUp', ['middleware' => 'auth', 'as' => 'filtro.filtroFollowUp', 'uses' => 'ReportController@filtroFollowUp']);
    Route::get('report.FollowUp/{par}', ['middleware' => 'auth', 'as' => 'report.FollowUp', 'uses' => 'ReportController@reportFollowUp']);
    Route::get('report.FollowUpXls/{par}', ['middleware' => 'auth', 'as' => 'report.FollowUpXls', 'uses' => 'ReportController@reportFollowUpXls']);

    //Transferencia
    Route::get('report.estoquetransferencia', ['as' => 'report.estoquetransferencia', 'uses' => 'ReportestoqueController@transferenciaIndex']);
    Route::get('report.transferenciapdf', ['as' => 'report.estoquetransferenciapdf', 'uses' => 'ReportestoqueController@transferenciaPdf']);

    //Requisicao
    Route::get('report.estoquerequisicao', ['as' => 'report.estoquerequisicao', 'uses' => 'ReportestoqueController@requisicaoIndex']);
    Route::get('report.estoquerequisicaopdf', ['as' => 'report.estoquerequisicaopdf', 'uses' => 'ReportestoqueController@requisicaoPdf']);

    //Estoque GLP
    Route::get('report.estoqueglp', ['as' => 'report.estoqueglp', 'uses' => 'ReportestoqueController@glpIndex']);
    Route::get('report.estoqueglppdf', ['as' => 'report.estoqueglppdf', 'uses' => 'ReportestoqueController@pdfGlp']);

    //Estoque Geral
    Route::get('report.estoquegeral', ['as' => 'report.estoquegeral', 'uses' => 'ReportestoqueController@geralIndex']);
    Route::get('report.estoquegeralpdf', ['as' => 'report.estoquegeralpdf', 'uses' => 'ReportestoqueController@geralPdf']);

    //Movimentações
    Route::get('report.movimentacoes', ['as' => 'report.movimentacoes', 'uses' => 'ReportmovimentacaoController@index']);
    Route::get('report.movimentacoes.filtro', ['as' => 'report.movimentacoes.filtro', 'uses' => 'ReportmovimentacaoController@movimentacaoFiltro']);

    //Aniversariantes
    Route::get('report.clientesaniversariantes', ['as' => 'report.clientesaniversariantes', 'uses' => 'ReportclientesaniversariantesController@reportClientesAniversariantes']);
    Route::get('report.clientesaniversariantes.pdf', ['as' => 'report.clientesaniversariantes.pdf', 'uses' => 'ReportclientesaniversariantesController@reportClientesAniversariantesPDF']);
    Route::get('report.aniversariantescompram', ['as' => 'report.aniversariantescompram', 'uses' => 'ReportclientesaniversariantesController@reportClientesAniversariantesCompram']);
    Route::get('report.aniversariantescompram.pdf', ['as' => 'report.aniversariantescompram.pdf', 'uses' => 'ReportclientesaniversariantesController@reportClientesAniversariantesCompramPDF']);

    //Bairro
    Route::get('report.clientesbairros', ['as' => 'report.clientesbairros', 'uses' => 'ReportclientesController@indexBairros']);
    Route::get('report.clientesbairrospdf', ['as' => 'report.clientesbairrospdf', 'uses' => 'ReportclientesController@filtroClientesBairro']);

    //Fornecedores
    Route::get('report.fornecedores', ['as' => 'report.fornecedores', 'uses' => 'ReportclientesController@fornecedoresIndex']);
    Route::get('report.fornecedorespdf', ['as' => 'report.fornecedorespdf', 'uses' => 'ReportclientesController@fornecedoresPdf']);

    //Clientes
    Route::get('report.clientes', ['as' => 'report.clientes', 'uses' => 'ReportclientesController@clientesIndex']);
    Route::get('report.clientespdf', ['as' => 'report.clientespdf', 'uses' => 'ReportclientesController@clientesPdf']);
    Route::get('report.clientesativospdf', ['as' => 'report.clientesativospdf', 'uses' => 'ReportclientesController@clientesAtivosPdf']);

    //Interações
    Route::get('report.interacoes', ['as' => 'report.interacoes', 'uses' => 'ReportclientesController@indexInteracoes']);
    Route::get('report.interacoesfiltro', ['as' => 'report.interacoesfiltro', 'uses' => 'ReportclientesController@interacoesFiltro']);

    //nao compram
    Route::get('report.semcompras', ['as' => 'report.semcompras', 'uses' => 'ReportclientesaniversariantesController@indexSemCompra']);
    Route::get('report.semcomprasfiltro', ['as' => 'report.semcomprasfiltro', 'uses' => 'ReportclientesaniversariantesController@semCompraFiltro']);

    //incompletos
    Route::get('report.incompletos', ['as' => 'report.incompletos', 'uses' => 'ReportclientesaniversariantesController@incompletosIndex']);
    Route::get('report.incompletosfiltro', ['as' => 'report.incompletosfiltro', 'uses' => 'ReportclientesaniversariantesController@incompletosFiltro']);

    //Relatório Convenios
    Route::get('report.conveniofuncionarios', ['as' => 'report.conveniofuncionarios', 'uses' => 'ReportconvenioController@reportConvenioFuncionario']);
    Route::get('report.conveniofuncionarios.pdf', ['as' => 'report.conveniofuncionarios.pdf', 'uses' => 'ReportconvenioController@reportConvenioFuncionarioPDF']);
    Route::get('report.convenio', ['as' => 'report.convenio', 'uses' => 'ReportconvenioController@reportConvenio']);
    Route::get('report.convenio.pdf', ['as' => 'report.convenio.pdf', 'uses' => 'ReportconvenioController@reportConvenioPDF']);

    //Relatório de Colaboradores
    Route::get('report.colaboradoresaniversariantes', ['as' => 'report.colaboradoresaniversariantes', 'uses' => 'ReportcolaboradorController@reportColaboradoresAniversariantes']);
    Route::get('report.colaboradoresaniversariantes.familiares', ['as' => 'report.colaboradoresaniversariantes.familiares.html', 'uses' => 'ReportcolaboradorController@reportColaboradoresAniversariantesFamiliares']);
    Route::get('report.colaboradoresaniversariantes.pdfonly', ['as' => 'report.colaboradoresaniversariantes.pdf', 'uses' => 'ReportcolaboradorController@reportColaboradoresAniversariantesPDF']);
    Route::get('report.colaboradoresaniversariantes.familiares.pdf', ['as' => 'report.colaboradoresaniversariantes.familiares.pdf', 'uses' => 'ReportcolaboradorController@reportColaboradoresAniversariantesFamiliaresPDF']);
    Route::get('report.colaboradoresexames', ['as' => 'report.colaboradoresexames', 'uses' => 'ReportcolaboradorController@reportColaboradoresExames']);
    Route::get('report.colaboradoresexames.pdf', ['as' => 'report.colaboradoresexames.pdf', 'uses' => 'ReportcolaboradorController@reportColaboradoresExamesPDF']);
    Route::get('report.colaboradoresfaixaetaria', ['as' => 'report.colaboradoresfaixaetaria', 'uses' => 'ReportcolaboradorController@reportColaboradoresFaixaEtaria']);
    Route::get('report.colaboradoresfaixaetaria.pdf', ['as' => 'report.colaboradoresfaixaetaria.pdf', 'uses' => 'ReportcolaboradorController@reportColaboradoresFaixaEtariaPDF']);
    Route::get('report.colaboradoresferiasvencimento', ['as' => 'report.colaboradoresferiasvencimento', 'uses' => 'ReportcolaboradorController@reportFeriasVencimento']);
    Route::get('report.colaboradoresferiasvencimento.pdf', ['as' => 'report.colaboradoresferiasvencimento.pdf', 'uses' => 'ReportcolaboradorController@reportFeriasVencimentoPDF']);

    //Relatórios de contas a pagar/receber
    Route::get('report.contaspagar', ['as' => 'report.contaspagar', 'uses' => 'ReportFinanceiroController@ReportContasPagar']);
    Route::get('report.contaspagar.pdf', ['as' => 'report.contaspagar.pdf', 'uses' => 'ReportFinanceiroController@ReportContasPagarPDF']);
    Route::get('report.contasreceber', ['as' => 'report.contasreceber', 'uses' => 'ReportFinanceiroController@ReportContasReceber']);
    Route::get('report.contasreceber.pdf', ['as' => 'report.contasreceber.pdf', 'uses' => 'ReportFinanceiroController@ReportContasReceberPDF']);

    //Relatório de Comodatos
    Route::get('report.comodatosativos', ['as' => 'report.comodatosativos', 'uses' => 'ReportcomodatoController@reportComodatosAtivos']);
    Route::get('report.comodatosativos.pdf', ['as' => 'report.comodatosativos.pdf', 'uses' => 'ReportcomodatoController@reportComodatosAtivosPDF']);
    Route::get('report.controlecomodato', ['as' => 'report.controlecomodato', 'uses' => 'ReportcomodatoController@reportControleComodatos']);
    Route::get('report.controlecomodato.pdf', ['as' => 'report.controlecomodato.pdf', 'uses' => 'ReportcomodatoController@reportControleComodatosPDF']);
    Route::get('report.girocomodato', ['as' => 'report.girocomodato', 'uses' => 'ReportcomodatoController@reportGiroComodatos']);
    Route::get('report.girocomodato.pdf', ['as' => 'report.girocomodato.pdf', 'uses' => 'ReportcomodatoController@reportGiroComodatosPDF']);

    //Relatório de NF
    Route::get('report.nfentradas', ['as' => 'report.nfentradas', 'uses' => 'ReportnfrecebidasController@index']);
    Route::get('report.nfentradas.pdf', ['as' => 'report.nfentradas.pdf', 'uses' => 'ReportnfrecebidasController@filtrarRecebidas']);
    Route::get('report.nfemitidas', ['as' => 'report.nfemitidas', 'uses' => 'ReportnfemitidasController@index']);
    Route::get('report.nfemitidas.pdf', ['as' => 'report.nfemitidas.pdf', 'uses' => 'ReportnfemitidasController@filtroEmitidas']);

    //Abastecimento
    Route::get('report.abastecimento', ['as' => 'report.abastecimento', 'uses' => 'ReportveiculosController@abastecimentoIndex']);
    Route::get('report.abastecimentopdf', ['as' => 'report.abastecimentopdf', 'uses' => 'ReportveiculosController@abastecimentoPdf']);

    //Gestão de Frota
    Route::get('report.gestaofrota', ['as' => 'report.gestaofrota', 'uses' => 'ReportveiculosController@frotaIndex']);
    Route::get('report.gestaofrotapdf', ['as' => 'report.gestaofrotapdf', 'uses' => 'ReportveiculosController@frotaPdf']);

    //Troca de Oleo
    Route::get('report.trocaoleo', ['as' => 'report.trocaoleo', 'uses' => 'ReportveiculosController@oleoIndex']);
    Route::get('report.trocaoleopdf', ['as' => 'report.trocaoleopdf', 'uses' => 'ReportveiculosController@pdfTroca']);

    //Troca de Oleo Vencido
    Route::get('report.trocaoleovencidofiltro', ['as' => 'report.trocaoleovencidofiltro', 'uses' => 'ReportveiculosController@filtroTrocaVencido']);
    Route::get('report.trocaoleovencidopdf', ['as' => 'report.trocaoleovencidopdf', 'uses' => 'ReportveiculosController@pdfTrocaVencida']);

    //Colaborador Malote
    Route::get('report.colaboradormalote', ['as' => 'report.colaboradormalote', 'uses' => 'ReportvendasmaloteController@vendasMaloteIndex']);
    Route::get('report.colaboradormalotepdf', ['as' => 'report.colaboradormalotepdf', 'uses' => 'ReportvendasmaloteController@vendasMalotePdf']);

    //Lançamento Retroativo
    Route::get('report.retroativo', ['as' => 'report.retroativo', 'uses' => 'ReportFinanceiroController@indexRetro']);
    Route::get('report.retroativofiltrar', ['as' => 'report.retroativo.filtro', 'uses' => 'ReportFinanceiroController@retroFiltrar']);
    Route::get('getuserbyempresa', ['as' => 'ajax.userbyempresa', 'uses' => 'SearchController@getUserByEmpresa']);

    //Por Setor
    Route::get('report.vendasetor', ['as' => 'report.vendasetor', 'uses' => 'ReportController@vendaSetorIndex']);
    Route::get('report.vendasetorfiltro', ['as' => 'report.vendasetorfiltro', 'uses' => 'ReportController@filtroVendaSetor']);

    //Vendas gerais
    Route::get("report.vendasgerais", ['as' => 'report.vendasgerais', 'uses' => 'ReportVendasController@index']);
    Route::get("report.vendasgerais.pdf", ['as' => 'report.vendasgerais.pdf', 'uses' => 'ReportVendasController@filtroVendas']);

    //Por App
    Route::get('report.vendaapp', ['as' => 'report.vendaapp', 'uses' => 'ReportController@vendaAppIndex']);
    Route::get('report.vendaappfiltro', ['as' => 'report.vendaappfiltro', 'uses' => 'ReportController@filtroVendaApp']);

    //Por produto
    Route::get('report.vendaproduto', ['as' => 'report.vendaproduto', 'uses' => 'ReportController@vendaProdutoIndex']);
    Route::get('report.vendaprodutofiltro', ['as' => 'report.vendaprodutofiltro', 'uses' => 'ReportController@filtroVendaProduto']);

    //Diaria
    Route::get('report.vendadiaria', ['as' => 'report.vendadiaria', 'uses' => 'ReportController@vendaDiariaIndex']);
    Route::get('report.vendadiariafiltro', ['as' => 'report.vendadiariafiltro', 'uses' => 'ReportController@filtroDiaria']);

    //Direta
    Route::get('report.vendadireta', ['as' => 'report.vendadireta', 'uses' => 'ReportvendasmaloteController@vendasDireta']);
    Route::get('report.vendadiretapdf', ['as' => 'report.vendadiretapdf', 'uses' => 'ReportvendasmaloteController@diretaFiltro']);

    //Por Operacoes
    Route::get('report.vendaoperacoes', ['as' => 'report.vendaoperacoes', 'uses' => 'ReportvendasmaloteController@vendasOperacoes']);
    Route::get('report.vendaoperacoespdf', ['as' => 'report.vendaoperacoespdf', 'uses' => 'ReportvendasmaloteController@operacoesFiltro']);

    //Por Segmento
    Route::get('report.vendasegmento', ['as' => 'report.vendasegmento', 'uses' => 'ReportvendapdvController@pdvIndex']);
    Route::get('report.vendasegmentopdf', ['as' => 'report.vendasegmentopdf', 'uses' => 'ReportvendapdvController@pdvPdf']);

    //Entregador
    Route::get('report.vendaentregador', ['as' => 'report.vendaentregador', 'uses' => 'ReportvendapdvController@entregadorIndex']);
    Route::get('report.vendaentregadorpdf', ['as' => 'report.vendaentregadorpdf', 'uses' => 'ReportvendapdvController@filtroEntregador']);

    //Convenio
    Route::get('report.vendaconvenio', ['as' => 'report.vendaconvenio', 'uses' => 'ReportvendapdvController@indexConvenio']);
    Route::get('report.vendaconveniopdf', ['as' => 'report.vendaconveniopdf', 'uses' => 'ReportvendapdvController@convenioFiltro']);

    //Comissões
    Route::get('report.comissoes', ['as' => 'report.comissoes', 'uses' => 'ReportcomissoesController@comissoesIndex']);
    Route::get('report.comissoespdf', ['as' => 'report.comissoespdf', 'uses' => 'ReportcomissoesController@filtroComissao']);

    //Vale Gás
    Route::get('report.valegas', ['as' => 'report.valegas', 'uses' => 'ReportvalegasController@valegasIndex']);
    Route::get('report.valegasfiltro', ['as' => 'report.valegasfiltro', 'uses' => 'ReportvalegasController@valegasHub']);

    //Pós-Venda
    Route::get('report.posvenda', ['as' => 'report.posvenda', 'uses' => 'ReportquestionariosController@indexPosVenda']);
    Route::get('report.posvendafiltro', ['as' => 'report.posvendafiltro', 'uses' => 'ReportquestionariosController@posvendaFiltro']);

    //Resumo de Vendas por Setor
    Route::get("report.resumovendadia", ['as' => 'report.resumovendadia', 'uses' => 'ReportResumoVendasController@index']);
    Route::get("report.resumovendadiafiltro", ['as' => 'report.resumovendadiafiltro', 'uses' => 'ReportResumoVendasController@filtroResumoDia']);

    //Checklists
    Route::get('report.checklist', ['as' => 'report.checklist', 'uses' => 'ReportquestionariosController@checkIndex']);
    Route::get('report.checklistfiltro', ['as' => 'report.checklistfiltro', 'uses' => 'ReportquestionariosController@filtroCheck']);
    Route::get('report.checklistajax', ['as' => 'report.checklistajax', 'uses' => 'ReportquestionariosController@checkAjax']);

    //Relatório de fluxo do caixa
    Route::get('report.fluxocaixa', ['as' => 'report.fluxocaixa', 'uses' => 'ReportCaixaController@fluxoCaixa']);
    Route::get('report.fluxocaixa.pdf', ['as' => 'report.fluxocaixa.pdf', 'uses' => 'ReportCaixaController@fluxoCaixaPDF']);

    //Relatório de clientes por segmento/setor/produto
    Route::get('report.clientesInativosFaltaCompra', ['as' => 'report.clientesInativosFaltaCompra', 'uses' => 'ReportclientesController@inativoFaltaIndex']);
    Route::get('report.inativoFaltaCompra', ['as' => 'report.clientesInativosFaltaCompra.pdf', 'uses' => 'ReportclientesController@inativoFaltaCompraPDF']);

    //Relatório de clientes por segmento/setor/produto
    Route::get('report.segSetorProd', ['as' => 'report.segSetorProd', 'uses' => 'ReportclientesController@segSetorProdPDF']);

    //Promocoes
    Route::get('report.acompanhamentopromo', ['as' => 'report.acompanhamentopromo', 'uses' => 'ReportpromocoesController@indexAcompanhamento']);
    Route::get('report.acompanhamentopromopdf', ['as' => 'report.acompanhamentopromopdf', 'uses' => 'ReportpromocoesController@filtroAcompanhamento']);

    //Mapa de Entregas
    Route::get('report.mapaentregas', ['as' => 'report.mapaentregas', 'uses' => 'ReportEntregasController@indexMapaEntregas']);
    Route::get('api/searchPedidosMapaEntregas', 'SearchController@searchPedidosMapaEntregas');

    //Mapa de entregas pendentes
    Route::get('report.mapaentregaspendentes', ['as' => 'report.mapaentregaspendentes', 'uses' => 'ReportEntregasController@indexMapaEntregasPendentes']);
    Route::get('api/searchPedidosPendentesMapaEntregas', 'SearchController@searchPedidosPendentesMapaEntregas');

    //Mapa de Entregas Corrdenadas
    Route::get('report.mapaentregascoordenadas', ['as' => 'report.mapaentregascoordenadas', 'uses' => 'ReportEntregasController@indexMapaEntregasCoordenadas']);
    Route::get('api/searchPedidosMapaEntregasCoordenadas', 'SearchController@searchPedidosMapaEntregasCoordenadas');

    //Mapa de Entregas Atrasadas
    Route::get('report.mapaentregasatrasadas', ['as' => 'report.mapaentregasatrasadas', 'uses' => 'ReportEntregasController@indexMapaEntregasAtrasadas']);
    Route::get('api/searchPedidosMapaEntregasAtrasadas', 'SearchController@searchPedidosMapaEntregasAtrasadas');

    //Tempo de Entregas
    Route::get('report.tempoentrega', ['as' => 'report.tempoentrega', 'uses' => 'ReportEntregasController@indexTempoEntregas']);
    Route::get('api/searchPedidosTempoEntregas', 'SearchController@searchPedidosTempoEntregas');

    //Mapa Vendas x Metas
    Route::get('report.mapametas', ['as' => 'report.mapametas', 'uses' => 'ReportEntregasController@indexMapaMetas']);
    Route::get('getmetasxvendas', ['as' => 'ajax.metasxvendas', 'uses' => 'ReportEntregasController@filtroSetores']);

    // Promotores
    Route::get('report.promoausentes', ['as' => 'report.promoausentes', 'uses'  => 'ReportpromotorController@indexAusentes']);
    Route::get('report.promoausentes.pdf', ['as' => 'report.promoausentes.pdf', 'uses'  => 'ReportpromotorController@getPdfAusentes']);
    Route::get('report.promovisitas', ['as'  => 'report.promovisitas', 'uses'   => 'ReportpromotorController@indexVisitas']);
    Route::get('report.promovisitas.pdf', ['as'  => 'report.promovisitas.pdf', 'uses'   => 'ReportpromotorController@getPdfVisitas']);

    // Uso de Senha Mestre
    Route::get('report.logsenha', ['as' => 'report.logsenha', 'uses'  => 'ReportlogsenhaController@index']);
    Route::get('report.logsenha.pdf', ['as' => 'report.logsenha.pdf', 'uses'  => 'ReportlogsenhaController@getPdfLog']);

    // Cercas
    Route::get('report.logcercas', ['as' => 'report.logcercas', 'uses'  => 'LogcercaController@index']);
    Route::get('report.logcercas.pdf', ['as' => 'report.logcercas.pdf', 'uses'  => 'LogcercaController@getPdfLog']);

    //NFRecebida - comprovante
    //Route::get('report.nfrecebidacomprovante', ['as' => 'report.nfrecebidacomprovante', 'uses' => 'ReportnfrecebidaController@comprovanteIndex']);
    Route::get('report.nfrecebidacomprovantepdf', ['as' => 'report.nfrecebidacomprovantepdf', 'uses' => 'ReportnfrecebidaController@filtroComprovante']);

    //Ajax Notificações
    Route::get('readnotification', ['as' => 'ajax.readnotification', 'uses' => 'NotificacoesController@readNotification']);
    Route::get('meliganotification', ['as' => 'ajax.meliga', 'uses' => 'NotificacoesController@meLiga']);

    // PIX
    Route::get("pix.index", ["as" => "pix.index", "uses" => "PixController@index"]);
    Route::get("pix.baixar/{parcelas}", ["as" => "pix.baixar", "uses" => "PixController@baixar"]);

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //Ajax SearchController
    Route::get('getContaById/{id}', ['as' => 'ajax.getContaById/{id}', 'uses' => 'SearchController@getContaById']);
    Route::get('getBairrosRuasByCidade/{cidad_id}', ['as' => 'ajax.getBairrosRuasByCidade/{cidad_id}', 'uses' => 'SearchController@getBairrosRuasByCidade']);
    Route::get('api/getCodMovRemessaByBanco/{id}/{tipo}', ['as' => 'ajax.getCodMovRemessaByBanco/{id}/{tipo}', 'uses' => 'SearchController@getCodMovRemessaByBanco']);
    Route::get('api/getCodMovRemessaByConta/{id}/{tipo}', ['as' => 'ajax.getCodMovRemessaByConta/{id}/{tipo}', 'uses' => 'SearchController@getCodMovRemessaByConta']);
    Route::get('api/searchParcelasByIds/{parcelas}', ['as' => 'ajax.searchParcelasByIds', 'uses' => 'SearchController@searchParcelasByIds']);
    //Empresas por Regional
    Route::get('api/empresasbyregional', ['as' => 'ajax.empresasbyregional', 'uses' => 'SearchController@getEmpresasByRegional']);
    //api banco
    Route::get('api/searchBanco', ['as' => 'ajax.searchbanco', 'uses' => 'SearchController@searchBanco']);
    Route::get('ajax.empresas', ['as' => 'ajax.getempresasbygrupo', 'uses' => 'SearchController@getEmpresasByGrupo']);
    //Centro por Pai
    Route::get('api/centrocustobypai', ['as' => 'ajax.centrocustobypai', 'uses' => 'SearchController@getCentroByPai']);
    //Turno
    Route::resource('turno', 'TurnoController');
    Route::get('api/nfimpostoByGfOperacao/{grupofiscal_id}/{operacao_id}', ['as' => 'ajax.imposotbyoperacao', 'uses' => 'SearchController@nfimpostoByGfOperacao']);
    //AJAX Search Cliente
    Route::get('api/searchCliente', ['as' => 'ajax.searchcliente', 'uses' => 'SearchController@indexcliente']);
    Route::get('api/searchgeralreportnf', ['as' => 'ajax.searchgeral', 'uses' => 'SearchController@clienteReportNf']);
    Route::get('api/searchClientePedido', ['as' => 'ajax.searchclientepedido', 'uses' => 'SearchController@searchClientePedido']);
    Route::get('api/searchClientePedidoConvenio', ['as' => 'ajax.searchClientePedidoConvenio', 'uses' => 'SearchController@searchClientePedidoConvenio']);
    Route::get('api/searchClienteNF', ['as' => 'ajax.searchclientenf', 'uses' => 'SearchController@searchClienteNF']);
    Route::get('api/searchRua/{cidade_id}', ['as' => 'ajax.searchrua', 'uses' => 'SearchController@searchRua']);
    Route::get('api/searchClienteTodasEmpresas', ['as' => 'ajax.clientegerais', 'uses' => 'SearchController@indexclientetodasempresas']);
    Route::get('api/searchClienteOutrasEmpresas', ['as' => 'ajax.clientesoutrasempresas', 'uses' => 'SearchController@indexclienteoutrasempresas']);
    Route::get('api/searchResponsavelOutrasEmpresas', ['as' => 'ajax.responsaveloutrasempresas', 'uses' => 'SearchController@indexresponsaveloutrasempresas']);
    Route::get('api/searchClientes', ['as' => 'ajax.indexclientes', 'uses' => 'SearchController@indexclientes']);
    Route::get('api/searchFornecedores', ['as' => 'ajax.searcfornecedores', 'uses' => 'SearchController@indexfornecedores']);
    Route::get('api/searchColaboradores', ['as' => 'ajax.searccolaboradores', 'uses' => 'SearchController@indexcolaboradores']);
    Route::get('api/searchDadosPedidoEmpresa/{empresa_id}', ['as' => 'ajax.dadospedidoempresa', 'uses' => 'SearchController@searchDadosPedidoEmpresa']);
    Route::get('api/buscaInfoEmpresaParaPedidos/{empresa_id}', ['as' => 'ajax.searchinfoempresaparapedidos', 'uses' => 'SearchController@buscaInfoEmpresaParaPedidos']);
    Route::get('api/searchTelefonesMonitoramento', ['as' => 'ajax.searchtelefonemonitoramento', 'uses' => 'SearchController@searchTelefonesMonitoramento']);
    Route::get('api/searchClientesComodato/{tipo}', ['as' => 'ajax.searchclientecomodato', 'uses' => 'SearchController@searchClientesComodato']);
    Route::get('api/searchClientesEmpresaUser/{empresas_id}', ['as' => 'ajax.clienteempresauser', 'uses' => 'SearchController@searchClientesEmpresaUser']);
    Route::get('api/searchFornecedoresEmpresaUser/{empresas_id}', ['as' => 'ajax.fornecedoresempresauser', 'uses' => 'SearchController@searchFornecedoresEmpresaUser']);
    //tipo cliente
    Route::get('api/clientespf', ['as' => 'ajax.clientespf', 'uses' => 'SearchController@clientespf']);
    Route::get('api/clientespj', ['as' => 'ajax.clientespj', 'uses' => 'SearchController@clientespj']);
    Route::get('api/fornecedorespj', ['as' => 'ajax.fornecedorespf', 'uses' => 'SearchController@fornecedorespj']);
    //AJAX Search Empresa
    Route::get('api/searchEmpresaNF', ['as' => 'ajax.searchnfempresa', 'uses' => 'SearchController@searchEmpresaNF']);
    //AJAX contas por empresa
    Route::get('api/searchContasByEmpresa/{empresa_id}', ['as' => 'ajax.contasbyempresa', 'uses' => 'SearchController@contasByEmpresa']);
    //Encontro de contas
    Route::get('/api/encontrocontas.searchContasReceber/{cliente_id}', ['as' => 'ajax.searchcontasreceber', 'uses' => 'SearchController@searchContasReceber']);
    Route::get('/api/encontrocontas.searchContasPagar/{cliente_id}', ['as' => 'ajax.searchcontaspagar', 'uses' => 'SearchController@searchContasPagar']);
    Route::get('api/searchClienteCompleto', ['as' => 'searchclientecompleto', 'uses' => 'SearchController@indexclientecompleto']);
    Route::get('ajax.getsetor', ['as' => 'ajax.getsetor', 'uses' => 'SearchController@getSetor']);
    Route::get('api/searchCondicaoPagamento', ['as' => 'ajax.searchcondicaopagamento', 'uses' => 'SearchController@searchCondicaoPagamento']);
    Route::get('api/searchMovimentoContaAtual', ['as' => 'ajax.movimentacaocontaatual', 'uses' => 'SearchController@indexmovimentocontaatual']);
    Route::get('api/getLayoutBanco/{id}', ['as' => 'ajax.getlayoutbance', 'uses' => 'SearchController@getLayoutBanco']);
    Route::get('api/checkCaixaIsFechado', ['as' => 'ajax.checkcaixafechado', 'uses' => 'SearchController@checkCaixaIsFechado']);
    Route::get('api/getCodMovRemessaByBanco/{id}/{tipo}', ['as' => 'ajax.getCodMovRemessaByBanco/{id}/{tipo}', 'uses' => 'SearchController@getCodMovRemessaByBanco']);
    Route::get('api/getTipoPgtoParcela/{id}', ['as' => 'ajax.getTipoPgtoParcela/{id}', 'uses' => 'SearchController@getTipoPgtoParcela']);

    //Cidades, Bairros, Ruas e outras Controllers
    //clientetelefone ajax
    Route::get('clientetelefone/buscaclientetelefone/{tel}', ['as' => 'ajax.clientetelefone', 'uses' => 'SearchController@buscaClienteTelefone']);
    Route::get('setorcolaboradores/buscacolaboradorsetor/{setor_id}', ['as' => 'ajax.buscacolaboradorsetor', 'uses' => 'SetorcolaboradoresController@buscaAjax']);

    // FASE 3: index de cidade/bairro com flag de UI moderna. O resource exclui o
    // index; o index é declarado à parte com o middleware 'ui.moderna' (quando a
    // flag do módulo está ON, redireciona p/ o recurso Filament). Sem a flag,
    // serve a tela legada normalmente. Demais ações seguem no resource.
    Route::resource('cidade', 'CidadeController')->except(['index']);
    Route::get('cidade', ['as' => 'cidade.index', 'uses' => 'CidadeController@index'])
        ->middleware('ui.moderna:cidade,/admin/cidades');
    Route::get('cidade/dropdown/{uf}', ['as' => 'ajax.dropdowncidadeuf', 'uses' => 'CidadeController@dropdown']);
    Route::get('cidade/buscaPorNomeEEstado/{nome}/{estado}', ['as' => 'ajax.buscapornomeuf', 'uses' => 'CidadeController@buscaPorNomeEEstado']);

    Route::resource('bairro', 'BairroController')->except(['index']);
    Route::get('bairro', ['as' => 'bairro.index', 'uses' => 'BairroController@index'])
        ->middleware('ui.moderna:bairro,/admin/bairros');
    Route::get('bairro/dropdown/{id}', ['as' => 'ajax.bairrodropdown', 'uses' => 'BairroController@dropdown']);
    Route::get('bairro/dropdown/{uf}/{getOptions}', ['as' => 'ajax.bairrodropdown.uf', 'uses' => 'BairroController@dropdown']);
    Route::get('bairro/buscaPorNomeECidade/{nome}/{cidade}', ['as' => 'ajax.buscanomecidade/{nome}/{cidade}', 'uses' => 'BairroController@buscaPorNomeECidade']);
    // Route::post('bairro', ['middleware' => 'auth', 'as' => 'bairro.store', 'uses' => 'BairroController@store']);

    Route::resource('rua', 'RuaController');
    Route::get('rua/dropdown/{id}', ['as' => 'ajax.ruadropdown', 'uses' => 'RuaController@dropdown']);
    Route::post('rua', ['middleware' => 'auth', 'as' => 'rua.store', 'uses' => 'RuaController@store']);
    Route::get('inconsistencia.index', ['as' => 'inconsistencia.index', 'uses' => 'InconsistenciaController@index']);
    Route::get('inconsistencia.getInconsistencias', ['as' => 'inconsistencia.getInconsistencias', 'uses' => 'InconsistenciaController@getInconsistencias']);
    Route::get('inconsistencia.getRegistros', ['as' => 'inconsistencia.getRegistros', 'uses' => 'InconsistenciaController@getRegistros']);
    Route::post('inconsistencia.ignorarRua', ['as' => 'inconsistencia.ignorarRua', 'uses' => 'InconsistenciaController@ignorarRua']);
    Route::post('inconsistencia.ignorarBairro', ['as' => 'inconsistencia.ignorarBairro', 'uses' => 'InconsistenciaController@ignorarBairro']);

    Route::get('getBairrosRuasByCidade/{cidad_id}', ['as' => 'ajax.getBairrosRuasByCidade/{cidad_id}', 'uses' => 'SearchController@getBairrosRuasByCidade']);

    Route::get('filtro.filtroFollowUpAll', ['middleware' => 'auth', 'as' => 'filtro.filtroFollowUpAll', 'uses' => 'ReportController@filtroFollowUpAll']);
    Route::get('report.FollowUpAll/{par}', ['middleware' => 'auth', 'as' => 'report.FollowUpAll', 'uses' => 'ReportController@reportFollowUpAll']);
    Route::post('/sendPedidoPendente', ['middleware' => 'auth', 'uses' => 'ApiController@sendPedidoPendente']);

    Route::get('logs', ['as' => 'logs.reports', 'uses' => 'ReportlogsController@index']);
    Route::get('logs.filtrar', ['as' => 'logs.filtrar', 'uses' => 'ReportlogsController@filtarLogs']);


    Route::get('excluirTelefoneChamada/{id}', function ($id) {
        $tel = \App\Monitoramentochamadas::find($id);
        if (is_null($tel)) {
            return "Ocorreu um erro ao localizar esta chamada em espera. "
                . "Certifique-se de que não clicou duas vezes consecutivas no botão "
                . "ou que a chamada tenha sido aceita/rejeitada antes.";
        }
        $tel->delete();
        return 'OK';
    });

    Route::post('rejeitaligacao', function (Request $request) {
        $data = $request->all();

        $empresa = \App\Empresa::find($data['empresa_id_telefonerejeitado']);

        $data['empresa_id'] = $data['empresa_id_telefonerejeitado'];
        $data['telefone'] = $data['telefonerejeitado'];
        $data['rejeitada'] = true;
        $data['atendida'] = isset($data["atendida"]) && $data["atendida"];
        $data['user_id'] = auth()->user()->id;
        $data['grupo_id'] = $empresa->grupo_id;
        $data['cliente_id'] = $data['rejcliente_id'];

        \App\Ligacoestelefonicas::create($data);

        return "OK|";
    })->name('rejeitaligacao.store');

    //Agencia
    Route::resource('agencia', 'AgenciaController');

    Route::post('log.js', ['as' => 'ajax.log.js', 'uses' => 'ReportlogsController@logJs']);
    Route::get('consultasoracle', function () {
        //        set_time_limit ( 0);
        //        $clientes = DB::select("select uf, cidade_id, bairro_id, rua_id, numero, id from clientes where id >= 41339 order by id asc ");
        //        $up = "";
        //        foreach ($clientes as $cliente) {
        //            $latLong = buscaLatitudeLongitude($cliente->uf, $cliente->cidade_id, $cliente->bairro_id, $cliente->rua_id, $cliente->numero);
        //            $up = "update clientes set latitude = " . $latLong->location->lat . ", longitude = "
        //                . $latLong->location->lng . ", locationtype = '" . $latLong->location_type . "' where id = " . $cliente->id . "";
        //            DB::statement($up);
        //            dump($cliente->id);
        //            $up = "update pedidos set latitude = " . $latLong->location->lat . ", longitude = "
        //                . $latLong->location->lng . " where cliente_id = " . $cliente->id . "";
        //            DB::statement($up);
        //        }
        dd("foi");
        //        $sql = "select id, latitude, longitude from setors where empresa_id = 2";
        //        $sel = DB::select($sql);
        //        dd($sel);
        //$sql = "update nfrecebidas set cliente_id = 42024 where nfnumero = 225049";
        //        dd(DB::statement($sql));
    })->name('ajax.consultasoracle');

    Route::get('organizarestoque', function () {
        try {
            DB::beginTransaction();
            $sql = collect(DB::select(
                "select "
                    . "setor_id, produto_id, "
                    . "sum(case when movimentacao = 'SAIDA' or quantidade < 0 then quantidade * -1 else quantidade end) as quantidade "
                    . " from estoquesetorhistoricos "
                    . " where datahoracompetencia >= to_date('2017-12-31 23:59:00', 'yyyy-mm-dd hh24:mi:ss')"
                    . " group by setor_id, produto_id "
                    . " order by setor_id, produto_id"
            ));
            foreach ($sql as $value) {
                $qtde = collect(DB::select("select quantidade from estoquefechamentosetors
                        where estoquefechamento_id = 645 and produto_id = " . $value->produto_id . " and setor_id = " . $value->setor_id))->first();
                dump($qtde);
                dump($value);
                if (is_null($qtde))
                    $quantidade = $value->quantidade;
                else
                    $quantidade = $value->quantidade + $qtde->quantidade;
                dump($quantidade);
                DB::statement('update estoquesetors set quantidade = '
                    . $quantidade . ' where produto_id = '
                    . $value->produto_id . ' and setor_id = ' . $value->setor_id);
            }
            //                dd(DB::select('select * from estoquesetors where produto_id = 98 and setor_id = 103'));
            DB::commit();
        } catch (Exception $ex) {
            DB::rollback();
            dd($ex);
        }
    })->name('ajax.organizarestoque');

    //Tipo de Documentos
    Route::resource('documentotipo', 'DocumentotipoController');
    //Documentos
    Route::resource('documento', 'DocumentoController');
    Route::get('documento.downloadversao/{id}', ['as' => 'documento.downloadversao', 'uses' => 'DocumentoController@downloadVersao']);
    Route::post('documento.versao', ['as' => 'documento.versao', 'uses' => 'DocumentoController@addEditVersao']);
    Route::get('documentogestao.index', ['as' => 'documentogestao.index', 'uses' => 'DocumentogestaoController@index']);
    Route::get('documentogestao.getDashboard', ['as' => 'documentogestao.getDashboard', 'uses' => 'DocumentogestaoController@getDashboard']);
});
Route::get('api/getToken', 'NfwebController@getToken');

Route::get('nfeConsulta', 'NfwebController@nfeConsulta');

Route::get('vendasmensaisgestao.downloadPowerPoint/{fileName}', ['as' => 'vendasmensaisgestao.downloadPowerPoint', 'uses' => 'VendasmensaisgestaoController@downloadPowerPoint']);
