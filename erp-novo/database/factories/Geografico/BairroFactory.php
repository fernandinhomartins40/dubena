<?php

namespace Database\Factories\Geografico;

use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Bairro> */
class BairroFactory extends Factory
{
    protected $model = Bairro::class;

    public function definition(): array
    {
        $cidade = Cidade::factory();

        return [
            'cidade_id' => $cidade,
            'grupo_id' => fn (array $attrs) => Cidade::find($attrs['cidade_id'])?->grupo_id,
            'descricao' => fake()->streetName(),
            'ativo' => true,
        ];
    }
}
