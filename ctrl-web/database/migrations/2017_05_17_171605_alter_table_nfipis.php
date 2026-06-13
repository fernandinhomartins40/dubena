<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableNfipis extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfipis', function (Blueprint $table) {
            $table->dropColumn('empresa_id');
            $table->dropColumn('grupo_id');
        });
        Schema::table('nfipis', function (Blueprint $table) {
            $table->unsignedInteger('empresa_id')->nullable();
            $table->unsignedInteger('grupo_id')->nullable();
            
            $table->foreign('empresa_id')->references('id')->on('empresas')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfipis', function (Blueprint $table) {
            //
        });
    }
}
