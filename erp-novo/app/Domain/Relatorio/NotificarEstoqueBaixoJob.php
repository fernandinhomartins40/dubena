<?php

namespace App\Domain\Relatorio;

use App\Domain\Mobile\PushService;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job de fila (N12) — notifica os gestores de cada empresa sobre itens de estoque
 * abaixo do mínimo. Disparado pelo cron diário (notify:alertas). Assíncrono.
 *
 * **T5.0 — tratamento de falha.** Este job era `ShouldQueue` sem `$tries`,
 * `$backoff` nem `failed()`, e saía por dois `return` mudos. Duas consequências:
 * com `retry_after` de 90s e sem limite de tentativas, uma falha persistente
 * (push fora do ar) reenfileirava o job indefinidamente; e quando ele não
 * notificava, ninguém ficava sabendo — nem que era erro, nem que era ausência
 * de itens. Um alerta de estoque que falha em silêncio é pior que não existir:
 * a operação assume que "não chegou alerta" significa "está tudo certo".
 */
class NotificarEstoqueBaixoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Três tentativas: o modo de falha esperado aqui é indisponibilidade
     * temporária do serviço de push, que costuma passar. Repetir para sempre
     * não faria o alerta chegar — só encheria a fila.
     */
    public int $tries = 3;

    /** Espera crescente entre as tentativas (segundos). */
    public array $backoff = [30, 120];

    /**
     * Limite de execução. Sem ele, uma chamada de push pendurada seguraria o
     * worker e atrasaria todo o resto da fila.
     */
    public int $timeout = 120;

    public function __construct(public int $empresaId)
    {
    }

    public function handle(RelatorioService $relatorios, PushService $push): void
    {
        $itens = $relatorios->estoqueBaixo($this->empresaId);

        if ($itens === []) {
            // Caminho normal, não falha — mas registrado para que "não recebi
            // alerta" possa ser distinguido de "o job nem rodou".
            Log::info('estoque-baixo: nada a notificar', ['empresa_id' => $this->empresaId]);

            return;
        }

        $empresa = Empresa::find($this->empresaId);

        if (! $empresa) {
            // Isto é anomalia de dados: o job foi despachado para uma empresa
            // que não existe mais. Sair mudo esconderia um cron desalinhado.
            Log::warning('estoque-baixo: empresa inexistente, alerta descartado', [
                'empresa_id' => $this->empresaId,
                'itens' => count($itens),
            ]);

            return;
        }

        // Notifica os usuários (gestores) da empresa.
        $userIds = User::query()->where('empresa_id', $this->empresaId)->where('ativo', true)->pluck('id');

        if ($userIds->isEmpty()) {
            Log::warning('estoque-baixo: empresa sem usuário ativo para notificar', [
                'empresa_id' => $this->empresaId,
                'itens' => count($itens),
            ]);

            return;
        }

        $n = count($itens);
        foreach ($userIds as $userId) {
            $push->paraUsuario($userId, 'Estoque baixo', "{$n} item(ns) abaixo do mínimo.", ['tipo' => 'estoque_baixo']);
        }

        Log::info('estoque-baixo: alerta enviado', [
            'empresa_id' => $this->empresaId,
            'itens' => $n,
            'destinatarios' => $userIds->count(),
        ]);
    }

    /**
     * Esgotadas as tentativas, o alerta NÃO chegou.
     *
     * Registrar é o mínimo: sem esta linha, a única evidência ficaria na tabela
     * `failed_jobs`, que ninguém lê no dia a dia — e a operação seguiria
     * achando que o estoque está bem porque nenhum alerta apareceu.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('estoque-baixo: alerta NAO enviado apos todas as tentativas', [
            'empresa_id' => $this->empresaId,
            'erro' => $e->getMessage(),
        ]);
    }
}
