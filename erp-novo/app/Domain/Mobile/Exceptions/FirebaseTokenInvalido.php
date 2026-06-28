<?php

namespace App\Domain\Mobile\Exceptions;

use RuntimeException;

/**
 * ID token do Firebase inválido/expirado (F1). Mapeado para 401 no login do app.
 */
class FirebaseTokenInvalido extends RuntimeException
{
    public function __construct(string $message = 'Token de verificação inválido ou expirado.')
    {
        parent::__construct($message);
    }
}
