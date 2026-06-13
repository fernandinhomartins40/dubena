<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContafechamentosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contafechamentos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('conta_id');
          $table->dateTime('datahoraabertura');
          $table->dateTime('datahorafechamento');
          $table->decimal('saldoinicial', 15, 4);
          $table->decimal('saldofinal', 15, 4);
          $table->boolean('fechado')->default(false);
          $table->boolean('ativo')->default(true);

          $table->timestamps();

          $table->foreign('conta_id')->references('id')->on('contas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('contafechamentos');
    }
}
