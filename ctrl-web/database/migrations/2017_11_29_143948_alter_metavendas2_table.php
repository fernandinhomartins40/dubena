<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterMetavendas2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('metavendas', function (Blueprint $table) {
            $table->decimal('valorvalegas', 15, 4)->default(0);
            $table->decimal('quantidadevalegas', 15, 4)->default(0);
            $table->decimal('valorconvenio', 15, 4)->default(0);
            $table->decimal('quantidadeconvenio', 15, 4)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('metavendas', function (Blueprint $table) {
            //
        });
    }
}
