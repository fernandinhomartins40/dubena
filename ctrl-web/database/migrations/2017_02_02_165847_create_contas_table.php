<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contas', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('banco_id');
          $table->unsignedInteger('contatipo_id');

          $table->string('agencia', 50);
          $table->string('conta', 50);
          $table->decimal('saldoinicial', 15,4);
          $table->decimal('saldoatual', 15,4);
          $table->string('descricao', 50);

          $table->boolean('boletoemite')->default(false);
          $table->unsignedInteger('boletosequencia');
          $table->string('boletocarteira', 3);
          $table->unsignedInteger('boletobyte');
          $table->decimal('boletomulta', 5, 3);
          $table->decimal('boletojuros', 5, 3);
          $table->string('boletoaceite', 5);
          $table->string('boletoespecie', 5);
          $table->unsignedInteger('boletoremessasequencia');
          $table->string('boletocedente', 50);
          $table->string('boletocedentedigito', 2);
          $table->boolean('boletocomprovanteentrega')->default(false);
          $table->string('boletoinstrucoes', 1000);
          $table->unsignedInteger('boletovencimentominimodias');
          $table->unsignedInteger('boletoposicoesnossonumero');
          $table->boolean('boletovidesacadoravalista')->default(false);
          $table->unsignedInteger('boletocnab');
          $table->boolean('boletocorrespondente')->default(false);
          $table->unsignedInteger('boletocorrespondentebanco_id')->nullable()->default(null);

          $table->boolean('fechado')->default(false);
          $table->boolean('ativo')->default(true);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('banco_id')->references('id')->on('bancos');
          $table->foreign('contatipo_id')->references('id')->on('contatipos');
          $table->foreign('boletocorrespondentebanco_id')->references('id')->on('bancos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('contas');
    }
}
