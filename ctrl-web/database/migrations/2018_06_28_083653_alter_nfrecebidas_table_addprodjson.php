<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfrecebidasTableAddprodjson extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfrecebidas', function (Blueprint $table) {
            $table->text('produtosJson')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfrecebidas', function (Blueprint $table) {
            $table->dropColumn('produtosJson');
        });
    }
}
