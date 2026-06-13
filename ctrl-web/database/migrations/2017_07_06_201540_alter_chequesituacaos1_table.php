<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterChequesituacaos1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('chequesituacaos', function (Blueprint $table) {
            $table->boolean('chequeemitido')->nullable()->default(false);
            $table->boolean('chequerecebido')->nullable()->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('chequesituacaos', function (Blueprint $table) {
            //
        });
    }
}
