<?php

namespace App\Domain\Mobile;

use App\Models\Mobile\AppDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PushService (N10) — envio de push via FCM, isolado. Sem credencial (dev/CI),
 * apenas registra a intenção; em produção envia via HTTP ao FCM.
 */
class PushService
{
    /** Envia push a todos os devices ativos de um usuário. @return int devices alcançados */
    public function paraUsuario(int $userId, string $titulo, string $corpo, array $dados = []): int
    {
        $tokens = AppDevice::query()
            ->where('user_id', $userId)->where('ativo', true)
            ->whereNotNull('push_token')->pluck('push_token')->all();

        return $this->enviar($tokens, $titulo, $corpo, $dados);
    }

    /** @param list<string> $tokens */
    public function enviar(array $tokens, string $titulo, string $corpo, array $dados = []): int
    {
        if (empty($tokens)) {
            return 0;
        }

        $serverKey = config('services.fcm.server_key');
        if (empty($serverKey)) {
            // Sem credencial (dev/homolog/CI) — não envia, só loga.
            Log::info('Push suprimido (sem FCM server key)', ['tokens' => count($tokens), 'titulo' => $titulo]);

            return 0;
        }

        try {
            Http::withToken($serverKey)->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $tokens,
                'notification' => ['title' => $titulo, 'body' => $corpo],
                'data' => $dados,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar push', ['erro' => $e->getMessage()]);

            return 0;
        }

        return count($tokens);
    }
}
