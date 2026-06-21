<?php

namespace Database\Factories\Cliente;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cliente> */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        $empresa = Empresa::factory();

        return [
            'empresa_id' => $empresa,
            'grupo_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])?->grupo_id,
            'nome' => fake()->name(),
            'cliente' => true,
            'fornecedor' => false,
            'transportador' => false,
            'ativo' => true,
        ];
    }
}
