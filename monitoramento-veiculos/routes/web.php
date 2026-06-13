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

  use Illuminate\Support\Facades\Input;
  use Illuminate\Http\Request;
  use App\Veiculo;
  use App\Empresa;

  Route::get('/', function () {
    return view('welcome');
});

////Rotas antigas

  Route::group(['middleware' => ['web']], function() {
    //  Route::resource('/empresas_grupo_form', ['middleware' => 'auth', 'as' => 'empresas_grupo_form', 'uses' => 'EmpresasGrupoController@form']);
  });
  Route::get('/login', ['as' => 'login', 'uses' => 'AuthController@login']);
  Route::post('/handleLogin', ['as' => 'handleLogin', 'uses' => 'AuthController@handleLogin']);
  Route::get('/logout', ['as' => 'logout', 'uses' => 'AuthController@logout']);
  Route::get('/changepassword', ['middleware' => 'auth', 'as' => 'changepassword', 'uses' => 'UsersController@indexchangepassword']);
  Route::patch('/updatepassword/{id}', ['middleware' => 'auth', 'as' => 'user.updatepassword', 'uses' => 'UsersController@updatepassword']);

  Route::group(['middleware' => 'auth', 'where' => ['id' => '[0-9]+']], function() {

    Route::get('/home', ['as' => 'home', 'uses' => 'UsersController@home']);
    //Grupos de Empresas
    Route::resource('empresas_grupo', 'EmpresasGrupoController');
    //Empresas
    Route::resource('empresa', 'EmpresaController');
    Route::get('/empresachange/{id}', ['middleware' => 'auth', 'as' => 'empresa.change', 'uses' => 'EmpresaController@change']);
    Route::get('empresa/carregaempresa/{id}', 'EmpresaController@carregaempresa');
    //Usuários
    Route::resource('user', 'UsersController');
    //Config
    Route::resource('config', 'ConfigController');
    //Veiculos
    Route::resource('veiculo', 'VeiculoController');
    //Cadastros
    Route::get('/cadastroindex', ['middleware' => 'auth', 'as' => 'cadastro.index', 'uses' => 'CadastroController@index']);
    Route::post('api/atualizarCadastro', 'CadastroController@atualizarCadastro');
    Route::get('/cadastroindexnew', ['middleware' => 'auth', 'as' => 'cadastro.indexnew', 'uses' => 'CadastroController@indexnew']);
    Route::post('api/criarCadastro', 'CadastroController@criarCadastro');
    //Rastreamento
    Route::get('/rastreamento', ['middleware' => 'auth', 'as' => 'rastreamento.index', 'uses' => 'RastreamentoController@index']);
    //Cerca
    Route::resource('cerca', 'CercaController');
    //relatórios
    Route::get('/rota', ['middleware' => 'auth', 'as' => 'rota.index', 'uses' => 'RotaController@index']);
    Route::get('/evento', ['middleware' => 'auth', 'as' => 'evento.index', 'uses' => 'EventoController@index']);
    Route::get('report.eventosVeiculo', ['as' => 'report.eventosVeiculo', 'uses' => 'ReportController@reportEventosVeiculo']);

    Route::post('api.encode', ['middleware' => 'auth', 'as' => 'api.encode', 'uses' => 'UsersController@encode_secret']);
    Route::get('api/getPosicaoAtual', 'SearchController@getPosicaoAtual');
    Route::get('api/getPedidosPendentes', 'SearchController@getPedidosPendentes');
    Route::get('api/getCercaRastreamento', 'SearchController@getCercaRastreamento');
    Route::get('api/getCoordenadasSetor', 'SearchController@getCoordenadasSetor');
    Route::get('api/getCercaPoligono', 'SearchController@getCercaPoligono');
    Route::get('api/getRotas', 'SearchController@getRotas');
    Route::get('api/updateToken', 'SearchController@updateToken');
    Route::get('veiculos/dropdown', function(){
        // FASE 1 (segurança): corrige IDOR (S9). Antes retornava veículos de
        // QUALQUER empresa_id informado. Agora restringe às empresas que o
        // usuário autenticado pode acessar (relação empresa_user) ou ao seu grupo.
        $input = Input::get('option');
        $user = \Auth::user();
        $empresasPermitidas = $user->empresas()->lists('id')->toArray();

        $empresa = Empresa::where('id', $input)
            ->where(function($q) use ($empresasPermitidas, $user) {
                $q->whereIn('id', $empresasPermitidas)
                  ->orWhere('grupo_id', $user->grupo_id);
            })
            ->first();

        if (is_null($empresa)) {
            return Response::make(['error' => 'Empresa não autorizada.'], 403);
        }

        $veiculos = Veiculo::where('empresa_id', $empresa->id)->orderby('placa')->get(['id','descricao', 'placa']);
        $empresa->logo = "";
        $empresa->logoimg = "";
        return Response::make(["veiculos"=>$veiculos, "empresa"=>$empresa]);
    });

});
