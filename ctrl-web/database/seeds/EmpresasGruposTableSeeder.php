<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\EmpresasGrupo;


class EmpresasGruposTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
		EmpresasGrupo::create([
          'id' => '1',
          'descricao' => 'GRUPO DUBENA',
		  'ativo' => '1'
		]);
    }
}
