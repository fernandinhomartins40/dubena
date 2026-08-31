<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2-06 — unifica o vocabulário das quatro trilhas de auditoria.
 *
 * São quatro tabelas com campos diferentes, e nenhuma responde sozinha às
 * perguntas que um SaaS precisa responder:
 *
 *   audit_logs        ator, empresa, antes/depois, tenant — sem correlação
 *   login_logs        ator, empresa, motivo, tenant       — sem correlação
 *   security_events   ator, empresa, detalhes, tenant     — sem correlação
 *   platform_audit    admin, empresa, antes/depois        — sem tenant, sem correlação
 *
 * O que se acrescenta:
 *
 *  - `correlation_id` (nas quatro): uma ação humana vira várias linhas em
 *    tabelas diferentes (o update do model, o evento de segurança, a trilha de
 *    plataforma). Sem um fio comum, reconstruir "o que aconteceu naquele
 *    clique" é adivinhação por timestamp.
 *  - `tenant_account_id` em `platform_audit_logs`: as outras três já ganharam a
 *    coluna na migration 000300 — só que **nada a preenchia**, e coluna vazia
 *    não responde pergunta nenhuma. É a metade que faltava, do lado do código.
 *  - `motivo` em `audit_logs`: já existia, mas escondido dentro do JSON
 *    `depois`, então não dava para filtrar nem exigir.
 *
 * `correlation_id` já existe no `TenantEnvelope` (vem do header `X-Request-Id`)
 * — falta apenas chegar até aqui.
 *
 * Todas as colunas são nullable: a trilha é append-only e histórica, e não se
 * reescreve o passado para preencher um campo que não existia.
 */
return new class extends Migration
{
    /**
     * Tabela => se recebe também a coluna `motivo`.
     *
     * @var array<string, bool>
     */
    private const TRILHAS = [
        'audit_logs' => true,
        'login_logs' => false,          // já tem `motivo`
        'security_events' => true,
        'platform_audit_logs' => true,
    ];

    public function up(): void
    {
        foreach (self::TRILHAS as $tabela => $comMotivo) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $t) use ($tabela, $comMotivo) {
                if (! Schema::hasColumn($tabela, 'tenant_account_id')) {
                    // Só `platform_audit_logs` cai aqui: as outras três já
                    // receberam a coluna (com FK) na migration 000300 — o que
                    // faltava nelas não era a coluna, era alguém preenchendo.
                    //
                    // Sem FK nesta: a trilha de plataforma tem de sobreviver à
                    // exclusão do tenant, senão apagar a conta apagaria a prova
                    // do que foi feito com ela. (As três antigas usam RESTRICT,
                    // que resolve o mesmo problema por outro caminho — hoje é
                    // inócuo, porque não existe exclusão de tenant no sistema.)
                    $t->unsignedBigInteger('tenant_account_id')->nullable()->index();
                }
                if (! Schema::hasColumn($tabela, 'correlation_id')) {
                    $t->string('correlation_id', 64)->nullable()->index();
                }
                if ($comMotivo && ! Schema::hasColumn($tabela, 'motivo')) {
                    $t->string('motivo', 500)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TRILHAS as $tabela => $comMotivo) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $t) use ($tabela, $comMotivo) {
                if (Schema::hasColumn($tabela, 'correlation_id')) {
                    $t->dropColumn('correlation_id');
                }

                // `tenant_account_id` só se remove de `platform_audit_logs`:
                // nas outras três a coluna é da migration 000300, e derrubá-la
                // aqui faria este rollback destruir o trabalho de outra.
                if ($tabela === 'platform_audit_logs' && Schema::hasColumn($tabela, 'tenant_account_id')) {
                    $t->dropColumn('tenant_account_id');
                }

                if ($comMotivo && Schema::hasColumn($tabela, 'motivo')) {
                    $t->dropColumn('motivo');
                }
            });
        }
    }
};
