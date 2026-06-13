<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterUsers1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('users', function($table)
      {
        $table->unsignedInteger('colaborador_id')->nullable()->index();
        $table->boolean('alertaschecklist')->default(false);
        $table->boolean('alertasfrota')->default(false);
        $table->boolean('ativo')->default(true);

        $table->foreign('colaborador_id')->references('id')->on('colaboradors');
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
