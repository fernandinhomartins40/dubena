<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOcorrenciasremessasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ocorrenciasremessas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tipo'); // 0 => remessa, 1 => retorno, 2 => pre-critica
            $table->unsignedInteger('codigo_banco');
            $table->string('codigo', 20);
            $table->string('descricao');
            $table->boolean('uso_banco')->default(false);
            $table->boolean('seed')->default(false);
            $table->boolean('allowed_user')->default(false);
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
        Schema::table('ocorrenciasremessas', function (Blueprint $table) {
            //
        });
    }
}
