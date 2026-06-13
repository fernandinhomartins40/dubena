<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Spedtipoitem;

class spedtipoitemsTableSeeder extends Seeder {

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        Spedtipoitem::create([
            'id' => '1',
            'codigo' => '00',
            'descricao' => '00-Mercadoria para Revenda'
        ]);
        Spedtipoitem::create([
            'id' => '2',
            'codigo' => '01',
            'descricao' => '01-Matéria-Prima'
        ]);
        Spedtipoitem::create([
            'id' => '3',
            'codigo' => '02',
            'descricao' => '02-Embalagem'
        ]);
        Spedtipoitem::create([
            'id' => '4',
            'codigo' => '03',
            'descricao' => '03-Produto em Processo'
        ]);
        Spedtipoitem::create([
            'id' => '5',
            'codigo' => '04',
            'descricao' => '04-Produto Acabado'
        ]);
        Spedtipoitem::create([
            'id' => '6',
            'codigo' => '05',
            'descricao' => '05-Subproduto'
        ]);
        Spedtipoitem::create([
            'id' => '7',
            'codigo' => '06',
            'descricao' => '06-Produto Intermediário'
        ]);
        Spedtipoitem::create([
            'id' => '8',
            'codigo' => '07',
            'descricao' => '07-Material de Uso e Consumo'
        ]);
        Spedtipoitem::create([
            'id' => '9',
            'codigo' => '08',
            'descricao' => '08-Ativo Imobilizado'
        ]);
        Spedtipoitem::create([
            'id' => '10',
            'codigo' => '09',
            'descricao' => '09-Serviços'
        ]);
        Spedtipoitem::create([
            'id' => '11',
            'codigo' => '10',
            'descricao' => '10-Outros Insumos'
        ]);
        Spedtipoitem::create([
            'id' => '12',
            'codigo' => '99',
            'descricao' => '99-Outras'
        ]);
    }

}
