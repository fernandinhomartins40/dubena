<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterProdutosTable1 extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('produtos', function (Blueprint $table) {

            $table->dropColumn('pesoliquido');
            $table->dropColumn('pesobruto');
            $table->dropColumn('precovenda');
            $table->dropColumn('custofrete');
            $table->dropColumn('precovendaminimo');
            $table->dropColumn('customedio');
            $table->dropColumn('observacao');
            $table->dropColumn('nfetipoitem');
            $table->dropColumn('nfecodenquadramentoipi');
            $table->dropColumn('nfeextipi');
            $table->dropColumn('nfecodlst');
            $table->dropColumn('nfecodgen');
            $table->dropColumn('nfedescricaofiscal');
            $table->dropColumn('ean');
            $table->dropColumn('ncm');
            $table->dropColumn('especie');
            $table->dropColumn('marca');
            $table->dropColumn('nfenatrec');
            /*$table->dropColumn('nfealiqipi');
            $table->dropColumn('nfebcipi');*/
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('produtos', function (Blueprint $table) {
            //
        });
    }

}
