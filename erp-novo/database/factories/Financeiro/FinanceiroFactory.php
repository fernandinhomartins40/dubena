<?php

namespace Database\Factories\Financeiro;

use App\Models\Empresa;
use App\Models\Financeiro\Financeiro;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Financeiro> */
class FinanceiroFactory extends Factory
{
    protected $model = Financeiro::class;

    public function definition(): array
    {
        $empresa = Empresa::factory();

        return [
            'empresa_id' => $empresa,
            'grupo_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])?->grupo_id,
            'pagarreceber' => 'R',
            'descricao' => 'Título '.fake()->numberBetween(1, 9999),
            'valor' => fake()->randomFloat(2, 50, 2000),
            'data_emissao' => now()->toDateString(),
            'agrupamento_status' => 0,
            'cancelado' => false,
        ];
    }
}
