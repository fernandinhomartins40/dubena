<?php

namespace Database\Seeders;

use App\Models\Saas\CidadePlataforma;
use Illuminate\Database\Seeder;

/**
 * Cidades da plataforma iniciais (P3) — idempotente. Catálogo GLOBAL de cidades
 * atendidas (centro p/ resolução por geolocalização). Guarapuava/PR é a base do
 * demo; demais cidades do PR servem de exemplo de multi-cidade.
 */
class CidadesPlataformaSeeder extends Seeder
{
    public function run(): void
    {
        $cidades = [
            ['nome' => 'Guarapuava', 'uf' => 'PR', 'cod_ibge' => 4109401, 'centro_lat' => -25.3935, 'centro_lng' => -51.4620],
            ['nome' => 'Curitiba', 'uf' => 'PR', 'cod_ibge' => 4106902, 'centro_lat' => -25.4284, 'centro_lng' => -49.2733],
            ['nome' => 'Ponta Grossa', 'uf' => 'PR', 'cod_ibge' => 4119905, 'centro_lat' => -25.0950, 'centro_lng' => -50.1619],
            ['nome' => 'Cascavel', 'uf' => 'PR', 'cod_ibge' => 4104808, 'centro_lat' => -24.9555, 'centro_lng' => -53.4552],
        ];

        foreach ($cidades as $c) {
            CidadePlataforma::query()->updateOrCreate(
                ['nome' => $c['nome'], 'uf' => $c['uf']],
                array_merge($c, ['ativo' => true]),
            );
        }
    }
}
