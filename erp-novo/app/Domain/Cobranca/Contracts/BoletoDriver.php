<?php

namespace App\Domain\Cobranca\Contracts;

use App\Models\Cobranca\Boleto;

/**
 * Driver de banco para boleto (GATE — N7). Isola a integração bancária real
 * (nosso número, linha digitável, CNAB) atrás de uma interface, para que o
 * BoletoService seja testável sem credencial. Em produção/homologação, um driver
 * por banco (Caixa 104, Itaú 341) porta a lib eduardokum/laravel-boleto.
 */
interface BoletoDriver
{
    /** Código FEBRABAN do banco (ex.: 104, 341). */
    public function bancoCodigo(): int;

    /**
     * Registra/calcula os dados do boleto (nosso número, linha digitável,
     * código de barras) a partir do título. Não transmite — só prepara.
     *
     * @return array{nosso_numero:string, linha_digitavel:string, codigo_barras:string, carteira:string}
     */
    public function gerar(Boleto $boleto): array;

    /**
     * Monta a linha de remessa CNAB do boleto (uma linha por boleto).
     */
    public function linhaRemessa(Boleto $boleto): string;

    /**
     * Interpreta uma linha de retorno CNAB → ocorrência normalizada.
     *
     * @return array{codigo:string, descricao:string, valor:?float, situacao:string}
     */
    public function interpretarRetorno(string $linha): array;
}
