<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPlanocontasTable extends Migration
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
        $table->dropColumn('codigo');
        $table->dropColumn('nivel');
        $table->dropColumn('finalizador');

        $table->unsignedInteger('insumo_valor');
        $table->boolean('provisao')->default(false);
        $table->boolean('investimento')->default(false);
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
