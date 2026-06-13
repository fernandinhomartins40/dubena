<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEstoquetransferencias extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('estoquetransferencias', function (Blueprint $table) {
            $table->dropColumn('observacoes');
        });
        Schema::table('estoquetransferencias', function (Blueprint $table) {
            $table->string('observacoes', 500)->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }

}
