<?php

namespace App\Domain\Shared;

use Carbon\CarbonImmutable;

/**
 * Cálculo de parcelas de uma condição de pagamento.
 *
 * Reescreve o helper global `calculoParcelas()` do legado (usado por TODOS os
 * geradores de financeiro: Pedido, NF emitida/recebida, CF-e, Convênio, Vale-gás).
 * Regras preservadas:
 *  - se a condição tem parcelas configuradas (percentual + dias), usa-as;
 *  - senão, divide pelo número de parcelas (dias_primeira como intervalo);
 *  - a ÚLTIMA parcela absorve a sobra de centavos (evita diferença por arredondamento).
 *
 * Trabalha com float (número cru) e CarbonImmutable — sem string-BR.
 */
final class CalculoParcelasService
{
    /**
     * @param  array<int,array{percentual: float, dias: int}>  $parcelasConfig
     *         Configuração de parcelas (percentualvalor + dias). Vazio = usa numParcelas.
     * @return list<Parcela>
     */
    public function calcular(
        float $total,
        CarbonImmutable $dataBase,
        array $parcelasConfig = [],
        int $numParcelas = 1,
        int $diasPrimeira = 0,
        int $intervalo = 30,
        float $descontoTotal = 0.0,
    ): array {
        $total = round($total, 2);

        if (! empty($parcelasConfig)) {
            return $this->porConfiguracao($total, $dataBase, $parcelasConfig, $descontoTotal);
        }

        return $this->porNumeroDeParcelas($total, $dataBase, max(1, $numParcelas), $diasPrimeira, $intervalo, $descontoTotal);
    }

    /** Parcelas por percentual configurado (condicaopagamentoparcelas). */
    private function porConfiguracao(float $total, CarbonImmutable $dataBase, array $config, float $descontoTotal): array
    {
        $parcelas = [];
        $restante = $total;
        $restanteDesc = round($descontoTotal, 2);
        $diasAcum = 0;
        $n = count($config);

        foreach (array_values($config) as $i => $linha) {
            $diasAcum += (int) ($linha['dias'] ?? 0);
            $ultima = ($i === $n - 1);

            if ($ultima) {
                $valor = $restante;
                $desconto = $restanteDesc;
            } else {
                $perc = (float) ($linha['percentual'] ?? 0) / 100;
                $valor = round($total * $perc, 2);
                $desconto = round($descontoTotal * $perc, 2);
                $restante = round($restante - $valor, 2);
                $restanteDesc = round($restanteDesc - $desconto, 2);
            }

            $parcelas[] = new Parcela($i + 1, $dataBase->addDays($diasAcum), $valor, $desconto);
        }

        return $parcelas;
    }

    /** Parcelas iguais por número (sobra de centavos na última). */
    private function porNumeroDeParcelas(
        float $total,
        CarbonImmutable $dataBase,
        int $numParcelas,
        int $diasPrimeira,
        int $intervalo,
        float $descontoTotal,
    ): array {
        $parcelas = [];
        $valorBase = round($total / $numParcelas, 2);
        $descBase = round($descontoTotal / $numParcelas, 2);
        $restante = $total;
        $restanteDesc = round($descontoTotal, 2);
        $dias = $diasPrimeira;

        for ($i = 1; $i <= $numParcelas; $i++) {
            $ultima = ($i === $numParcelas);
            $valor = $ultima ? $restante : $valorBase;
            $desconto = $ultima ? $restanteDesc : $descBase;

            $parcelas[] = new Parcela($i, $dataBase->addDays($dias), $valor, $desconto);

            $restante = round($restante - $valor, 2);
            $restanteDesc = round($restanteDesc - $desconto, 2);
            $dias += $intervalo > 0 ? $intervalo : $diasPrimeira;
        }

        return $parcelas;
    }
}
