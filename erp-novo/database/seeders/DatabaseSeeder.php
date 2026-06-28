<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // NÃO usar WithoutModelEvents: o preenchimento automático de empresa_id/grupo_id
    // (trait BelongsToTenant) depende do evento `creating`. Desativá-lo faria as
    // tabelas tenant-scoped nascerem com empresa_id NULL.

    /**
     * Seed the application's database.
     *
     * - PRODUÇÃO: só o essencial — admin/empresa base + RBAC (sem massa demo).
     * - DEMAIS (local/homolog): massa completa de demonstração (Guarapuava),
     *   que internamente já roda DeployAdminSeeder + RbacSeeder.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->call([
                DeployAdminSeeder::class,
                RbacSeeder::class,
                PlanosSeeder::class,
            ]);

            return;
        }

        $this->call(DemoGuarapuavaSeeder::class);
    }
}
