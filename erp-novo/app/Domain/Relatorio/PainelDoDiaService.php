<?php

namespace App\Domain\Relatorio;

use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Shared\PeriodoPorDia;
use Illuminate\Support\Facades\DB;

/**
 * "Para hoje": o que pede atenção agora, em vez de totais históricos.
 *
 * O painel inicial mostrava "92 mil clientes cadastrados" — verdade que não
 * leva a ação nenhuma. Aqui cada número é uma pergunta com resposta: há
 * pedido esperando, conta vencendo, produto abaixo do mínimo?
 *
 * Só fatos, nenhum limiar inventado: "em aberto" é a situação de efeito
 * PENDENTE; "abaixo do mínimo" é o mínimo que a própria revenda cadastrou no
 * saldo. Um "pedido atrasado" exigiria um prazo, e esse prazo é decisão de
 * cada revenda — por isso o painel mostra há quanto tempo o mais antigo
 * espera, e a pessoa julga.
 */
class PainelDoDiaService
{
    /** @return array{em_aberto: int, mais_antigo: string|null, hoje: int, hoje_valor: float} */
    public function pedidos(int $empresaId): array
    {
        $abertos = DB::table('pedidos as p')
            ->join('pedidosituacoes as s', 's.id', '=', 'p.pedidosituacao_id')
            ->where('p.empresa_id', $empresaId)
            ->where('s.efeito', EfeitoPedido::PENDENTE->value)
            ->selectRaw('count(*) as n, min(p.datahora) as mais_antigo')
            ->first();

        // Faixa do dia, não whereDate: com o cast a consulta varria os 400 mil
        // pedidos (3,1 s na homologação). Ver PeriodoPorDia.
        $hoje = PeriodoPorDia::noDia(DB::table('pedidos as p'), 'p.datahora', today())
            ->join('pedidosituacoes as s', 's.id', '=', 'p.pedidosituacao_id')
            ->where('p.empresa_id', $empresaId)
            ->where('s.efeito', '<>', EfeitoPedido::CANCELADO->value)
            ->selectRaw('count(*) as n, coalesce(sum(p.valor_venda), 0) as valor')
            ->first();

        return [
            'em_aberto' => (int) $abertos->n,
            'mais_antigo' => $abertos->mais_antigo !== null ? \Illuminate\Support\Carbon::parse($abertos->mais_antigo)->toIso8601String() : null,
            'hoje' => (int) $hoje->n,
            'hoje_valor' => round((float) $hoje->valor, 2),
        ];
    }

    /** @return array<string, array{quantidade: int, valor: float}> */
    public function financeiro(int $empresaId): array
    {
        $base = fn () => DB::table('financeiroparcelas as fp')
            ->join('financeiros as f', 'f.id', '=', 'fp.financeiro_id')
            ->where('f.empresa_id', $empresaId)
            ->where('f.cancelado', false)
            ->where('fp.baixado', false);

        $soma = fn ($q) => (fn ($r) => ['quantidade' => (int) $r->n, 'valor' => round((float) $r->valor, 2)])(
            $q->selectRaw('count(*) as n, coalesce(sum(fp.valor), 0) as valor')->first()
        );

        return [
            'receber_hoje' => $soma($base()->where('f.pagarreceber', 'R')->whereDate('fp.vencimento', today())),
            'receber_vencido' => $soma($base()->where('f.pagarreceber', 'R')->whereDate('fp.vencimento', '<', today())),
            'pagar_hoje' => $soma($base()->where('f.pagarreceber', 'P')->whereDate('fp.vencimento', today())),
        ];
    }

    /** Saldos abaixo do mínimo que a revenda cadastrou (sem mínimo, não conta). */
    public function estoqueAbaixoDoMinimo(int $empresaId): int
    {
        return (int) DB::table('estoquesaldos')
            ->where('empresa_id', $empresaId)
            ->where('quantidade_minima', '>', 0)
            ->whereColumn('quantidade', '<', 'quantidade_minima')
            ->count();
    }

    /** Contratos ativos cujo prazo de devolução já passou. */
    public function comodatosVencidos(int $empresaId): int
    {
        return (int) DB::table('comodatos')
            ->where('empresa_id', $empresaId)
            ->where('situacao', 'ATIVO')
            ->whereNotNull('data_vencimento')
            ->whereDate('data_vencimento', '<', today())
            ->count();
    }

    public function cadastrosARevisar(int $empresaId): int
    {
        return (int) DB::table('cliente_revisoes')->where('empresa_id', $empresaId)->where('situacao', 'pendente')->count();
    }
}
