<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfemitidacartacorrecaos1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfemitidacartacorrecaos', function (Blueprint $table) {
            $table->string('hash')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfemitidacartacorrecaos', function (Blueprint $table) {
            $table->dropColumn('hash');
        });
    }
}
