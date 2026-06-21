<?php

namespace Database\Factories\Apoio;

use App\Models\Apoio\Segmento;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Segmento> */
class SegmentoFactory extends Factory
{
    protected $model = Segmento::class;

    public function definition(): array
    {
        return [
            'grupo_id' => Grupo::factory(),
            'descricao' => fake()->unique()->word(),
            'ativo' => true,
        ];
    }
}
