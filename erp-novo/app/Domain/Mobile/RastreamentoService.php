<?php

namespace App\Domain\Mobile;

use App\Domain\Mobile\Events\EntregadorPosicaoAtualizada;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Mobile\EntregadorPosicao;
use App\Models\Pedido\Pedido;

/**
 * RastreamentoService (P6) — recebe o ping de posição do app do entregador,
 * persiste o snapshot (1 linha por entregador) e PUBLICA a posição nos pedidos
 * ATIVOS atribuídos a ele (canal pedido.{id}.entregador), para o cliente ver no
 * mapa em tempo real.
 *
 * Privacidade (requisito do plano): a posição só é publicada para entregas em
 * andamento (situação de efeito PENDENTE). Pedidos concluídos/cancelados não
 * recebem mais a posição — o rastreamento "cessa" naturalmente ao encerrar.
 */
class RastreamentoService
{
    /**
     * Registra a posição do entregador e publica nos pedidos ativos dele.
     *
     * @param  array{latitude:float, longitude:float, velocidade?:float|null, direcao?:int|null}  $dados
     * @return int pedidos ativos notificados
     */
    public function registrarPing(int $empresaId, int $entregadorUserId, array $dados): int
    {
        $lat = (float) $dados['latitude'];
        $lng = (float) $dados['longitude'];

        // Snapshot (upsert por entregador).
        EntregadorPosicao::query()->updateOrCreate(
            ['entregador_user_id' => $entregadorUserId],
            [
                'empresa_id' => $empresaId,
                'latitude' => $lat,
                'longitude' => $lng,
                'velocidade' => $dados['velocidade'] ?? null,
                'direcao' => $dados['direcao'] ?? null,
                'atualizado_em' => now(),
            ],
        );

        // Pedidos ATIVOS (efeito PENDENTE) atribuídos a este entregador.
        $pedidosAtivos = Pedido::query()
            ->where('empresa_id', $empresaId)
            ->where('entregador_user_id', $entregadorUserId)
            ->whereHas('situacao', fn ($q) => $q->where('efeito', EfeitoPedido::PENDENTE->value))
            ->pluck('id');

        $velocidade = isset($dados['velocidade']) ? (float) $dados['velocidade'] : null;
        foreach ($pedidosAtivos as $pedidoId) {
            EntregadorPosicaoAtualizada::dispatch((int) $pedidoId, $lat, $lng, $velocidade);
        }

        return $pedidosAtivos->count();
    }
}
