<?php

namespace App\Domain\Caixa;

use App\Domain\Financeiro\BaixaService;
use App\Models\Caixa\Cheque;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ChequeService (N6) — máquina de estados do cheque + integração com o caixa.
 * Ao COMPENSAR um cheque recebido numa conta, gera a entrada no caixa (via
 * CaixaService); ao DEVOLVER um compensado, estorna.
 */
class ChequeService
{
    public function __construct(
        private CaixaService $caixa,
        private BaixaService $baixas,
    ) {}

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
                $this->caixa->movimentar($contaId, (float) $cheque->valor, CaixaService::AJUSTE, (int) $cheque->empresa_id, [
                    'origem' => 'cheque', 'origem_id' => $cheque->id,
                    'descricao' => "Compensação do cheque #{$cheque->numero}", 'user_id' => $userId,
                ]);
            }

            $cheque->update(['situacao' => $destino->value]);

            return $cheque->refresh();
        });
    }

    /**
     * Encontro de contas (C4): usa um cheque RECEBIDO em carteira para quitar um
     * compromisso (repasse a terceiro / troco em cheque). Marca o cheque como
     * REPASSADO e registra a baixa do título a pagar associado quando informado.
     * Não toca no caixa (não há trânsito de dinheiro — é compensação direta).
     *
     * Devolve o cheque atualizado e, opcionalmente, a diferença (troco) quando o
     * valor do cheque excede o compromisso.
     *
     * @return array{cheque: Cheque, troco: float}
     */
    public function encontroDeContas(Cheque $cheque, int $empresaId, float $valorCompromisso, ?int $financeiroParcelaId = null, ?int $userId = null): array
    {
        if ($cheque->especie !== 'R') {
            throw ValidationException::withMessages(['cheque' => 'Só cheques recebidos entram em encontro de contas.']);
        }
        if (! $cheque->situacao->podeIrPara(SituacaoCheque::REPASSADO)) {
            throw ValidationException::withMessages([
                'situacao' => "Cheque em {$cheque->situacao->value} não pode ser repassado.",
            ]);
        }

        return DB::transaction(function () use ($cheque, $empresaId, $valorCompromisso, $financeiroParcelaId) {
            $cheque = Cheque::withoutTenant()
                ->whereKey($cheque->getKey())
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->first();
            if (! $cheque) {
                throw ValidationException::withMessages(['cheque' => 'Cheque invalido para a empresa ativa.']);
            }
            if ($cheque->especie !== 'R' || ! $cheque->situacao->podeIrPara(SituacaoCheque::REPASSADO)) {
                throw ValidationException::withMessages(['cheque' => 'Cheque nao pode ser usado no encontro de contas.']);
            }

            if ($financeiroParcelaId) {
                $parcela = FinanceiroParcela::withoutTenant()
                    ->whereKey($financeiroParcelaId)
                    ->where('empresa_id', $empresaId)
                    ->lockForUpdate()
                    ->first();
                $valorAplicado = round(min((float) $cheque->valor, $valorCompromisso), 2);
                if (! $parcela || $parcela->baixado || $valorAplicado < round((float) $parcela->valor, 2)) {
                    throw ValidationException::withMessages(['financeiro_parcela_id' => 'Parcela invalida, ja baixada ou sem cobertura integral.']);
                }

                $this->baixas->baixar($parcela->id, $empresaId, $valorAplicado, 'cheque');
            }

            $cheque->update(['situacao' => SituacaoCheque::REPASSADO->value]);
            $troco = round(max(0.0, (float) $cheque->valor - $valorCompromisso), 2);

            return ['cheque' => $cheque->refresh(), 'troco' => $troco];
        });
    }
}
