<?php

namespace Database\Factories\Produto;

use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Produto\Produto;
use App\Models\Produto\ProdutoCondicaoPreco;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProdutoCondicaoPreco> */
class ProdutoCondicaoPrecoFactory extends Factory
{
    protected $model = ProdutoCondicaoPreco::class;

    public function definition(): array
    {
        return [
            'produto_id' => Produto::factory(),
            'condicaopagamento_id' => CondicaoPagamento::factory(),
            'gasdopovo' => false,
            'valor' => fake()->randomFloat(2, 50, 200),
        ];
    }
}
