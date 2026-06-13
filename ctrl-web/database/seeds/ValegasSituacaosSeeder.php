<?php

use Illuminate\Database\Seeder;

class ValegasSituacaosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('valegassituacaos')->insert(['id'=>'22','descricao' => 'Impresso']);
        DB::table('valegassituacaos')->insert(['id'=>'23','descricao' => 'Baixado']);
        DB::table('valegassituacaos')->insert(['id'=>'24','descricao' => 'Cancelado']);
        DB::table('valegassituacaos')->insert(['id'=>'25','descricao' => 'Vendido']);
        DB::table('valegassituacaos')->insert(['id'=>'26','descricao' => 'Pré-Venda']);
        DB::table('valegassituacaos')->insert(['id'=>'27','descricao' => 'Impresso Pré-Venda']);
    }
}
