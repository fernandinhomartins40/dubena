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

     \App\Helpers\MigrationHelper::addBinary('empresas_grupos', 'logoimg'); // BLOB→bytea
     \App\Helpers\MigrationHelper::addBinary('empresas_grupos', 'logo');    // BLOB→bytea
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
