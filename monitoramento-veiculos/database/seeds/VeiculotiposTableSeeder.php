<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Veiculotipo;


class VeiculotiposTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
		Veiculotipo::create([
                    'id' => '1',
                    'descricao' => 'CARRO',
                    'velocidade_maxima' => 110
		]);
                Veiculotipo::create([
                    'id' => '2',
                    'descricao' => 'CAMINHÃO',
                    'velocidade_maxima' => 110
		]);
                Veiculotipo::create([
                    'id' => '3',
                    'descricao' => 'CAMINHONETE',
                    'velocidade_maxima' => 110
		]);
                Veiculotipo::create([
                    'id' => '4',
                    'descricao' => 'MOTO',
                    'velocidade_maxima' => 110
		]);
                Veiculotipo::create([
                    'id' => '5',
                    'descricao' => 'OUTRO',
                    'velocidade_maxima' => 110
		]);
    }
}
