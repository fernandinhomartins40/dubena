<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Empresa;

class EmpresasTableSeeder extends Seeder
{

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Empresa::create([
            'id' => '1',
            'grupo_id' => '1',
            'razao_social' => 'DISTRIBUIDORA DUBENA',
            'ativo' => '1',
            'nome_informal' => 'DISTRIBUIDORA DUBENA',
            'nome_fantasia' => 'DISTRIBUIDORA DUBENA',
        ]);
    }

}
