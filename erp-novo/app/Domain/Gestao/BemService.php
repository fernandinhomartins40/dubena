<?php

namespace App\Domain\Gestao;

use App\Models\Gestao\EmpresaBem;
use Illuminate\Support\Carbon;

/**
 * BemService (C11) — depreciação linear do ativo imobilizado.
 *
 * Depreciação acumulada = (valor_aquisicao - residual) × taxa%/100 × anos,
 * limitada ao depreciável (não passa do valor residual). Valor contábil =
 * aquisicao - acumulada. Anos = fração desde a aquisição até a data de referência.
 */
class BemService
{
    /**
     * @return array{
     *   depreciacao_anual: float, depreciacao_acumulada: float,
     *   valor_contabil: float, anos: float
     * }
     */
    public function depreciacao(EmpresaBem $bem, ?string $referencia = null): array
    {
        $ref = $referencia ? Carbon::parse($referencia) : now();
        $aquisicao = $bem->data_aquisicao ? Carbon::parse($bem->data_aquisicao) : $ref;
        $anos = max(0.0, $aquisicao->floatDiffInYears($ref));

        $valor = (float) $bem->valor_aquisicao;
        $residual = (float) $bem->valor_residual;
        $taxa = (float) $bem->taxa_depreciacao_anual;
        $depreciavel = max(0.0, $valor - $residual);

        $anual = round($depreciavel * $taxa / 100, 2);
        $acumulada = min($depreciavel, round($anual * $anos, 2));
        $contabil = round($valor - $acumulada, 2);

        return [
            'depreciacao_anual' => $anual,
            'depreciacao_acumulada' => round($acumulada, 2),
            'valor_contabil' => $contabil,
            'anos' => round($anos, 2),
        ];
    }
}
