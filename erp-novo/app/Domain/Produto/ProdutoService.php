<?php

namespace App\Domain\Produto;

use App\Models\Produto\Produto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Regra de negócio do Produto (sem HTTP). Origens de combustível como
 * sub-relação ANINHADA (não array posicional).
 */
class ProdutoService
{
    /** @param array<string, mixed> $dados */
    public function criar(array $dados): Produto
    {
        return DB::transaction(function () use ($dados) {
            $origens = $dados['origens'] ?? null;
            unset($dados['origens']);

            $produto = Produto::create($dados);

            if (is_array($origens)) {
                $this->sincronizarOrigens($produto, $origens);
            }

            return $produto->load('origens');
        });
    }

    /** @param array<string, mixed> $dados */
    public function atualizar(Produto $produto, array $dados): Produto
    {
        return DB::transaction(function () use ($produto, $dados) {
            $origens = $dados['origens'] ?? null;
            unset($dados['origens']);

            $produto->update($dados);

            if (is_array($origens)) {
                $this->sincronizarOrigens($produto, $origens);
            }

            return $produto->refresh()->load('origens');
        });
    }

    public function excluir(Produto $produto): void
    {
        $produto->delete();
    }

    /**
     * Calcula o novo preço de venda de cada produto (filtrável por classe) SEM
     * persistir — usado pelo preview da SPA antes de aplicar.
     *
     * @return list<array{id:int, descricao:string, atual:float, novo:float}>
     */
    public function previewReajuste(string $tipo, float $valor, ?int $classeId = null): array
    {
        return $this->produtosParaReajuste($classeId)
            ->map(fn (Produto $p) => [
                'id' => $p->id,
                'descricao' => $p->descricao,
                'atual' => (float) $p->preco_venda,
                'novo' => $this->novoPreco((float) $p->preco_venda, $tipo, $valor),
            ])
            ->values()->all();
    }

    /**
     * Aplica o reajuste de preço de venda (percentual ou valor fixo somado),
     * numa transação. Retorna a quantidade de produtos afetados.
     */
    public function aplicarReajuste(string $tipo, float $valor, ?int $classeId = null): int
    {
        return DB::transaction(function () use ($tipo, $valor, $classeId) {
            $afetados = 0;
            foreach ($this->produtosParaReajuste($classeId) as $produto) {
                $produto->preco_venda = $this->novoPreco((float) $produto->preco_venda, $tipo, $valor);
                $produto->save();
                $afetados++;
            }

            return $afetados;
        });
    }

    /** @return Collection<int, Produto> */
    private function produtosParaReajuste(?int $classeId): Collection
    {
        return Produto::query()
            ->when($classeId, fn ($q) => $q->where('produtoclasse_id', $classeId))
            ->orderBy('descricao')
            ->get();
    }

    /** percentual: +N%; fixo: +N reais. Nunca abaixo de 0. */
    private function novoPreco(float $atual, string $tipo, float $valor): float
    {
        $novo = $tipo === 'percentual'
            ? $atual * (1 + $valor / 100)
            : $atual + $valor;

        return round(max($novo, 0), 2);
    }

    /** @param list<array<string, mixed>> $origens */
    private function sincronizarOrigens(Produto $produto, array $origens): void
    {
        $produto->origens()->delete();
        foreach ($origens as $o) {
            $produto->origens()->create([
                'uf' => $o['uf'] ?? null,
                'ind_import' => (int) ($o['ind_import'] ?? $o['indimport'] ?? 0),
                'cuf_orig' => $o['cuf_orig'] ?? $o['cuforig'] ?? null,
                'p_orig' => $o['p_orig'] ?? $o['porig'] ?? null,
            ]);
        }
    }
}
