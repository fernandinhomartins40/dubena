<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * vendas:diaria (C9) — apura o total vendido no dia (pedidos concretizados) por
 * empresa. Base do e-mail de venda diária do legado; o ENVIO SMTP é gate.
 */
class VendaDiaria extends Command
{
    protected $signature = 'vendas:diaria {--data=}';

    protected $description = 'Apura o total de vendas do dia por empresa.';

    public function handle(): int
    {
        $data = $this->option('data') ?: now()->toDateString();
        $linhas = DB::table('pedidos')
            ->where('estoque_movimentado', true)
            ->whereDate('datahora', $data)
            ->groupBy('empresa_id')
            ->selectRaw('empresa_id, count(*) as qtd, sum(valor_venda) as total')
            ->get();

        foreach ($linhas as $l) {
            $this->info("Empresa {$l->empresa_id}: {$l->qtd} venda(s), R$ ".number_format((float) $l->total, 2, ',', '.'));
        }
        $this->info("Apuração de {$data} concluída ({$linhas->count()} empresa(s)).");

        return self::SUCCESS;
    }
}
