<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
        ],

        'api' => [
            'throttle:60,1',
        ],

        // S1 (SPA React): grupo da API ADMIN consumida pelo SPA (/api/admin).
        // Sanctum SPA cookie-based → precisa do stateful (injeta sessão+CSRF p/
        // requests do frontend confiável) + sessão + cookies criptografados.
        'api_admin' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'throttle:120,1',
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'can' => \Illuminate\Foundation\Http\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'pode' => \App\Http\Middleware\AuthorizeCustom::class,
        'mailware' => \App\Http\Middleware\CustomMailWare::class,
        // FASE 5 (unificação): middleware de log das rotas da API do app mobile.
        'access' => \App\Api\Http\Middleware\Access::class,
        // UNIFICAÇÃO: auth do módulo Monitoramento (guard 'monitora', redirect
        // p/ /monitora/login; sem a validação de empresa do ERP).
        'auth.monitora' => \App\Monitora\Http\Middleware\Authenticate::class,
        // FASE 3: redireciona a tela legada p/ o recurso Filament quando a flag
        // de UI moderna do módulo está ligada. Ex.: 'ui.moderna:cidade,/admin/cidades'
        'ui.moderna' => \App\Http\Middleware\RedirecionaUiModerna::class,
    ];
}
