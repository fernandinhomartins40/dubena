<?php

namespace App\Domain\Tenant;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Captura a fronteira aprovada no dispatch, antes de o job deixar o request. */
final class TenantEnvelopeDispatch
{
    public function __construct(
        private readonly TenantEnvelopeRuntime $runtime,
        private readonly TenantEnvelopeResolver $resolver,
        private readonly TenantContext $legacyContext,
    ) {
    }

    public function capture(): TenantEnvelope
    {
        if ($this->runtime->current() !== null) {
            return $this->runtime->current();
        }

        $user = Auth::user();
        $empresaId = $this->legacyContext->empresaId();
        if (! $user instanceof User || $empresaId === null) {
            throw new TenantAccessDeniedException('Dispatch de job de negocio exige usuario e empresa ativa aprovados.');
        }

        return $this->resolver->resolveFor($user, $empresaId, (string) Str::uuid());
    }
}
