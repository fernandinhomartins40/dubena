<?php

namespace App\Domain\Cobranca\Events;

use App\Models\Cobranca\PixCobranca;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de tempo real (P5): uma cobrança PIX foi confirmada (paga). Substitui o
 * polling de status do PIX no app (statusPix). Quando a cobrança está ligada a um
 * pedido, transmite no canal do pedido para o cliente ver "pago" na hora.
 */
class PixConfirmado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $cobrancaId;

    public int $empresaId;

    public ?int $pedidoId;

    public string $txid;

    public function __construct(PixCobranca $cobranca)
    {
        $this->cobrancaId = $cobranca->id;
        $this->empresaId = (int) $cobranca->empresa_id;
        $this->pedidoId = $cobranca->pedido_id;
        $this->txid = (string) $cobranca->txid;
    }

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        $canais = [new PrivateChannel("empresa.{$this->empresaId}.pedidos")];

        if ($this->pedidoId !== null) {
            $canais[] = new PrivateChannel("pedido.{$this->pedidoId}");
        }

        return $canais;
    }

    public function broadcastAs(): string
    {
        return 'pix.confirmado';
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        return [
            'cobranca_id' => $this->cobrancaId,
            'pedido_id' => $this->pedidoId,
            'txid' => $this->txid,
            'pago' => true,
        ];
    }
}
