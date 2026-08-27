<?php

namespace App\Domain\Pagamento;

use App\Domain\Caixa\CaixaService;
use App\Models\Pagamento\CartaoTransacao;
use App\Models\Pagamento\GasDoPovoBeneficio;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PagamentoService (C4 resto) — meios de pagamento cartão e "Gás do Povo".
 *
 * Cartão: registra a transação (NSU/bandeira/parcelas), calcula o líquido pela
 * taxa da operadora e credita o VALOR LÍQUIDO no caixa (a taxa é despesa). Isso
 * mantém a invariância de saldo do CaixaService (Σ movimentos = saldo).
 *
 * Gás do Povo: registra o benefício e o "saca" contra um pedido (marca utilizado);
 * o valor entra no caixa como recebimento do auxílio.
 */
class PagamentoService
{
    public function __construct(private CaixaService $caixa) {}

    /**
     * Registra um pagamento por cartão e credita o líquido no caixa (se houver conta).
     *
     * @param  array<string,mixed>  $dados
     */
    public function registrarCartao(array $dados): CartaoTransacao
    {
        return DB::transaction(function () use ($dados) {
            $bruto = round((float) $dados['valor_bruto'], 2);
            $taxa = (float) ($dados['taxa_percentual'] ?? 0);
            $liquido = round($bruto * (1 - $taxa / 100), 2);

            $trans = CartaoTransacao::create([
                'empresa_id' => $dados['empresa_id'],
                'pedido_id' => $dados['pedido_id'] ?? null,
                'conta_id' => $dados['conta_id'] ?? null,
                'bandeira' => $dados['bandeira'] ?? null,
                'tipo' => $dados['tipo'] ?? 'credito',
                'nsu' => $dados['nsu'] ?? null,
                'autorizacao' => $dados['autorizacao'] ?? null,
                'parcelas' => $dados['parcelas'] ?? 1,
                'valor_bruto' => $bruto,
                'taxa_percentual' => $taxa,
                'valor_liquido' => $liquido,
                'situacao' => 'confirmada',
            ]);

            if (! empty($dados['conta_id'])) {
                $this->caixa->movimentar((int) $dados['conta_id'], $liquido, 'CARTAO', (int) $dados['empresa_id'], [
                    'descricao' => 'Cartão '.($dados['bandeira'] ?? '').' NSU '.($dados['nsu'] ?? ''),
                    'origem' => 'cartao_transacao',
                    'origem_id' => $trans->id,
                ]);
            }

            return $trans;
        });
    }

    /**
     * Registra um benefício "Gás do Povo".
     *
     * @param  array<string,mixed>  $dados
     */
    public function registrarBeneficio(array $dados): GasDoPovoBeneficio
    {
        return GasDoPovoBeneficio::create([
            'cliente_id' => $dados['cliente_id'] ?? null,
            'nis' => $dados['nis'] ?? null,
            'competencia' => $dados['competencia'],
            'valor' => round((float) $dados['valor'], 2),
            'situacao' => 'disponivel',
        ]);
    }

    /**
     * "Saca" o benefício contra um pedido: marca utilizado e credita o valor no
     * caixa (recebimento do auxílio). Idempotente por estado (só saca disponível).
     */
    public function sacarBeneficio(GasDoPovoBeneficio $beneficio, int $pedidoId, ?int $contaId = null): GasDoPovoBeneficio
    {
        if ($beneficio->situacao !== 'disponivel') {
            throw ValidationException::withMessages(['beneficio' => 'Benefício não está disponível para uso.']);
        }

        return DB::transaction(function () use ($beneficio, $pedidoId, $contaId) {
            $beneficio->update(['situacao' => 'utilizado', 'pedido_id' => $pedidoId]);

            if ($contaId) {
                $this->caixa->movimentar($contaId, (float) $beneficio->valor, 'GASDOPOVO', (int) $beneficio->empresa_id, [
                    'descricao' => 'Gás do Povo — benefício #'.$beneficio->id,
                    'origem' => 'gasdopovo_beneficio',
                    'origem_id' => $beneficio->id,
                ]);
            }

            return $beneficio->refresh();
        });
    }
}
