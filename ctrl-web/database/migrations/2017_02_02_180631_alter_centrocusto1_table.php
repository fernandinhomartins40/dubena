<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterCentrocusto1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('centrocustos', function($table)
      {
        $table->string('codigo', 20);
        $table->unsignedInteger('nivel');
        $table->boolean('finalizador')->default(false);
        $table->unsignedInteger('paicentrocusto_id')->nullable()->default(null);

        $table->foreign('paicentrocusto_id')->references('id')->on('centrocustos');
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
