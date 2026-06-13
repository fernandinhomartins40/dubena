<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEmpresas5Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('empresas', function($table)
      {
        $table->string('contnome')->nullable()->default(null);
        $table->string('contcpf')->nullable()->default(null);
        $table->string('contcnpj')->nullable()->default(null);
        $table->string('contcrc')->nullable()->default(null);
        $table->string('conttelefone')->nullable()->default(null);
        $table->string('contfax')->nullable()->default(null);
        $table->string('contemail')->nullable()->default(null);

        $table->string('contuf', 2)->nullable()->default(null);
        $table->unsignedInteger('contcidade_id')->nullable()->default(null);
        $table->unsignedInteger('contbairro_id')->nullable()->default(null);

        $table->string('contcep', 10)->nullable()->default(null);
        $table->string('contendereco', 500)->nullable()->default(null);
        $table->string('contnumero', 10)->nullable()->default(null);
        $table->string('contcomplemento', 100)->nullable()->default(null);

        $table->foreign('contuf')->references('uf')->on('estados');
        $table->foreign('contcidade_id')->references('id')->on('cidades');
        $table->foreign('contbairro_id')->references('id')->on('bairros');
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
