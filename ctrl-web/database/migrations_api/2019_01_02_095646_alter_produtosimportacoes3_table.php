<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterProdutosimportacoes3Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->table('produtoimportacoes', function (Blueprint $table) {
            $table->dropColumn(["mensagemsemestoque", "semestoque"]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->table('produtoimportacoes', function (Blueprint $table) {
            $table->string("mensagemsemestoque", 45)->nullable()->default(null);
            $table->boolean("semestoque")->default(false);
        });
    }
}
