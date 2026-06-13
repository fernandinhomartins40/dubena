<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEmpresaconfigsAddCondicaopagamentoConvenio extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->unsignedInteger('condicaopagamentoconvenio_id')->nullable()->default(null);
            $table->foreign('condicaopagamentoconvenio_id')->references('id')->on('condicaopagamentos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->dropColumn("condicaopagamentoconvenio_id");
        });
    }
}
