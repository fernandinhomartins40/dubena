<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFinanceirorateiosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('financeirorateios', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('financeiro_id');
          $table->unsignedInteger('planoconta_id');
          $table->unsignedInteger('centrocusto_id');
          $table->decimal('valor', 15, 4);
          $table->decimal('percentual', 6, 3);

          $table->timestamps();

          $table->foreign('financeiro_id')->references('id')->on('financeiros')->onDelete('cascade');
          $table->foreign('planoconta_id')->references('id')->on('planocontas')->onDelete('cascade');
          $table->foreign('centrocusto_id')->references('id')->on('centrocustos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('financeirorateios');
    }
}
