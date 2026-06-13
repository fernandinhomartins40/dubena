<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterFinanceiroparcelas1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('financeiroparcelas', function($table)
      {
          $table->unsignedInteger('agrupamento_status')->nullable()->default(0); //0=normal 1=agrupador 2=agrupado
          $table->unsignedInteger('agrupador_financeiro_id')->nullable()->default(null);
          $table->foreign('agrupador_financeiro_id')->references('id')->on('financeiros');
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
