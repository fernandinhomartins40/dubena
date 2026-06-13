<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateComissaoExcecaoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {     
     Schema::create('comissaoexcecoes', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('segmento_id');
        $table->unsignedInteger('colaboradorcomissao_id');
        $table->unsignedInteger('tipoexcecao')->default(1);
        $table->decimal('valorexcecao', 8, 2);

        $table->timestamps();

        $table->foreign('segmento_id')->references('id')->on('segmentos')->onDelete('cascade');
        $table->foreign('colaboradorcomissao_id')->references('id')->on('colaboradorcomissaos');
    });
}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
