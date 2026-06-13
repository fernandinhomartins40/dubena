<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNfrecebidavolumesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nfrecebidavolumes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('nfrecebida_id');
            $table->unsignedInteger('produto_id');
            
            $table->unsignedInteger('quantidadevolume');
            $table->string('produtoespecie', 60);
            $table->string('produtomarca', 60);
            $table->decimal('pesoliquido', 15, 4);
            $table->decimal('pesobruto', 15, 4);
            
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('nfrecebida_id')->references('id')->on('nfrecebidas');
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
        Schema::drop('nfrecebidavolumes');
    }
}
