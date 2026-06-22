<?php

namespace App\Domain\Fiscal\Contracts;

use App\Models\Fiscal\NotaFiscal;

/**
 * Driver SEFAZ (GATE — N9). Isola a transmissão/autorização/cancelamento (NFePHP,
 * certificado A1, webservices) atrás de uma interface, para o FiscalService ser
 * testável sem certificado/SEFAZ. Em produção, o driver real porta NFePHP.
 */
interface SefazDriver
{
    /**
     * Transmite a nota e retorna o resultado da autorização.
     *
     * @return array{autorizada:bool, chave:?string, protocolo:?string, motivo:?string}
     */
    public function transmitir(NotaFiscal $nota): array;

    /**
     * Cancela uma nota autorizada.
     *
     * @return array{cancelada:bool, protocolo:?string, motivo:?string}
     */
    public function cancelar(NotaFiscal $nota, string $justificativa): array;
}
