<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterColaborador5Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('colaboradors', function($table) { // FASE 3: case Postgres
            $table->string('ctps', 12)->nullable()->default(null);
            $table->unsignedInteger('cargo_id')->nullable()->default(null);
            $table->foreign('cargo_id')->references('id')->on('cargos');
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
