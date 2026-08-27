<?php

namespace App\Domain\Financeiro;

use App\Domain\Shared\CalculoParcelasService;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Financeiro\Financeiro;
use App\Models\Pedido\Pedido;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FinanceiroService (N5) — o ÚNICO gerador de financeiro.
 *
 * TODOS os geradores (Pedido N4, NF N9, convênio/vale-gás N8, OFX) passam por aqui
 * (elimina a duplicação inline do legado). Garante a invariante Σ parcelas = valor.
 * Agrupamento/reparcelamento expresso pelo enum AgrupamentoStatus.
 */
class FinanceiroService
{
    public function __construct(private CalculoParcelasService $parcelas) {}

    /**
     * Cria um título + suas parcelas (e rateios opcionais), numa transação.
     *
     * @param  array<string, mixed>  $dados  valor, pagarreceber, cliente_id, origem...
     * @param  array<int,array{percentual:float,dias:int}>  $parcelasConfig
     * @param  list<array{planoconta_id?:int,centrocusto_id?:int,valor:float}>  $rateios
     */
    public function criar(array $dados, array $parcelasConfig = [], int $numParcelas = 1, array $rateios = [], int $diasPrimeira = 0, int $intervalo = 30): Financeiro
    {
        $valor = round((float) $dados['valor'], 2);
        if ($valor <= 0) {
            throw ValidationException::withMessages(['valor' => 'Valor do título deve ser positivo.']);
        }

        return DB::transaction(function () use ($dados, $valor, $parcelasConfig, $numParcelas, $rateios, $diasPrimeira, $intervalo) {
            $financeiro = Financeiro::create(array_merge($dados, [
                'valor' => $valor,
                'data_emissao' => $dados['data_emissao'] ?? now()->toDateString(),
            ]));

            $base = CarbonImmutable::parse($dados['data_emissao'] ?? now());
            $parcelas = $this->parcelas->calcular(
                total: $valor,
                dataBase: $base,
                parcelasConfig: $parcelasConfig,
                numParcelas: max(1, $numParcelas),
                diasPrimeira: $diasPrimeira,
                intervalo: $intervalo,
            );

            foreach ($parcelas as $p) {
                $financeiro->parcelas()->create([
                    'numero' => $p->numero,
                    'vencimento' => $p->vencimento->toDateString(),
                    'valor' => $p->valor,
                    'desconto' => $p->desconto,
                ]);
            }

            foreach ($rateios as $r) {
                $financeiro->rateios()->create([
                    'planoconta_id' => $r['planoconta_id'] ?? null,
                    'centrocusto_id' => $r['centrocusto_id'] ?? null,
                    'valor' => round((float) $r['valor'], 2),
                ]);
            }

            return $financeiro->load('parcelas', 'rateios');
        });
    }

    /**
     * Gera um financeiro a partir de um pedido (chamado pelo PedidoService — N4).
     * Idempotente por (origem='pedido', origem_id=pedido->id).
     */
    public function gerarDoPedido(Pedido $pedido, ?int $numParcelas = null): ?Financeiro
    {
        if ((float) $pedido->valor_venda <= 0) {
            return null;
        }

        $existente = Financeiro::query()
            ->where('empresa_id', $pedido->empresa_id)
            ->where('origem', 'pedido')
            ->where('origem_id', $pedido->id)
            ->where('cancelado', false)
            ->first();
        if ($existente) {
            return $existente; // já gerado
        }

        // Parcelamento DIRIGIDO pela condição de pagamento do pedido (a auditoria
        // apontou que antes gerava sempre 1 parcela). Sem condição: à vista (1).
        $condicao = $pedido->condicaopagamento_id
            ? CondicaoPagamento::query()->find($pedido->condicaopagamento_id)
            : null;

        $parcelas = $numParcelas ?? ($condicao?->num_parcelas ?? 1);
        $diasPrimeira = $condicao?->dias_primeira ?? 0;
        $intervalo = $condicao?->intervalo_dias ?? 30;

        return $this->criar([
            'empresa_id' => $pedido->empresa_id,
            'grupo_id' => $pedido->grupo_id,
            'cliente_id' => $pedido->cliente_id,
            'pagarreceber' => 'R',
            'descricao' => "Pedido #{$pedido->id}",
            'valor' => (float) $pedido->valor_venda,
            'origem' => 'pedido',
            'origem_id' => $pedido->id,
        ], numParcelas: max(1, (int) $parcelas), diasPrimeira: (int) $diasPrimeira, intervalo: (int) $intervalo);
    }

    /** Estorna (cancela) o financeiro de um pedido cancelado — N4. */
    public function estornarDoPedido(Pedido $pedido): void
    {
        Financeiro::query()
            ->where('empresa_id', $pedido->empresa_id)
            ->where('origem', 'pedido')->where('origem_id', $pedido->id)->where('cancelado', false)
            ->get()
            ->each(fn (Financeiro $f) => $this->cancelar($f));
    }

