<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClienteImportacaoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->create('clienteimportacoes', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();

            $table->string('nome');
            $table->string('email')->nullable()->default(null);
            $table->boolean('ativo')->default(false);
            $table->string('cpf', 14)->nullable()->default(null);

            $table->unsignedInteger("user_id")->nullable()->index();

            $table->foreign('user_id')->references('id')->on('users');

            $table->date('datanascimento')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->dropIfExists('clienteimportacoes');
    }
}
