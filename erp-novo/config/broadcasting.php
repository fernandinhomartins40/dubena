<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster (P5)
    |--------------------------------------------------------------------------
    |
    | Conexão padrão de broadcasting. Em produção use 'reverb' (WebSocket nativo
    | do Laravel) para tempo real; em dev/CI 'log'/'null' não exige servidor.
    | Trocar via BROADCAST_CONNECTION — o código (eventos ShouldBroadcast) não muda.
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'log'),

    'connections' => [

        // Reverb — servidor WebSocket nativo do Laravel (instalar laravel/reverb no
        // deploy: `composer require laravel/reverb` + `php artisan reverb:start`).
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
