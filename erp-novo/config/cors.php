<?php

/**
 * CORS (S-8 da auditoria) — publicado explicitamente em vez de herdar o default.
 *
 * A SPA é SERVIDA no mesmo domínio da API (/novo/app → mesmo host), então em
 * produção não há cross-origin de fato. Os apps mobile usam Bearer (sem cookie,
 * sem CORS). Este arquivo existe para a postura ser EXPLÍCITA e para o dev
 * cross-origin (Vite em :5173) funcionar sem abrir tudo:
 *  - `allowed_origins` vem de CORS_ALLOWED_ORIGINS (lista separada por vírgula);
 *    vazio = apenas o APP_URL (nada de '*').
 *  - `supports_credentials` = true porque o fluxo cookie do Sanctum precisa dele
 *    (e com credentials o '*' é PROIBIDO pelo navegador — outra razão p/ não usar).
 */

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', (string) env('APP_URL', ''))),
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins ?: [(string) env('APP_URL', 'http://localhost')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
