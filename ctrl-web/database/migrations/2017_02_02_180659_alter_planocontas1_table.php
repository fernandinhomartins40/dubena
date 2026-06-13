<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPlanocontas1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('planocontas', function($table)
      {
        $table->string('pagarreceber', 1);//'P'-Pagar, 'R'-Receber, 'A'-Ambos
        $table->string('codigo', 20);
        $table->unsignedInteger('nivel');
        $table->boolean('finalizador')->default(false);
        $table->unsignedInteger('paiplanoconta_id')->nullable()->default(null);

        $table->foreign('paiplanoconta_id')->references('id')->on('planocontas');
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
