<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProdutosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->create('produtos', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();

            $table->string("caminhoimagem", 45)->default("no-image.png");

            $table->string("descricao", 45);
            $table->string("ativo")->default(true);

            $table->unsignedInteger("produtocategoria_id")->nullable()->default(null);
            $table->foreign("produtocategoria_id")->references("id")->on("produtocategorias");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->dropIfExists('produtos');
    }
}
