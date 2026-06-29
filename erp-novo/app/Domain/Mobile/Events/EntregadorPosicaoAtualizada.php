<?php

namespace App\Domain\Mobile\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de tempo real (P6): o entregador se moveu. Transmitido no canal privado
 * do PEDIDO em entrega (pedido.{id}.entregador) para o cliente acompanhar a
 * posição no mapa. Um ping pode alimentar VÁRIOS pedidos ativos do entregador
 * (lote), por isso o pedidoId vem no evento (1 evento por pedido ativo).
 *
 * Não persiste o trajeto: o snapshot já está no banco e o trajeto efêmero, se
 * necessário, em Redis. Este evento é só o empurrão em tempo real.
 */
class EntregadorPosicaoAtualizada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $pedidoId,
        public float $latitude,
        public float $longitude,
        public ?float $velocidade = null,
    ) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("pedido.{$this->pedidoId}.entregador")];
    }

    public function broadcastAs(): string
    {
        return 'entregador.posicao';
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        return [
            'pedido_id' => $this->pedidoId,
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'velocidade' => $this->velocidade,
        ];
    }
}
