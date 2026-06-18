<?php

use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Sanctum — SPA cookie-based auth (S1, PLANO_SPA_REACT)
|--------------------------------------------------------------------------
| O SPA React (/app) e a API admin (/api/admin) vivem no MESMO domínio do
| Laravel, então a autenticação é por SESSÃO/COOKIE (stateful). Os domínios
| abaixo são tratados como "frontend confiável" → o middleware
| EnsureFrontendRequestsAreStateful injeta sessão+CSRF nas requests do SPA.
*/

return [

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', implode(',', array_filter([
        'localhost',
        'localhost:5173',   // Vite dev server
        '127.0.0.1',
        '127.0.0.1:8000',
        '127.0.0.1:8082',   // nginx dev
        '::1',
        // domínio de produção (mesmo host do SPA): gasemcasa.com
        parse_url(env('APP_URL', ''), PHP_URL_HOST),
    ])))),

    'guard' => ['web'],

    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
