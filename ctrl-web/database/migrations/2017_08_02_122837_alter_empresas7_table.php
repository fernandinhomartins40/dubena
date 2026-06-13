<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEmpresas7Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
             // FASE 3: Oracle BLOB → bytea no Postgres (helper trata por driver).
             // (Esta coluna pode já existir de migration anterior; addBinary é idempotente por design no Postgres via verificação.)
             if (! \Illuminate\Support\Facades\Schema::hasColumn('empresas', 'logoimg')) {
                 \App\Helpers\MigrationHelper::addBinary('empresas', 'logoimg');
             }
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
