<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterSetorsAddOperacaopedidoId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('setors', function (Blueprint $table) {
            $table->unsignedInteger('pedidooperacao_id')->nullable()->default(null);

            $table->foreign('pedidooperacao_id')->references('id')->on('nfoperacaos')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('setors', function (Blueprint $table) {
            $table->dropColumn('pedidooperacao_id');
        });
    }
}
