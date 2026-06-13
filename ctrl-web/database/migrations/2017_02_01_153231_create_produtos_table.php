<?php

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
        Schema::create('produtos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('produtoclasse_id');
            $table->unsignedInteger('unidademedida_id');
            $table->unsignedInteger('produtoretornavel_id')->nullable()->default(null);
            $table->boolean('vasilhameretornavel')->default(false);
            $table->string('descricao', 50);
            $table->decimal('customedio', 15, 4);
            $table->decimal('custofrete', 15, 4);
            $table->decimal('precovenda', 15, 4);
            $table->decimal('precovendaminimo', 15, 4);
            $table->decimal('pesoliquido', 15, 4);
            $table->decimal('pesobruto', 15, 4);
            $table->string('observacao', 500);
            $table->boolean('ativo')->default(true);
            $table->string('ean', 14);
            $table->string('ncm', 8);
            $table->string('especie', 60);
            $table->string('marca', 60);
            $table->boolean('nfepermite')->default(false);
            $table->string('nfedescricaofiscal', 50);
            $table->unsignedInteger('nfetipoitem');
            $table->string('nfeextipi', 50);
            $table->unsignedInteger('nfecodgen');
            $table->unsignedInteger('nfecodlst');
            $table->string('nfenatrec', 50);
            $table->unsignedInteger('nfecodenquadramentoipi');
            $table->string('nfecprodanp', 9);
            $table->decimal('nfeqbcprod', 15, 4);
            $table->decimal('nfevaliqprod', 15, 4);
            $table->decimal('nfevcide', 15, 4);
            $table->boolean('controlaestoque')->default(true);

            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('produtoclasse_id')->references('id')->on('produtoclasses');
            $table->foreign('unidademedida_id')->references('id')->on('unidademedidas');
            $table->foreign('produtoretornavel_id')->references('id')->on('produtos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('produtos');
    }
}
