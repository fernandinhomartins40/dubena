<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNfemitidavolumesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nfemitidavolumes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('nfemitida_id');
            $table->unsignedInteger('produto_id');
            
            $table->unsignedInteger('quantidadevolume');
            $table->string('produtoespecie', 60);
            $table->string('produtomarca', 60);
            $table->decimal('pesoliquido', 15, 4);
            $table->decimal('pesobruto', 15, 4);
            
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('nfemitida_id')->references('id')->on('nfemitidas');
            $table->foreign('produto_id')->references('id')->on('produtos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('nfemitidavolumes');
    }
}
