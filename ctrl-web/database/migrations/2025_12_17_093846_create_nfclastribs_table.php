<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNfclastribsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nfclastribs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');

            $table->string('codigo', 6);
            $table->string('nome', 100);
            $table->string('descricao');
            $table->boolean('ind_gTribRegular')->default(false);
            $table->boolean('ind_gCredPresOper')->default(false);
            $table->boolean('ind_gMonoPadrao')->default(false);
            $table->boolean('ind_gMonoReten')->default(false);
            $table->boolean('ind_gMonoRet')->default(false);
            $table->boolean('ind_gMonoDif')->default(false);
            $table->boolean('ind_gEstornoCred')->default(false);

            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nfclastribs');
    }
}
