<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Qual condição de pagamento é "PIX pelo app" passa a ser DADO, não palpite.
 *
 * O app gerava cobrança PIX para todo pedido — até o de quem escolheu dinheiro
 * (auditoria PIE, R2-02). Para recusar, o servidor precisa saber qual condição
 * é PIX, e a tabela não dizia: a Dubena tem "Pix (App Gás em Casa)" e "Pix" só
 * no NOME. Decidir pelo texto em runtime seria transformar convenção de uma
 * revenda em regra do produto; vira coluna editável no painel (Configurações →
 * Financeiro → Condições de pagamento).
 *
 * O preenchimento abaixo é a classificação INICIAL, por nome, e roda uma vez.
 * Depois dela quem manda é o cadastro. Aditiva: coluna nova com default false;
 * a policy de `condicaopagamentos` é por linha e continua valendo. Roda como
 * owner (superusuário nos composes), que não é barrado pelo FORCE RLS — e a
 * contagem vai para o log para que um "0" não passe calado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('condicaopagamentos', function (Blueprint $t) {
            $t->boolean('pix_online')->default(false)->after('a_vista');
        });

        $marcadas = DB::table('condicaopagamentos')
            ->whereRaw('LOWER(descricao) LIKE ?', ['%pix%'])
            ->update(['pix_online' => true]);

        Log::info("condicaopagamentos.pix_online: {$marcadas} condicao(oes) marcada(s) como PIX pelo nome.");
    }

    public function down(): void
    {
        Schema::table('condicaopagamentos', function (Blueprint $t) {
            $t->dropColumn('pix_online');
        });
    }
};
