<?php

namespace Database\Factories\Produto;

use App\Models\Empresa;
use App\Models\Produto\Produto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Produto> */
class ProdutoFactory extends Factory
{
    protected $model = Produto::class;

    public function definition(): array
    {
        $empresa = Empresa::factory();

        return [
            'empresa_id' => $empresa,
            'grupo_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])?->grupo_id,
            'descricao' => 'Botijão '.fake()->numberBetween(2, 45).'kg',
            'ativo' => true,
            'preco_venda' => fake()->randomFloat(2, 50, 150),
        ];
    }
}
