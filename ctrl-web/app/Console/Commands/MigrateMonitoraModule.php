<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

/**
 * UNIFICAÇÃO: migra o módulo Monitoramento (ex-app monitoramento-veiculos) para
 * o schema 'monitora' do mesmo PostgreSQL do ERP. Cria o schema se não existir
 * e roda as migrations de database/migrations_monitora na conexão 'monitora'.
 *
 * Uso: php artisan migrate:monitora-module [--fresh]
 */
class MigrateMonitoraModule extends Command
{
    protected $signature = 'migrate:monitora-module {--fresh : recria o schema monitora do zero}';

    protected $description = 'Roda as migrations do módulo Monitoramento no schema "monitora" do Postgres';

    public function handle()
    {
        $schema = config('database.connections.monitora.schema', 'monitora');
        $driver = config('database.connections.monitora.driver');

        if ($driver !== 'pgsql') {
            $this->warn("Conexão monitora não é pgsql ($driver) — rodando migrations sem criar schema.");
        } else {
            if ($this->option('fresh')) {
                $this->warn("Recriando schema '$schema' (DROP CASCADE)...");
                DB::connection('monitora')->statement("DROP SCHEMA IF EXISTS \"$schema\" CASCADE");
            }
            DB::connection('monitora')->statement("CREATE SCHEMA IF NOT EXISTS \"$schema\"");
            $this->info("Schema '$schema' pronto.");
        }

        // O MigrationHelper usa DB::statement/Schema sem conexão explícita.
        // Aponta-o para 'monitora' para que os ALTER caiam no schema certo
        // (e não nas tabelas homônimas de public).
        \App\Helpers\MigrationHelper::useConnection('monitora');

        $this->info('Rodando migrations do módulo Monitoramento...');
        Artisan::call('migrate', [
            '--database' => 'monitora',
            '--path'     => 'database/migrations_monitora',
            '--force'    => true,
        ], $this->getOutput());

        \App\Helpers\MigrationHelper::resetConnection();
        $this->info('Módulo Monitoramento migrado.');
        return 0;
    }
}
