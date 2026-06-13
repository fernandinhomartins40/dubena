<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVeiculosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('veiculos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('veiculotipo_id');
            $table->unsignedInteger('tipocombustivel_id');
            $table->unsignedInteger('colaborador_id');
            $table->unsignedInteger('cliente_id');//cod_fornecedor
            $table->string('placa', 20);
            $table->string('descricao', 100);
            $table->string('motorista', 100);
            $table->decimal('kminicial', 13, 3);
            $table->decimal('kmatual', 13, 3);
            $table->decimal('kmtrocaoleo', 13, 3);
            $table->decimal('kmultimatrocaoleo', 13, 3);
            $table->decimal('cubagem', 10, 2);
            $table->string('pneus', 100);
            $table->decimal('pneusvidautilkm', 13, 3);
            $table->unsignedInteger('alertasdiasantes');
            $table->boolean('veiculoproprio')->default(true);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('veiculotipo_id')->references('id')->on('veiculotipos');
            $table->foreign('tipocombustivel_id')->references('id')->on('tipocombustivels');
            $table->foreign('colaborador_id')->references('id')->on('colaboradors');
            $table->foreign('cliente_id')->references('id')->on('clientes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('veiculos');
    }
}
