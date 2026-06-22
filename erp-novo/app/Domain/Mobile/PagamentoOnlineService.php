<?php

namespace App\Domain\Mobile;

use App\Domain\Mobile\Contracts\PagamentoDriver;
use App\Models\Mobile\PagamentoOnline;
use App\Models\Pedido\Pedido;
use Illuminate\Support\Facades\DB;

/**
 * PagamentoOnlineService (N10 — GATE Rede). Autoriza cartão via PagamentoDriver e
 * grava a transação rastreável, isolando o gateway (ex-sgcm_api do legado).
 */
class PagamentoOnlineService
{
    public function __construct(private PagamentoDriver $driver)
    {
    }

    /**
     * Cobra um pedido no cartão. Grava a transação e devolve o registro.
     *
     * @param array{token:string, parcelas?:int} $cartao
     */
    public function cobrarPedido(Pedido $pedido, array $cartao): PagamentoOnline
    {
        $parcelas = (int) ($cartao['parcelas'] ?? 1);
        $valor = (float) $pedido->valor_venda;

        return DB::transaction(function () use ($pedido, $cartao, $parcelas, $valor) {
            $pagamento = PagamentoOnline::create([
                'empresa_id' => $pedido->empresa_id,
                'pedido_id' => $pedido->id,
                'cliente_id' => $pedido->cliente_id,
                'gateway' => $this->driver->gateway(),
                'valor' => $valor,
                'parcelas' => $parcelas,
                'situacao' => SituacaoPagamento::PENDENTE->value,
            ]);

            $res = $this->driver->autorizar(['valor' => $valor, 'parcelas' => $parcelas, 'token' => $cartao['token']]);

            $pagamento->update([
                'situacao' => $res['aprovado'] ? SituacaoPagamento::AUTORIZADO->value : SituacaoPagamento::NEGADO->value,
                'tid' => $res['tid'],
                'nsu' => $res['nsu'],
                'autorizacao' => $res['autorizacao'],
                'bandeira' => $res['bandeira'],
                'mensagem' => $res['mensagem'],
            ]);

            return $pagamento->refresh();
        });
    }

    public function estornar(PagamentoOnline $pagamento): PagamentoOnline
    {
        if ($pagamento->tid) {
            $this->driver->estornar($pagamento->tid);
        }
        $pagamento->update(['situacao' => SituacaoPagamento::ESTORNADO->value]);

        return $pagamento->refresh();
    }
}
