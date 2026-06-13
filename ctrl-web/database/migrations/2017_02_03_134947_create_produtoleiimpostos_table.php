<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProdutoleiimpostosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('produtoleiimpostos', function (Blueprint $table) {
          $table->increments('id');
          $table->string('uf', 2)->nullable()->default(null);

          $table->unsignedInteger('ncm');
          $table->string('ex', 3);
          $table->unsignedInteger('tabela');
          $table->string('descricao', 200);
          $table->decimal('aliqnac', 15, 2);
          $table->decimal('aliqimp', 15, 2);
          $table->decimal('aliqestadual', 15, 2);
          $table->decimal('aliqmunicipal', 15, 2);
          $table->string('chave', 50);
          $table->string('versao', 50);

          $table->timestamps();

          $table->foreign('uf')->references('uf')->on('estados');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('produtoleiimpostos');
    }
}
