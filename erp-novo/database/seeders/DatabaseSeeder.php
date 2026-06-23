<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database (deploy/homologação).
     *
     * Ordem: admin/empresa base → RBAC (permissions + papéis do grupo).
     */
    public function run(): void
    {
        $this->call([
            DeployAdminSeeder::class,
            RbacSeeder::class,
        ]);
    }
}
