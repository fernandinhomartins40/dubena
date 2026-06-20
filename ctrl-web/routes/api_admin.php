<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API ADMIN (SPA React) — prefixo /api/admin, grupo 'api_admin' (Sanctum)
|--------------------------------------------------------------------------
| Namespace: App\ApiAdmin\Http\Controllers (definido no RouteServiceProvider).
| Auth por sessão/cookie (Sanctum SPA). Separada da API do app mobile.
*/

// Público (sessão stateful, mas sem exigir login) — login + health.
Route::post('login', 'AuthController@login')->name('login');
Route::get('health', fn () => response()->json(['ok' => true, 'app' => 'ctrl-web-admin-api']))->name('health');

// Autenticado (Sanctum / guard web).
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', 'AuthController@logout')->name('logout');
    Route::get('me', 'AuthController@me')->name('me');
    Route::get('dashboard/resumo', 'DashboardController@resumo')->name('dashboard.resumo');

    // S2 — lookups p/ selects do SPA
    Route::get('lookups/cidades', 'LookupController@cidades')->name('lookups.cidades');
    Route::get('lookups/bairros', 'LookupController@bairros')->name('lookups.bairros');
    Route::get('lookups/segmentos', 'LookupController@segmentos')->name('lookups.segmentos');
    Route::get('lookups/tipo-pessoa', 'LookupController@tipoPessoa')->name('lookups.tipopessoa');
    Route::get('lookups/parentescos', 'LookupController@parentescos')->name('lookups.parentescos');
    Route::get('lookups/contato-tipos', 'LookupController@contatoTipos')->name('lookups.contatotipos');
    Route::get('lookups/contato-situacoes', 'LookupController@contatoSituacoes')->name('lookups.contatosituacoes');
    Route::get('lookups/produtos', 'LookupController@produtos')->name('lookups.produtos');

    // S2 — Cliente (página completa)
    Route::get('clientes', 'ClienteController@index')->name('clientes.index');
    Route::post('clientes', 'ClienteController@store')->name('clientes.store');
    Route::get('clientes/{id}', 'ClienteController@show')->name('clientes.show');
    Route::put('clientes/{id}', 'ClienteController@update')->name('clientes.update');
    Route::delete('clientes/{id}', 'ClienteController@destroy')->name('clientes.destroy');

    // S2 — sub-recurso telefones do cliente (aba na ficha)
    Route::get('clientes/{id}/telefones', 'ClienteController@telefones')->name('clientes.telefones');
    Route::post('clientes/{id}/telefones', 'ClienteController@addTelefone')->name('clientes.telefones.add');
    Route::delete('clientes/{id}/telefones/{telId}', 'ClienteController@delTelefone')->name('clientes.telefones.del');

    // lookup de tipos de telefone
    Route::get('lookups/telefone-tipos', 'LookupController@telefoneTipos')->name('lookups.telefonetipos');

    // F1 — Produto (página completa, abas)
    Route::get('produtos', 'ProdutoController@index')->name('produtos.index');
    Route::post('produtos', 'ProdutoController@store')->name('produtos.store');
    Route::get('produtos/{id}', 'ProdutoController@show')->name('produtos.show');
    Route::put('produtos/{id}', 'ProdutoController@update')->name('produtos.update');
    Route::delete('produtos/{id}', 'ProdutoController@destroy')->name('produtos.destroy');
    // F1 — sub-recurso Origens do combustível (soma 100%)
    Route::get('produtos/{id}/origens', 'ProdutoController@origens')->name('produtos.origens');
    Route::put('produtos/{id}/origens', 'ProdutoController@salvarOrigensEndpoint')->name('produtos.origens.save');
    // F1 — visão nova: estoque por setor + giro (agregado na ficha)
    Route::get('produtos/{id}/estoque', 'ProdutoController@estoque')->name('produtos.estoque');
    // F1 — ação: atualizar preços em massa (preview + aplicar)
    Route::get('produtos-precos/preview', 'ProdutoController@precosPreview')->name('produtos.precos.preview');
    Route::put('produtos-precos/aplicar', 'ProdutoController@precosAplicar')->name('produtos.precos.aplicar');
    // F1 — Configurações do Produto (Classes / Unidades)
    Route::get('produto-config/classes', 'ProdutoConfigController@classes')->name('produtoconfig.classes');
    Route::post('produto-config/classes', 'ProdutoConfigController@salvarClasse')->name('produtoconfig.classes.store');
    Route::put('produto-config/classes/{id}', 'ProdutoConfigController@salvarClasse')->name('produtoconfig.classes.update');
    Route::delete('produto-config/classes/{id}', 'ProdutoConfigController@excluirClasse')->name('produtoconfig.classes.del');
    Route::get('produto-config/unidades', 'ProdutoConfigController@unidades')->name('produtoconfig.unidades');
    Route::post('produto-config/unidades', 'ProdutoConfigController@salvarUnidade')->name('produtoconfig.unidades.store');
    Route::put('produto-config/unidades/{id}', 'ProdutoConfigController@salvarUnidade')->name('produtoconfig.unidades.update');
    Route::delete('produto-config/unidades/{id}', 'ProdutoConfigController@excluirUnidade')->name('produtoconfig.unidades.del');
    // F1 — lookups do Produto
    Route::get('lookups/produto-classes', 'LookupController@produtoClasses')->name('lookups.produtoclasses');
    Route::get('lookups/unidades', 'LookupController@unidades')->name('lookups.unidades');
    Route::get('lookups/nf-grupos-fiscais', 'LookupController@nfGruposFiscais')->name('lookups.nfgruposfiscais');
    Route::get('lookups/ipis', 'LookupController@ipis')->name('lookups.ipis');
    Route::get('lookups/estados', 'LookupController@estados')->name('lookups.estados');
    Route::get('lookups/produtos-vasilhame', 'LookupController@produtosVasilhame')->name('lookups.produtosvasilhame');
    Route::get('lookups/produtos-ressarcimento', 'LookupController@produtosRessarcimento')->name('lookups.produtosressarcimento');
    Route::get('lookups/tipo-glp', 'LookupController@tipoGlp')->name('lookups.tipoglp');

    // F5 — Estoque (saldos + transferência + requisição + acerto + inventário + físico + fechamento)
    Route::get('estoque/saldos', 'EstoqueController@saldos')->name('estoque.saldos');
    Route::post('estoque/acerto', 'EstoqueController@acerto')->name('estoque.acerto');
    Route::get('estoque/transferencias', 'EstoqueController@transferencias')->name('estoque.transferencias');
    Route::post('estoque/transferencias', 'EstoqueController@criarTransferencia')->name('estoque.transferencias.store');
    Route::get('estoque/requisicoes', 'EstoqueController@requisicoes')->name('estoque.requisicoes');
    Route::post('estoque/requisicoes', 'EstoqueController@criarRequisicao')->name('estoque.requisicoes.store');
    Route::get('estoque/inventarios', 'EstoqueController@inventarios')->name('estoque.inventarios');
    Route::post('estoque/inventarios', 'EstoqueController@criarInventario')->name('estoque.inventarios.store');
    Route::get('estoque/fisico', 'EstoqueController@fisicos')->name('estoque.fisicos');
    Route::post('estoque/fisico', 'EstoqueController@criarFisico')->name('estoque.fisicos.store');
    Route::post('estoque/fisico/{id}/efetivar', 'EstoqueController@efetivarFisico')->name('estoque.fisicos.efetivar');
    Route::get('estoque/fechamentos', 'EstoqueController@fechamentos')->name('estoque.fechamentos');
    Route::post('estoque/fechamentos', 'EstoqueController@fechar')->name('estoque.fechamentos.store');
    Route::post('estoque/fechamentos/abrir', 'EstoqueController@abrir')->name('estoque.fechamentos.abrir');

    // F4 — Cadastros de apoio (consolidado em Configurações dos módulos donos)
    Route::get('cadastros/{tipo}', 'CadastroApoioController@index')->name('cadastros.index');
    Route::post('cadastros/{tipo}', 'CadastroApoioController@salvar')->name('cadastros.store');
    Route::put('cadastros/{tipo}/{id}', 'CadastroApoioController@salvar')->name('cadastros.update');
    Route::delete('cadastros/{tipo}/{id}', 'CadastroApoioController@excluir')->name('cadastros.del');

    // F3 — Empresa / Config / Grupos
    Route::get('empresas', 'EmpresaController@index')->name('empresas.index');
    Route::post('empresas', 'EmpresaController@store')->name('empresas.store');
    Route::get('empresas/{id}', 'EmpresaController@show')->name('empresas.show');
    Route::put('empresas/{id}', 'EmpresaController@update')->name('empresas.update');
    Route::delete('empresas/{id}', 'EmpresaController@destroy')->name('empresas.destroy');
    Route::post('empresas/{id}/ativar', 'EmpresaController@ativar')->name('empresas.ativar');
    // Config da empresa (106 col, sub-abas) + senha mestra + teste SMTP
    Route::get('empresas/{id}/config', 'EmpresaConfigController@show')->name('empresas.config');
    Route::put('empresas/{id}/config', 'EmpresaConfigController@update')->name('empresas.config.save');
    Route::put('empresas/{id}/config/senha-mestra', 'EmpresaConfigController@senhaMestra')->name('empresas.config.senha');
    Route::post('empresas/{id}/config/testar-email', 'EmpresaConfigController@testarEmail')->name('empresas.config.email');
    // Grupos de empresas
    Route::get('grupos', 'EmpresaController@grupos')->name('grupos.index');
    Route::post('grupos', 'EmpresaController@salvarGrupo')->name('grupos.store');
    Route::put('grupos/{id}', 'EmpresaController@salvarGrupo')->name('grupos.update');
    Route::delete('grupos/{id}', 'EmpresaController@excluirGrupo')->name('grupos.del');
    // Lookups contábeis/operacionais p/ a config
    Route::get('lookups/planos-conta', 'LookupController@planosConta')->name('lookups.planosconta');
    Route::get('lookups/centros-custo', 'LookupController@centrosCusto')->name('lookups.centroscusto');
    Route::get('lookups/contas', 'LookupController@contas')->name('lookups.contas');
    Route::get('lookups/setores', 'LookupController@setores')->name('lookups.setores');
    Route::get('lookups/regioes', 'LookupController@regioes')->name('lookups.regioes');

    // F2 — Geográfico (Cidade/Bairro/Rua/Região) — 1 página com abas
    Route::get('geo/cidades', 'GeograficoController@cidades')->name('geo.cidades');
    Route::post('geo/cidades', 'GeograficoController@salvarCidade')->name('geo.cidades.store');
    Route::put('geo/cidades/{id}', 'GeograficoController@salvarCidade')->name('geo.cidades.update');
    Route::delete('geo/cidades/{id}', 'GeograficoController@excluirCidade')->name('geo.cidades.del');
    Route::get('geo/bairros', 'GeograficoController@bairros')->name('geo.bairros');
    Route::post('geo/bairros', 'GeograficoController@salvarBairro')->name('geo.bairros.store');
    Route::put('geo/bairros/{id}', 'GeograficoController@salvarBairro')->name('geo.bairros.update');
    Route::delete('geo/bairros/{id}', 'GeograficoController@excluirBairro')->name('geo.bairros.del');
    Route::get('geo/ruas', 'GeograficoController@ruas')->name('geo.ruas');
    Route::post('geo/ruas', 'GeograficoController@salvarRua')->name('geo.ruas.store');
    Route::put('geo/ruas/{id}', 'GeograficoController@salvarRua')->name('geo.ruas.update');
    Route::delete('geo/ruas/{id}', 'GeograficoController@excluirRua')->name('geo.ruas.del');
    Route::get('geo/regioes', 'GeograficoController@regioes')->name('geo.regioes');
    Route::post('geo/regioes', 'GeograficoController@salvarRegiao')->name('geo.regioes.store');
    Route::put('geo/regioes/{id}', 'GeograficoController@salvarRegiao')->name('geo.regioes.update');
    Route::delete('geo/regioes/{id}', 'GeograficoController@excluirRegiao')->name('geo.regioes.del');

    // S2 — sub-recursos do Cliente (abas Histórico/Interações/Convênio/Preços)
    Route::get('clientes/{id}/historico', 'ClienteSubController@historico')->name('clientes.historico');
    Route::get('clientes/{id}/interacoes', 'ClienteSubController@interacoes')->name('clientes.interacoes');
    Route::post('clientes/{id}/interacoes', 'ClienteSubController@addInteracao')->name('clientes.interacoes.add');
    Route::delete('clientes/{id}/interacoes/{subId}', 'ClienteSubController@delInteracao')->name('clientes.interacoes.del');
    Route::get('clientes/{id}/convenio', 'ClienteSubController@convenio')->name('clientes.convenio');
    Route::put('clientes/{id}/convenio', 'ClienteSubController@salvarConvenio')->name('clientes.convenio.save');
    Route::post('clientes/{id}/convenio/dependentes', 'ClienteSubController@addDependente')->name('clientes.dep.add');
    Route::delete('clientes/{id}/convenio/dependentes/{depId}', 'ClienteSubController@delDependente')->name('clientes.dep.del');
    Route::get('clientes/{id}/precos', 'ClienteSubController@precos')->name('clientes.precos');
});
