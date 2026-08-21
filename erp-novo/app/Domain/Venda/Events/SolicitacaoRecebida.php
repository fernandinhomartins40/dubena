<?php

namespace App\Domain\Venda\Events;

use App\Models\Venda\PedidoSolicitacao;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tempo real (F3): o campo mandou uma solicitação de venda para a Central.
 *
 * Reusa o canal `empresa.{id}.central`, que já existe e já é autorizado por
 * tenant (routes/channels.php) — o mesmo painel que recebe a fila de
 * distribuição passa a receber a fila de vendas, sem infraestrutura nova.
 */
class SolicitacaoRecebida implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $solicitacaoId;

    public int $empresaId;

    public ?string $cliente;

    public float $descontoSolicitado;

    public ?string $solicitante;

    public function __construct(PedidoSolicitacao $s)
    {
        $this->solicitacaoId = $s->id;
        $this->empresaId = (int) $s->empresa_id;
        $this->cliente = $s->cliente?->nome;
        $this->descontoSolicitado = (float) $s->desconto_solicitado;
        $this->solicitante = $s->solicitante?->name;
    }

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("empresa.{$this->empresaId}.central")];
    }

    public function broadcastAs(): string
    {
        return 'venda.solicitacao';
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        return [
            'solicitacao_id' => $this->solicitacaoId,
            'cliente' => $this->cliente,
            'solicitante' => $this->solicitante,
            'desconto_solicitado' => $this->descontoSolicitado,
        ];
    }
}
