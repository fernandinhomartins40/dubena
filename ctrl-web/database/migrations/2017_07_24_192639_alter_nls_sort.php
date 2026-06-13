<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNlsSort extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // FASE 3: NLS_SORT é exclusivo do Oracle (ordenação acento-insensível).
        // No Postgres o equivalente é collation/extension unaccent, tratado fora
        // das migrations. Aqui vira no-op nos demais bancos.
        \App\Helpers\MigrationHelper::oracleOnly("ALTER SYSTEM SET NLS_SORT = 'BINARY_AI' SCOPE = SPFILE");
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
