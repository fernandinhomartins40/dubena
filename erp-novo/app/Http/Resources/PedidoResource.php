<?php

namespace App\Http\Resources;

use App\Models\Fiscal\NotaFiscal;
use App\Models\Pedido\Pedido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pedido
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
            // Concretizado (efeito CONCLUIDO) → habilita o faturamento na SPA (F03).
            'fechadoconcluido' => $this->whenLoaded('situacao', fn () => $this->situacao?->efeito?->concretiza() ? 1 : 0),
            // Já tem documento fiscal vivo (não-cancelado)? Prefere o `tem_nf` vindo
            // do withExists('notasVivas') do controller (1 query p/ a lista toda);
            // só cai no exists() por linha quando o atributo não foi pré-carregado
            // (ex.: show de um pedido isolado). Fecha o N+1 do Kanban (PF-2).
            'tem_nf' => $this->resource->getAttribute('tem_nf') !== null
                ? (bool) $this->resource->getAttribute('tem_nf')
                : NotaFiscal::query()
                    ->where('pedido_id', $this->id)->where('situacao', '!=', 'CANCELADA')->exists(),
            'setor_id' => $this->setor_id,
            'datahora' => $this->datahora?->toIso8601String(),
            'datahora_acao' => $this->datahora_acao?->toIso8601String(),
            'entrega_urgente' => (bool) $this->entrega_urgente,
            'entrega_telefone' => $this->entrega_telefone,
            'entrega_taxa' => (float) $this->entrega_taxa,
            'entrega_troco_para' => $this->entrega_troco_para !== null ? (float) $this->entrega_troco_para : null,
            // A lista, o kanban e o diálogo de pedido leem `valorvenda` (grafia
            // do legado): sem o alias os três mostravam R$ 0,00 com o valor
            // gravado. Os dois nomes viajam para não quebrar outro consumidor.
            'valorvenda' => (float) $this->valor_venda,
            'valor_venda' => (float) $this->valor_venda,
            'valor_desconto' => (float) $this->valor_desconto,
            'observacao' => $this->observacao,
            'estoque_movimentado' => (bool) $this->estoque_movimentado,
            // A forma de pagamento do pedido. A relação existia e o Resource
            // nunca a emitia — o diálogo mostrava "Condição: —" mesmo nos
            // 400.070 pedidos que têm `condicaopagamento_id` preenchido.
            'condicaopagamento_id' => $this->condicaopagamento_id,
            'condicao' => $this->whenLoaded('condicao', fn () => $this->condicao?->descricao),
            'itens' => $this->whenLoaded('itens', fn () => $this->itens->map(fn ($i) => [
                'id' => $i->id,
                'produto_id' => $i->produto_id,
                // O diálogo mostra o NOME do produto e lê `precovendatotal`
                // (grafia do legado): sem os dois, cada item aparecia como
                // "— × 1  R$ 0,00" com produto e valor gravados.
                'produto' => $i->produto?->descricao,
                'quantidade' => (float) $i->quantidade,
                'precovendatotal' => (float) $i->valor_total,
                'preco_unitario' => (float) $i->preco_unitario,
                'desconto' => (float) $i->desconto,
                'valor_total' => (float) $i->valor_total,
            ])),
        ];
    }
}
