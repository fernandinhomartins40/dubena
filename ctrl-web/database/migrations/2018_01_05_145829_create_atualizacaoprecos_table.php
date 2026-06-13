<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAtualizacaoPrecosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('atualizacaoprecos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('produto_id');
            $table->string('descricao',125)->nullable();
            $table->string('tipo',1);
            $table->string('variacao',1);
            $table->decimal('valor',10,3);
            $table->boolean('mudoubase')->default(false);
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos')->onUpdate('cascade');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade');
            $table->foreign('produto_id')->references('id')->on('produtos')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('atualizacaoprecos');
    }
}
