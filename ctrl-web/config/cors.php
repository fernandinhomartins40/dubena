<?php

/*
|--------------------------------------------------------------------------
| CORS — necessário para o SPA enviar cookies (credentials) à API/CSRF.
|--------------------------------------------------------------------------
| Em produção o SPA e a API são same-domain (gasemcasa.com/app + /api/admin),
| então CORS quase não atua. Mas o `supports_credentials=true` é obrigatório
| p/ o Sanctum SPA (envio do cookie de sessão). Em DEV, o Vite roda em :5173
| e precisa das origens liberadas.
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', implode(',', array_filter([
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:8082',
        env('APP_URL'),
    ])))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
