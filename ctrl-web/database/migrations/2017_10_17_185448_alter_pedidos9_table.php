<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPedidos9Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('cpf_cnpj_nf');
            $table->dropColumn('tiponf');
        });
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('nfcpfcnpj')->nullable();
            $table->unsignedInteger('nftipo')->nullable(); 
            //0 NFCe sem nada ou se possuir cpf/cnpj, 
            //1 NFCe com cliente do pedido com cnpj/cpf, 
            //2 Presença comprador 4
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
