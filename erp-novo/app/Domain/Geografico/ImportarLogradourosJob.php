<?php

namespace App\Domain\Geografico;

use App\Models\Geografico\Cidade;
use App\Models\Geografico\ImportacaoLogradouro;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Importa os logradouros de uma cidade em SEGUNDO PLANO.
 *
 * Vai para fila porque a varredura faz de dezenas a centenas de requisições à
 * base de CEP — minutos de trabalho. Segurar o request por isso daria timeout no
 * navegador e deixaria o operador sem saber se importou.
 *
 * A linha de `importacoes_logradouro` é criada ANTES da varredura, em situação
 * "processando": é ela que a tela consulta para mostrar o andamento, e é ela que
 * registra a falha caso o job morra no meio.
 */
class ImportarLogradourosJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Duas tentativas.
     *
     * A varredura é longa (centenas de requisições a um serviço de terceiros) e
     * a falha típica é de rede no meio do caminho — com uma tentativa só, isso
     * jogaria fora todo o trabalho já feito. A gravação é idempotente, então
     * repetir é seguro: a segunda passada completa o que faltou sem duplicar.
     * Não vai além de 2 para não martelar a fonte quando ela está fora do ar.
     */
    public int $tries = 2;

    /** Espera antes da retentativa: se a fonte oscilou, insistir na hora repete a falha. */
    public array $backoff = [120];

    /** Cidade grande com muito refino leva minutos; o teto evita o job eterno. */
    public int $timeout = 1800;

    public function __construct(public int $importacaoId) {}

    public function handle(ImportarLogradouros $importador): void
    {
        $registro = ImportacaoLogradouro::withoutGrupo()->find($this->importacaoId);

        if ($registro === null || $registro->situacao !== 'processando') {
            return;
        }

        $cidade = Cidade::withoutGrupo()->find($registro->cidade_id);

        if ($cidade === null) {
            $registro->forceFill(['situacao' => 'falhou', 'erro' => 'Cidade não encontrada.'])->save();

            return;
        }

        try {
            $r = $importador->varrer((string) $cidade->uf, $this->nomeParaBusca($cidade));
            $g = $importador->gravar($cidade, $r['logradouros']);

            $registro->forceFill([
                'situacao' => 'concluida',
                'ruas_criadas' => $g['ruas_criadas'],
                'bairros_criados' => $g['bairros_criados'],
                'ruas_atualizadas' => $g['ruas_atualizadas'],
                'consultas' => $r['consultas'],
                'termos_truncados' => $r['truncados'],
            ])->save();
        } catch (\Throwable $e) {
            // Registrar a falha é o ponto: sem isso a tela mostraria
            // "processando" para sempre e ninguém saberia o que houve.
            Log::error('logradouros: importação falhou', [
                'importacao_id' => $this->importacaoId,
                'cidade_id' => $cidade->id,
                'erro' => $e->getMessage(),
            ]);

            // Só marca 'falhou' na ÚLTIMA tentativa: marcar antes faria a guarda
            // do topo (situacao !== 'processando') abortar a retentativa, e o
            // $tries = 2 viraria decoração.
            if ($this->attempts() >= $this->tries) {
                $registro->forceFill([
                    'situacao' => 'falhou',
                    'erro' => mb_substr($e->getMessage(), 0, 500),
                ])->save();
            }

            throw $e;
        }
    }

    /** A base de CEP conhece o MUNICÍPIO; distrito vem entre parênteses. */
    private function nomeParaBusca(Cidade $cidade): string
    {
        if (preg_match('/\(([^)]+)\)/', (string) $cidade->descricao, $m) === 1) {
            return trim($m[1]);
        }

        return (string) $cidade->descricao;
    }
}
