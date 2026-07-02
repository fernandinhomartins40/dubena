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
 * Evento de tempo real (L2): um pedido PENDENTE entrou na fila de distribuição.
 * A Central (painel) recebe e insere o card na bandeja sem refresh. Também é o
 * gatilho do AtribuirPedidoJob (L3) quando a empresa está em modo `auto`.
 */
class PedidoEntrouNaFila implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $pedidoId;

    public int $empresaId;

    public ?string $cliente;

    public bool $urgente;

    public function __construct(Pedido $pedido)
    {
        $this->pedidoId = $pedido->id;
        $this->empresaId = (int) $pedido->empresa_id;
        $this->cliente = $pedido->cliente?->nome;
        $this->urgente = (bool) $pedido->entrega_urgente;
    }

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("empresa.{$this->empresaId}.central")];
    }

    public function broadcastAs(): string
    {
        return 'pedido.fila';
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        return [
            'pedido_id' => $this->pedidoId,
            'cliente' => $this->cliente,
            'urgente' => $this->urgente,
        ];
    }
}
