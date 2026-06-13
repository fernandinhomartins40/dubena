<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEmpresasGrupoTableAtivo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // DB::statement("ALTER TABLE `empresas_grupos`CHANGE COLUMN `ativo` `ativo` TINYINT NOT NULL DEFAULT '1'");

        Schema::table('empresas_grupos', function (Blueprint $table) {
            $table->boolean('ativo')->nullable()->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresas_grupos', function (Blueprint $table) {
            //
        });
    }
}
