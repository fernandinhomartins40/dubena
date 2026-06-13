<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfimpostoestados4Table extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfimpostoestados',
                function (Blueprint $table) {
            $table->decimal('mva', 8, 2)->change();
            $table->decimal('pfmva', 8, 2)->change();
            $table->decimal('mvareduzido', 8, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfimpostoestados',
                function (Blueprint $table) {
            $table->decimal('mva', 3, 2)->change();
            $table->decimal('pfmva', 3, 2)->change();
            $table->decimal('mvareduzido', 3, 2)->change();
        });
    }

}
