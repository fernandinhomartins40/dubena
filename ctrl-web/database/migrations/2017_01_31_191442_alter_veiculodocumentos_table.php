<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterVeiculodocumentosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('veiculodocumentos', function($table)
      {
        $table->string('descricao', 100);
        $table->string('numero', 100);
        $table->date('vencimento')->nullable()->default(null);
        $table->boolean('alerta')->default(true);
        $table->unsignedInteger('tipodocumento_id');
        $table->foreign('tipodocumento_id')->references('id')->on('tipodocumentos');
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
