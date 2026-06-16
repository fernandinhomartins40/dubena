<?php

/*
|--------------------------------------------------------------------------
| Segredos de integração (apps publicados / oauth)
|--------------------------------------------------------------------------
|
| Estes valores PRECISAM ser lidos via config() (não env() direto): em
| produção o Laravel roda com `config:cache`, e env() fora dos arquivos de
| config/ retorna null sob cache. Referenciando aqui, o valor é "congelado"
| no cache e fica disponível em runtime.
|
*/

return [

    // Segredo PRÓPRIO usado para validar a app_key dos apps (NfwebController e
    // App\Api\SecretController). Se vazio, há fallback p/ sha1(APP_KEY) no código.
    'app_token_key' => env('APP_TOKEN_KEY'),

    // Usuário padrão para emissão de token da API (DEFAULT_USER_ID).
    'default_user_id' => env('DEFAULT_USER_ID'),

    // Chave HMAC do secret dos oauth clients (UsersController). Default 'secret'
    // para não invalidar secrets já gravados.
    'oauth_client_hmac_key' => env('OAUTH_CLIENT_HMAC_KEY', 'secret'),

];
