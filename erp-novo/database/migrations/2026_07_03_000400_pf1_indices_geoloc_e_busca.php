<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PF-1/PF-4 (auditoria) — índices para o matching por geolocalização e a busca
 * textual dos clientes.
 *
 *  - GEOLOC: índice composto (empresa_id, latitude, longitude) para o BOUNDING BOX
 *    (lat/lng BETWEEN …) do PedidoMobileService/MissaoService parar de varrer a
 *    tabela inteira. Portável (pgsql e sqlite).
 *  - BUSCA (só pgsql): índice GIN trigram (pg_trgm) em nome/cpf/cnpj para o
 *    `ilike '%q%'` do ClienteController não fazer full scan. Requer a extensão
 *    pg_trgm (contrib padrão). Envolto em try/catch: se a extensão não puder ser
 *    criada (permissão), a migration segue sem o índice — a busca ainda funciona,
 *    só sem a aceleração.
 *
 * Idempotente. NO-OP de trigram fora do pgsql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clientes')) {
            if (! $this->temIndice('clientes', 'clientes_empresa_lat_lng_idx')) {
                Schema::table('clientes', function ($t) {
                    $t->index(['empresa_id', 'latitude', 'longitude'], 'clientes_empresa_lat_lng_idx');
                });
            }

            $this->trigram();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clientes') && $this->temIndice('clientes', 'clientes_empresa_lat_lng_idx')) {
            Schema::table('clientes', function ($t) {
                $t->dropIndex('clientes_empresa_lat_lng_idx');
            });
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            foreach (['nome', 'cpf', 'cnpj'] as $col) {
                DB::statement("DROP INDEX IF EXISTS clientes_{$col}_trgm_idx");
            }
        }
    }

    /** Índices trigram (pgsql) para acelerar `ilike '%q%'`. */
    private function trigram(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            foreach (['nome', 'cpf', 'cnpj'] as $col) {
                DB::statement("CREATE INDEX IF NOT EXISTS clientes_{$col}_trgm_idx ON clientes USING gin ({$col} gin_trgm_ops)");
            }
        } catch (\Throwable) {
            // Sem permissão para criar a extensão: segue sem o índice trigram.
        }
    }

    private function temIndice(string $tabela, string $indice): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return DB::selectOne('SELECT 1 AS x FROM pg_indexes WHERE tablename = ? AND indexname = ?', [$tabela, $indice]) !== null;
        }
        if ($driver === 'sqlite') {
            return DB::selectOne("SELECT 1 AS x FROM sqlite_master WHERE type='index' AND name = ?", [$indice]) !== null;
        }

        return false;
    }
};
