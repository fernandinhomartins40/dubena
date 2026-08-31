<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5-08 — o item congela a resolução tributária **inteira**.
 *
 * ## O defeito
 *
 * `XmlNfeBuilder` monta o XML lendo do item:
 *
 * ```php
 * $icms->orig = (int) $item->origem_icms;
 * $pis->CST   = $item->cst_pis;
 * $pis->vBC   = (float) $item->bc_pis;
 * ```
 *
 * Só que **essas colunas não existem em `nota_itens`**. Existem em
 * `nf_impostos` — a regra —, e nunca foram copiadas para o item.
 *
 * `FiscalService` calcula todas elas na montagem, e as descarta: o `create()`
 * do item não as inclui, e o `$fillable` também não. Três camadas concordando
 * em jogar fora o mesmo dado.
 *
 * O resultado é o pior tipo de defeito fiscal — **campos vazios num XML que o
 * sistema considera pronto**:
 *
 *  - `orig` (origem da mercadoria) vai como 0, dizendo "nacional" para qualquer
 *    produto, inclusive importado;
 *  - o CST de PIS e de COFINS vai nulo;
 *  - as bases de cálculo de PIS/COFINS vão zeradas, enquanto os valores dos
 *    tributos vão preenchidos — o XML fica internamente inconsistente.
 *
 * Não apareceu antes porque o driver real da SEFAZ é gate: em homologação quem
 * responde é o `FakeSefazDriver`, que autoriza tudo.
 *
 * ## Por que colunas no item e não uma releitura da regra
 *
 * Releitura é o que a tarefa proíbe, e com razão: depois de autorizada a NF-e é
 * imutável na SEFAZ. Se a regra mudar — e agora ela tem vigência justamente
 * porque muda —, um XML remontado divergiria do autorizado.
 *
 * É a mesma decisão já tomada para descrição, NCM e unidade (F3-03). Este
 * commit só completa o congelamento que já existia pela metade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nota_itens', function (Blueprint $t) {
            // Origem da mercadoria (0=nacional, 1=importação direta, ...). Sem
            // ela o XML afirma "nacional" para tudo.
            $t->unsignedTinyInteger('origem_icms')->nullable()->after('cst_icms');

            // Modalidade de determinação da BC do ICMS.
            $t->unsignedTinyInteger('modalidade_bc_icms')->nullable()->after('origem_icms');

            // CST e base de PIS/COFINS — hoje o XML manda o valor do tributo
            // sem a base nem o código que o justifica.
            $t->string('cst_pis', 4)->nullable()->after('aliq_pis');
            $t->decimal('bc_pis', 14, 2)->default(0)->after('cst_pis');
            $t->string('cst_cofins', 4)->nullable()->after('aliq_cofins');
            $t->decimal('bc_cofins', 14, 2)->default(0)->after('cst_cofins');

            // ICMS-ST: existe na regra e no cálculo, e não tinha onde ficar.
            $t->decimal('bc_icms_st', 14, 2)->default(0)->after('valor_icms');
            $t->decimal('aliq_icms_st', 7, 4)->default(0)->after('bc_icms_st');
            $t->decimal('valor_icms_st', 14, 2)->default(0)->after('aliq_icms_st');
        });
    }

    public function down(): void
    {
        Schema::table('nota_itens', function (Blueprint $t) {
            $t->dropColumn([
                'origem_icms', 'modalidade_bc_icms',
                'cst_pis', 'bc_pis', 'cst_cofins', 'bc_cofins',
                'bc_icms_st', 'aliq_icms_st', 'valor_icms_st',
            ]);
        });
    }
};
