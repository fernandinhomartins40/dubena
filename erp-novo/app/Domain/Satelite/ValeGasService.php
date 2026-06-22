<?php

namespace App\Domain\Satelite;

use App\Domain\Financeiro\FinanceiroService;
use App\Models\Satelite\ValeGas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ValeGasService (N8) — cupom pré-pago. Emite (gera código + financeiro a receber),
 * paga, utiliza (resgate num pedido) e cancela — máquina de estados por enum.
 */
class ValeGasService
{
    public function __construct(private FinanceiroService $financeiro)
    {
    }

    /** @param array<string,mixed> $dados */
    public function emitir(array $dados): ValeGas
    {
        $valor = round((float) $dados['valor'], 2);
        if ($valor <= 0) {
            throw ValidationException::withMessages(['valor' => 'Valor do vale deve ser positivo.']);
        }

        return DB::transaction(function () use ($dados, $valor) {
            $vale = ValeGas::create(array_merge($dados, [
                'codigo' => $dados['codigo'] ?? $this->gerarCodigo(),
                'valor' => $valor,
                'situacao' => SituacaoValeGas::EMITIDO->value,
            ]));

            // Financeiro a receber do cliente pelo cupom (gerador único N5).
            if (! empty($dados['cliente_id'])) {
                $fin = $this->financeiro->criar([
                    'empresa_id' => $vale->empresa_id,
                    'grupo_id' => $vale->grupo_id,
                    'cliente_id' => $vale->cliente_id,
                    'pagarreceber' => 'R',
                    'descricao' => "Vale-gás {$vale->codigo}",
                    'valor' => $valor,
                    'origem' => 'vale_gas',
                    'origem_id' => $vale->id,
                ]);
                $vale->update(['financeiro_id' => $fin->id]);
            }

            return $vale->refresh();
        });
    }

    public function mudarSituacao(ValeGas $vale, SituacaoValeGas $destino, ?int $pedidoId = null): ValeGas
    {
        if (! $vale->situacao->podeIrPara($destino)) {
            throw ValidationException::withMessages([
                'situacao' => "Transição inválida: {$vale->situacao->value} → {$destino->value}.",
            ]);
        }

        $extra = [];
        if ($destino === SituacaoValeGas::UTILIZADO) {
            $extra = ['pedido_id' => $pedidoId, 'utilizado_em' => now()];
        }

        $vale->update(array_merge(['situacao' => $destino->value], $extra));

        return $vale->refresh();
    }

    private function gerarCodigo(): string
    {
        return 'VG-'.Str::upper(Str::random(8));
    }
}
