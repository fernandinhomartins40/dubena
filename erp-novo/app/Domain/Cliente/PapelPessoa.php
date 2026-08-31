<?php

namespace App\Domain\Cliente;

/**
 * F3-01 — o que uma pessoa É para a revenda, num período.
 *
 * O cadastro tinha três booleanos paralelos (`cliente`, `fornecedor`,
 * `transportador`), e três problemas com eles:
 *
 *  - respondem "é?" e não "era, quando?" — desmarcar apaga o passado;
 *  - permitem combinações sem restrição, o que aqui até é correto (a mesma
 *    empresa pode ser cliente E fornecedor), mas sem vigência não dá para dizer
 *    que ela foi as duas coisas em épocas diferentes;
 *  - exigem uma coluna nova a cada papel que a revenda passe a distinguir.
 *
 * Papel novo agora é uma linha nesta enum e nada mais — nem migration, nem
 * coluna.
 */
enum PapelPessoa: string
{
    /** Compra da revenda. */
    case CLIENTE = 'CLIENTE';

    /** Vende para a revenda (nota de entrada). */
    case FORNECEDOR = 'FORNECEDOR';

    /** Transporta mercadoria — entra no XML da NF-e como transportador. */
    case TRANSPORTADOR = 'TRANSPORTADOR';

    public function rotulo(): string
    {
        return match ($this) {
            self::CLIENTE => 'Cliente',
            self::FORNECEDOR => 'Fornecedor',
            self::TRANSPORTADOR => 'Transportador',
        };
    }

    /** Coluna booleana legada equivalente — usada enquanto as duas convivem. */
    public function colunaLegada(): string
    {
        return match ($this) {
            self::CLIENTE => 'cliente',
            self::FORNECEDOR => 'fornecedor',
            self::TRANSPORTADOR => 'transportador',
        };
    }
}
