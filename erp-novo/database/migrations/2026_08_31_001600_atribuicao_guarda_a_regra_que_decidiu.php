<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F6-06 — a atribuição automática guarda a REGRA que decidiu, não só o rótulo.
 *
 * ## O que já estava certo
 *
 * `pedido_atribuicoes` é uma boa trilha: de quem, para quem, veículo, operador,
 * ação, se foi automático e o motivo. A autoria humana está resolvida.
 *
 * ## O que faltava
 *
 * No caminho automático, o motivo é uma **string fixa**:
 *
 * ```php
 * 'Auto-atribuição (distância/carga)'
 * ```
 *
 * Ela diz *que critério* foi usado e não diz *com quais valores*. E os valores
 * importam: `peso_distancia`, `peso_carga`, raio máximo e teto de carga são
 * **configuráveis por empresa** e mudam com o tempo.
 *
 * Então quando o operador contesta — *"por que este pedido foi para aquele
 * entregador, se tinha outro mais perto?"* — a resposta é irreproduzível. Rodar
 * o ranking de novo usa os pesos de **hoje**, que podem não ser os de então, e a
 * conclusão sai errada nas duas direções: culpando o algoritmo por uma decisão
 * correta, ou inocentando-o de uma errada.
 *
 * ## Por que congelar, e não referenciar a config
 *
 * Mesma razão do snapshot fiscal (F5-08): a config é editável, e uma trilha que
 * aponta para dado mutável não é trilha. Guardar os pesos usados torna a decisão
 * **reproduzível anos depois**, que é o que se pede de um registro de autoria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_atribuicoes', function (Blueprint $t) {
            // Qual regra decidiu. Hoje só há uma ('distancia_carga'), e é
            // exatamente por isso que precisa ser nomeada: quando a segunda
            // aparecer, as linhas antigas continuam dizendo qual foi a delas.
            $t->string('regra', 40)->nullable()->after('automatico');

            // Os parâmetros efetivamente usados, congelados. JSON porque o
            // conjunto muda com a regra — uma regra futura por janela de entrega
            // terá outros campos, e criar coluna por parâmetro exigiria migration
            // a cada ajuste de algoritmo.
            $t->json('regra_parametros')->nullable()->after('regra');

            // O score do escolhido. É o número que responde "quão melhor ele era
            // que o segundo" — sem ele, a lista de candidatos não tem escala.
            $t->decimal('score', 8, 4)->nullable()->after('regra_parametros');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_atribuicoes', function (Blueprint $t) {
            $t->dropColumn(['regra', 'regra_parametros', 'score']);
        });
    }
};
