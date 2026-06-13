<?php

use Illuminate\Database\Seeder;
use App\Nfgrupofiscal;

class GrupoFiscalSeeder extends Seeder
{
  /**
  * Run the database seeds.
  *
  * @return void
  */
  public function run()
  {
    Nfgrupofiscal::create([
      'id' => '1',
      'grupo_id' => '1',
      'empresa_id' => '1',
      'descricao' => 'GRUPO DUBENA',
      'ativo' => '1'
    ]);
  }
}
