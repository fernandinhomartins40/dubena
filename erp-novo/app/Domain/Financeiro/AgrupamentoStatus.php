<?php

namespace App\Domain\Financeiro;

/**
 * Status de agrupamento/reparcelamento de um título financeiro (N5).
 * Substitui o inteiro mágico do legado (agrupamento_status 0/1/2/3) por enum.
 *
 *   NORMAL      (0) — título comum.
 *   AGRUPADOR   (1) — título consolidado que agrupa outros (ex.: fechamento de convênio).
 *   AGRUPADO    (2) — título original que foi consolidado num agrupador (inativo p/ cobrança).
 *   REPARCELADO (3) — título cuja dívida foi renegociada em novas parcelas.
 */
enum AgrupamentoStatus: int
{
    case NORMAL = 0;
    case AGRUPADOR = 1;
    case AGRUPADO = 2;
    case REPARCELADO = 3;

    /** O título está ativo para cobrança (não foi consolidado/reparcelado)? */
    public function ativoParaCobranca(): bool
    {
        return $this === self::NORMAL || $this === self::AGRUPADOR;
    }
}
