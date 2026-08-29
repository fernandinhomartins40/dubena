<?php

namespace App\Http\Middleware;

use App\Domain\Tenant\TenantAccessDeniedException;
use App\Domain\Tenant\TenantContext;
use App\Domain\Tenant\TenantEnvelopeResolver;
use App\Domain\Tenant\TenantEnvelopeRuntime;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * F1 cutover middleware. Roda após `tenant` durante o dual-read.
 *
 * Já está anexado a todas as rotas `auth:sanctum` (routes/api.php). O que
 * decide se ele atua é `SAAS_ENFORCE_TENANT_ENVELOPE`: desligado, ele é
 * passagem livre e vale o modelo legado; ligado, nenhuma requisição autenticada
 * segue sem fronteira aprovada — e a troca é uma variável de ambiente, não um
 * deploy, para o rollback ser imediato.
 */
class ResolveTenantEnvelope
{
    public function __construct(
        private readonly TenantEnvelopeResolver $resolver,
        private readonly TenantEnvelopeRuntime $runtime,
        private readonly TenantContext $legacyContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('saas_transformation.enforcement.tenant_envelope')) {
            return $next($request);
        }

        $user = $request->user();
        $empresaId = $this->legacyContext->empresaId();
        if ($user === null || $empresaId === null) {
            throw new TenantAccessDeniedException('Envelope SaaS exige usuário e empresa ativa resolvidos.');
        }

        $correlationId = (string) ($request->header('X-Request-Id') ?: Str::uuid());

        return $this->runtime->withAuthenticatedUser(
            (int) $user->id,
            function () use ($user, $empresaId, $correlationId, $next, $request): Response {
                $envelope = $this->resolver->resolveFor($user, $empresaId, $correlationId);

                return $this->runtime->run($envelope, fn (): Response => $next($request));
            },
        );
    }
}
