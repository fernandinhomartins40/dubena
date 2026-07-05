<?php

use App\Domain\Integracao\CredencialNaoConfiguradaException;
use App\Domain\Tenant\TenantNotResolvedException;
use App\Http\Middleware\AppRole;
use App\Http\Middleware\Permissao;
use App\Http\Middleware\Recurso;
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
    // P5 — Broadcasting: rota /broadcasting/auth autenticada pelo guard 'sanctum'
    // (apps Bearer + SPA cookie). Carrega routes/channels.php (autorização de canais).
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum stateful: requisições da SPA (mesmo domínio, cookie) são tratadas
        // como sessão; apps/integrações continuam usando token Bearer. Os dois
        // modos coexistem no mesmo guard 'sanctum'.
        $middleware->statefulApi();

        // Alias para uso em rotas (auth:sanctum + tenant).
        // `permissao:modulo.acao` é o enforcement de borda de ACESSO (Fase A1),
        // delegando ao mesmo Gate central que os controllers usam.
        // `recurso:chave` é o enforcement de LICENÇA/produto (P2) — 402 se a empresa
        // não tem o feature-flag no plano. Acesso (RBAC) e licença (SaaS) são camadas
        // independentes: o usuário precisa de ambos.
        // `approle:cliente|entregador` separa os PAPÉIS dos tokens do app (F3 do
        // plano de segurança multi-tenant): token de cliente não alcança rota de
        // entregador e vice-versa. Staff stateful (SPA) não é afetado.
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'permissao' => Permissao::class,
            'recurso' => Recurso::class,
            'approle' => AppRole::class,
        ]);

        // API pura: convidado NUNCA é redirecionado para uma rota 'login' (que não
        // existe aqui). Sem isto, request sem Accept: application/json num endpoint
        // autenticado estoura Route [login] not defined (500) em vez de 401.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API é JSON SEMPRE: sem isto, um cliente que não manda Accept:
        // application/json num endpoint autenticado faz o handler tentar
        // redirect()->route('login') — rota que não existe numa API pura —
        // e o 401 vira um 500 confuso (Route [login] not defined).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson(),
        );

        // Tenant não resolvido → 409 (conflito de contexto), JSON uniforme.
        $exceptions->render(function (TenantNotResolvedException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 409);
            }
        });

        // FASE 2 — dinheiro fail-closed: empresa sem credencial própria em produção
        // → 503 com mensagem neutra (detalhe fica no log; nada interno vaza ao app).
        $exceptions->render(function (CredencialNaoConfiguradaException $e, Request $request) {
            \Illuminate\Support\Facades\Log::warning('integracao: credencial ausente (fail-closed)', [
                'servico' => $e->servico, 'empresa_id' => $e->empresaId,
            ]);
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->mensagemUsuario], 503);
            }
        });
    })->create();
