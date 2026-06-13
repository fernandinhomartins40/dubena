<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterColaboradorexamesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('colaboradorexames', function($table)
      {
        $table->dropForeign('colaboradorexames_tipoexames_id_foreign');
        $table->dropColumn('tipoexames_id');

        $table->unsignedInteger('tipoexame_id');
        $table->foreign('tipoexame_id')->references('id')->on('tipoexames');
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
