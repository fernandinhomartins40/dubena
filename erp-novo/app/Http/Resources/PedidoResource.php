<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Pedido\Pedido
 */
class PedidoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->whenLoaded('cliente', fn () => $this->cliente?->nome),
            'pedidooperacao_id' => $this->pedidooperacao_id,
            'pedidosituacao_id' => $this->pedidosituacao_id,
            'situacao' => $this->whenLoaded('situacao', fn () => $this->situacao?->descricao),
            'efeito' => $this->whenLoaded('situacao', fn () => $this->situacao?->efeito?->value),
            'setor_id' => $this->setor_id,
            'datahora' => $this->datahora?->toIso8601String(),
            'datahora_acao' => $this->datahora_acao?->toIso8601String(),
            'entrega_urgente' => (bool) $this->entrega_urgente,
            'entrega_telefone' => $this->entrega_telefone,
            'entrega_taxa' => (float) $this->entrega_taxa,
            'entrega_troco_para' => $this->entrega_troco_para !== null ? (float) $this->entrega_troco_para : null,
            'valor_venda' => (float) $this->valor_venda,
            'valor_desconto' => (float) $this->valor_desconto,
            'observacao' => $this->observacao,
            'estoque_movimentado' => (bool) $this->estoque_movimentado,
            'itens' => $this->whenLoaded('itens', fn () => $this->itens->map(fn ($i) => [
                'id' => $i->id,
                'produto_id' => $i->produto_id,
                'quantidade' => (float) $i->quantidade,
                'preco_unitario' => (float) $i->preco_unitario,
                'desconto' => (float) $i->desconto,
                'valor_total' => (float) $i->valor_total,
            ])),
        ];
    }
}
