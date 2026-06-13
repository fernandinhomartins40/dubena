<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterContasTable1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contas', function (Blueprint $table) {
            // FASE 3: dropForeign por nome Oracle fixo falha no Postgres.
            // Dropamos por coluna (Laravel infere o nome) e toleramos ausência.
            foreach ([['banco_id'], ['boletocorrespondentebanco_id']] as $cols) {
                try { $table->dropForeign($cols); } catch (\Exception $e) {}
            }

            $table->dropColumn('banco_id');
            $table->dropColumn('agencia');
            $table->dropColumn('boletoemite');
            $table->dropColumn('boletosequencia');
            $table->dropColumn('boletocarteira');
            $table->dropColumn('boletobyte');
            $table->dropColumn('boletomulta');
            $table->dropColumn('boletojuros');
            $table->dropColumn('boletoaceite');
            $table->dropColumn('boletoespecie');
            $table->dropColumn('boletoremessasequencia');
            $table->dropColumn('boletocedente');
            $table->dropColumn('boletocedentedigito');
            $table->dropColumn('boletocomprovanteentrega');
            $table->dropColumn('boletoinstrucoes');
            $table->dropColumn('boletovencimentominimodias');
            $table->dropColumn('boletoposicoesnossonumero');
            $table->dropColumn('boletovidesacadoravalista');
            $table->dropColumn('boletocnab');
            $table->dropColumn('boletocorrespondente');
            $table->dropColumn('boletocorrespondentebanco_id');
            $table->dropColumn('fechado');
            $table->dropColumn('ativo');
            
            
            
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
