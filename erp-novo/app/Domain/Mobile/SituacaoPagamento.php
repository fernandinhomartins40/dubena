<?php

namespace App\Domain\Mobile;

/**
 * Situação de uma transação de pagamento online (Rede) — N10.
 */
enum SituacaoPagamento: string
{
    case PENDENTE = 'PENDENTE';
    case AUTORIZADO = 'AUTORIZADO';
    case CAPTURADO = 'CAPTURADO';
    case NEGADO = 'NEGADO';
    case CANCELADO = 'CANCELADO';
    case ESTORNADO = 'ESTORNADO';

    /**
     * F6-08 — a operadora não respondeu. **Não é recusa.**
     *
     * Antes, uma queda de rede virava `NEGADO`: o driver capturava a exceção e
     * devolvia `aprovado => false`, indistinguível de "cartão sem limite".
     *
     * A diferença é dinheiro do cliente. Se o timeout aconteceu **depois** que a
     * operadora autorizou — o caso mais comum de timeout, porque a resposta é
     * que se perde —, o cliente foi cobrado, o sistema diz "recusado", e a venda
     * é refeita: **cobrança em duplicidade**.
     *
     * Estado próprio porque a ação é outra: recusa se resolve com outro cartão,
     * indeterminado se resolve **consultando a operadora** antes de qualquer
     * coisa. Tratar os dois igual leva à ação errada nos dois casos.
     */
    case INDETERMINADO = 'INDETERMINADO';

    public function aprovado(): bool
    {
        return $this === self::AUTORIZADO || $this === self::CAPTURADO;
    }

    /**
     * Precisa de conferência humana ou de consulta à operadora antes de decidir.
     *
     * Nem aprovado nem recusado: o sistema não sabe, e fingir que sabe é o que
     * produz a cobrança duplicada.
     */
    public function indeterminado(): bool
    {
        return $this === self::INDETERMINADO;
    }
}
