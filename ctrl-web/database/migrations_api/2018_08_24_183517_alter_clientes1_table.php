<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClientes1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->table('clienteimportacoes', function (Blueprint $table) {
            $table->unsignedInteger("enderecopadrao_id")->nullable();
            $table->foreign("enderecopadrao_id")->references("id")->on("clienteenderecos");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->table('clienteimportacoes', function (Blueprint $table) {
            $table->dropForeign("clienteimportacoes_enderecopadrao_id_foreign");
            $table->dropColumn("enderecopadrao_id");
        });
    }
}
