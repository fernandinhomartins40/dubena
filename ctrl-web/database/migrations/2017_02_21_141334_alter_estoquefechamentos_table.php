<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEstoquefechamentosTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('estoquefechamentos', function($table) {
            $table->boolean('reaberto')->default(false);
            $table->unsignedInteger('reabertouser_id')->nullable()->default(null);
            $table->string('reabertomotivo', 500)->nullable()->default(null);
            $table->dateTime('reabertodatahora')->nullable()->default(null);
            
            $table->foreign('reabertouser_id')->references('id')->on('users');
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
