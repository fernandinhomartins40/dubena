<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Bairro;


class BairrosTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
      Bairro::create([
        'id' => 1,
        'cidade_id' => '4109401',
        'grupo_id' => 1,
        'descricao' => 'CENTRO',
        ]);
  }
}
