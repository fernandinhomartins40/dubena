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
     * @param array{valor:float, parcelas:int, token:string} $dados
     * @return array{aprovado:bool, tid:?string, nsu:?string, autorizacao:?string, bandeira:?string, mensagem:string}
     */
    public function autorizar(array $dados): array;

    /**
     * Estorna/cancela uma transação pelo tid.
     *
     * @return array{cancelado:bool, mensagem:string}
     */
    public function estornar(string $tid): array;
}
