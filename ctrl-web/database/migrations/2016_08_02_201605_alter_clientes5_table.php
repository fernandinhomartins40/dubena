<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClientes5Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('clientes', function($table)
      {
        $table->unsignedInteger('user_id')->nullable()->default(null);
        $table->unsignedInteger('situacao_id')->nullable()->default(null);
        $table->foreign('situacao_id')->references('id')->on('clientesituacaos');
        $table->foreign('user_id')->references('id')->on('users');
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
