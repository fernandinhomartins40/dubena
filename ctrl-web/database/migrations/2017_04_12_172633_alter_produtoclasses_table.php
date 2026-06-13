<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterProdutoclassesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('produtoclasses', function (Blueprint $table) {
            $table->dropColumn(['materia_prima', 'produto_acabado', 'produto_processo']);
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
            //
        });
    }

}
