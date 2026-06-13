<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterProdutoclasses3Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('produtoclasses', function (Blueprint $table) {
            $table->dropColumn('vasilhame');
            $table->char('tipo',1)->nullable()->default('O'); //'G' => 'GLP', 'V' => 'Vasilhame', 'B' => 'Brinde', 'O' => 'Outros'
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
