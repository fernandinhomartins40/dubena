<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        //

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        // FASE 5 (unificação): rotas do app mobile (ex-api-app-gc) carregadas
        // ANTES das rotas próprias do ERP, para que os contratos publicados
        // (/api/getToken, /api/v2/order/root, etc.) tenham prioridade e sejam
        // preservados (retrocompatibilidade com apps já nas lojas).
        $this->mapAppMobileApiRoutes();

        $this->mapApiRoutes();

        $this->mapWebRoutes();

        //
    }

    /**
     * FASE 5: rotas da API do app mobile, servidas pelo próprio ERP.
     * Namespace aponta para o módulo App\Api\Http\Controllers (código portado
     * de api-app-gc). Paths preservados — nada de quebrar apps publicados.
     */
    protected function mapAppMobileApiRoutes()
    {
        Route::group([
            'middleware' => 'api',
            'namespace'  => 'App\Api\Http\Controllers',
            'prefix'     => 'api',
        ], function ($router) {
            require base_path('routes/api_mobile.php');
        });
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::group([
            'middleware' => 'web',
            'namespace' => $this->namespace,
        ], function ($router) {
            require base_path('routes/web.php');
        });
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::group([
            'middleware' => 'api',
            'namespace' => $this->namespace,
            'prefix' => 'api',
        ], function ($router) {
            require base_path('routes/api.php');
        });
    }
}
