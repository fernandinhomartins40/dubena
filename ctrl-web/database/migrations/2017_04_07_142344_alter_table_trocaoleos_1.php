<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableTrocaoleos1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('veiculotrocaoleos',function(Blueprint $table){
            $table->dropColumn('id_colaborador');
            $table->dropColumn('id_veiculo');
        });
        Schema::table('veiculotrocaoleos',function(Blueprint $table){
            $table->unsignedInteger('colaborador_id');
            $table->unsignedInteger('veiculo_id');
            
            $table->foreign('veiculo_id')->references('id')->on('veiculos')
                    ->onUpdate('cascade');
            $table->foreign('colaborador_id')->references('id')->on('colaboradors')
                    ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
