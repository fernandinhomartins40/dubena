<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterProdutocondicaopagamento1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->table('produtocondicaopagamentos', function (Blueprint $table) {
            $table->unsignedInteger("user_id")->nullable()->index();
            $table->foreign("user_id")->references("id")->on("users");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->table('produtocondicaopagamentos', function (Blueprint $table) {
            $table->dropForeign("produtocondicaopagamentos_user_id_foreign");
            $table->dropIndex("produtocondicaopagamentos_user_id_index");
            $table->dropColumn("user_id");
        });
    }
}
