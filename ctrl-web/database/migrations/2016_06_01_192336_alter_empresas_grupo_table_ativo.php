<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Helpers\MigrationHelper;

class AlterEmpresasGrupoTableAtivo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // FASE 3: PostgreSQL exige USING explícito ao converter int→boolean.
        // Helper trata o cast por driver, preservando o comportamento original.
        MigrationHelper::toBoolean('empresas_grupos', 'ativo', true, 1);
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
