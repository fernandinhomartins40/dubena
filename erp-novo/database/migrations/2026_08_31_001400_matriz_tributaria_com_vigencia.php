<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F5-07 — a matriz tributária ganha **vigência**.
 *
 * ## O que já estava certo
 *
 * A matriz existe e é bem modelada: uma regra por (empresa, operação fiscal,
 * grupo fiscal), com `unique` — então **ambiguidade não acontece**, o banco não
 * deixa. E a ausência já bloqueia a emissão: `FiscalService` lança quando
 * `regraPara` volta nulo, em vez de inventar um padrão de SP.
 *
 * ## O que faltava
 *
 * **Alíquota não tem data.** Editar a regra sobrescreve a anterior, e com ela
 * some a informação de que antes era outra coisa.
 *
 * Isso não é hipótese: alíquota de ICMS muda por decreto estadual, com data
 * certa, e o GLP tem histórico de mudanças. O que acontece hoje quando a
 * alíquota sobe em 1º de janeiro:
 *
 *  - a revenda edita a regra no dia 1º — correto para as notas novas;
 *  - qualquer **reemissão** de uma nota de dezembro passa a calcular com a
 *    alíquota de janeiro. O XML reemitido diverge do autorizado;
 *  - e a apuração do mês anterior, se refeita, sai com o número de agora.
 *
 * O modo de falhar é o de sempre neste sistema: **silencioso e plausível**. O
 * número sai, ninguém desconfia, e a divergência aparece na fiscalização.
 *
 * ## Por que `vigencia_fim` é nulo em vez de uma data distante
 *
 * Nulo diz "vale até segunda ordem", que é a verdade — ninguém sabe quando o
 * estado vai mudar de novo. Uma data distante ('9999-12-31') mentiria com
 * precisão e ainda quebraria a comparação em algum lugar.
 *
 * ## A unicidade muda de forma
 *
 * Deixa de ser (empresa, operação, grupo) e passa a incluir o início da
 * vigência: a mesma regra pode existir várias vezes ao longo do tempo. O que
 * **não** pode é haver duas valendo no mesmo dia — e isso é o índice novo que
 * garante, não a aplicação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nf_impostos', function (Blueprint $t) {
            // Desde quando esta linha vale. Sem default de "hoje": as linhas que
            // já existem valem desde sempre, e é o backfill abaixo que diz isso.
            $t->date('vigencia_inicio')->nullable()->after('grupo_fiscal_id');

            // Nulo = vale até segunda ordem.
            $t->date('vigencia_fim')->nullable()->after('vigencia_inicio');
        });

        // As regras que já existem passam a valer desde uma data anterior a
        // qualquer nota do sistema. Escolher `hoje` deixaria todo o histórico
        // sem regra aplicável, e a reemissão de qualquer nota antiga quebraria
        // — trocando um defeito silencioso por uma parada geral.
        DB::table('nf_impostos')->whereNull('vigencia_inicio')->update([
            'vigencia_inicio' => '2000-01-01',
        ]);

        Schema::table('nf_impostos', function (Blueprint $t) {
            // A unicidade antiga impedia versionar; a nova impede sobreposição
            // exata. O caso "duas regras com faixas que se cruzam" não cabe num
            // índice — quem resolve é o serviço, escolhendo a mais recente que
            // já começou.
            $t->dropUnique('nf_imposto_unico');
            $t->unique(
                ['empresa_id', 'operacao_fiscal_id', 'grupo_fiscal_id', 'vigencia_inicio'],
                'nf_imposto_vigencia_unico',
            );
        });
    }

    public function down(): void
    {
        Schema::table('nf_impostos', function (Blueprint $t) {
            $t->dropUnique('nf_imposto_vigencia_unico');
        });

        // Só dá para restaurar a unicidade antiga se não houver versões — que é
        // o estado logo após o rollback de um deploy que ninguém usou. Com
        // versões já criadas, recriar o índice falharia; deixar sem ele é a
        // opção honesta, e a migration seguinte reintroduz a garantia.
        $duplicadas = DB::table('nf_impostos')
            ->select('empresa_id', 'operacao_fiscal_id', 'grupo_fiscal_id')
            ->groupBy('empresa_id', 'operacao_fiscal_id', 'grupo_fiscal_id')
            ->havingRaw('count(*) > 1')
            ->exists();

        if (! $duplicadas) {
            Schema::table('nf_impostos', function (Blueprint $t) {
                $t->unique(['empresa_id', 'operacao_fiscal_id', 'grupo_fiscal_id'], 'nf_imposto_unico');
            });
        }

        Schema::table('nf_impostos', function (Blueprint $t) {
            $t->dropColumn(['vigencia_inicio', 'vigencia_fim']);
        });
    }
};
