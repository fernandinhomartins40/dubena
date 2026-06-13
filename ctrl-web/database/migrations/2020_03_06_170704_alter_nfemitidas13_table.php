<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfemitidas13Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfemitidas', function (Blueprint $table) {
            DB::statement("ALTER TABLE nfemitidas ADD CONSTRAINT nfemitidas_unq UNIQUE(empresa_id, nfnumero, nfmodelo, nfserie, nftipoambiente)");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfemitidas', function (Blueprint $table) {
            DB::statement("ALTER TABLE nfemitidas DROP CONSTRAINT nfemitidas_unq");
        });
    }
}
