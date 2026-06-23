<?php

namespace App\Domain\Financeiro;

use App\Models\Caixa\ContaMovimento;
use Illuminate\Support\Carbon;

/**
 * ConciliacaoService (C8) — concilia o extrato bancário (OFX) com os movimentos
 * de conta do ERP. Casa por (valor, data) dentro de uma tolerância de dias; o que
 * casa vira "conciliado", o resto fica pendente dos dois lados para ação manual.
 */
class ConciliacaoService
{
    public function __construct(private OfxParser $ofx) {}

    /**
     * Concilia o OFX contra os movimentos da conta no período.
     *
     * @return array{
     *   conciliados: list<array<string,mixed>>,
     *   ofx_pendentes: list<array<string,mixed>>,
     *   erp_pendentes: list<array<string,mixed>>,
     *   resumo: array{ofx:int, erp:int, conciliados:int}
     * }
     */
    public function conciliar(int $contaId, string $ofxConteudo, string $inicio, string $fim, int $toleranciaDias = 2): array
    {
        $transacoes = $this->ofx->transacoes($ofxConteudo);

        $movimentos = ContaMovimento::query()
            ->where('conta_id', $contaId)
            ->whereBetween('datahora', [Carbon::parse($inicio)->startOfDay(), Carbon::parse($fim)->endOfDay()])
            ->get()
            ->map(fn (ContaMovimento $m) => [
                'id' => $m->id,
                'data' => $m->datahora?->toDateString(),
                'valor' => round((float) $m->valor, 2),
                'descricao' => $m->descricao,
                'usado' => false,
            ])->all();

        $conciliados = [];
        $ofxPendentes = [];

        foreach ($transacoes as $t) {
            $idx = $this->casar($t, $movimentos, $toleranciaDias);
            if ($idx !== null) {
                $movimentos[$idx]['usado'] = true;
                $conciliados[] = [
                    'ofx' => $t,
                    'movimento_id' => $movimentos[$idx]['id'],
                    'valor' => $t['valor'],
                ];
            } else {
                $ofxPendentes[] = $t;
            }
        }

        $erpPendentes = array_values(array_filter($movimentos, fn ($m) => ! $m['usado']));

        return [
            'conciliados' => $conciliados,
            'ofx_pendentes' => $ofxPendentes,
            'erp_pendentes' => array_map(fn ($m) => [
                'id' => $m['id'], 'data' => $m['data'], 'valor' => $m['valor'], 'descricao' => $m['descricao'],
            ], $erpPendentes),
            'resumo' => [
                'ofx' => count($transacoes),
                'erp' => count($movimentos),
                'conciliados' => count($conciliados),
            ],
        ];
    }

    /**
     * Acha o índice de um movimento do ERP que casa com a transação OFX:
     * mesmo valor (centavo) e data dentro da tolerância. Retorna null se nenhum.
     *
     * @param  list<array<string,mixed>>  $movimentos
     */
    private function casar(array $transacao, array $movimentos, int $toleranciaDias): ?int
    {
        $valorOfx = round((float) $transacao['valor'], 2);
        $dataOfx = $transacao['data'] ? Carbon::parse($transacao['data']) : null;

        foreach ($movimentos as $i => $m) {
            if ($m['usado'] || round((float) $m['valor'], 2) !== $valorOfx) {
                continue;
            }
            if ($dataOfx === null || $m['data'] === null) {
                return $i; // sem data confiável: casa só pelo valor
            }
            if (abs(Carbon::parse($m['data'])->diffInDays($dataOfx)) <= $toleranciaDias) {
                return $i;
            }
        }

        return null;
    }
}
