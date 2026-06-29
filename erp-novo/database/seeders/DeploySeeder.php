<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeders que devem rodar em TODO deploy (homolog/produção) — idempotentes.
 *
 * O workflow de deploy chama SÓ este agregador para a parte "sempre roda".
 * Adicione aqui qualquer seeder futuro que precise estar presente em todo
 * deploy (catálogos, configs base, permissões) — desde que seja IDEMPOTENTE
 * (firstOrCreate/updateOrCreate). Massa de demonstração NÃO entra aqui;
 * fica no DemoGuarapuavaSeeder, que só roda com banco vazio (1º deploy).
 */
class DeploySeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DeployAdminSeeder::class, // grupo/empresa/admin base
            RbacSeeder::class,        // catálogo de permissões + papéis (chaves novas entram aqui)
            PlanosSeeder::class,      // catálogo de planos SaaS + recursos (P2)
            CidadesPlataformaSeeder::class, // catálogo de cidades da plataforma (P3)
        ]);
    }
}
