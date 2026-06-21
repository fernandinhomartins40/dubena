<?php

namespace App\Etl\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Contexto passado a cada Migrator: conexões legado/novo, modo dry-run, logger.
 */
final class MigrationContext
{
    public function __construct(
        public readonly bool $dryRun = false,
        public readonly string $conexaoLegado = 'legado',
        public readonly string $conexaoNova = 'pgsql',
    ) {
    }

    /** Conexão de LEITURA do banco legado. */
    public function legado(): ConnectionInterface
    {
        return DB::connection($this->conexaoLegado);
    }

    /** Conexão de ESCRITA do banco novo (default da app). */
    public function novo(): ConnectionInterface
    {
        return DB::connection();
    }
}
