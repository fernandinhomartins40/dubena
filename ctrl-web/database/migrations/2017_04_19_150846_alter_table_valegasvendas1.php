<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableValegasvendas1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('valegasvendas', function (Blueprint $table) {
            $table->dropColumn('valorunitario');
            $table->dropColumn('valortotal');
            $table->dropColumn('quantidade');            
            $table->dropColumn('prevendaquantidade');
            $table->dropColumn('condicaopagamento_id');
            $table->dropColumn('financeiro_id');
        });
        
        Schema::table('valegasvendas', function (Blueprint $table) {
            $table->unsignedInteger('condicaopagamento_id')->nullable();
            $table->unsignedInteger('financeiro_id')->nullable();
            $table->integer('quantidade')->nullable();
            $table->decimal('valortotal',15,2)->nullable();
            $table->decimal('valorunitario',15,2)->nullable();            
            
            $table->foreign('condicaopagamento_id')->references('id')->on('condicaopagamentos');
            $table->foreign('financeiro_id')->references('id')->on('financeiros');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('valegasvendas', function (Blueprint $table) {
            //
        });
    }
}
