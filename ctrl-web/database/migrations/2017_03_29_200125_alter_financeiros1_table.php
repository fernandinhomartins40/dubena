<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterFinanceiros1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // FASE 3: (1) dropForeign por nome Oracle → por coluna, tolerante.
        //         (2) colunas em MAIÚSCULAS → minúsculas (Postgres é case-sensitive;
        //             as colunas reais foram criadas em minúsculas).
        Schema::table('financeiros', function (Blueprint $table) {
            foreach ([['condicaopagamento_id'], ['agrupadorfinanceiro_id']] as $cols) {
                try { $table->dropForeign($cols); } catch (\Exception $e) {}
            }
            $table->dropColumn('condicaopagamento_id');
            $table->dropColumn('descricao');
            $table->dropColumn('documento');
            $table->dropColumn('cartaoautorizacao');
            $table->dropColumn('cartaonsu');
            $table->dropColumn('dataemissao');
            $table->dropColumn('datacompetencia');
            $table->dropColumn('agrupamentostatus');
            $table->dropColumn('agrupadorfinanceiro_id');
        });
        Schema::table('financeiros', function (Blueprint $table) {
            $table->unsignedInteger('condicaopagamento_id')->nullable()->default(null);
            $table->string('descricao')->nullable()->default(null);
            $table->string('documento', 50)->nullable()->default(null);
            $table->string('cartaoautorizacao', 20)->nullable()->default(null);
            $table->string('cartaonsu', 20)->nullable()->default(null);
            $table->dateTime('dataemissao')->nullable()->default(null);
            $table->dateTime('datacompetencia')->nullable()->default(null);
            $table->foreign('condicaopagamento_id')->references('id')->on('condicaopagamentos');

        });

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
