<?php

namespace Database\Factories;

use App\Models\Grupo;
use App\Models\Regiao;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Regiao> */
class RegiaoFactory extends Factory
{
    protected $model = Regiao::class;

    public function definition(): array
    {
        return [
            'grupo_id' => Grupo::factory(),
            'descricao' => fake()->unique()->city(),
            'ativo' => true,
        ];
    }
}
