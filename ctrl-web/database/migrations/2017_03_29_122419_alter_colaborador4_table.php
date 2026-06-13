<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterColaborador4Table extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('colaboradors', function($table) { // FASE 3: case Postgres
            $table->dropColumn('complemento');
        });
        Schema::table('colaboradors', function($table) { // FASE 3: case Postgres
            $table->unsignedInteger('complemento')->nullable()->default(null);
        });            
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        //
    }

}
