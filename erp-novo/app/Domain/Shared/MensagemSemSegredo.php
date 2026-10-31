<?php

namespace App\Domain\Shared;

/**
 * Tira da mensagem de erro HTTP o que não pode ir para log (PIE P2-E-08).
 *
 * Falha de conexão do cliente HTTP embute a URL completa na mensagem
 * (`cURL error 28: ... for https://...&key=AIza...`). Nas APIs do Google que
 * recebem a chave na query, isso levava a chave de cada revenda — guardada
 * cifrada no banco — em claro para o `laravel.log` e o `failed_jobs`, junto
 * com o endereço do cliente que estava sendo geocodificado.
 */
final class MensagemSemSegredo
{
    /** Parâmetros de query mascarados: credencial e dado pessoal. */
    private const PARAMETROS = ['key', 'address', 'latlng', 'path', 'token', 'access_token'];

    public static function limpar(string $mensagem): string
    {
        $nomes = implode('|', self::PARAMETROS);

        return (string) preg_replace('/([?&](?:'.$nomes.')=)[^&\s"\']+/i', '$1***', $mensagem);
    }
}
