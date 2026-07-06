<?php

namespace App\Domain\Cobranca\Drivers;

use App\Domain\Cobranca\Contracts\PixDriver;

/**
 * Driver PIX FAKE (dev/homolog/CI). Gera um BR Code sintético determinístico —
 * sem rede, sem credencial. O copia-e-cola de PRODUÇÃO vem do PSP (driver real);
 * este payload existe só para teste/exibição.
 */
class FakePixDriver implements PixDriver
{
    public function nome(): string
    {
        return 'fake';
    }

    public function criarCobranca(array $dados, array $credencial): array
    {
        $txid = (string) $dados['txid'];
        $valor = (float) $dados['valor'];

        // Representação simplificada do payload EMV (BR Code) — antes vivia no
        // PixService::montarBrCode.
        $copiaECola = sprintf('00020126%s52040000530398654%02d%.2f5802BR6009GASEMCASA62%02d%s6304',
            strlen($txid) + 22, strlen(number_format($valor, 2, '.', '')) + 0, $valor, strlen($txid) + 4, $txid);

        return ['copia_e_cola' => $copiaECola, 'qrcode' => null];
    }
}
