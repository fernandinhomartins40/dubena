<?php

/** @noinspection PhpUndefinedMethodInspection */

use App\Api\Resources\ApiResources;
use Composer\DependencyResolver\Rule;
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

Route::post("web/testTokenFromApi", 'SecretController@testTokenFromApi');


Route::get('video/get', 'VideoController@get');

Route::group(['middleware' => ['auth:api', 'access']], function () {
    Route::get('/users', function (Request $request) {
        // FASE 1 (segurança — S5): removido `dd($request)` de produção, que
        // despejava o request/ambiente inteiro na resposta.
        return $request->user();
    });

    /**
     * Notifications test
     */
    Route::post('sendmsg', function () {
        //        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        //        Artisan::call("notifications:send", [], $output);
        //        return $output->fetch());
        $to = [env('FCM_ID'), env('FCM_ID_2')];
        define("IS_CURL_OPT", true);
        return ApiResources::notifyDevices(Input::get("title", "Teste"), Input::get("body", "Estamos Testando"), $to);
    });

    /**
     * Notifications test
     */
    Route::post('reportLog', "ApiController@reportLog");

    /**
     * logout and delete oauth_client
     */
    Route::delete('logout', 'SecretController@destroy');

    /**
     * test token
     */
    Route::get('testToken', 'SecretController@testToken');

    /**
     * get address from gmaps
     */
    Route::get('getAddressFromLatLng', 'ApiController@getAddressFromLatLng');

    /**
     * test token api
     */
    Route::get('testTokenERP', 'SecretController@testTokenERP');

    /**
     * get all necessary data on open app
     */
    Route::get('app/init', 'SecretController@onOpenApp');

    /**
     * all client's routes
     */
    Route::prefix('client')->group(function () {

        /**
         * save client
         */
        Route::post('create', 'ClienteController@store');

        /**
         * save token for notifications push
         */
        Route::post('setPushToken', 'ClienteController@setPushToken');

        /**
         * update client
         */
        Route::put('update', 'ClienteController@update');

        /**
         * returns the client
         */
        Route::get('get', 'ClienteController@get');

        /**
         * takes all the necessary information from the client for the system to consume the api
         */
        Route::get('getToLink', 'ClienteController@getToLink');

        /**
         * update client's phone
         */
        Route::post('updatePhone', 'ClienteController@updatePhone');

        /**
         * delete client's
         */
        Route::delete('delete', 'ClienteController@destroy');

        /**
         * get all necessary data to create order
         */
        Route::get('migrate', 'ClienteController@migrate');
    });

    /**
     * all addresses routes
     */
    Route::prefix('address')->group(function () {

        /**
         * save address
         */
        Route::post('create', 'EnderecoController@store');

        /**
         * update address
         */
        Route::put('update', 'EnderecoController@update');

        /**
         * make address as favorite
         */
        Route::put('makeFavorite', 'EnderecoController@makeFavorite');

        /**
         * return address
         */
        Route::get('get', 'EnderecoController@get');

        /**
         * return all addresses of client
         */
        Route::get('getAll', 'EnderecoController@getAll');

        /**
         * return all addresses of client
         */
        Route::get('migrate', 'EnderecoController@migrate');

        /**
         * return the selected address
         */
        Route::get('getStandard', 'EnderecoController@getStandard');

        /**
         * delete address
         */
        Route::delete('delete', 'EnderecoController@destroy');
    });

    /**
     * all reseller's routes
     */
    Route::prefix('reseller')->group(function () {

        /**
         * returns a reseller
         */
        Route::get('get', 'EmpresaController@get');

        /**
         * returns wheter gas do povo is allowed
         */
        Route::get("isGpAllowed", "EmpresaController@siGpAllowed");
    });
    /**
     * all order's routes
     */
    Route::prefix('order')->group(function () {

        /**
         * save order
         */
        Route::post('create', 'PedidoController@store');

        /**
         * update order
         */
        Route::put('update', 'PedidoController@update');

        /**
         * returns a order
         */
        Route::get('get', 'PedidoController@get');

        /**
         * returns historic of client's orders
         */
        Route::get('history', 'PedidoController@history');

        /**
         * return a pendent orders of client
         */
        Route::get('track', 'PedidoController@track');

        /**
         * update status of orders
         */
        Route::post('tracking', 'PedidoController@tracking');

        /**
         * evaluate order
         */
        Route::post('evaluate', 'PedidoController@evaluate');

        /**
         * cancel order
         */
        Route::post('cancel', 'PedidoController@cancel');

        /**
         * get all necessary data to create order
         */
        Route::get('root', 'PedidoController@root');

        /**
         * request to complete pix transaction
         */
        Route::post('pixpaid', 'PedidoController@setPaidOrder');

        /**
         * check if pix is paid
         */
        Route::get('ispaid/{pedido_id}', 'PedidoController@isPixPaid');

        // ? Used for the new app
        /**
         * get the latest status and position of the order
         */
        Route::get('getLastestStatus', 'PedidoController@getLastestStatus');

        /**
         * get the items of the order
         */
        Route::get('getItems', 'PedidoController@getItems');

        /**
         * set the expired PIX orders
         */
        Route::post("expired", "PedidoController@setExpiredOrders");
    });

    /**
     * all product's routes
     */
    Route::prefix('product')->group(function () {

        /**
         * get all products to order
         */
        Route::get('get', 'ProdutoController@getToOrder');

        /**
         * takes all the necessary information from the product for the system to consume the api
         */
        Route::get('getToLink', 'ProdutoController@getToLink');

        /**
         * link product api with erp payments
         */
        Route::post('link', 'ProdutoController@linkFrom');
    });

    /**
     * all payment's routes
     */
    Route::prefix('payment')->group(function () {

        /**
         * get all payment methods to order
         */
        Route::get('get', 'CondicaoPagamentoController@getToOrder');

        /**
         * takes all the necessary information from the payment conditions for the system to consume the api
         */
        Route::get('getToLink', 'CondicaoPagamentoController@getToLink');

        /**
         * link payments api with erp payments
         */
        Route::post('link', 'CondicaoPagamentoController@linkFrom');

        /**
         * get coupons info
         */
        Route::get('coupon', 'CondicaoPagamentoController@coupon');
    });

    /**
     * all payment's routes
     */
    Route::prefix('coupons')->group(function () {

        /**
         * check coupons information
         */
        Route::post('verify', 'CouponsController@verify');

        /**
         * gets the current valid coupon
         */
        Route::get('get', 'CouponsController@get');
    });

    /**
     * all status routes
     */
    Route::prefix('orderStatus')->group(function () {

        /**
         * takes all the necessary information from the status conditions for the system to consume the api
         */
        Route::get('getToLink', 'PedidoSituacaoController@getToLink');

        /**
         * link status api with erp payments
         */
        Route::post('link', 'PedidoSituacaoController@linkFrom');
    });

    /**
     * all price's routes
     */
    Route::prefix('price')->group(function () {

        /**
         * get all price's to order
         */
        Route::get('get', 'CondicaoPagamentoController@getPrices');

        /**
         * get all price's to order
         */
        Route::get('getToLink', 'CondicaoPagamentoController@getPricesToLink');

        /**
         * get all price's to order
         */
        Route::post('link', 'CondicaoPagamentoController@linkPricesFrom');
    });

    /**
     * all holidays routes
     */
    Route::prefix('holiday')->group(function () {
        /**
         * get all holidays of user/reseller
         */
        Route::get('getToLink', 'FeriadoController@getToLink');

        /**
         * save a holiday of user/reseller
         */
        Route::post('link', 'FeriadoController@store');
    });

    /**
     * all configs routes
     */
    Route::prefix('config')->group(function () {
        /**
         * get all holidays of user/reseller
         */
        Route::get('getToLink', 'UserController@getToLink');

        /**
         * save a holiday of user/reseller
         */
        Route::post('link', 'UserController@link');

        /**
         * save info about GP user/reseller
         */
        Route::post('updateGasPovo', 'UserController@updateGasPovo');
    });

    /**
     * all configs routes
     */
    Route::prefix('polygons')->group(function () {
        /**
         * get all holidays of user/reseller
         */
        Route::get('getToLink', 'UserController@polylines');

        /**
         * save a holiday of user/reseller
         */
        Route::post('link', 'UserController@storePolylines');
    });

    /**
     * Envia notificacoes aos clientes
     */
    Route::post('sendNotification', 'NotificacaoController@sendNotification');

    /**
     * Envia notificacoes aos clientes para recompra
     */
    Route::post('sendNotificationRecompra', 'NotificacaoController@sendNotificationRecompra');

    /**
     * Envia notificacoes aos clientes que tem cupom
     */
    Route::post('sendNotificationCoupon', 'NotificacaoController@sendNotificationCoupon');

    /**
     * Envia notificacoes aos entregadores
     */
    Route::post('sendNotificationDelivery', 'NotificacaoController@sendNotificationDelivery');

    Route::prefix("v2")->group(function () {

        Route::get("order/root", "PedidoController@newRoot");

        /**
         * returns the client
         */
        Route::get('client/getById', 'ClienteController@getById');
    });

    Route::prefix('video')->group(function () {

        Route::post('sync', 'VideoController@sync');
    });
});

