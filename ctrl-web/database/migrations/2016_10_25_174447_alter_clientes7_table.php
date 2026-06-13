<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClientes7Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('clientes', function($table)
      {
        $table->unsignedInteger('tipopessoa_id')->nullable()->default(null);
        $table->unsignedInteger('segmento_id')->nullable()->default(null);
        $table->foreign('tipopessoa_id')->references('id')->on('tipopessoas');
        $table->foreign('segmento_id')->references('id')->on('segmentos');
        $table->string('fantasia')->nullable()->default(null);
        $table->string('cnpj')->nullable()->default(null);
        $table->string('inscricao_estadual')->nullable()->default(null);
        $table->string('observacoes')->nullable()->default(null);
        $table->boolean('consumidor_final')->default(false);
        $table->boolean('simples')->default(false);
        $table->unsignedInteger('indicador_ie')->nullable()->default(null);
        $table->string('ponto_referencia')->nullable()->default(null);
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
