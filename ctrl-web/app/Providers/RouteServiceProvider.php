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

        // UNIFICAÇÃO: módulo Monitoramento sob prefixo /monitora (namespace e
        // nomes de rota próprios — 'monitora.*'). Antes do web do ERP.
        $this->mapMonitoraRoutes();

        // S1 (SPA React): API ADMIN do ERP consumida pelo SPA (/api/admin), com
        // Sanctum stateful (cookie). SEPARADA da API do app mobile (mapAppMobile),
        // cujo contrato publicado não pode mudar.
        $this->mapAdminApiRoutes();

        $this->mapApiRoutes();

        $this->mapWebRoutes();

        //
    }

    /**
     * S1: rotas da API ADMIN do ERP (consumidas pelo SPA React em /app).
     * Prefixo /api/admin, grupo de middleware 'api_admin' (Sanctum stateful).
     */
    protected function mapAdminApiRoutes()
    {
        Route::group([
            'middleware' => 'api_admin',
            'namespace'  => 'App\ApiAdmin\Http\Controllers',
            'prefix'     => 'api/admin',
            'as'         => 'admin.api.',
        ], function ($router) {
            require base_path('routes/api_admin.php');
        });
    }

    /**
     * UNIFICAÇÃO: rotas do módulo Monitoramento (ex-app monitoramento-veiculos),
     * servidas pelo próprio ERP sob /monitora. Controllers em
     * App\Monitora\Http\Controllers; nomes de rota prefixados com 'monitora.'.
     */
    protected function mapMonitoraRoutes()
    {
        Route::group([
            'middleware' => 'web',
            'namespace'  => 'App\Monitora\Http\Controllers',
            'prefix'     => 'monitora',
        ], function ($router) {
            require base_path('routes/monitora.php');
        });

        Route::group([
            'middleware' => 'api',
            'namespace'  => 'App\Monitora\Http\Controllers',
            'prefix'     => 'monitora/api',
        ], function ($router) {
            require base_path('routes/monitora_api.php');
        });
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
