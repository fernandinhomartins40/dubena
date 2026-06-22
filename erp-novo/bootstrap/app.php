<?php

use App\Domain\Tenant\TenantNotResolvedException;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum stateful: requisições da SPA (mesmo domínio, cookie) são tratadas
        // como sessão; apps/integrações continuam usando token Bearer. Os dois
        // modos coexistem no mesmo guard 'sanctum'.
        $middleware->statefulApi();

        // Alias para uso em rotas (auth:sanctum + tenant).
        $middleware->alias([
            'tenant' => ResolveTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tenant não resolvido → 409 (conflito de contexto), JSON uniforme.
        $exceptions->render(function (TenantNotResolvedException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 409);
            }
        });
    })->create();
