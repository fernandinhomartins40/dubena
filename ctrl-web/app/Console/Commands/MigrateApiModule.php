<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

/**
 * FASE 5/6: migra o módulo Api (ex-api-app-gc) para o schema 'api' do mesmo
 * PostgreSQL do ERP. Cria o schema se não existir e roda as migrations de
 * database/migrations_api na conexão sgcm_api.
 *
 * Uso: php artisan migrate:api-module [--fresh]
 */
class MigrateApiModule extends Command
{
    protected $signature = 'migrate:api-module {--fresh : recria o schema api do zero}';

    protected $description = 'Roda as migrations do módulo Api no schema "api" do Postgres';

    public function handle()
    {
        $schema = config('database.connections.sgcm_api.schema', 'api');

        // Só faz sentido para o driver pgsql (no destino unificado).
        $driver = config('database.connections.sgcm_api.driver');
        if ($driver !== 'pgsql') {
            $this->warn("Conexão sgcm_api não é pgsql ($driver) — rodando migrations sem criar schema.");
        } else {
            if ($this->option('fresh')) {
                $this->warn("Recriando schema '$schema' (DROP CASCADE)...");
                DB::connection('sgcm_api')->statement("DROP SCHEMA IF EXISTS \"$schema\" CASCADE");
            }
            DB::connection('sgcm_api')->statement("CREATE SCHEMA IF NOT EXISTS \"$schema\"");
            $this->info("Schema '$schema' pronto.");
        }

        $this->info('Rodando migrations do módulo Api...');
        Artisan::call('migrate', [
            '--database' => 'sgcm_api',
            '--path'     => 'database/migrations_api',
            '--force'    => true,
        ], $this->getOutput());

        $this->info('Módulo Api migrado.');
        return 0;
    }
}
