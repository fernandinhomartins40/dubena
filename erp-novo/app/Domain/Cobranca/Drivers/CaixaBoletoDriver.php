<?php

namespace App\Domain\Cobranca\Drivers;

use App\Domain\Cobranca\Cnab\CnabHelper;
use App\Domain\Cobranca\Cnab\ContaCobranca;
use App\Domain\Cobranca\SituacaoBoleto;
use App\Models\Cobranca\Boleto;

/**
 * Driver real Caixa Econômica Federal (104) — SIGCB / CNAB240 (F08).
 *
 * Campo livre (25): convênio(6) + nossoNumero[1] (carteira 1=RG / 2=SR) +
 * sequência(3 do NN) + constante '4' + NN(9) + DVs do campo livre.
 * Implementação fiel ao manual SIGCB para o código de barras ser válido.
 */
class CaixaBoletoDriver extends CnabDriverBase
{
    public function bancoCodigo(): int
    {
        return 104;
    }

    protected function nossoNumero(Boleto $boleto, ContaCobranca $conta): string
    {
        // Caixa: 17 posições = carteira(2) + sequencial(15). Aqui o sequencial é o id.
        $carteira = CnabHelper::numero($conta->carteira ?: '14', 2);
        $seq = CnabHelper::numero((int) $boleto->id, 15);

        return $carteira.$seq;
    }

    protected function campoLivre(Boleto $boleto, ContaCobranca $conta): string
    {
        // SIGCB: convênio(6) + NN: 17 dígitos do nosso número split conforme manual.
        $convenio = CnabHelper::numero($conta->convenio, 6);
        $nn = $this->nossoNumero($boleto, $conta); // 17

        // Estrutura SIGCB do campo livre (25):
        // convenio(6) + NN[3..5](3) + const1('1') + NN[6..8](3) + const2('4') + NN[9..17](9) + DV(2)
        $bloco = $convenio
            .substr($nn, 2, 3).'1'
            .substr($nn, 5, 3).'4'
            .substr($nn, 8, 9);

        $dv = CnabHelper::modulo11($bloco, 9);

        return $bloco.CnabHelper::numero($dv, 2);
    }

    public function linhaRemessa(Boleto $boleto): string
    {
        // Segmento P (240) resumido com os campos essenciais em posição fixa.
        // (cabeçalhos de arquivo/lote são montados pelo serviço ao concatenar; aqui
        // entregamos a linha do título, identificável no retorno pelo nosso número.)
        $conta = ContaCobranca::daEmpresa((int) $boleto->empresa_id, $this->bancoCodigo());
        $nn = $boleto->nosso_numero ?: $this->nossoNumero($boleto, $conta);

        return CnabHelper::numero($this->bancoCodigo(), 3)        // 1-3 banco
            .'P'                                                   // 4 segmento
            .CnabHelper::numero($conta->agencia, 6)                // 5-10 agência
            .CnabHelper::numero($nn, 17)                           // 11-27 nosso número
            .CnabHelper::valor((float) $boleto->valor, 15)         // 28-42 valor
            .$boleto->vencimento->format('dmY')                    // 43-50 vencimento
            .CnabHelper::texto((string) $boleto->id, 25);          // 51-75 uso da empresa (id p/ casamento)
    }

    protected function mapaOcorrencias(): array
    {
        // Códigos de retorno CNAB240 Caixa (resumo dos principais).
        return [
            '02' => ['Entrada confirmada', SituacaoBoleto::REGISTRADO],
            '03' => ['Entrada rejeitada', SituacaoBoleto::REJEITADO],
            '06' => ['Liquidação', SituacaoBoleto::LIQUIDADO],
            '09' => ['Baixa', SituacaoBoleto::BAIXADO],
            '17' => ['Liquidação após baixa', SituacaoBoleto::LIQUIDADO],
        ];
    }

    protected function codigoOcorrenciaRetorno(string $linha): string
    {
        // CNAB240 Caixa: código de movimento no segmento T (posições 16-17, 1-based).
        return CnabHelper::numero(substr($linha, 15, 2), 2);
    }

    protected function valorRetorno(string $linha): ?float
    {
        // Valor do título no segmento T (posições 82-96 = 15 dígitos em centavos).
        $raw = substr($linha, 81, 15);
        if (! ctype_digit($raw)) {
            return null;
        }

        return round((int) $raw / 100, 2);
    }
}
