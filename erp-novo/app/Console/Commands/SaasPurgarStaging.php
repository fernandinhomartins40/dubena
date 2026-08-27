<?php

namespace App\Console\Commands;

use App\Models\Saas\TenantStagingArtifact;
use Illuminate\Console\Command;

class SaasPurgarStaging extends Command
{
    protected $signature = 'saas:staging:purgar {--dry-run : Apenas informa os artefatos expirados}';

    protected $description = 'Remove payloads de staging expirados, preservando a trilha de purge.';

    public function handle(): int
    {
        $expired = TenantStagingArtifact::query()->whereNull('purged_at')->where('expires_at', '<=', now());
        $count = $expired->count();

        if ($this->option('dry-run')) {
            $this->info("{$count} artefato(s) expirado(s).");

            return self::SUCCESS;
        }

        $expired->eachById(function (TenantStagingArtifact $artifact): void {
            $artifact->forceFill(['payload' => [], 'purged_at' => now()])->save();
        });
        $this->info("{$count} artefato(s) purgado(s).");

        return self::SUCCESS;
    }
}
