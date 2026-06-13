<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePromotorvendasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promotorvendas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('cliente_id')->nullable();
            $table->boolean('ausente')->default(false)->nullable();
            $table->string('uf', 2)->nullable()->default(null);
            $table->unsignedInteger('cidade_id')->nullable()->default(null);
            $table->unsignedInteger('bairro_id')->nullable()->default(null);
            $table->unsignedInteger('setor_id')->nullable()->default(null);
            $table->unsignedInteger('rua_id')->nullable()->index();
            $table->string('numero', 10)->nullable();
            $table->string('complemento', 100)->nullable()->default(null);
            $table->string('ponto_referencia')->nullable()->default(null);
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete("cascade")->onUpdate("cascade");
            $table->foreign('uf')->references('uf')->on('estados');
            $table->foreign('cidade_id')->references('id')->on('cidades');
            $table->foreign('rua_id')->references('id')->on('ruas');
            $table->foreign('bairro_id')->references('id')->on('bairros');
            $table->foreign('setor_id')->references('id')->on('setors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('promotorvendas');
    }
}
