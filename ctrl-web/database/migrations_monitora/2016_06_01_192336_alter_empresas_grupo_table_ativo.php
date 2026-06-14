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
        // Alinhamento Postgres: cast int→boolean exige USING (helper trata por driver).
        \App\Helpers\MigrationHelper::toBoolean('empresas_grupos', 'ativo', true, 1);
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
