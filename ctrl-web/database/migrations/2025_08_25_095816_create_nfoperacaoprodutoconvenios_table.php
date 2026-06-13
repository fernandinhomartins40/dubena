<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNfoperacaoprodutoconveniosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nfoperacaoprodutoconvenios', function (Blueprint $table) {
            // FASE 3: increments() já define a PK; o ->primary() extra gerava
            // "multiple primary keys" no Postgres. Removido o redundante.
            $table->increments('id');
            $table->unsignedInteger('nfoperacao_id');
            $table->index('nfoperacao_id', 'nfoperprod_idx1');
            $table->unsignedInteger('produto_id');
            $table->index('produto_id', 'nfoperprod_idx2');
            $table->unsignedInteger('nfoperacaoconvenio_id');
            $table->index('nfoperacaoconvenio_id', 'nfoperprod_idx3');
            $table->foreign('nfoperacao_id', 'fk_nfoperacaoprodconv_1')->references('id')->on('nfoperacaos');
            $table->foreign('nfoperacaoconvenio_id', 'fk_nfoperacaoprodconv_2')->references('id')->on('nfoperacaos');
            $table->foreign('produto_id', 'fk_nfoperacaoprodconv_3')->references('id')->on('produtos');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nfoperacaoprodutoconvenios');
    }
}
