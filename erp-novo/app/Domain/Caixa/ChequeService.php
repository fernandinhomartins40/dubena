<?php

namespace App\Domain\Caixa;

use App\Models\Caixa\Cheque;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ChequeService (N6) — máquina de estados do cheque + integração com o caixa.
 * Ao COMPENSAR um cheque recebido numa conta, gera a entrada no caixa (via
 * CaixaService); ao DEVOLVER um compensado, estorna.
 */
class ChequeService
{
    public function __construct(private CaixaService $caixa)
    {
    }

    /** @param array<string,mixed> $dados */
    public function criar(array $dados): Cheque
    {
        $dados['situacao'] = $dados['situacao'] ?? SituacaoCheque::CARTEIRA->value;

        return Cheque::create($dados);
    }

    public function atualizar(Cheque $cheque, array $dados): Cheque
    {
        // Não permite trocar a situação por aqui (use mudarSituacao).
        unset($dados['situacao']);
        $cheque->update($dados);

        return $cheque->refresh();
    }

    public function excluir(Cheque $cheque): void
    {
        if ($cheque->situacao === SituacaoCheque::COMPENSADO) {
            throw ValidationException::withMessages(['cheque' => 'Cheque compensado não pode ser excluído.']);
        }
        $cheque->delete();
    }

    /**
     * Transição de situação validada pela máquina de estados. Ao compensar um
     * cheque RECEBIDO, credita a conta informada.
     */
    public function mudarSituacao(Cheque $cheque, SituacaoCheque $destino, ?int $contaId = null, ?int $userId = null): Cheque
    {
        $atual = $cheque->situacao;
        if (! $atual->podeIrPara($destino)) {
            throw ValidationException::withMessages([
                'situacao' => "Transição inválida: {$atual->value} → {$destino->value}.",
            ]);
        }

        return DB::transaction(function () use ($cheque, $destino, $contaId, $userId) {
            // Cheque recebido compensado → entra no caixa.
            if ($destino === SituacaoCheque::COMPENSADO && $cheque->especie === 'R') {
                if (! $contaId) {
                    throw ValidationException::withMessages(['conta_id' => 'Informe a conta para compensar o cheque.']);
                }
                $this->caixa->movimentar($contaId, (float) $cheque->valor, CaixaService::AJUSTE, [
                    'origem' => 'cheque', 'origem_id' => $cheque->id,
                    'descricao' => "Compensação do cheque #{$cheque->numero}", 'user_id' => $userId,
                ]);
            }

            $cheque->update(['situacao' => $destino->value]);

            return $cheque->refresh();
        });
    }
}
