<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterRuasTable3 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ruas', function (Blueprint $table) {
            DB::statement("alter table ruas add constraint ruas_unq unique(empresa_id, cidade_id, descricao)");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ruas', function (Blueprint $table) {
            DB::statement("alter table ruas drop constraint ruas_unq");
        });
    }
}
