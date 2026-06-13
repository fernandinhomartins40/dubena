<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEmpresaconfigs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->dropColumn('geravenda');
            $table->dropColumn('lancamentorapidocliente_id');
            $table->dropColumn('lancamentorapidofornecedor_id');
            $table->dropColumn('utilizavasilhame');
            $table->dropColumn('utilizalimitecredito');
            $table->dropColumn('taxaentrega'); 
                        
        });
        
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->unsignedInteger('nfcecliente_id');
            $table->unsignedInteger('planoconta_id')->nullable();
            $table->unsignedInteger('centrocusto_id')->nullable();
            $table->unsignedInteger('nfoperacoes_id')->nullable();
            $table->string('pedidoemitenfce',1);
            $table->integer('operacaodisk')->nullable();
            $table->string('contadevolucaocheck')->nullable();
            $table->string('integracaopgto');
            $table->integer('qnddiasinativocompra')->nullable();
            
            $table->foreign('nfcecliente_id')->references('id')->on('clientes')->onUpdate('cascade');
            $table->foreign('planoconta_id')->references('id')->on('planocontas')->onUpdate('cascade');
            $table->foreign('centrocusto_id')->references('id')->on('centrocustos')->onUpdate('cascade');
            $table->foreign('nfoperacoes_id')->references('id')->on('nfoperacaos')->onUpdate('cascade');
        });
        
        Schema::dropIfExists('empresaconfignfes');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            //
        });
    }
}
