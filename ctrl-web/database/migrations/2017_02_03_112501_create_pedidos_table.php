<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedidos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('cliente_id');
          $table->unsignedInteger('entregarua_id');
          $table->unsignedInteger('entregabairro_id');
          $table->unsignedInteger('entregacidade_id');
          $table->unsignedInteger('entregasetor_id')->nullable()->default(null);
          $table->unsignedInteger('atendenteuser_id');
          $table->unsignedInteger('entregadoruser_id')->nullable()->default(null);
          $table->unsignedInteger('condicaopagamento_id');
          $table->unsignedInteger('pedidooperacao_id');
          $table->unsignedInteger('pedidosituacao_id');
          $table->unsignedInteger('financeiro_id');
          $table->unsignedInteger('pedidomotivoatraso_id')->nullable()->default(null);
          $table->unsignedInteger('motivonaovenda_id')->nullable()->default(null);

          $table->dateTime('datahora');
          $table->dateTime('datahoraacao')->nullable()->default(null);//Sempre a ultima alteracao
          $table->dateTime('datahoraprevisaoentrega')->nullable()->default(null);//Pedido programado
          $table->dateTime('datahoraenvioentregador')->nullable()->default(null);//Envio pro celular
          $table->dateTime('entregadatahora')->nullable()->default(null);//Pedido entregue

          $table->unsignedInteger('entreganumero');
          $table->unsignedInteger('entregacomplemento')->nullable()->default(null);
          $table->unsignedInteger('entregapontoreferencia');
          $table->boolean('entregaurgente')->default(false);
          $table->string('entregatelefone')->nullable()->default(null);
          $table->decimal('entregataxa', 15, 4)->nullable()->default(0);
          $table->decimal('entregatrocopara', 15, 4)->nullable()->default(0);
          $table->decimal('entregatroco', 15, 4)->nullable()->default(0);

          $table->decimal('valorvenda', 15, 4);
          $table->decimal('valordesconto', 15, 4)->nullable()->default(0);
          $table->string('observacao', 500)->nullable()->default(null);
          $table->boolean('automatico')->default(false);//Entregador e Setor Automatico

          $table->float('latitude')->nullable()->default(null);
          $table->float('longitude')->nullable()->default(null);
          $table->float('entregalatitude')->nullable()->default(null);
          $table->float('entregalongitude')->nullable()->default(null);

          $table->boolean('nfcegerou')->default(false);
          $table->unsignedInteger('nfce_id')->default(false);

          $table->integer('numerocartao')->nullable()->default(null);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('cliente_id')->references('id')->on('clientes');
          $table->foreign('entregarua_id')->references('id')->on('ruas');
          $table->foreign('entregabairro_id')->references('id')->on('bairros');
          $table->foreign('entregacidade_id')->references('id')->on('cidades');
          $table->foreign('entregasetor_id')->references('id')->on('setors');
          $table->foreign('atendenteuser_id')->references('id')->on('users');
          $table->foreign('entregadoruser_id')->references('id')->on('users');
          $table->foreign('condicaopagamento_id')->references('id')->on('condicaopagamentos');
          $table->foreign('pedidooperacao_id')->references('id')->on('pedidooperacaos');
          $table->foreign('pedidosituacao_id')->references('id')->on('pedidosituacaos');
          $table->foreign('financeiro_id')->references('id')->on('financeiros');
          $table->foreign('pedidomotivoatraso_id')->references('id')->on('pedidomotivoatrasos');
          $table->foreign('motivonaovenda_id')->references('id')->on('motivonaovendas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('pedidos');
    }
}
