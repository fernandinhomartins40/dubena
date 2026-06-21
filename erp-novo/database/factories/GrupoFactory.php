<?php

namespace Database\Factories;

use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Grupo> */
class GrupoFactory extends Factory
{
    protected $model = Grupo::class;

    public function definition(): array
    {
        return [
            'descricao' => fake()->company().' (Grupo)',
            'ativo' => true,
        ];
    }
}
