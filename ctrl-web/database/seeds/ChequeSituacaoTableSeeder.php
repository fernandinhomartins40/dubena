<?php

use Illuminate\Database\Seeder;

class ChequeSituacaoTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
      DB::table('chequesituacaos')->insert(['id' => 1, 'descricao' => 'EMITIDO', 'chequeemitido' => 1, 'chequerecebido' => 0]);
      DB::table('chequesituacaos')->insert(['id' => 2, 'descricao' => 'BAIXADO', 'chequeemitido' => 1, 'chequerecebido' => 1]);
      DB::table('chequesituacaos')->insert(['id' => 3, 'descricao' => 'INUTILIZADO', 'chequeemitido' => 1, 'chequerecebido' => 0]);
      DB::table('chequesituacaos')->insert(['id' => 4, 'descricao' => 'PENDENTE', 'chequeemitido' => 0, 'chequerecebido' => 1]);
      DB::table('chequesituacaos')->insert(['id' => 5, 'descricao' => 'DEPOSITADO', 'chequeemitido' => 0, 'chequerecebido' => 1]);
      DB::table('chequesituacaos')->insert(['id' => 6, 'descricao' => 'DEVOLVIDO', 'chequeemitido' => 0, 'chequerecebido' => 1]);
      DB::table('chequesituacaos')->insert(['id' => 7, 'descricao' => 'REAPRESENTADO', 'chequeemitido' => 0, 'chequerecebido' => 1]);
    }
}
