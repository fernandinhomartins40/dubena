<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClientes15Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedBigInteger("api_id")->nullable()->default(null);
            $table->string("endereco_app", 200)->nullable()->default(null);
            $table->string("nome_app", 150)->nullable()->default(null);
            $table->float("latitude_app")->nullable()->default(null);
            $table->float("longitude_app")->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn("api_id");
            $table->dropColumn("endereco_app");
            $table->dropColumn("nome_app");
            $table->dropColumn("latitude_app");
            $table->dropColumn("longitude_app");
        });
    }
}
