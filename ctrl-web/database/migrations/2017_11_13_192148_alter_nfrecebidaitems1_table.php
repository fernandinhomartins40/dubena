<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfrecebidaitems1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfrecebidaitems', function (Blueprint $table) {
            $table->unsignedInteger('qVol')->nullable()->default(0);
            $table->decimal('pesoL', 15, 3)->nullable()->default(0);
            $table->decimal('pesoB', 15, 3)->nullable()->default(0);
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
