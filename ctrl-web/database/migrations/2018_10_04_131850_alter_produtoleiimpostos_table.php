<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterProdutoleiimpostosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('produtoleiimpostos', function (Blueprint $table) {
            $table->date("inicio")->nullable()->default(null);
            $table->date("fim")->nullable()->default(null);
            $table->index(["id", "versao", "uf", "inicio", "fim", "chave", "ncm"]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('produtoleiimpostos', function (Blueprint $table) {
            $table->dropColumn("inicio");
            $table->dropColumn("fim");
        });
    }
}
