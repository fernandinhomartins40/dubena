<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCupomFiscalParcelasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cupomfiscalparcela', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('numeroparcela');
            $table->string('referencia', 7);
            $table->date('datavencimento');
            $table->boolean('baixado')->default(false);
            
            $table->string("cmp", 3);
            $table->double("vmp", 18, 8);
            $table->double("vtroco", 18, 8)->default(0.00);

            $table->unsignedInteger("cupomfiscal_id");
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');

            $table->foreign("cupomfiscal_id")->references("id")->on("cuponsfiscais");
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
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
        Schema::dropIfExists('cupomfiscalparcela');
    }
}
