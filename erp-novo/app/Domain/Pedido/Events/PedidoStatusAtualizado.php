<?php

namespace App\Domain\Pedido\Events;

use App\Models\Pedido\Pedido;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de tempo real (P5): a situação de um pedido mudou. Substitui o polling
 * de status (track.tsx / Kanban) por push em tempo real.
 *
 * Transmitido em DOIS canais privados, ambos namespaced por tenant (autorização
 * em routes/channels.php — a 2ª barreira do sigilo no tempo real):
 *  - empresa.{empresaId}.pedidos → a SPA/admin atualiza listas/Kanban;
 *  - pedido.{pedidoId}           → o cliente dono/entregador acompanha o pedido.
 *
 * Implementa ShouldBroadcast: é enfileirado e transmitido pelo broadcaster
 * configurado (reverb em prod; log/null em dev/CI — sem servidor).
 */
class PedidoStatusAtualizado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $pedidoId;

    public int $empresaId;

    public ?int $situacaoId;

    public ?string $situacao;

    public ?string $efeito;

    public function __construct(Pedido $pedido)
    {
        $this->pedidoId = $pedido->id;
        $this->empresaId = (int) $pedido->empresa_id;
        $this->situacaoId = $pedido->pedidosituacao_id;
        $this->situacao = $pedido->situacao?->descricao;
        $this->efeito = $pedido->situacao?->efeito?->value;
    }

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("empresa.{$this->empresaId}.pedidos"),
            new PrivateChannel("pedido.{$this->pedidoId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pedido.status';
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        return [
            'pedido_id' => $this->pedidoId,
            'situacao_id' => $this->situacaoId,
            'situacao' => $this->situacao,
            'efeito' => $this->efeito,
        ];
    }
}
