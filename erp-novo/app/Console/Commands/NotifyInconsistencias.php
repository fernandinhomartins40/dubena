<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * notify:inconsistencias (C9) — varre invariantes de saldo (estoque e caixa) e
 * reporta divergências (Σ histórico ≠ saldo). Base do alerta de inconsistências
 * do legado; aqui retorna a contagem (e lista no output).
 */
class NotifyInconsistencias extends Command
{
    protected $signature = 'notify:inconsistencias';

    protected $description = 'Reporta divergências de saldo (estoque/caixa) vs histórico.';

    public function handle(): int
    {
        $estoque = DB::table('estoquesaldos as s')
            ->selectRaw('s.id, s.quantidade, (select coalesce(sum(h.quantidade),0) from estoquehistorico h '
                .'where h.setor_id = s.setor_id and h.produto_id = s.produto_id) as hist')
            ->get()
            ->filter(fn ($r) => round((float) $r->quantidade, 4) !== round((float) $r->hist, 4));

        $caixa = DB::table('contas as c')
            ->selectRaw('c.id, c.saldo_atual, (select coalesce(sum(m.valor),0) from contamovimentos m '
                .'where m.conta_id = c.id) as soma')
            ->get()
            ->filter(fn ($r) => round((float) $r->saldo_atual, 2) !== round((float) $r->soma, 2));

        $total = $estoque->count() + $caixa->count();
        $this->info("Inconsistências: estoque={$estoque->count()}, caixa={$caixa->count()} (total {$total}).");

        return self::SUCCESS;
    }
}
