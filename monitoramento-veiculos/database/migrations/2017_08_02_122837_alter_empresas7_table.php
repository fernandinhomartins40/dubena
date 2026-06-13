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
             if (! \Illuminate\Support\Facades\Schema::hasColumn('empresas', 'logoimg')) {
                 \App\Helpers\MigrationHelper::addBinary('empresas', 'logoimg'); // BLOB→bytea
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
