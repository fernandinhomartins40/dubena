<?php

use Illuminate\Database\Seeder;

class populate_clientes_table_seeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
    	for ($i=0; $i < 20000; $i++) { 
    		DB::table('clientes')->insert(["GRUPO_ID" => 83,	"EMPRESA_ID" =>68,	"NOME" => "Cliente $i",	"SEXO" =>'M',	"NUMERO" =>32,	"CIDADE_ID" =>4106902,	"CEP" =>'80250-100',	"BAIRRO_ID" =>152,	"UF" =>'PR',	"USER_ID" =>2,	"TIPOPESSOA_ID" =>103,	"SEGMENTO_ID" =>127,	"FORNECEDOR" =>0,	"CLIENTE" =>1,	"TRANSPORTADOR" =>0,	"CONVENIOLIMITE" =>0,	"LATITUDE" => -25,	"LONGITUDE" =>-49,	"LOCATIONTYPE" =>'ROOFTOP',	"NFEMITE" =>0,	"CONVENIO" =>1,	"CONVENIOATIVO" =>0,	"RUA_ID" =>475,	"ATIVO" =>1]);
    	}
    }
}
