<?php

namespace App\Domain\Satelite;

use App\Domain\Estoque\EstoqueService;
use App\Models\Satelite\Comodato;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ComodatoService (N8) — vasilhame emprestado ao cliente. Emprestar baixa o
 * estoque (SAÍDA); devolver repõe (ENTRADA), via EstoqueService (saldo auditável).
 */
class ComodatoService
{
    public function __construct(private EstoqueService $estoque)
    {
    }

    /** @param array<string,mixed> $dados */
    public function emprestar(array $dados, ?int $userId = null): Comodato
    {
        $qtd = (float) $dados['quantidade'];
        if ($qtd <= 0) {
            throw ValidationException::withMessages(['quantidade' => 'Quantidade deve ser positiva.']);
        }

        return DB::transaction(function () use ($dados, $qtd, $userId) {
            $comodato = Comodato::create(array_merge($dados, [
                'quantidade' => $qtd,
                'quantidade_devolvida' => 0,
                'situacao' => 'ATIVO',
                'data_emprestimo' => $dados['data_emprestimo'] ?? now()->toDateString(),
            ]));

            if ($comodato->setor_id) {
                $this->estoque->saida($comodato->setor_id, $comodato->produto_id, $qtd, 'comodato', $comodato->id, $userId);
            }

            return $comodato->refresh();
        });
    }

    /** Devolve (parcial ou total) o vasilhame, repondo o estoque. */
    public function devolver(Comodato $comodato, float $quantidade, ?int $userId = null): Comodato
    {
        if ($quantidade <= 0) {
            throw ValidationException::withMessages(['quantidade' => 'Quantidade deve ser positiva.']);
        }

        $pendente = (float) $comodato->quantidade - (float) $comodato->quantidade_devolvida;
        if ($quantidade > $pendente + 0.0001) {
            throw ValidationException::withMessages(['quantidade' => "Devolução ({$quantidade}) maior que o pendente ({$pendente})."]);
        }

        return DB::transaction(function () use ($comodato, $quantidade, $userId) {
            if ($comodato->setor_id) {
                $this->estoque->entrada($comodato->setor_id, $comodato->produto_id, $quantidade, null, 'comodato-devolucao', $comodato->id, $userId);
            }

            $devolvida = round((float) $comodato->quantidade_devolvida + $quantidade, 3);
            $total = (float) $comodato->quantidade;
            $situacao = $devolvida >= $total - 0.0001 ? 'DEVOLVIDO' : 'PARCIAL';

            $comodato->update([
                'quantidade_devolvida' => $devolvida,
                'situacao' => $situacao,
                'data_devolucao' => $situacao === 'DEVOLVIDO' ? now()->toDateString() : $comodato->data_devolucao,
            ]);

            return $comodato->refresh();
        });
    }
}
