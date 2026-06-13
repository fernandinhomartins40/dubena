<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClientes10Table extends Migration
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
        $table->unsignedInteger('conveniolimite');
        $table->float('latitude');
        $table->float('longitude');
        $table->string('locationtype', 100);
        $table->boolean('nfemite')->default(false);
        $table->decimal('creditolimite', 15, 4);
        $table->decimal('creditosaldo', 15, 4);
        $table->date('dataultimacompra')->nullable()->default(null);
        $table->boolean('convenio')->default(false);
        $table->unsignedInteger('convenio_id')->nullable()->default(null);

        $table->foreign('convenio_id')->references('id')->on('clientes');
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
