<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNfemitidacartacorrecaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nfemitidacartacorrecaos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('nfemitida_id');
            
            $table->unsignedInteger('nfnumero');
            $table->unsignedInteger('idlote');
            $table->unsignedInteger('corgao')->default(43);
            $table->unsignedInteger('tpamb')->default(2);
            $table->string('cnpj', 14);
            $table->string('chnfe', 100);
            $table->unsignedInteger('tpevento')->default(110110);
            $table->unsignedInteger('nseqevento');
            $table->string('verevento', 5);
            $table->string('descevento', 100);
            $table->string('xcorrecao', 1000);
            $table->string('xconduso', 1000);
            $table->dateTime('datahoraevento')->nullable()->default(null);
            $table->text('xmlretornoevento')->nullable()->default(null);
            $table->string('protocoloretornoevento', 100)->nullable()->default(null);

            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('nfemitida_id')->references('id')->on('nfemitidas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('nfemitidacartacorrecaos');
    }
}
