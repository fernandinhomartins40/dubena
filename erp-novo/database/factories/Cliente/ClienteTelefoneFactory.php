<?php

namespace Database\Factories\Cliente;

use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteTelefone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClienteTelefone> */
class ClienteTelefoneFactory extends Factory
{
    protected $model = ClienteTelefone::class;

    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'telefone' => fake()->numerify('(42) 9####-####'),
            'whatsapp' => true,
        ];
    }
}
