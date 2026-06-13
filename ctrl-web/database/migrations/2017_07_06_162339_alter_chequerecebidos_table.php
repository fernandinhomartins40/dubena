<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterChequerecebidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('chequerecebidos', function (Blueprint $table) {
            $table->unsignedInteger('adiantamentoconta_id')->nullable();
            $table->decimal('diferencavalor', 8, 2)->nullable();

            $table->foreign('adiantamentoconta_id', 'contas_chequerecebido_fk')->references('id')->on('contas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('chequerecebidos', function (Blueprint $table) {
            //
        });
    }
}
