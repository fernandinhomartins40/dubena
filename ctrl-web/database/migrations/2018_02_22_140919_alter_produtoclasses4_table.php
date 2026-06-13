<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterProdutoclasses4Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('produtoclasses', function (Blueprint $table) {
            $table->dropColumn('entrada');
            $table->dropColumn('saida');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('produtoclasses', function (Blueprint $table) {
            $table->boolean('entrada')->default(false);
            $table->boolean('saida')->default(false);
        });
    }
}
