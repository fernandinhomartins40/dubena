<?php

namespace App\Domain\Satelite;

use App\Domain\Financeiro\FinanceiroService;
use App\Models\Pedido\Pedido;
use App\Models\Satelite\Convenio;
use App\Models\Satelite\ConvenioFechamento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ConvenioFechamentoService (N8) — consolida os pedidos do período de um convênio
 * num ÚNICO financeiro (via FinanceiroService, o gerador único do N5). Espelha o
 * fechamento mensal do legado, mas sem geração inline de financeiro.
 */
class ConvenioFechamentoService
{
    public function __construct(private FinanceiroService $financeiro)
    {
    }

    /**
     * Fecha o período: pega os pedidos CONCLUÍDOS do cliente do convênio no
     * intervalo que ainda não estão em nenhum fechamento, e gera 1 financeiro.
     */
    public function fechar(Convenio $convenio, string $inicio, string $fim, int $numParcelas = 1): ConvenioFechamento
    {
        $dtInicio = Carbon::parse($inicio)->startOfDay();
        $dtFim = Carbon::parse($fim)->endOfDay();

        return DB::transaction(function () use ($convenio, $dtInicio, $dtFim, $numParcelas) {
            // Pedidos do conveniado, concluídos (estoque movimentado), no período,
            // ainda não vinculados a um fechamento.
            $pedidos = Pedido::query()
                ->where('cliente_id', $convenio->cliente_id)
                ->where('estoque_movimentado', true)
                ->whereBetween('datahora', [$dtInicio, $dtFim])
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))->from('convenio_fechamento_pedidos')
                        ->whereColumn('convenio_fechamento_pedidos.pedido_id', 'pedidos.id');
                })
                ->get();

            if ($pedidos->isEmpty()) {
                throw ValidationException::withMessages(['pedidos' => 'Nenhum pedido elegível no período.']);
            }

            $total = round((float) $pedidos->sum('valor_venda'), 2);

            $fechamento = ConvenioFechamento::create([
                'empresa_id' => $convenio->empresa_id,
                'convenio_id' => $convenio->id,
                'periodo_inicio' => $dtInicio->toDateString(),
                'periodo_fim' => $dtFim->toDateString(),
                'valor_total' => $total,
                'total_pedidos' => $pedidos->count(),
                'situacao' => 'FECHADO',
            ]);

            foreach ($pedidos as $p) {
                $fechamento->pedidos()->attach($p->id, ['valor' => $p->valor_venda]);
            }

            // 1 financeiro consolidado para o conveniado (via gerador único N5).
            $financeiro = $this->financeiro->criar([
                'empresa_id' => $convenio->empresa_id,
                'grupo_id' => $convenio->grupo_id,
                'cliente_id' => $convenio->cliente_id,
                'pagarreceber' => 'R',
                'descricao' => "Fechamento convênio {$convenio->descricao} ({$dtInicio->format('d/m/Y')}–{$dtFim->format('d/m/Y')})",
                'valor' => $total,
                'origem' => 'convenio_fechamento',
                'origem_id' => $fechamento->id,
            ], numParcelas: $numParcelas);

            $fechamento->update(['financeiro_id' => $financeiro->id, 'situacao' => 'FATURADO']);

            return $fechamento->refresh()->load('financeiro');
        });
    }
}
