<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');
//Bina
Route::post('/testarToken', ['middleware' => 'auth:api', 'uses' => 'ApiController@testarToken']);
Route::get('/test', function () {
    return collect([
        "name" => "Jefffff"
    ])->toJson();
})->middleware('auth:api');

Route::post('app/storeConfig', 'App\AppController@storeConfig');

Route::post("webhook/pix", "PixController@webhookPix");
Route::match(["GET", "POST"], "webhook", "PixController@webhook")->name("webhook.pix");

Route::post('/revokeToken', function () {
    $tokenId = request()->get("token");

    $explodedToken = explode('.', $tokenId);

    $payload = base64_decode($explodedToken[1]);

    $payloadArray = json_decode($payload, true);

    $token = \Laravel\Passport\Token::findOrFail($payloadArray["jti"]);

    $token->revoke();

    return responseSuccess([], "Token revoked");
});

Route::post('/setAndroidRegistration', 'ApiController@setAndroidRegistration');
// Route::post('app/getTokenToken', 'App\AppController@getTokenToken');
Route::group(['middleware' => ['auth:api']], function () {
    Route::post('/gravarTelefone', 'ApiController@gravarTelefone');
    //Android
    Route::post('/getUsuarios', 'ApiController@getUsuarios');
    Route::post('/getEmpresas', 'ApiController@getEmpresas');
    Route::post('/getPedidosMotivosAtrasos', 'ApiController@getPedidosMotivosAtrasos');
    Route::post('/getPedidosSituacoes', 'ApiController@getPedidosSituacoes');
    Route::post('/sendPedidoPendente', 'ApiController@sendPedidoPendente');
    Route::post('/getPedidosPendentes', 'ApiController@getPedidosPendentes');
    Route::post('/getVeiculos', 'ApiController@getVeiculos');
    Route::post('/setVeiculoAtivo', 'ApiController@setVeiculoAtivo');
    Route::post('/setPedidoSituacao', 'ApiController@setPedidoSituacao');
    Route::post('/getValeGas', 'ApiController@getValeGas');
    Route::post('/setAndroidMensagem', 'ApiController@setAndroidMensagem');
    Route::post('/getPedidosReport', 'ApiController@getPedidosReport');

    Route::post('/getRastreamentoGrupos', 'ApiController@getRastreamentoGrupos');
    Route::post('/getRastreamentoEmpresas', 'ApiController@getRastreamentoEmpresas');
    Route::post('/getRastreamentoVeiculos', 'ApiController@getRastreamentoVeiculos');
    Route::post('/getRastreamentoSetors', 'ApiController@getRastreamentoSetors');
    Route::post('/getRastreamentoUsers', 'ApiController@getRastreamentoUsers');
    Route::post('/getRastreamentoPedidos', 'ApiController@getRastreamentoPedidos');


    Route::get('/satcfe/{id}', 'CupomFiscalController@getDoc');
    Route::get('/satcfe/cancel/{id}', 'CupomFiscalController@getDocCancel');
    Route::get('/sat/getEmpresa', 'CupomFiscalController@getEmpresa');
    Route::post('/sat/getUserInfo', 'CupomFiscalController@getUserInfo');

    //** App routers

    Route::get('app/testFromApi', 'App\AppController@testTokenApi');

    Route::get('app/testTokenERP', 'App\AppController@testTokenERP');

    Route::get('testTokenNfweb', 'NfwebController@testToken');

    /**
     * send notification to user
     */
    Route::get('notify', 'App\AppController@notify')->middleware("throttle:540,1");

    /**
     * all addresses routes
     */
    Route::prefix('address')->group(function () {

        /**
         * save address
         */
        Route::post('link', 'App\AppController@updateAppAddress');

        /**
         * save address
         */
        Route::post('migrate', 'App\AppController@migrateAddresses');
    });

    /**
     * all order's routes
     */
    Route::prefix('order')->group(function () {

        /**
         * return all client's orders
         */
        Route::post('link', 'App\AppController@createOrder');

        /**
         * return order history of client
         */
        Route::get('history', 'PedidoController@history');

        /**
         * cancel order
         */
        Route::post('cancel', 'App\AppController@cancelOrder');

        /**
         * pegar pix
         */
        Route::get('getPix', 'App\AppController@getPix');
    });
    /**
     * all vehicles routes
     */
    Route::prefix('vehicle')->group(function () {

        /**
         * return all client's orders
         */
        Route::get('lastPosition', 'App\AppController@lastPosition');
    });

    /**
     * all payment's routes
     */
    Route::prefix('payment')->group(function () {

        /**
         * gas de bolso info
         */
        Route::get('infoGB', 'App\AppController@infoGB');
    });

    Route::prefix('client')->group(function () {

        /**
         * Migrate all clientes
         */
        Route::post('migrate', 'App\AppController@migrateClients');
    });

    /**
     * all convenio's routes
     */
    Route::prefix('convenio')->group(function () {

        /**
         * Link customer from api and erp by convenio
         */
        Route::post('link', 'App\AppController@linkConvenio');
    });

    Route::prefix('/nfweb')->group(function () {
        Route::post('login', 'NfwebController@login');
        Route::get('init', 'NfwebController@nfwebRootRequest');
        Route::post('changeRegistrationId', 'NfwebController@changeRegistrationId');
        Route::post('changeVeiculo', 'NfwebController@changeVeiculo');
        Route::post('getParcelasVencidasCliente', 'NfwebController@getParcelasVencidasCliente');
        Route::post('getCliente', 'NfwebController@getCliente');
        Route::post('savePedido', 'NfwebController@savePedido');
        Route::post('saveCliente', 'NfwebController@saveCliente');
        Route::post('saveClienteObs', 'NfwebController@saveClienteObs');
        Route::get('pedidosReport', 'NfwebController@pedidosReport');
        Route::get('pedidoConsulta', 'NfwebController@pedidoConsulta');
        Route::get('nfeConsulta', 'NfwebController@nfeConsulta');
        Route::get('pedidoDuplicata', 'NfwebController@pedidoDuplicata');
        Route::get('visualizarDanfe', 'NfwebController@visualizarDanfe');
        Route::get('enviarEmail', 'NfwebController@enviarEmail');
        Route::get('visualizarBoleto', 'NfwebController@visualizarBoleto');
        Route::get('getCadastros', 'NfwebController@getCadastros');
        Route::get('baixarDanfe', 'NfwebController@baixarDanfe');
    });

    Route::put('appnotification/massReturn/{notification_id}', 'AppnotificationController@massReturn');

    // * Emissão de PIX
    Route::post("pix/gerartransacao", "PixController@generatePix");

    Route::post("pix/transacaocompleta", "PixController@checkPixComplete");

    // * Teste para criar um job para deixar o pedido como pago
    Route::get('test-paid-pix/{pedido_id}', function ($pedido_id) {
        dispatch(new App\Jobs\ProcessPixPedido($pedido_id));
        // (new App\Processors\MobileAppProcessor())->createPixOrder(679);
        return responseSuccess();
    });
});
