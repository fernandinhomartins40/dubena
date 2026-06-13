<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClientesAddconveniado extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->table('clienteimportacoes', function (Blueprint $table) {
            $table->boolean("conveniado")->nullable()->default(false);
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
            $table->dropColumn("conveniado");
        });
    }
}
