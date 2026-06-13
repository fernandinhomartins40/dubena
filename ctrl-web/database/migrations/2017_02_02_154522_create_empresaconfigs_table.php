<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmpresaconfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('empresaconfigs', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->text('mensagemgasbolso')->nullable()->default(null);
          $table->unsignedInteger('tempoentrega')->nullable()->default(30);
          $table->unsignedInteger('tempourgente')->nullable()->default(20);
          $table->boolean('validacordenadasentrega')->default(false);
          $table->boolean('validagasbolso')->default(false);
          $table->boolean('validaatraso')->default(false);
          $table->boolean('androidenviatodos')->default(false);//Envia pedido a todos android do setor
          $table->boolean('geravenda')->default(false);// ?
          $table->text('mensagemduplicata')->nullable()->default(null);
          $table->string('senhamestre', 100)->nullable()->default("");
          $table->decimal('percentualencargos', 15, 4);
          $table->decimal('percentualprovisaodevedores', 15, 4);
          $table->decimal('percentualremuneracaocapital', 15, 4);
          $table->decimal('percentualdistribuicaoresul', 15, 4);
          $table->unsignedInteger('lancamentorapidocliente_id');
          $table->unsignedInteger('lancamentorapidofornecedor_id');
          $table->boolean('utilizafechamentoestoque')->default(false);
          $table->boolean('utilizavasilhame')->default(false);
          $table->boolean('utilizalimitecredito')->default(false);
          $table->boolean('permiteestoquenegativo')->default(false);
          $table->integer('timezone');
          $table->string('emailnomeremente', 100);
          $table->string('emailremetente', 100);
          $table->string('emailusuario', 100);
          $table->string('emailsenha', 100);
          $table->string('emailservidorsmtp', 100);
          $table->string('emailportasmtp', 10);
          $table->boolean('emailrequerautenticacao')->default(false);
          $table->boolean('emailrequerconexaotls')->default(false);
          $table->string('emailassunto', 100);
          $table->string('emailcorpo', 1000);
          $table->decimal('taxaentrega', 15, 4);
          $table->integer('impressaotipo');//0-Comum 1-Bematech
          $table->string('impressaomodelo', 50);
          $table->string('impressaoporta', 50);
          $table->boolean('impressaoautomatica')->default(false);
          $table->unsignedInteger('impressaoqtdviaspedido');
          $table->boolean('pedidovalidacartao')->default(false);
          $table->unsignedInteger('pedidovalidacartaodias');
          $table->boolean('pedidocontrolatempoligacoes')->default(false);
          $table->boolean('androidutiliza')->default(false);
          $table->unsignedInteger('diastrabalhadosemana');
          $table->unsignedInteger('monitoramentogrupo_id');

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('lancamentorapidocliente_id', 'empresaconfigs_cliente_foreign')->references('id')->on('clientes');
          $table->foreign('lancamentorapidofornecedor_id', 'empresaconfigs_forn_foreign')->references('id')->on('clientes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('empresaconfigs');
    }
}
