<?php

namespace App\Domain\Mobile;

use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PapelSituacao;
use App\Domain\Pedido\PedidoService;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoComprovacao;
use App\Models\Pedido\PedidoOcorrencia;
use App\Models\Pedido\PedidoSituacao;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
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
        return DB::transaction(function () use ($pedido) {
            $this->exigirPendente($pedido);
            $pedido->forceFill(['datahora_acao' => now()])->save();

            return $pedido->refresh();
        });
    }

    /** Recusa da corrida — registra ocorrência e desvincula o entregador. */
    public function recusar(Pedido $pedido, int $entregadorUserId, ?string $motivo = null): PedidoOcorrencia
    {
        return DB::transaction(function () use ($pedido, $entregadorUserId, $motivo) {
            $this->exigirPendente($pedido);

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
     * Aceitar e recusar só valem para corrida em aberto (PIE P2-C-01).
     *
     * Recusar um pedido CONCLUÍDO desvinculava o entregador — e o malote confere
     * por `entregador_user_id`: o pedido em dinheiro sumia do acerto de quem tinha
     * o dinheiro na mão, com um toque, e o título seguia aberto contra o cliente
     * que já pagou. Conferido sob lock, como em `mudarSituacao`.
     */
    private function exigirPendente(Pedido $pedido): void
    {
        $atual = Pedido::query()->with('situacao:id,efeito')->lockForUpdate()->findOrFail($pedido->id);

        if ($atual->situacao?->efeito !== EfeitoPedido::PENDENTE) {
            throw ValidationException::withMessages([
                'pedido' => 'Este pedido não está mais em aberto; não é possível aceitar ou recusar.',
            ]);
        }
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

        // Caminhos gravados nesta tentativa: o disco não participa da transação,
        // então quem desfaz o arquivo quando o banco desfaz a linha somos nós.
        $gravados = [];

        try {
            return DB::transaction(function () use ($pedido, $entregadorUserId, $dados, $foto, $assinatura, &$gravados) {
                // PIE P2-C-05: o app reenvia a conclusão quando o 1º upload estoura
                // o tempo — e o 1º, muitas vezes, JÁ gravou. Antes o reenvio dava
                // 422 ("o pedido mudou de situação") e o entregador achava que a
                // entrega não estava registrada. Agora, se ESTE entregador já
                // concluiu este pedido, devolve a comprovação que existe — sem
                // gravar arquivo novo. Conferido sob lock: dois reenvios juntos
                // não passam os dois pela checagem.
                $atual = Pedido::query()->with('situacao:id,efeito')->lockForUpdate()->findOrFail($pedido->id);
                if ($atual->situacao?->efeito === EfeitoPedido::CONCLUIDO) {
                    $existente = PedidoComprovacao::query()
                        ->where('pedido_id', $pedido->id)
                        ->where('entregador_user_id', $entregadorUserId)
                        ->orderByDesc('id')
                        ->first();
                    if ($existente !== null) {
                        return $existente;
                    }
                }

                $fotoPath = $foto ? $gravados[] = $this->guardar($pedido, $foto, 'comprovacoes') : null;
                $assinaturaPath = $assinatura ? $gravados[] = $this->guardar($pedido, $assinatura, 'assinaturas') : null;

                $comprovacao = PedidoComprovacao::create([
                    'pedido_id' => $pedido->id,
                    'entregador_user_id' => $entregadorUserId,
                    'recebido_por' => $dados['recebido_por'] ?? null,
                    'foto_path' => $fotoPath,
                    'assinatura_path' => $assinaturaPath,
                    'latitude' => $dados['latitude'] ?? null,
                    'longitude' => $dados['longitude'] ?? null,
                ]);

                // Move para a 1ª situação CONCLUÍDA do grupo (reusa a máquina de estados).
                $concluida = $this->situacaoPorEfeito($pedido->grupo_id, EfeitoPedido::CONCLUIDO);
                // PIE R3-10: só conclui o que ainda está em aberto — um pedido que
                // o cliente cancelou não volta a CONCLUIDO pela mão do entregador.
                // A exceção desfaz a comprovação junto (mesma transação).
                $this->pedidos->mudarSituacao($pedido, $concluida->id, $entregadorUserId, [EfeitoPedido::PENDENTE]);

                return $comprovacao;
            });
        } catch (\Throwable $e) {
            // A linha foi desfeita; o arquivo não. Sem isto, cada recusa deixava
            // uma foto de cliente órfã no disco (dado pessoal sem dono — LGPD).
            if ($gravados !== []) {
                Storage::disk(self::DISCO)->delete($gravados);
            }
            throw $e;
        }
    }

    /**
     * Inicia a ROTA (L6): move as entregas PENDENTES do entregador para a situação
     * "Saiu para entrega" do grupo (criada se o grupo ainda não tem uma situação de
     * deslocamento). Reusa a máquina de estados — tempo real/central notificados.
     *
     * @return array{iniciados:int, pedidos:Collection<int,Pedido>}
     */
    public function iniciarRota(int $empresaId, int $entregadorUserId): array
    {
        $pedidos = Pedido::query()
            ->where('empresa_id', $empresaId)
            ->where('entregador_user_id', $entregadorUserId)
            ->whereHas('situacao', fn ($q) => $q->where('efeito', EfeitoPedido::PENDENTE->value))
            ->with('situacao')
            ->get();

        $movidos = collect();
        foreach ($pedidos->groupBy('grupo_id') as $grupoId => $doGrupo) {
            $alvo = $this->situacaoSaiuParaEntrega((int) $grupoId);
            foreach ($doGrupo as $pedido) {
                if ((int) $pedido->pedidosituacao_id === (int) $alvo->id) {
                    continue; // já está em rota — idempotente
                }
                $movidos->push($this->pedidos->mudarSituacao($pedido, $alvo->id, $entregadorUserId));
            }
        }

        return ['iniciados' => $movidos->count(), 'pedidos' => $movidos];
    }

    /**
     * Situação de DESLOCAMENTO do grupo, pelo PAPEL declarado (F3-04A).
     *
     * Antes isto procurava por `LIKE '%saiu%' OR '%rota%' OR '%caminho%'` e,
     * não achando, CRIAVA "Saiu para entrega". Funcionava para uma revenda que
     * escreveu essas palavras; para a segunda — "Em trânsito", ou qualquer
     * coisa em espanhol — a busca falhava e o sistema cadastrava uma situação
     * concorrente, invisível na configuração que o cliente montou.
     *
     * Agora a intenção vem do cadastro. Sem papel declarado, a ação FALHA com
     * uma pergunta respondível, em vez de inventar um estado: um erro que diz o
     * que configurar custa um minuto; uma situação duplicada em silêncio
     * contamina o relatório do cliente e ninguém liga uma coisa à outra.
     */
    private function situacaoSaiuParaEntrega(int $grupoId): PedidoSituacao
    {
        $alvo = PedidoSituacao::query()
            ->where('grupo_id', $grupoId)
            ->where('papel', PapelSituacao::EM_ROTA->value)
            ->where('ativo', true)
            ->orderBy('id')
            ->first();

        if ($alvo === null) {
            throw ValidationException::withMessages([
                'situacao' => 'Nenhuma situação de pedido está marcada como "Saiu para entrega". '
                    .'Configure o papel da situação em Configurações › Situações de pedido.',
            ]);
        }

        return $alvo;
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
