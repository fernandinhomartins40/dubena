<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContamovimentosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contamovimentos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('contamovimentotipo_id');
          $table->unsignedInteger('financeiroparcela_id')->nullable()->default(null);
          $table->unsignedInteger('contatransferencia_id')->nullable()->default(null);
          $table->unsignedInteger('conta_id');
          $table->dateTime('datahorabaixa');
          $table->decimal('valor', 15, 4);
          $table->decimal('multa', 15, 4);
          $table->decimal('juros', 15, 4);
          $table->decimal('desconto', 15, 4);
          $table->decimal('valorefetivado', 15, 4);
          $table->string('pagarreceber', 1);//'P'-Pagar, 'R'-Receber
          $table->boolean('ativo')->default(true);

          $table->timestamps();

          $table->foreign('contamovimentotipo_id')->references('id')->on('contamovimentotipos');
          $table->foreign('financeiroparcela_id')->references('id')->on('financeiroparcelas');
          $table->foreign('contatransferencia_id')->references('id')->on('contatransferencias');
          $table->foreign('conta_id')->references('id')->on('contas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('financeirobaixas');
    }
}
