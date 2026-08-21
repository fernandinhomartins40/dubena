<?php

namespace App\Domain\Rh;

/**
 * Como o franqueado carrega mercadoria — F5.
 *
 * O cliente usa os DOIS modelos na rede, fixos por pessoa. A diferença não é
 * administrativa: decide de quem é o botijão que está na rua, e portanto quem
 * responde por ele.
 */
enum ModoEstoque: string
{
    /**
     * Mercadoria da EMPRESA em poder do franqueado. Só vira venda quando ele
     * entrega ao cliente; o que sobra na volta retorna ao depósito.
     */
    case CONSIGNACAO = 'consignacao';

    /**
     * O franqueado comprou. A saída do depósito já é a venda da empresa para
     * ele — o que acontece depois é estoque dele, não da rede.
     */
    case COMPRA = 'compra';

    /**
     * A carga gera título a receber contra o franqueado no ato?
     * Na compra sim (ele deve pela mercadoria assim que leva). Na consignação
     * não — ele só deve pelo que efetivamente vendeu.
     */
    public function faturaNaCarga(): bool
    {
        return $this === self::COMPRA;
    }

    /**
     * A devolução do que sobrou faz sentido?
     * Só na consignação: o que é do franqueado não "volta", ele fica com o
     * estoque dele.
     */
    public function aceitaDevolucao(): bool
    {
        return $this === self::CONSIGNACAO;
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::CONSIGNACAO => 'Consignação',
            self::COMPRA => 'Compra',
        };
    }
}
