<?php

namespace App\Domain\Mobile\Drivers;

use App\Domain\Mobile\Contracts\PushTransport;
use Illuminate\Support\Facades\Log;

/**
 * Transporte de push FAKE (CI/homolog/dev — sem credencial FCM). Não faz rede:
 * apenas registra a intenção e devolve 0 enviados. Espelha o comportamento legado
 * "sem FCM server key → push suprimido", agora isolado num driver testável.
 */
class FakePushTransport implements PushTransport
{
    public function enviar(array $tokens, string $titulo, string $corpo, array $dados = []): int
    {
        Log::info('Push suprimido (FakePushTransport — sem credencial FCM)', [
            'tokens' => count($tokens),
            'titulo' => $titulo,
        ]);

        return 0;
    }
}
