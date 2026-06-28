<?php

namespace App\Domain\Mobile\Contracts;

/**
 * Transporte de push (GATE — N10). Isola o envio efetivo da notificação para o
 * EnviarPushJob ser testável sem credencial/rede. O driver real é o FCM HTTP v1
 * (OAuth2 + service account); em CI/dev usa-se o Fake (no-op + log).
 */
interface PushTransport
{
    /**
     * Envia a notificação para os tokens informados.
     *
     * @param  list<string>  $tokens
     * @param  array<string,mixed>  $dados  data payload (valores serão coeridos a string pelo FCM)
     * @return int tokens efetivamente enviados (0 quando suprimido/sem credencial)
     */
    public function enviar(array $tokens, string $titulo, string $corpo, array $dados = []): int;
}
