<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfemitidaitems4Table extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfemitidaitems',
                function (Blueprint $table) {
            $table->dropColumn(['aliqnac', 'aliqimp', 'impostonac', 'impostoimp']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfemitidaitems',
                function (Blueprint $table) {
            $table->decimal('aliqnac', 15, 4)->nullable();
            $table->decimal('aliqimp', 15, 4)->nullable();
            $table->decimal('impostonac', 15, 4)->nullable();
            $table->decimal('impostoimp', 15, 4)->nullable();
        });
    }

}
