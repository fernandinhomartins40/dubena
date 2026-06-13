<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterVeiculosColaboradorIdNullTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('veiculos', function (Blueprint $table) {
            $table->unsignedInteger('colaborador_id')->nullable()->default(null)->change();
        });

        // FASE 3: o ->change() acima já torna nullable no Postgres; o MODIFY
        // Oracle redundante vira no-op nos demais bancos.
        \App\Helpers\MigrationHelper::oracleOnly("ALTER TABLE veiculos MODIFY colaborador_id DEFAULT NULL NULL");

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
