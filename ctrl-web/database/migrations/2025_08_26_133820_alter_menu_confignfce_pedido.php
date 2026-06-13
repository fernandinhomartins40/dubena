<?php

use App\Menu;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterMenuConfignfcePedido extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $row = Menu::where('descricao', 'confgNfcePedido.index')->first();
        if($row){
            $row->titulo = 'Config NF Pedido e Fech.Convênio';
            $row->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $row = Menu::where('descricao', 'confgNfcePedido.index')->first();
        if($row){
            $row->titulo = 'Configuração NFCe Pedido';
            $row->save();
        }
    }
}
