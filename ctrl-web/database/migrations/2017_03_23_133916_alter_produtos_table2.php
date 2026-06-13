<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterProdutosTable2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('produtos', function (Blueprint $table) {

           $table->decimal('pesoliquido', 15, 4)->nullable();
           $table->decimal('pesobruto', 15, 4)->nullable();
           $table->decimal('precovenda', 15, 4)->nullable();
           $table->decimal('custofrete', 15, 4)->nullable();
           $table->decimal('precovendaminimo', 15, 4)->nullable();
           $table->decimal('customedio', 15, 4)->nullable();
           $table->string('observacao', 500)->nullable();
           $table->decimal('nfealiqipi', 15, 4)->nullable();
           $table->decimal('nfebcipi', 15, 4)->nullable();
           $table->unsignedInteger('nfecodenquadramentoipi')->nullable();
           $table->unsignedInteger('nfetipoitem')->nullable();
           $table->string('nfeextipi', 50)->nullable();
           $table->unsignedInteger('nfecodlst')->nullable();
           $table->unsignedInteger('nfecodgen')->nullable();
           $table->string('nfedescricaofiscal', 50)->nullable();
           $table->string('ean', 14)->nullable();
           $table->string('ncm', 8)->nullable();
           $table->string('especie', 60)->nullable();
           $table->string('marca', 60)->nullable();
           $table->string('nfenatrec', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('produtos', function (Blueprint $table) {
            //
        });
    }
}
