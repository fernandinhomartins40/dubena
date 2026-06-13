<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterAgenciasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('agencias', function($table)
      {
        $table->string('endereco')->nullable()->default(null);
        $table->string('numero', 10)->nullable()->default(null);
        $table->string('complemento')->nullable()->default(null);
        $table->string('pontoreferencia')->nullable()->default(null);
        $table->string('email')->nullable()->default(null);
        $table->string('cep', 10)->nullable()->default(null);
        $table->string('uf', 2)->nullable()->default(null);
        $table->foreign('uf')->references('uf')->on('estados');
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
