<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterBoletos3Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('boletos', function (Blueprint $table) {
            $table->dropColumn('financeiro_id');
            $table->dropColumn('financeiroparcela_id');
        });
        Schema::table('boletos', function (Blueprint $table) {
            $table->unsignedInteger('financeiro_id')->nullable();
            $table->unsignedInteger('financeiroparcela_id')->nullable();
            $table->foreign('financeiro_id')->references('id')->on('financeiros');
            $table->foreign('financeiroparcela_id')->references('id')->on('financeiroparcelas');
            $table->unsignedInteger('protesto_devolucao')->nullable();
            $table->boolean('alterou')->default(false);
            $table->decimal('valor_abatimento', 8,4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('boletos', function (Blueprint $table) {
            //
        });
    }
}
