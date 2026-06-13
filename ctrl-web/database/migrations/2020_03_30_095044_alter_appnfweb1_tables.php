<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterAppnfweb1Tables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::table('produtos', function (Blueprint $table) {
            $table->boolean('enviaappnf')->nullable()->default(false);
        });
        Schema::table('condicaopagamentos', function (Blueprint $table) {
            $table->boolean('enviaappnf')->nullable()->default(false);
            //$table->unsignedInteger('pedidosituacao_id')->nullable()->default(null);
            //$table->foreign('pedidosituacao_id')->references('id')->on('pedidosituacaos');
        });
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->unsignedInteger('pedidooperacaoappnf_id')->nullable()->default(null);
            $table->foreign('pedidooperacaoappnf_id')->references('id')->on('pedidooperacaos');
        });
        Schema::table('nfoperacaos', function (Blueprint $table) {
            $table->boolean('enviaappnf')->nullable()->default(false);
        }); 
        Schema::create('nfoperacaoprodutos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('nfoperacao_id')->index();
            $table->unsignedInteger('produto_id')->index();
            $table->unsignedInteger('nfoperacaoapp_id')->index();
            $table->timestamps();
            $table->foreign('nfoperacao_id')->references('id')->on('nfoperacaos');
            $table->foreign('nfoperacaoapp_id')->references('id')->on('nfoperacaos');
            $table->foreign('produto_id')->references('id')->on('produtos');
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
