<?php

use App\Domain\Pedido\CanalVenda;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3-05 — o pedido passa a registrar por qual porta entrou.
 *
 * Quatro caminhos criam pedido — painel admin, app do consumidor, app do
 * entregador (venda em campo) e ponte do app legado — e no banco os quatro
 * ficavam idênticos. "Quanto do meu faturamento vem do app?" não tinha resposta,
 * e essa é justamente a decisão de investir ou não no canal digital.
 *
 * ## Sem conversão retroativa, e isso é deliberado
 *
 * Os pedidos existentes ficam `DESCONHECIDO`.
 *
 * Seria possível adivinhar: pedido com `entregador_user_id` e sem
 * `atendente_user_id` *provavelmente* veio do campo. Mas "provavelmente" num
 * dado que vira relatório de faturamento por canal é pior que "não sei" — o
 * gráfico ficaria bonito e errado, e ninguém saberia de onde veio a linha.
 *
 * `DESCONHECIDO` é honesto: o relatório mostra a fatia sem origem, ela encolhe
 * sozinha conforme os pedidos novos entram, e a decisão se baseia no que foi
 * medido de verdade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            $t->string('canal', 20)
                ->default(CanalVenda::DESCONHECIDO->value)
                ->after('pedidooperacao_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            $t->dropColumn('canal');
        });
    }
};
