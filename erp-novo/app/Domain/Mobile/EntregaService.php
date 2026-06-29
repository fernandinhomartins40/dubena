<?php

namespace App\Domain\Mobile;

use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoComprovacao;
use App\Models\Pedido\PedidoOcorrencia;
use App\Models\Pedido\PedidoSituacao;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * EntregaService (P7) — ciclo da entrega pelo app do entregador. Reusa o
 * PedidoService para mudanças de situação (que já emitem o evento de tempo real
 * do P5) — nenhuma regra de venda é reescrita aqui. Foco: registrar ocorrências,
 * gravar a comprovação (foto/assinatura em storage privado) e CONCLUIR a entrega.
 *
 * Aceite/recusa: como a atribuição do pedido ao entregador já existe
 * (pedidos.entregador_user_id), "aceitar" é uma confirmação leve; "recusar" gera
 * uma ocorrência e desvincula o entregador (volta para a fila de atribuição).
 */
class EntregaService
{
    /** Disco privado p/ fotos/assinaturas (mesmo do certificado A1). */
    private const DISCO = 'local';

    public function __construct(private PedidoService $pedidos) {}

    /** Aceite da corrida — confirmação (sem efeito patrimonial). */
    public function aceitar(Pedido $pedido): Pedido
    {
        // No-op de estado: a atribuição já existe. Mantido como ponto de extensão
        // (ex.: marcar datahora_acao) e simetria com recusar().
        $pedido->forceFill(['datahora_acao' => now()])->save();

        return $pedido->refresh();
    }

    /** Recusa da corrida — registra ocorrência e desvincula o entregador. */
    public function recusar(Pedido $pedido, int $entregadorUserId, ?string $motivo = null): PedidoOcorrencia
    {
        return DB::transaction(function () use ($pedido, $entregadorUserId, $motivo) {
            $ocorrencia = PedidoOcorrencia::create([
                'pedido_id' => $pedido->id,
                'entregador_user_id' => $entregadorUserId,
                'tipo' => 'recusou',
                'descricao' => $motivo,
            ]);

            // Volta para a fila: sem entregador atribuído.
            $pedido->forceFill(['entregador_user_id' => null])->save();

            return $ocorrencia;
        });
    }

    /**
     * Registra uma ocorrência (com foto opcional).
     *
     * @param  array{tipo:string, descricao?:?string, latitude?:?float, longitude?:?float}  $dados
     */
    public function registrarOcorrencia(Pedido $pedido, int $entregadorUserId, array $dados, ?UploadedFile $foto = null): PedidoOcorrencia
    {
        $fotoPath = $foto ? $this->guardar($pedido, $foto, 'ocorrencias') : null;

        return PedidoOcorrencia::create([
            'pedido_id' => $pedido->id,
            'entregador_user_id' => $entregadorUserId,
            'tipo' => $dados['tipo'],
            'descricao' => $dados['descricao'] ?? null,
            'foto_path' => $fotoPath,
            'latitude' => $dados['latitude'] ?? null,
            'longitude' => $dados['longitude'] ?? null,
        ]);
    }

    /**
     * Conclui a entrega: grava a comprovação (foto/assinatura) e move o pedido para
     * a situação CONCLUÍDA do grupo (via PedidoService — baixa estoque/financeiro +
     * emite o evento de tempo real). Foto OU assinatura é exigida como prova.
     *
     * @param  array{recebido_por?:?string, latitude?:?float, longitude?:?float}  $dados
     */
    public function concluir(Pedido $pedido, int $entregadorUserId, array $dados, ?UploadedFile $foto = null, ?UploadedFile $assinatura = null): PedidoComprovacao
    {
        if ($foto === null && $assinatura === null) {
            throw ValidationException::withMessages(['comprovacao' => 'Envie foto ou assinatura como comprovação.']);
        }

        return DB::transaction(function () use ($pedido, $entregadorUserId, $dados, $foto, $assinatura) {
            $comprovacao = PedidoComprovacao::create([
                'pedido_id' => $pedido->id,
                'entregador_user_id' => $entregadorUserId,
                'recebido_por' => $dados['recebido_por'] ?? null,
                'foto_path' => $foto ? $this->guardar($pedido, $foto, 'comprovacoes') : null,
                'assinatura_path' => $assinatura ? $this->guardar($pedido, $assinatura, 'assinaturas') : null,
                'latitude' => $dados['latitude'] ?? null,
                'longitude' => $dados['longitude'] ?? null,
            ]);

            // Move para a 1ª situação CONCLUÍDA do grupo (reusa a máquina de estados).
            $concluida = $this->situacaoPorEfeito($pedido->grupo_id, EfeitoPedido::CONCLUIDO);
            $this->pedidos->mudarSituacao($pedido, $concluida->id, $entregadorUserId);

            return $comprovacao;
        });
    }

    /** Guarda um upload no disco privado, em pasta por tenant/pedido. */
    private function guardar(Pedido $pedido, UploadedFile $arquivo, string $pasta): string
    {
        $dir = "entregas/{$pedido->empresa_id}/{$pedido->id}/{$pasta}";

        return Storage::disk(self::DISCO)->putFile($dir, $arquivo);
    }

    private function situacaoPorEfeito(int $grupoId, EfeitoPedido $efeito): PedidoSituacao
    {
        $situacao = PedidoSituacao::query()
            ->where('grupo_id', $grupoId)
            ->where('efeito', $efeito->value)
            ->where('ativo', true)
            ->orderBy('id')->first();

        if (! $situacao) {
            throw ValidationException::withMessages(['situacao' => "Nenhuma situação de efeito {$efeito->value} configurada."]);
        }

        return $situacao;
    }
}
