<?php

namespace Database\Factories\Geografico;

use App\Models\Estado;
use App\Models\Geografico\Cidade;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cidade> */
class CidadeFactory extends Factory
{
    protected $model = Cidade::class;

    public function definition(): array
    {
        // Garante o Estado referenciado pela FK uf (idempotente).
        Estado::firstOrCreate(['uf' => 'SP'], ['descricao' => 'São Paulo', 'cod_ibge' => 35]);

        return [
            'grupo_id' => Grupo::factory(),
            'descricao' => fake()->city(),
            'uf' => 'SP',
            'cod_ibge' => fake()->numberBetween(1000000, 5999999),
            'ativo' => true,
        ];
    }
}
