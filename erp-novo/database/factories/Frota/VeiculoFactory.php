<?php

namespace Database\Factories\Frota;

use App\Models\Empresa;
use App\Models\Frota\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Veiculo>
 */
class VeiculoFactory extends Factory
{
    protected $model = Veiculo::class;

    public function definition(): array
    {
        $empresa = Empresa::factory();

        return [
            'empresa_id' => $empresa,
            'grupo_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])?->grupo_id,
            'placa' => strtoupper(fake()->bothify('???#?##')),
            'descricao' => 'Veículo '.fake()->randomNumber(3),
            'km_atual' => fake()->numberBetween(10000, 200000),
            'ativo' => true,
        ];
    }
}
