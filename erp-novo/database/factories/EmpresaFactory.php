<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Empresa> */
class EmpresaFactory extends Factory
{
    protected $model = Empresa::class;

    public function definition(): array
    {
        return [
            'grupo_id' => Grupo::factory(),
            'razao_social' => fake()->company(),
            'nome_fantasia' => fake()->company(),
            'nome_informal' => fake()->company(),
            'cnpj' => fake()->numerify('##############'),
            'uf' => 'SP',
            'ativo' => true,
        ];
    }
}
