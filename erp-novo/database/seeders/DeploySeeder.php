<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeders que devem rodar em TODO deploy (homolog/produção) — idempotentes.
 *
 * O workflow de deploy chama SÓ este agregador para a parte "sempre roda".
 * Adicione aqui qualquer seeder futuro que precise estar presente em todo
 * deploy (catálogos, configs base, permissões) — desde que seja IDEMPOTENTE
 * (firstOrCreate/updateOrCreate).
 *
 * ## Classificação dos seeders (T3.7)
 *
 * **PRODUÇÃO-SAFE** — idempotentes e sem dado fictício; são os chamados abaixo:
 *   - `DeployAdminSeeder`      grupo + empresa-matriz + admin (senha via env, T1.1)
 *   - `RbacSeeder`             catálogo de permissões e papéis
 *   - `PlanosSeeder`           catálogo de planos SaaS
 *   - `CidadesPlataformaSeeder` catálogo de cidades
 *   - `SuperAdminSeeder`       operador da plataforma (senha via env, T1.1)
 *
 * **NUNCA EM PRODUÇÃO** — criam massa fictícia e têm gate de ambiente próprio:
 *   - `DemoGuarapuavaSeeder`   200 clientes + 500 pedidos fake de Guarapuava
 *   - `MarketplaceDemoSeeder`  revenda "Unidade Batel" demo no marketplace
 *   - `AcessoRedeDubenaSeeder` / `AcessoMigracaoSeeder` — usuários de teste
 *
 * O workflow de produção não chama os da segunda lista, E eles próprios abortam
 * em `app()->environment('production')`. Defesa em profundidade: a ausência do
 * passo no workflow é fácil de perder num merge; o gate interno, não.
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
            SuperAdminSeeder::class,  // operador da plataforma / SuperAdmin (P4)
        ]);
    }
}
