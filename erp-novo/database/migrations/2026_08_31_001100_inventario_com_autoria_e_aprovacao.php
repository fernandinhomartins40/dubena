<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4-03 — o inventário passa a registrar quem contou e quem aprovou.
 *
 * A estrutura existe e a efetivação está correta: usa o saldo derivado do
 * ledger, grava `quantidade_sistema` no momento e gera o movimento de acerto —
 * o ajuste **já é rastreável**.
 *
 * O que falta é a outra metade da tarefa: *"contagem por local/item, **autoria,
 * aprovação** e ajuste rastreável"*.
 *
 * ## Por que autoria e aprovação, e não só o ator do movimento
 *
 * O `user_id` do movimento de acerto diz quem apertou o botão de efetivar. Não
 * diz quem **contou** — e num inventário essas costumam ser pessoas diferentes:
 * o conferente vai ao depósito com a lista, e o supervisor aprova o ajuste.
 *
 * Sem essa separação, um ajuste de estoque de milhares de reais fica com um
 * único nome, e a pergunta que a auditoria faz ("quem contou? quem autorizou?")
 * não tem resposta no sistema.
 *
 * ## `aprovado_por` é nullable de propósito
 *
 * Nem toda revenda vai exigir aprovação — muitas são pequenas e a mesma pessoa
 * faz tudo. Tornar obrigatório aqui imporia um fluxo de duas pessoas a quem tem
 * uma só. O que se registra é o que aconteceu; exigir dupla aprovação é decisão
 * de produto, e o lugar dela é a configuração, não a coluna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estoque_inventarios', function (Blueprint $t) {
            // Quem CONTOU (foi ao depósito com a lista).
            $t->foreignId('contado_por')->nullable()->after('situacao')
                ->constrained('users')->nullOnDelete();

            // Quem APROVOU o ajuste — pode ser a mesma pessoa numa revenda
            // pequena, e é justamente por isso que a coluna é separada: para
            // deixar VISÍVEL quando é a mesma.
            $t->foreignId('aprovado_por')->nullable()->after('contado_por')
                ->constrained('users')->nullOnDelete();

            $t->timestamp('aprovado_em')->nullable()->after('aprovado_por');
        });
    }

    public function down(): void
    {
        Schema::table('estoque_inventarios', function (Blueprint $t) {
            $t->dropConstrainedForeignId('contado_por');
            $t->dropConstrainedForeignId('aprovado_por');
            $t->dropColumn('aprovado_em');
        });
    }
};
