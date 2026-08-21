<?php

namespace App\Domain\Venda;

/**
 * Estados de uma solicitação de venda — F3.
 *
 * Enum, não string, porque a transição decide dinheiro: só `aprovada` gera
 * pedido, e só a partir de `pendente` se decide. O CLAUDE.md manda enum para
 * máquina de estados.
 */
enum SituacaoSolicitacao: string
{
    /** Esperando a Central. É o único estado a partir do qual se decide. */
    case PENDENTE = 'pendente';

    /** A Central aceitou (talvez com desconto menor) e o pedido nasceu. */
    case APROVADA = 'aprovada';

    /** A Central recusou. Terminal — o vendedor abre outra se quiser. */
    case RECUSADA = 'recusada';

    /** O próprio solicitante desistiu antes da decisão. */
    case CANCELADA = 'cancelada';

    /** Já teve desfecho? Estado terminal não aceita nova decisão. */
    public function encerrada(): bool
    {
        return $this !== self::PENDENTE;
    }

    /**
     * Pode transitar para o estado dado?
     * Tudo sai de PENDENTE; nada sai de um terminal — evita aprovar duas vezes
     * (e gerar dois pedidos) numa corrida entre dois atendentes.
     */
    public function podeIrPara(self $novo): bool
    {
        return $this === self::PENDENTE && $novo !== self::PENDENTE;
    }
}
