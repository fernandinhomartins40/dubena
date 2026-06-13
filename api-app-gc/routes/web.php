<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\Http\Resources\ApiResources;

Route::get('setToken', function () {
    Storage::disk('public')->put("token.txt", json_encode(Input::all()));
});

Route::get('/', function () {
    return redirect()->route('home');
});

Route::get('api/getToken', 'SecretController@getToken');
Route::get('api/marker', 'ApiController@marker');
Route::get('downloadApp', 'ApiController@downloadApp');
//Route::get('order/track', 'PedidoController@track');

Auth::routes();

Route::post('sendmsg', function () {
    $to = [env('FCM_ID'), env('FCM_ID_2')];
    return ApiResources::notifyDevices("Teste", "Estamos Testando", $to);
});

Route::group(['middleware' => ['auth']], function () {
    Route::get('/home', ['as' => 'home', 'uses' => 'HomeController@index']);
    Route::get('/logs', ['as' => 'logs', 'uses' => 'ApiController@logs']);
    Route::post('/testTokenErp', ['as' => 'testTokenErp', 'uses' => 'SecretController@testTokenERP']);

    //    Route::resource('/configuser', 'ConfigUserController');

    Route::post('/user/getToken', 'UserController@getToken');
    Route::post('/user/password/{user}', 'UserController@password');

    Route::resource('/user', 'UserController')->except([
        'destroy',
        'show'
    ]);

    Route::resource('/generalConfig', 'GeneralConfigController')->only([
        'store',
        'index'
    ]);

    Route::get('/passport', ['as' => 'passport', 'uses' => 'PassportController@index']);

    Route::resource('/produtocategorias', 'ProdutoCategoriaController');

    Route::resource('/condicaopagamento', 'CondicaoPagamentoController');

    // Route::resource('appconfig', 'AppConfigController');

    Route::resource('produto', 'ProdutoController');
    Route::resource('pedidosituacao', 'PedidoSituacaoController');
    Route::get('ajax/getLog', 'ApiController@getLog');
});
