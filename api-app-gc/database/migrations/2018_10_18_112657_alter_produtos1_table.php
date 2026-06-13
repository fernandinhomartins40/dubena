<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterProdutos1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE produtos ADD COLUMN thumbnail LONGBLOB DEFAULT NULL");

//        Schema::table('produtoimportacoes', function (Blueprint $table) {
//            $table->dropColumn("caminhoimagem");
//        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn("thumbnail");
        });
//        Schema::table('produtoimportacoes', function (Blueprint $table) {
//            $table->string("caminhoimagem", 100)->nullable()->default(null);
//        });
    }
}
