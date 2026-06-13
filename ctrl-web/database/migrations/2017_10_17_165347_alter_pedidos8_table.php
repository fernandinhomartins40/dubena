<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPedidos8Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('cpf_cnpj_nf')->nullable();
            $table->unsignedInteger('tiponf')->nullable(); //0 NFCe Cliente sem cpf, 1 NFCe Cliente com cpf, 2 NFCe Cliente Com nome e 3 NFCe Cliente Endereço
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            //
        });
    }
}
