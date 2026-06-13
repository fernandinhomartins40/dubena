<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEstoquefisicosetorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('estoquefisicosetors', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('setor_id');
          $table->unsignedInteger('estoquefechamento_id');
          $table->unsignedInteger('produto_id');
          $table->unsignedInteger('estoquefisico_id');
          $table->decimal('quantidadesistema', 15, 4);
          $table->decimal('quantidadefisica', 15, 4);
          $table->decimal('quantidadediferenca', 15, 4);
          $table->boolean('estoquezerar')->default(false);
          $table->boolean('estoqueremover')->default(false);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('setor_id')->references('id')->on('setors');
          $table->foreign('estoquefechamento_id')->references('id')->on('estoquefechamentos')->onDelete('cascade');
          $table->foreign('produto_id')->references('id')->on('produtos');
          $table->foreign('estoquefisico_id')->references('id')->on('estoquefisicos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('estoquefisicosetors');
    }
}
