<?php

namespace App\Domain\Mobile\Contracts;

use App\Domain\Mobile\Exceptions\FirebaseTokenInvalido;

/**
 * Verificador do ID token do Firebase (F1 — GATE phone-auth).
 *
 * Isola a checagem da assinatura/validade do token emitido pelo Firebase quando o
 * cliente confirma o SMS no app. O AppAuthController depende só deste contrato, então
 * o login é testável sem credenciais (FakeFirebaseVerifier no CI/homolog) e usa o
 * driver real (kreait/firebase-php) em produção.
 */
interface FirebaseVerifier
{
    /**
     * Verifica o ID token e devolve a identidade do telefone.
     *
     * @return array{uid:string, phone:?string}
     *
     * @throws FirebaseTokenInvalido quando inválido/expirado.
     */
    public function verify(string $idToken): array;
}
