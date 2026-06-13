<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterUsers5Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // FASE 5/6: LONGBLOB (MySQL) → binary/bytea (Postgres), via conexão sgcm_api.
        Schema::connection('sgcm_api')->table('users', function (Blueprint $table) {
            $table->binary('thumbnail')->nullable()->default(null);
        });

        Schema::connection('sgcm_api')->table('users', function (Blueprint $table) {
            $table->dropColumn("caminhoimagem");
            $table->float("avaliacao", 3,2);
            $table->string("telefone", 20);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->table('users', function (Blueprint $table) {
            $table->dropColumn("thumbnail");
            $table->dropColumn("avaliacao");
            $table->dropColumn("telefone");
            $table->string("caminhoimagem")->nullable()->default(null);
        });
    }
}
