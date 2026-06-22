<?php

namespace App\Domain\Fiscal;

/**
 * Modelo de documento fiscal (N9).
 */
enum ModeloDocumento: string
{
    case NFE = '55';    // NF-e
    case NFCE = '65';   // NFC-e
    case CFE = '59';    // CF-e (SAT)

    public function rotulo(): string
    {
        return match ($this) {
            self::NFE => 'NF-e',
            self::NFCE => 'NFC-e',
            self::CFE => 'CF-e',
        };
    }

    /** Chave da sequência de numeração por empresa+modelo+série. */
    public function chaveSequencia(int $empresaId, int $serie): string
    {
        return "nf:{$empresaId}:{$this->value}:{$serie}";
    }
}
