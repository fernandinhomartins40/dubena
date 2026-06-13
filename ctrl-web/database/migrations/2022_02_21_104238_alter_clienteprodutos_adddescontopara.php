<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClienteprodutosAdddescontopara extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clienteprodutos', function (Blueprint $table) {
            $table->unsignedTinyInteger("descontopara")->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clienteprodutos', function (Blueprint $table) {
            $table->dropColumn("descontopara");
        });
    }
}
