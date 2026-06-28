<?php

namespace App\Domain\Mobile\Drivers;

use App\Domain\Mobile\Contracts\PushTransport;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\Notification;

/**
 * Transporte de push REAL via FCM HTTP v1 (N10 — GATE).
 *
 * Substitui o endpoint legacy `fcm/send` (depreciado pelo Google) pelo FCM HTTP v1,
 * autenticado por OAuth2 com a SERVICE ACCOUNT. Usa o kreait/firebase-php (já
 * dependência, usada na verificação do phone-auth) — `createMessaging()->sendMulticast()`
 * fala o protocolo v1 e cuida do OAuth, então não reimplementamos JWT/HTTP na mão.
 *
 * Ativado por FCM_DRIVER=v1 + FIREBASE_CREDENTIALS/FIREBASE_PROJECT_ID. Sem
 * credencial, é NO-OP seguro. NÃO exercido pela suíte (gate externo) — homologação
 * com o projeto Firebase real, como KreaitFirebaseVerifier/EredeDriver.
 */
class FcmV1Transport implements PushTransport
{
    public function enviar(array $tokens, string $titulo, string $corpo, array $dados = []): int
    {
        $credenciais = config('services.firebase.credentials');
        if (empty($credenciais)) {
            Log::info('Push suprimido (FCM v1 sem credenciais)', ['tokens' => count($tokens)]);

            return 0;
        }

        try {
            $messaging = (new Factory)->withServiceAccount($credenciais)->createMessaging();

            // FCM v1 exige data como mapa string→string.
            $data = array_map(fn ($v) => is_scalar($v) ? (string) $v : (string) json_encode($v), $dados);

            $mensagem = CloudMessage::new()
                ->withNotification(Notification::create($titulo, $corpo))
                ->withData($data);

            /** @var MulticastSendReport $report */
            $report = $messaging->sendMulticast($mensagem, $tokens);

            return $report->successes()->count();
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar push (FCM v1)', ['erro' => $e->getMessage()]);

            return 0;
        }
    }
}
