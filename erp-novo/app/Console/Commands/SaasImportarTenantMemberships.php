<?php

namespace App\Console\Commands;

use App\Domain\Tenant\TenantMembershipMappingImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;

class SaasImportarTenantMemberships extends Command
{
    protected $signature = 'saas:tenant:importar-memberships {input : JSON documental de memberships/grants} {--apply : Persiste apos dry-run valido}';

    protected $description = 'Importa memberships e grants explicitamente mapeados; por padrao apenas valida.';

    public function handle(TenantMembershipMappingImporter $importer): int
    {
        $previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('pgsql_owner');
        try {
            $plan = json_decode((string) file_get_contents((string) $this->argument('input')), true, flags: JSON_THROW_ON_ERROR);
            $summary = $this->option('apply') ? $importer->apply($plan) : $importer->preview($plan);
        } catch (JsonException|InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            DB::setDefaultConnection($previousConnection);
        }

        $this->info(($this->option('apply') ? 'Aplicado' : 'Dry-run valido').': '.json_encode($summary));

        return self::SUCCESS;
    }
}
