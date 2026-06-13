<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableChecklistpesquisa extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('checklistpesquisas', function (Blueprint $table) {
            $table->dropColumn('observacoes');
        });
        Schema::table('checklistpesquisas', function (Blueprint $table) {
            $table->text('observacoes',500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('checklistpesquisas', function (Blueprint $table) {
            //
        });
    }
}
