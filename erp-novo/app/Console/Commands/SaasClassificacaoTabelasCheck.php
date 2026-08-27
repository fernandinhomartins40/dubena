<?php

namespace App\Console\Commands;

use App\Domain\Tenant\TableClassificationManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LogicException;

class SaasClassificacaoTabelasCheck extends Command
{
    protected $signature = 'saas:classificacao-tabelas:check
        {--connection=pgsql_owner : Conexao PostgreSQL que enxerga o catalogo efetivo}';

    protected $description = 'Falha se uma tabela efetiva nao tiver classificacao, owner e justificativa revisaveis.';

    public function handle(): int
    {
        $connection = DB::connection((string) $this->option('connection'));
        if ($connection->getDriverName() !== 'pgsql') {
            $this->error('O gate de classificacao exige PostgreSQL efetivo; SQLite/MySQL nao comprovam o catalogo SaaS.');

            return self::FAILURE;
        }

        $tables = $connection->select(
            "select c.relname as name from pg_class c join pg_namespace n on n.oid = c.relnamespace where n.nspname = 'public' and c.relkind in ('r', 'p') order by c.relname"
        );

        try {
            (new TableClassificationManifest((array) config('saas_table_classification')))
                ->assertComplete(array_map(fn (object $table) => $table->name, $tables));
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Classificacao SaaS completa e aderente ao catalogo PostgreSQL efetivo.');

        return self::SUCCESS;
    }
}
