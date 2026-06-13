<?php

use Illuminate\Database\Seeder;
use App\Cidade;
use App\Estado;

class CodigoIbgeTableSeeder extends Seeder
{

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $cidades = Cidade::all();
        foreach ($cidades as $cidade) {
            $cidade->cod_ibge = $cidade->id;
            $cidade->save();
        }
        
        
    	Estado::find('RO')->update(['cod_ibge' => 11]);
    	Estado::find('AC')->update(['cod_ibge' => 12]);
    	Estado::find('AM')->update(['cod_ibge' => 13]);
    	Estado::find('RR')->update(['cod_ibge' => 14]);
    	Estado::find('PA')->update(['cod_ibge' => 15]);
    	Estado::find('AP')->update(['cod_ibge' => 16]);
    	Estado::find('TO')->update(['cod_ibge' => 17]);
        Estado::find('MA')->update(['cod_ibge' => 21]);
    	Estado::find('PI')->update(['cod_ibge' => 22]);
    	Estado::find('CE')->update(['cod_ibge' => 23]);
    	Estado::find('RN')->update(['cod_ibge' => 24]);
    	Estado::find('PB')->update(['cod_ibge' => 25]);
    	Estado::find('PE')->update(['cod_ibge' => 26]);
    	Estado::find('AL')->update(['cod_ibge' => 27]);
    	Estado::find('SE')->update(['cod_ibge' => 28]);
    	Estado::find('BA')->update(['cod_ibge' => 29]);
    	Estado::find('MG')->update(['cod_ibge' => 31]);
    	Estado::find('ES')->update(['cod_ibge' => 32]);
    	Estado::find('RJ')->update(['cod_ibge' => 33]);
    	Estado::find('SP')->update(['cod_ibge' => 35]);
    	Estado::find('PR')->update(['cod_ibge' => 41]);
    	Estado::find('SC')->update(['cod_ibge' => 42]);
    	Estado::find('RS')->update(['cod_ibge' => 43]);
    	Estado::find('MS')->update(['cod_ibge' => 50]);
    	Estado::find('MT')->update(['cod_ibge' => 51]);
    	Estado::find('GO')->update(['cod_ibge' => 52]);
    	Estado::find('DF')->update(['cod_ibge' => 53]);
        
    }

}
