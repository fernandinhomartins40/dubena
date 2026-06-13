<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfoperacaos4Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfoperacaos', function (Blueprint $table) {
            $table->string('descricao', 62)->change();
            $table->string('descricaofiscal', 62)->change();
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
