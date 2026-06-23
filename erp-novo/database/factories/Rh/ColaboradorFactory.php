<?php

namespace Database\Factories\Rh;

use App\Models\Empresa;
use App\Models\Rh\Colaborador;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Colaborador>
 */
class ColaboradorFactory extends Factory
{
    protected $model = Colaborador::class;

    public function definition(): array
    {
        $empresa = Empresa::factory();

        return [
            'empresa_id' => $empresa,
            'grupo_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])?->grupo_id,
            'nome' => fake()->name(),
            'cpf' => fake()->numerify('###########'),
            'data_admissao' => fake()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
            'entregador' => false,
            'ativo' => true,
        ];
    }
}
