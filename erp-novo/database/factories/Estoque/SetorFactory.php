<?php

namespace Database\Factories\Estoque;

use App\Models\Empresa;
use App\Models\Estoque\Setor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Setor> */
class SetorFactory extends Factory
{
    protected $model = Setor::class;

    public function definition(): array
    {
        $empresa = Empresa::factory();

        return [
            'empresa_id' => $empresa,
            'grupo_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])?->grupo_id,
            'descricao' => fake()->randomElement(['Depósito', 'Loja', 'Veículo']).' '.fake()->numberBetween(1, 9),
            'ativo' => true,
        ];
    }
}
