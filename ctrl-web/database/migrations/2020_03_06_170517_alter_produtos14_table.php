<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterProdutos14Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // FASE 3: Oracle MODIFY ... null → Postgres ALTER COLUMN ... DROP NOT NULL.
        \App\Helpers\MigrationHelper::setNullable('produtos', 'eantrib', true);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        \App\Helpers\MigrationHelper::setNullable('produtos', 'eantrib', true);
    }
}
