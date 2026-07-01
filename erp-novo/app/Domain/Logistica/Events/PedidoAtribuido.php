<?php

namespace App\Domain\Logistica\Events;

use App\Models\Pedido\Pedido;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de tempo real (L2): um pedido foi atribuído/redistribuído a um entregador.
 * Emitido no canal da CENTRAL (para o painel atualizar a fila/mapa) e no canal do
 * pedido (o cliente já "sabe" que saiu para entrega). Espelha o padrão de
 * PedidoStatusAtualizado (ShouldBroadcast → reverb em prod, log/null em dev/CI).
 */
class PedidoAtribuido implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $pedidoId;

    public int $empresaId;

    public ?int $deEntregadorUserId;

    public ?int $paraEntregadorUserId;

    public ?string $entregadorNome;

    public bool $automatico;

    public function __construct(Pedido $pedido, ?int $de, bool $automatico)
    {
        $this->pedidoId = $pedido->id;
        $this->empresaId = (int) $pedido->empresa_id;
        $this->deEntregadorUserId = $de;
        $this->paraEntregadorUserId = $pedido->entregador_user_id;
        $this->entregadorNome = $pedido->entregador?->name;
        $this->automatico = $automatico;
    }

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("empresa.{$this->empresaId}.central"),
            new PrivateChannel("pedido.{$this->pedidoId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pedido.atribuido';
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        return [
            'pedido_id' => $this->pedidoId,
            'de_entregador_user_id' => $this->deEntregadorUserId,
            'para_entregador_user_id' => $this->paraEntregadorUserId,
            'entregador_nome' => $this->entregadorNome,
            'automatico' => $this->automatico,
        ];
    }
}
