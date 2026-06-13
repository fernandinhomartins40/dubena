<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProdutoImportacoesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->create('produtoimportacoes', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();

            $table->string("mensagemsemestoque", 45)->nullable()->default(null);
            $table->boolean("semestoque")->default(false);

            $table->unsignedInteger("erp_id");
            $table->unsignedInteger("user_id")->index();
            $table->foreign("user_id")->references("id")->on("users");

            $table->string("ativo")->default(true);

            $table->unsignedInteger("produto_id")->index();
            $table->foreign("produto_id")->references("id")->on("produtos");

            $table->unsignedInteger("produtocategoriaimportacao_id")->nullable()->index();
            $table->foreign("produtocategoriaimportacao_id")->references("id")->on("produtocategoriaimportacoes");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->dropIfExists('produtoimportacoes');
    }
}
