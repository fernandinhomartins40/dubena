<?php

use App\Domain\Integracao\CredencialNaoConfiguradaException;
use App\Domain\Saas\TransformationFrozenException;
use App\Domain\Tenant\TenantAccessDeniedException;
use App\Domain\Tenant\TenantNotResolvedException;
use App\Http\Middleware\AppRole;
use App\Http\Middleware\CapturarSchemaDaRota;
use App\Http\Middleware\DialetoLegado;
use App\Http\Middleware\Idempotente;
use App\Http\Middleware\Permissao;
use App\Http\Middleware\Recurso;
use App\Http\Middleware\RecursoPorRota;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\ResolveTenantEnvelope;
use App\Http\Middleware\ValidaRevendaLegado;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
            'tenant.saas' => ResolveTenantEnvelope::class,
            'permissao' => Permissao::class,
            'recurso' => Recurso::class,
            // F2-03: mesmo enforcement, resolvido pelo prefixo da rota.
            'licenca.rota' => RecursoPorRota::class,
            'approle' => AppRole::class,
            // F0 — ponte para os apps legados: traduz o envelope e confere o
            // revenda_id contra o token. Saem quando os legados forem desligados.
            'dialeto.legado' => DialetoLegado::class,
            'revenda.legado' => ValidaRevendaLegado::class,
            // F7 — reenvio da fila offline nao repete o efeito.
            'idempotente' => Idempotente::class,
        ]);

        // F2-01: captura a forma de request/response por rota. Inerte fora dos
        // testes — sai na primeira linha se a coleta não estiver ligada.
        $middleware->appendToGroup('api', CapturarSchemaDaRota::class);

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

        $exceptions->render(function (TenantAccessDeniedException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 403);
            }
        });

        // F0: the operation exists but is intentionally locked until the
        // corresponding SaaS transformation gate has been approved.
        $exceptions->render(function (TransformationFrozenException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'operation' => $e->operation,
                ], 423);
            }
        });

        // Regra de negócio violada → 422, não 500. Um DomainException é o
        // domínio dizendo "isto não pode", como imprimir DANFE de nota não
        // autorizada: a mensagem é para o operador ler, e devolvê-la como erro
        // do servidor esconderia justamente o motivo.
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });

        // FASE 2 — dinheiro fail-closed: empresa sem credencial própria em produção
        // → 503 com mensagem neutra (detalhe fica no log; nada interno vaza ao app).
        $exceptions->render(function (CredencialNaoConfiguradaException $e, Request $request) {
            Log::warning('integracao: credencial ausente (fail-closed)', [
                'servico' => $e->servico, 'empresa_id' => $e->empresaId,
            ]);
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->mensagemUsuario], 503);
            }
        });
    })->create();
