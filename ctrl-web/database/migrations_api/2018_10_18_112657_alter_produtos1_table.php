<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterProdutos1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // FASE 5/6: LONGBLOB (MySQL) → binary/bytea (Postgres), via conexão sgcm_api.
        Schema::connection('sgcm_api')->table('produtos', function (Blueprint $table) {
            $table->binary('thumbnail')->nullable()->default(null);
        });

//        Schema::connection('sgcm_api')->table('produtoimportacoes', function (Blueprint $table) {
//            $table->dropColumn("caminhoimagem");
//        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->table('produtos', function (Blueprint $table) {
            $table->dropColumn("thumbnail");
        });
//        Schema::connection('sgcm_api')->table('produtoimportacoes', function (Blueprint $table) {
//            $table->string("caminhoimagem", 100)->nullable()->default(null);
//        });
    }
}
