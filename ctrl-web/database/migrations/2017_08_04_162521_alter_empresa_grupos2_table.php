<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEmpresaGrupos2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

     // FASE 3: Oracle BLOB→bytea, CLOB→text no Postgres (helper trata por driver).
     \App\Helpers\MigrationHelper::addBinary('empresas_grupos', 'logoimg');
     \App\Helpers\MigrationHelper::addLongText('empresas_grupos', 'logo');
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
