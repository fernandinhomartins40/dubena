<?php

namespace App\Domain\Mobile;

use App\Domain\Mobile\Jobs\EnviarPushJob;
use App\Models\Mobile\AppDevice;

/**
 * PushService (N10) — orquestra o envio de push, isolado.
 *
 * P0 (modernização): o envio passa a ser ASSÍNCRONO (despacha EnviarPushJob na
 * fila) e usa o FCM HTTP v1 no transporte (FcmV1Transport), no lugar do endpoint
 * legacy `fcm/send` (depreciado pelo Google). Este serviço resolve os tokens-alvo
 * e enfileira a entrega; o transporte real (OAuth2/service account) roda no job,
 * fora do request.
 *
 * Mantém a assinatura pública (`paraUsuario`/`enviar` → int) por compatibilidade:
 * o retorno é a quantidade de tokens ENFILEIRADOS (não a confirmação do FCM, que
 * ocorre no job). Sem tokens, retorna 0 e nada é enfileirado.
 */
class PushService
{
    /** Enfileira push para todos os devices ativos de um usuário. @return int tokens enfileirados */
    public function paraUsuario(int $userId, string $titulo, string $corpo, array $dados = []): int
    {
        $tokens = AppDevice::query()
            ->where('user_id', $userId)->where('ativo', true)
            ->whereNotNull('push_token')->pluck('push_token')->all();

        return $this->enviar($tokens, $titulo, $corpo, $dados);
    }

    /**
     * Enfileira o envio para uma lista de tokens.
     *
     * @param  list<string>  $tokens
     * @return int tokens enfileirados
     */
    public function enviar(array $tokens, string $titulo, string $corpo, array $dados = []): int
    {
        $tokens = array_values(array_filter($tokens));
        if (empty($tokens)) {
            return 0;
        }

        EnviarPushJob::dispatch($tokens, $titulo, $corpo, $dados);

        return count($tokens);
    }
}
