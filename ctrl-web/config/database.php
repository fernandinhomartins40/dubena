<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDO Fetch Style
    |--------------------------------------------------------------------------
    |
    | By default, database results will be returned as instances of the PHP
    | stdClass object; however, you may desire to retrieve records in an
    | array format for simplicity. Here you can tweak the fetch style.
    |
    */

    'fetch' => PDO::FETCH_CLASS,

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [
        // PostgreSQL — destino da migração (saída do Oracle, Fase 3).
        // Adicionado na Fase 0 para o ERP rodar em Docker sobre Postgres.
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'ctrl'),
            'username' => env('DB_USERNAME', ''),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        'oracle' => [
            'driver' => 'oracle',
            'host' => env('DB_HOST_ORACLE', 'localhost'),
            'port' => env('DB_PORT_ORACLE', '1521'),
            'database' => env('DB_DATABASE_ORACLE', 'ctrl'),
            'username' => env('DB_USERNAME_ORACLE', ''),
            'password' => env('DB_PASSWORD_ORACLE', ''),
            'charset' => 'AL32UTF8',
            'prefix' => '',
            'options' => [],
        ],

        'monitora' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_MONITORA', '127.0.0.1'),
            'port' => env('DB_PORT_MONITORA', '3306'),
            'database' => env('DB_DATABASE_MONITORA', 'forge'),
            'username' => env('DB_USERNAME_MONITORA', 'forge'),
            'password' => env('DB_PASSWORD_MONITORA', ''),
            'unix_socket' => env('DB_SOCKET_MONITORA', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],
        // FASE 5 (unificação): conexão do módulo Api (ex-api-app-gc). Driver
        // agora configurável por env — no destino unificado aponta para o mesmo
        // PostgreSQL do ERP. As tabelas espelho *_importacao permanecem por ora;
        // a eliminação/migração de dados é etapa posterior.
        'sgcm_api' => [
            'driver' => env('DB_DRIVER_API', 'pgsql'),
            'host' => env('DB_HOST_API', '127.0.0.1'),
            'port' => env('DB_PORT_API', '5432'),
            'database' => env('DB_DATABASE_API', 'ctrl'),
            'username' => env('DB_USERNAME_API', ''),
            'password' => env('DB_PASSWORD_API', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer set of commands than a typical key-value systems
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'cluster' => false,

        'default' => [
            'host' => env('REDIS_HOST', 'localhost'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => 0,
        ],

    ],

];
