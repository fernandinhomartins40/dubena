<?php

namespace App\Domain\Mobile\Contracts;

/**
 * Driver de adquirente para pagamento online (GATE — N10, Rede/eRede). Isola a
 * comunicação com o gateway para o PagamentoOnlineService ser testável sem PV/token.
 */
interface PagamentoDriver
{
    public function gateway(): string;

    /**
     * Autoriza um pagamento com cartão (token).
     *
     * ## `indeterminado` (F6-08)
     *
     * `aprovado => false` responde duas perguntas diferentes, e o chamador
     * precisa distinguir: **a operadora recusou** (cartão sem limite, dados
     * inválidos) ou **a operadora não respondeu** (rede caiu, timeout).
     *
     * A diferença é dinheiro do cliente. Um timeout costuma acontecer *depois*
     * que a operadora autorizou — é a resposta que se perde. Tratar isso como
     * recusa faz a venda ser refeita, e o cliente é cobrado duas vezes.
     *
     * Quando `indeterminado` é `true`, `aprovado` é sempre `false`: o sistema
     * não pode entregar mercadoria sobre uma dúvida. Mas o registro fica
     * marcado para consulta à operadora, em vez de encerrado como recusa.
     *
     * @param  array{valor:float, parcelas:int, token:string}  $dados
     * @return array{aprovado:bool, indeterminado?:bool, tid:?string, nsu:?string, autorizacao:?string, bandeira:?string, mensagem:string}
     */
    public function autorizar(array $dados): array;

    /**
     * Estorna/cancela uma transação pelo tid.
     *
     * @return array{cancelado:bool, mensagem:string}
     */
    public function estornar(string $tid): array;
}
