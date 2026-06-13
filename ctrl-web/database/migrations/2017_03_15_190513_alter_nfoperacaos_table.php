<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfoperacaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfoperacaos', function (Blueprint $table) {
            $table->unsignedInteger('tiponf')->nullable()->default(0); // 0 - entrada e 1 - saída 
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfoperacaos', function (Blueprint $table) {
            //
        });
    }
}
