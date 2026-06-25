<?php

namespace App\Domain\Cobranca\Drivers;

use App\Domain\Cobranca\Cnab\CnabHelper;
use App\Domain\Cobranca\Cnab\ContaCobranca;
use App\Domain\Cobranca\SituacaoBoleto;
use App\Models\Cobranca\Boleto;

/**
 * Driver real Itaú (341) — CNAB400 (F08).
 *
 * Campo livre (25): carteira(3) + nossoNumero(8) + DAC[carteira+NN](1) +
 * agência(4) + conta(5) + DAC[ag+conta](1) + '000'(3). Fiel ao manual Itaú para
 * o código de barras ser válido.
 */
class ItauBoletoDriver extends CnabDriverBase
{
    public function bancoCodigo(): int
    {
        return 341;
    }

    protected function nossoNumero(Boleto $boleto, ContaCobranca $conta): string
    {
        // Itaú: nosso número = 8 dígitos (sequencial). DAC calculado à parte.
        return CnabHelper::numero((int) $boleto->id, 8);
    }

    /** DAC do nosso número (módulo 10 sobre agência+conta+carteira+NN). */
    private function dacNossoNumero(Boleto $boleto, ContaCobranca $conta): int
    {
        $base = CnabHelper::numero($conta->agencia, 4)
            .CnabHelper::numero($conta->conta, 5)
            .CnabHelper::numero($conta->carteira, 3)
            .$this->nossoNumero($boleto, $conta);

        return CnabHelper::modulo10($base);
    }

    protected function campoLivre(Boleto $boleto, ContaCobranca $conta): string
    {
        $carteira = CnabHelper::numero($conta->carteira, 3);
        $nn = $this->nossoNumero($boleto, $conta);
        $dacNn = CnabHelper::numero($this->dacNossoNumero($boleto, $conta), 1);
        $ag = CnabHelper::numero($conta->agencia, 4);
        $cc = CnabHelper::numero($conta->conta, 5);
        $dacAgCc = CnabHelper::numero(CnabHelper::modulo10($ag.$cc), 1);

        return $carteira.$nn.$dacNn.$ag.$cc.$dacAgCc.'000';
    }

    public function linhaRemessa(Boleto $boleto): string
    {
        $conta = ContaCobranca::daEmpresa((int) $boleto->empresa_id, $this->bancoCodigo());
        $nn = $boleto->nosso_numero ?: $this->nossoNumero($boleto, $conta);

        // Registro detalhe tipo 1 (CNAB400) resumido com campos essenciais por posição.
        return '1'                                                  // 1 tipo de registro
            .CnabHelper::numero($conta->agencia, 4)                 // 2-5 agência
            .CnabHelper::numero($conta->conta, 5)                   // 6-10 conta
            .CnabHelper::numero($conta->carteira, 3)                // 11-13 carteira
            .CnabHelper::numero($nn, 8)                             // 14-21 nosso número
            .CnabHelper::texto((string) $boleto->id, 25)           // 22-46 uso da empresa (id p/ casamento)
            .$boleto->vencimento->format('dmy')                    // 47-52 vencimento
            .CnabHelper::valor((float) $boleto->valor, 13);        // 53-65 valor
    }

    protected function mapaOcorrencias(): array
    {
        // Códigos de retorno CNAB400 Itaú (resumo dos principais).
        return [
            '02' => ['Entrada confirmada', SituacaoBoleto::REGISTRADO],
            '03' => ['Entrada rejeitada', SituacaoBoleto::REJEITADO],
            '06' => ['Liquidação normal', SituacaoBoleto::LIQUIDADO],
            '09' => ['Baixa simples', SituacaoBoleto::BAIXADO],
            '10' => ['Baixa por ter sido liquidado', SituacaoBoleto::LIQUIDADO],
        ];
    }

    protected function codigoOcorrenciaRetorno(string $linha): string
    {
        // CNAB400 Itaú: código de ocorrência nas posições 109-110 (1-based).
        return CnabHelper::numero(substr($linha, 108, 2), 2);
    }

    protected function valorRetorno(string $linha): ?float
    {
        // Valor do título nas posições 153-165 (13 dígitos em centavos).
        $raw = substr($linha, 152, 13);
        if (! ctype_digit($raw)) {
            return null;
        }

        return round((int) $raw / 100, 2);
    }
}
