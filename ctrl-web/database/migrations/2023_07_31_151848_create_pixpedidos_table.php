<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePixpedidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pixpedidos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedBigInteger("clienteapi_id");
            $table->unsignedBigInteger("pedidoapi_id");
            $table->decimal('valorvenda', 15, 4);
            $table->text("json_data");
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pixpedidos');
    }
}
