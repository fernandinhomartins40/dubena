<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPedidosituacaohistoricoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        Schema::table('pedidosituacaohistoricos', function (Blueprint $table) {
            $table->dropColumn('pedidomotivoatraso_id');
        });
        
        Schema::table('pedidosituacaohistoricos', function (Blueprint $table) {
            $table->unsignedInteger('pedidomotivoatraso_id')->nullable();
            
            $table->foreign('pedidomotivoatraso_id', 'pedidosithist_motivoatraso_fk')->references('id')->on('pedidomotivoatrasos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedidosituacaohistoricos', function (Blueprint $table) {
            //
        });
    }
}
