<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `produtos.grupo_fiscal_id` — o elo que faltava entre o produto e a matriz de
 * tributação.
 *
 * O legado tem `PRODUTOS.NFGRUPOFISCAL_ID` e é por ele que a regra tributária é
 * escolhida: a matriz (`nf_impostos`) é indexada por operação fiscal × GRUPO
 * FISCAL. A reescrita migrou o produto sem essa coluna — com ela ausente, toda
 * resolução cairia na regra coringa da operação e 25 dos 26 produtos seriam
 * tributados pela regra errada.
 *
 * Aponta para `malha_fiscal` (tipo='grupos-fiscais'), onde o FiscalConfigMigrator
 * já deposita os grupos fiscais do legado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('produtos', 'grupo_fiscal_id')) {
            return;
        }

        Schema::table('produtos', function (Blueprint $t) {
            $t->foreignId('grupo_fiscal_id')->nullable()->after('produtoclasse_id')
                ->constrained('malha_fiscal')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('produtos', 'grupo_fiscal_id')) {
            return;
        }

        Schema::table('produtos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('grupo_fiscal_id');
        });
    }
};
