<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCupons extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->create('cupons', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('empresa_id');
            $table->dateTime("datainicio");
            $table->dateTime("datafim");
            $table->string("codigo")->unique();
            $table->decimal('valor', 15, 4);
            $table->tinyInteger("tipo");
            $table->integer("limiteuso");
            $table->boolean("ativo")->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->dropIfExists('cupons');
    }
}
