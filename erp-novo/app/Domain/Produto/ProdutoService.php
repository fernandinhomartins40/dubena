<?php

namespace App\Domain\Produto;

use App\Models\Produto\Produto;
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
