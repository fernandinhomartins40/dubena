<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidomotivoatrasosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedidomotivoatrasos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->string('descricao', 100);
            $table->boolean('ativo')->default(true);

            $table->timestamps();
            $table->foreign('grupo_id', 'motivoatraso_grupo_foreign')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id', 'motivoatraso_empresa_foreign')->references('id')->on('empresas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('pedidomotivoatrasos');
    }
}
