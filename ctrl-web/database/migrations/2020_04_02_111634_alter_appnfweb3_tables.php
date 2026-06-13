<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterAppnfweb3Tables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->integer('presencacompradorappnf')->nullable()->default(null);
            $table->integer('fretemodalidadeappnf')->nullable()->default(null);

            $table->unsignedInteger('transportadorappnf_id')->nullable()->default(null);
            $table->foreign('transportadorappnf_id')->references('id')->on('clientes');
        });    }

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
