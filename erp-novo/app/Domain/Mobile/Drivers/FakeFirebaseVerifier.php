<?php

namespace App\Domain\Mobile\Drivers;

use App\Domain\Mobile\Contracts\FirebaseVerifier;
use App\Domain\Mobile\Exceptions\FirebaseTokenInvalido;

/**
 * Verificador FAKE do Firebase (F1 — CI/homolog). Não faz rede: aceita tokens no
 * formato "fake:+5542999999999" e devolve o telefone; qualquer outro valor é inválido.
 * Permite testar todo o login de cliente sem credenciais do Firebase.
 */
class FakeFirebaseVerifier implements FirebaseVerifier
{
    public function verify(string $idToken): array
    {
        if (! str_starts_with($idToken, 'fake:')) {
            throw new FirebaseTokenInvalido;
        }

        $phone = trim(substr($idToken, strlen('fake:')));
        if ($phone === '') {
            throw new FirebaseTokenInvalido('Token fake sem telefone.');
        }

        return ['uid' => 'fake-'.md5($phone), 'phone' => $phone];
    }
}
