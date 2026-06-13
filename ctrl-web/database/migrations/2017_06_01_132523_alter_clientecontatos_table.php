<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClientecontatosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clientecontatos', function (Blueprint $table) {
            $table->dropColumn('acao');
        });

        Schema::table('clientecontatos', function (Blueprint $table) {
          $table->string('acao')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clientecontatos', function (Blueprint $table) {
            //
        });
    }
}
