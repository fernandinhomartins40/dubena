<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProdutoCategoriaImportacoesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('produtocategoriaimportacoes', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();

            $table->boolean("ativo")->default(false);

            $table->unsignedInteger("user_id")->index();
            $table->foreign("user_id")->references("id")->on("users");

            $table->unsignedInteger("erp_id");
            $table->string("caminhoimagem", 45)->default("no-image.png");

            $table->unsignedInteger("produtocategoria_id");
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
        Schema::dropIfExists('produtocategoriaimportacoes');
    }
}
