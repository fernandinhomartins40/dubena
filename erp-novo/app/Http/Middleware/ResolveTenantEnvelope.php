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
 * F1 cutover middleware. Deve rodar após `tenant` durante o dual-read;
 * propositalmente não é adicionado às rotas antes do import documental F1-10.
 */
class ResolveTenantEnvelope
{
    public function __construct(
        private readonly TenantEnvelopeResolver $resolver,
        private readonly TenantEnvelopeRuntime $runtime,
        private readonly TenantContext $legacyContext,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $empresaId = $this->legacyContext->empresaId();
        if ($user === null || $empresaId === null) {
            throw new TenantAccessDeniedException('Envelope SaaS exige usuário e empresa ativa resolvidos.');
        }

        $correlationId = (string) ($request->header('X-Request-Id') ?: Str::uuid());
        $envelope = $this->resolver->resolveFor($user, $empresaId, $correlationId);

        return $this->runtime->run($envelope, fn (): Response => $next($request));
    }
}
