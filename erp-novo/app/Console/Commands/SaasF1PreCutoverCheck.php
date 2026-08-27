<?php

namespace App\Console\Commands;

use App\Domain\Tenant\TableClassificationManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Portao preparatorio de F1. Nao habilita rotas, nao cria vinculos e nao
 * substitui a recertificacao RLS/worker exigida pelo gate da fase.
 */
class SaasF1PreCutoverCheck extends Command
{
    protected $signature = 'saas:f1:pre-cutover-check
        {--connection=pgsql_owner : Conexao PostgreSQL que enxerga o schema efetivo}';

    protected $description = 'Verifica pre-requisitos documentais e estruturais antes do cutover SaaS de F1.';

    public function handle(): int
    {
        $connection = DB::connection((string) $this->option('connection'));
        if ($connection->getDriverName() !== 'pgsql') {
            $this->error('O pre-cutover de F1 exige PostgreSQL efetivo; SQLite/MySQL nao comprovam schema, funcoes ou RLS.');

            return self::FAILURE;
        }

        try {
            $tables = $connection->select(
                "select c.relname as name from pg_class c join pg_namespace n on n.oid = c.relnamespace where n.nspname = 'public' and c.relkind in ('r', 'p') order by c.relname"
            );
            (new TableClassificationManifest((array) config('saas_table_classification')))
                ->assertComplete(array_map(fn (object $table) => $table->name, $tables));
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $missingFunctions = [];
        foreach ([
            'app_current_tenant_account_id()',
            'app_current_tenant_membership_id()',
            'app_tenant_can_read(bigint,bigint)',
            'app_tenant_can_operate(bigint,bigint)',
        ] as $function) {
            if ($connection->selectOne('select to_regprocedure(?) as procedure', [$function])->procedure === null) {
                $missingFunctions[] = $function;
            }
        }

        $unmappedCompanies = (int) $connection->scalar(
            "select count(*) from empresas e left join tenant_companies tc on tc.empresa_id = e.id and tc.status = 'APPROVED' where tc.id is null"
        );
        $unresolvedOwnership = (int) $connection->scalar(
            "select count(*) from empresas where ownership_status is distinct from 'OWNERSHIP_APPROVED'"
        );
        $companyTablesWithoutKey = $this->companyTablesWithoutTenantKey($connection);

        if ($missingFunctions !== []) {
            $this->error('Funcoes RLS canonicas ausentes: '.implode(', ', $missingFunctions));
        }
        if ($companyTablesWithoutKey !== []) {
            $this->error('Tabelas COMPANY sem tenant_account_id: '.implode(', ', $companyTablesWithoutKey));
        }
        if ($unmappedCompanies > 0 || $unresolvedOwnership > 0) {
            $this->error("Mapeamento documental ainda pendente: {$unmappedCompanies} empresa(s) sem TenantCompany APPROVED; {$unresolvedOwnership} sem ownership aprovado.");
        }

        if ($missingFunctions !== [] || $companyTablesWithoutKey !== [] || $unmappedCompanies > 0 || $unresolvedOwnership > 0) {
            $this->error('PRE-CUTOVER F1 BLOQUEADO — execute somente o dry-run/importador documental; nao habilite tenant.saas.');

            return self::FAILURE;
        }

        $this->warn('Pre-requisitos estruturais aprovados. O gate F1 ainda exige policies canonicas com role runtime, negações cruzadas e worker sequencial.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function companyTablesWithoutTenantKey($connection): array
    {
        $companyTables = array_keys(array_filter(
            (array) config('saas_table_classification'),
            fn (array $entry) => $entry['class'] === 'COMPANY',
        ));
        $existing = $connection->select(
            "select table_name from information_schema.columns where table_schema = 'public' and column_name = 'tenant_account_id'"
        );
        $withKey = array_flip(array_map(fn (object $column) => $column->table_name, $existing));

        return array_values(array_filter($companyTables, fn (string $table) => ! isset($withKey[$table])));
    }
}
