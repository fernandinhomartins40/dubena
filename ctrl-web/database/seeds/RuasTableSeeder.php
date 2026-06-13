<?php

use Illuminate\Database\Seeder;
use App\Rua;

class RuasTableSeeder extends Seeder
{

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Rua::create([
            'id' => 1,
            'descricao' => 'Rua XV de Novembro',
            'bairro_id' => 1,
            'cidade_id' => 4109401,
            'grupo_id' => 1,
            'empresa_id' => 1,
            'cep' => '85060-100',
            'importacaocep_id' => '0',
            'nfecompl' => 'Rua'
        ]);
    }

}
