<?php

namespace App\Console\Commands;

use App\Domain\Tenant\TenantMappingImporter;
use Illuminate\Console\Command;
use JsonException;

class SaasImportarTenantMapping extends Command
{
    protected $signature = 'saas:tenant:importar {input : JSON documental de tenants/empresas/memberships/grants} {--apply : Persiste o mapeamento após preview válido}';

    protected $description = 'Converte empresas somente a partir de evidência explícita; o padrão é dry-run.';

    public function handle(TenantMappingImporter $importer): int
    {
        $path = (string) $this->argument('input');
        try {
            $plan = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $summary = $this->option('apply') ? $importer->apply($plan) : $importer->preview($plan);
        } catch (JsonException|\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info(($this->option('apply') ? 'Aplicado' : 'Dry-run válido').': '.json_encode($summary));

        return self::SUCCESS;
    }
}
