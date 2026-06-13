<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterBoletos5Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('boletos', function (Blueprint $table) {
            $table->unsignedInteger('ultimaocorrencia_id')->nullable()->default(null);
            $table->boolean('imprimiu')->nullable()->default(false);

            $table->foreign('ultimaocorrencia_id')->references('id')->on('ocorrenciasremessas');
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
            $table->dropColumn(['ultimaocorrencia_id', 'imprimiu']);
        });
    }
}
