<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Ultimaposicao;

class UltimaposicaosTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
      Ultimaposicao::create([
                      'id' => '1',
                      'veiculo_id' => '61',
                      'grupo_id' => '2',
                      'empresa_id' => '2',
                      'latitude' => -25.41316,
                      'longitude' => -49.228492,
                      'datahora' => '2011-04-14 07:16:18'
      ]);
      Ultimaposicao::create([
                      'id' => '2',
                      'veiculo_id' => '62',
                      'grupo_id' => '2',
                      'empresa_id' => '2',
                      'latitude' => -25.343596,
                      'longitude' => -51.491137,
                      'datahora' => '2011-04-14 07:16:18'
      ]);
      Ultimaposicao::create([
                      'id' => '3',
                      'veiculo_id' => '63',
                      'grupo_id' => '2',
                      'empresa_id' => '2',
                      'latitude' => -25.36037,
                      'longitude' => -51.47422,
                      'datahora' => '2011-04-14 07:16:18'
      ]);
    }
}