    public function cancelar(Financeiro $financeiro): void
    {
        $temBaixa = $financeiro->parcelas()->where('baixado', true)->exists();
        if ($temBaixa) {
            throw ValidationException::withMessages(['financeiro' => 'Título com parcela baixada não pode ser cancelado; estorne a baixa primeiro.']);
        }
        $financeiro->update(['cancelado' => true]);
    }

    /**
     * Agrupa vários títulos num título AGRUPADOR consolidado (ex.: fechamento de
     * convênio do mês → 1 título). Os originais viram AGRUPADO.
     *
     * @param  Collection<int,Financeiro>|list<Financeiro>  $titulos
     */
    public function agrupar(iterable $titulos, array $dadosAgrupador, int $numParcelas = 1): Financeiro
    {
        $ids = collect($titulos)->map(fn (Financeiro $titulo) => $titulo->getKey());
        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['titulos' => 'Informe ao menos um título para agrupar.']);
        }

        $empresaId = (int) ($dadosAgrupador['empresa_id'] ?? 0);
        if ($empresaId <= 0 || $ids->contains(null) || $ids->uniqueStrict()->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'titulos' => 'Agrupamento exige títulos normais, em aberto, da mesma empresa, cliente e natureza.',
            ]);
        }

        return DB::transaction(function () use ($ids, $empresaId, $dadosAgrupador, $numParcelas) {
            // Releitura autoritativa + lock evita confiar em models desatualizados
            // e impede duas consolidacoes concorrentes dos mesmos titulos.
            $titulos = Financeiro::withoutTenant()
                ->where('empresa_id', $empresaId)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            $clientes = $titulos->pluck('cliente_id')->uniqueStrict();
            $sentidos = $titulos->pluck('pagarreceber')->uniqueStrict();
            $invalidos = $titulos->count() !== $ids->count()
                || $titulos->contains(fn (Financeiro $t) => $t->cancelado
                    || $t->agrupamento_status !== AgrupamentoStatus::NORMAL
                    || $t->parcelas()->where('baixado', true)->exists());

            if ($invalidos || $clientes->count() !== 1 || $sentidos->count() !== 1) {
                throw ValidationException::withMessages([
                    'titulos' => 'Agrupamento exige titulos normais, em aberto, da mesma empresa, cliente e natureza.',
                ]);
            }

            $total = round((float) $titulos->sum('valor'), 2);
            $agrupador = $this->criar(array_merge($dadosAgrupador, [
                'valor' => $total,
                'agrupamento_status' => AgrupamentoStatus::AGRUPADOR->value,
            ]), numParcelas: $numParcelas);

            foreach ($titulos as $t) {
                $t->update([
                    'agrupamento_status' => AgrupamentoStatus::AGRUPADO->value,
                    'agrupador_id' => $agrupador->id,
                ]);
            }

            return $agrupador->refresh()->load('parcelas');
        });
    }

    /** Desfaz um agrupamento: reativa os originais e cancela o agrupador. */
    public function desagrupar(Financeiro $agrupador): void
    {
        if ($agrupador->agrupamento_status !== AgrupamentoStatus::AGRUPADOR) {
            throw ValidationException::withMessages(['financeiro' => 'Título não é um agrupador.']);
        }

        DB::transaction(function () use ($agrupador) {
            Financeiro::query()
                ->where('empresa_id', $agrupador->empresa_id)
                ->where('agrupador_id', $agrupador->id)
                ->update([
                'agrupamento_status' => AgrupamentoStatus::NORMAL->value,
                'agrupador_id' => null,
            ]);
            $agrupador->update(['cancelado' => true]);
        });
    }

    /**
     * Reparcela a dívida em aberto de um título: marca o original como REPARCELADO
     * e cria um novo título com as novas parcelas.
     */
    public function reparcelar(Financeiro $original, int $numParcelas, ?string $dataBase = null): Financeiro
    {
        $emAberto = round((float) $original->parcelas()->where('baixado', false)->sum('valor'), 2);
        if ($emAberto <= 0) {
            throw ValidationException::withMessages(['financeiro' => 'Não há saldo em aberto para reparcelar.']);
        }

        return DB::transaction(function () use ($original, $numParcelas, $dataBase, $emAberto) {
            $original->update(['agrupamento_status' => AgrupamentoStatus::REPARCELADO->value]);

            return $this->criar([
                'empresa_id' => $original->empresa_id,
                'grupo_id' => $original->grupo_id,
                'cliente_id' => $original->cliente_id,
                'pagarreceber' => $original->pagarreceber,
                'descricao' => "Reparcelamento do título #{$original->id}",
                'valor' => $emAberto,
                'data_emissao' => $dataBase ?? now()->toDateString(),
                'origem' => 'reparcelamento',
                'origem_id' => $original->id,
            ], numParcelas: $numParcelas);
        });
    }
}
