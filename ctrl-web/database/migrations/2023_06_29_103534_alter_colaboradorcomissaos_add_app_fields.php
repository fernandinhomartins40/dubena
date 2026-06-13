<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterColaboradorcomissaosAddAppFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('colaboradorcomissaos', function (Blueprint $table) {
            $table->decimal('percentualapp', 5, 2)->nullable()->default(0);
            $table->decimal('empresavalorapp', 15, 4)->nullable()->default(0);
            $table->boolean('tonelagem')->nullable()->default(false);
        });
        // FASE 3: Oracle MODIFY ... null → Postgres ALTER COLUMN ... DROP NOT NULL.
        \App\Helpers\MigrationHelper::setNullable('colaboradorcomissaos', 'setor_id', true);
        \App\Helpers\MigrationHelper::setNullable('colaboradorcomissaos', 'condicaopagamento_id', true);

        Schema::table('comissaoexcecoes', function (Blueprint $table) {
            $table->decimal('valorexcecaoapp', 15, 4)->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('colaboradorcomissaos', function (Blueprint $table) {
            $table->dropColumn("percentualapp");
            $table->dropColumn("empresavalorapp");
            $table->unsignedInteger('condicaopagamento_id')->change();
            $table->unsignedInteger('setor_id')->change();
            $table->dropColumn("tonelagem");
        });
        Schema::table('comissaoexcecoes', function (Blueprint $table) {
            $table->dropColumn("valorexcecaoapp");
        });
        \App\Helpers\MigrationHelper::setNullable('colaboradorcomissaos', 'setor_id', false);
        \App\Helpers\MigrationHelper::setNullable('colaboradorcomissaos', 'condicaopagamento_id', false);

    }
}
