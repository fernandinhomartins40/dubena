<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClienteprodutos1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clienteprodutos', function (Blueprint $table) {
            $table->decimal('desconto', 13, 3)->nullable();
            $table->unsignedTinyInteger('tipo')->nullable();
            $table->dropColumn('preco');
        });

        Schema::table('clienteprodutos', function (Blueprint $table) {
            $table->decimal('preco', 15, 4)->nullable();
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
            $table->dropColumn('preco');
        });

        Schema::table('clienteprodutos', function (Blueprint $table) {
            $table->dropColumn('desconto');
            $table->dropColumn('tipo');
            $table->decimal('preco', 15, 4)->nullable();
        });
    }
}
