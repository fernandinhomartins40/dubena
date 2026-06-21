<?php

namespace App\Domain\Pedido;

/**
 * Efeito de uma situação de pedido na MÁQUINA DE ESTADOS (N4).
 *
 * Substitui a matriz implícita do legado (flags entregafinalizada/entregacancelada/
 * fechadoconcluido/... espalhadas) por 3 estados explícitos que governam a
 * movimentação de estoque/financeiro na TRANSIÇÃO:
 *
 *   PENDENTE  → pedido em aberto; sem baixa de estoque nem financeiro.
 *   CONCLUIDO → venda concretizada; SAÍDA de estoque + gera financeiro.
 *   CANCELADO → venda revertida; devolve estoque (ENTRADA) + estorna financeiro.
 */
enum EfeitoPedido: string
{
    case PENDENTE = 'PENDENTE';
    case CONCLUIDO = 'CONCLUIDO';
    case CANCELADO = 'CANCELADO';

    /** A venda está concretizada (estoque baixado, financeiro gerado)? */
    public function concretiza(): bool
    {
        return $this === self::CONCLUIDO;
    }

    public function cancela(): bool
    {
        return $this === self::CANCELADO;
    }
}
