<?php

use Illuminate\Database\Seeder;
use App\Contamovimentotipo;

class ContamovimentotiposSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
		Contamovimentotipo::create([
		  'id' => '1',
		  'grupo_id' => '1',
		  'descricao' => 'DINHEIRO',
		  'ativo' => '1',
		  'cheque' => '0',
		  'valegas' => '0',
		  'convenio' => '0',
		  'pagarreceber' => 'A',
		  'cartao' => '0'
		]);
		Contamovimentotipo::create([
		  'id' => '2',
		  'grupo_id' => '1',
		  'descricao' => 'CHEQUE',
		  'ativo' => '1',
		  'cheque' => '1',
		  'valegas' => '0',
		  'convenio' => '0',
		  'pagarreceber' => 'A',
		  'cartao' => '0'
		]);		
		Contamovimentotipo::create([
		  'id' => '3',
		  'grupo_id' => '1',
		  'descricao' => 'CARTÃO DE CRÉDITO',
		  'ativo' => '1',
		  'cheque' => '1',
		  'valegas' => '0',
		  'convenio' => '0',
		  'pagarreceber' => 'A',
		  'cartao' => '0'
		]);		
		Contamovimentotipo::create([
		  'id' => '4',
		  'grupo_id' => '1',
		  'descricao' => 'CHEQUE PRÉ-DATADO',
		  'ativo' => '1',
		  'cheque' => '1',
		  'valegas' => '0',
		  'convenio' => '0',
		  'pagarreceber' => 'A',
		  'cartao' => '0'
		]);		
		Contamovimentotipo::create([
		  'id' => '5',
		  'grupo_id' => '1',
		  'descricao' => 'DUPLICATA',
		  'ativo' => '1',
		  'cheque' => '0',
		  'valegas' => '0',
		  'convenio' => '0',
		  'pagarreceber' => 'A',
		  'cartao' => '0'
		]);		
		Contamovimentotipo::create([
		  'id' => '6',
		  'grupo_id' => '1',
		  'descricao' => 'CONVÊNIO',
		  'ativo' => '1',
		  'cheque' => '0',
		  'valegas' => '0',
		  'convenio' => '1',
		  'pagarreceber' => 'A',
		  'cartao' => '0'
		]);		
		Contamovimentotipo::create([
		  'id' => '7',
		  'grupo_id' => '1',
		  'descricao' => 'GÁS DE BOLSO',
		  'ativo' => '1',
		  'cheque' => '0',
		  'valegas' => '1',
		  'convenio' => '0',
		  'pagarreceber' => 'A',
		  'cartao' => '0'
		]);		
		Contamovimentotipo::create([
		  'id' => '8',
		  'grupo_id' => '1',
		  'descricao' => 'CARTÃO DE DÉBITO',
		  'ativo' => '1',
		  'cheque' => '0',
		  'convenio' => '0',
		  'pagarreceber' => 'A',
		  'cartao' => '1'
		]);		
    }
}
