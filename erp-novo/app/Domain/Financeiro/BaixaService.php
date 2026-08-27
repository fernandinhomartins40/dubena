<?php

namespace App\Domain\Financeiro;

use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/** Porta unica para a transicao de uma parcela aberta para baixada. */
final class BaixaService
{
    public function baixar(
        int $parcelaId,
        int $empresaId,
        float $valorEfetivado,
        string $origem,
        bool $reentregaIdempotente = false,
    ): bool {
        if ($empresaId <= 0 || $parcelaId <= 0 || ! is_finite($valorEfetivado) || $valorEfetivado < 0 || trim($origem) === '') {
            throw ValidationException::withMessages([
                'baixa' => 'Parcela, empresa, valor e origem validos sao obrigatorios.',
            ]);
        }

        $parcela = FinanceiroParcela::withoutTenant()
            ->whereKey($parcelaId)
            ->where('empresa_id', $empresaId)
            ->lockForUpdate()
            ->first();
        if (! $parcela) {
            throw ValidationException::withMessages([
                'parcela' => 'Parcela nao pertence a empresa da operacao.',
            ]);
        }

        if ($parcela->baixado) {
            if (! $reentregaIdempotente) {
                throw ValidationException::withMessages(['parcela' => 'Parcela ja baixada.']);
            }

            Log::warning('Baixa externa recebida para parcela ja baixada.', [
                'empresa_id' => $empresaId,
                'financeiroparcela_id' => $parcelaId,
                'origem' => $origem,
            ]);

            return false;
        }

        $parcela->update([
            'baixado' => true,
            'valor_efetivado' => round($valorEfetivado, 2),
            'datahora_baixa' => now(),
        ]);

        return true;
    }
}
