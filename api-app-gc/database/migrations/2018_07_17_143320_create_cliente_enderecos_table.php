<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClienteEnderecosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clienteenderecos', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->integer('numero');
            $table->string('complemento', 100)->nullable()->default('');
            $table->string('rua', 100);
            $table->string('cep', 9)->nullable()->default('');
            $table->string('titulo', 100)->default('Casa');
            $table->float('latitude', 19,15)->nullable()->default(null);
            $table->float('longitude', 19,15)->nullable()->default(null);

            $table->unsignedInteger('cliente_id')->index();
            $table->foreign('cliente_id')->references('id')->on('clienteimportacoes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('clienteenderecos');
    }
}
