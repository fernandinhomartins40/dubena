<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfemitidaitems5Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfemitidaitems', function (Blueprint $table) {
            $table->decimal('vPart', 15, 2)->default(0);
            $table->decimal('pGNn', 7, 4)->default(0);
            $table->decimal('pGNi', 7, 4)->default(0);
            $table->decimal('pGLP', 7, 4)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfemitidaitems', function (Blueprint $table) {
            $table->dropColumn(['pGNn', 'pGNi', 'pGLP', 'vPart']);
        });
    }
}
