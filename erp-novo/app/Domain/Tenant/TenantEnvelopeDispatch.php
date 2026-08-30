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
    ) {}

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

    /**
     * Igual ao `capture()`, mas devolve `null` em vez de recusar quando nao ha
     * ator autenticado.
     *
     * Serve ao despacho que nasce fora de um request — service chamado por
     * console, seed ou teste de dominio. Nao afrouxa a fronteira: o job que usa
     * isto continua obrigado a chamar `requireOperation()` no `handle()`, e sem
     * envelope ele cai no caminho legado, que o enforcement ja restringe pela
     * RLS. Recusar aqui apenas trocaria uma falha de autorizacao por um erro em
     * codigo que nunca teve ator.
     */
    public function captureOrNull(): ?TenantEnvelope
    {
        try {
            return $this->capture();
        } catch (TenantAccessDeniedException) {
            return null;
        }
    }
}
