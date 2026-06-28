<?php

namespace App\Domain\Mobile\Drivers;

use App\Domain\Mobile\Contracts\FirebaseVerifier;
use App\Domain\Mobile\Exceptions\FirebaseTokenInvalido;
use Kreait\Firebase\Factory;
use Throwable;

/**
 * Verificador REAL do Firebase (F1 — GATE). Valida a assinatura/expiração do ID token
 * via kreait/firebase-php contra o projeto configurado e extrai o telefone (claim
 * `phone_number`). Ativado por FIREBASE_DRIVER=kreait + FIREBASE_CREDENTIALS/PROJECT_ID.
 * NÃO exercido pela suíte (gate externo) — homologação com o projeto Firebase real.
 */
class KreaitFirebaseVerifier implements FirebaseVerifier
{
    public function verify(string $idToken): array
    {
        try {
            $factory = (new Factory)
                ->withServiceAccount(config('services.firebase.credentials'));

            $verified = $factory->createAuth()->verifyIdToken($idToken);

            $uid = (string) $verified->claims()->get('sub');
            $phone = $verified->claims()->get('phone_number');

            return ['uid' => $uid, 'phone' => $phone !== null ? (string) $phone : null];
        } catch (Throwable $e) {
            throw new FirebaseTokenInvalido($e->getMessage());
        }
    }
}
