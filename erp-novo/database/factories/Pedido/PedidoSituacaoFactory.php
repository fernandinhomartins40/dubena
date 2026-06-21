<?php

namespace Database\Factories\Pedido;

use App\Domain\Pedido\EfeitoPedido;
use App\Models\Grupo;
use App\Models\Pedido\PedidoSituacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PedidoSituacao> */
class PedidoSituacaoFactory extends Factory
{
    protected $model = PedidoSituacao::class;

    public function definition(): array
    {
        return [
            'grupo_id' => Grupo::factory(),
            'descricao' => fake()->word(),
            'efeito' => EfeitoPedido::PENDENTE->value,
            'ativo' => true,
        ];
    }

    public function efeito(EfeitoPedido $efeito): static
    {
        return $this->state(fn () => ['efeito' => $efeito->value, 'descricao' => ucfirst(strtolower($efeito->value))]);
    }
}
