<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterLigacoestelefonicasChangetelefone extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // FASE 3: Oracle MODIFY ... null → Postgres ALTER COLUMN ... DROP NOT NULL.
        \App\Helpers\MigrationHelper::setNullable('ligacoestelefonicas', 'telefone', true);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ligacoestelefonicas', function (Blueprint $table) {
            $table->string('telefone', 20)->nullable(false)->change();
        });
    }
}
