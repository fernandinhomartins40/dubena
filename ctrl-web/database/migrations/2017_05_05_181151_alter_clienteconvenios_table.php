<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClienteconveniosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clienteconvenios', function (Blueprint $table) {
            $table->string('cpfrepresentante', 20)->nullable();
            $table->string('rgrepresentante', 20)->nullable()->default(null);
            $table->string('nomerepresentante', 200)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clienteconvenios', function (Blueprint $table) {
            //
        });
    }
}
