<?php

namespace Database\Factories\Caixa;

use App\Models\Caixa\Conta;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Conta> */
class ContaFactory extends Factory
{
    protected $model = Conta::class;

    public function definition(): array
    {
        $empresa = Empresa::factory();

        return [
            'empresa_id' => $empresa,
            'grupo_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])?->grupo_id,
            'descricao' => fake()->randomElement(['Caixa Loja', 'Banco', 'Cofre']).' '.fake()->numberBetween(1, 9),
            'tipo' => 'CAIXA',
            'saldo_inicial' => 0,
            'saldo_atual' => 0,
            'fechado' => false,
            'ativo' => true,
        ];
    }
}
