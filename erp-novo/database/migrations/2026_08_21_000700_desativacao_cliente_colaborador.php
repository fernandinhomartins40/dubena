<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha de desativação de cliente e colaborador.
 *
 * Antes desta migration, "excluir" chamava delete() físico: para cliente COM
 * pedido o Postgres recusava (pedidos.cliente_id é restrictOnDelete) e o
 * operador contornava renomeando o cadastro ("FULANO - EXCLUIDO"), destruindo
 * o nome real; para cliente SEM pedido o registro sumia de vez, levando junto
 * telefones/endereços/convênio por cascade.
 *
 * A exclusão passa a ser desativação (ativo = false). Estas colunas guardam
 * QUEM, QUANDO e POR QUÊ — sem isso o inativo é indistinguível de um cadastro
 * que nasceu inativo, e não há como auditar quem tirou o cliente da lista.
 *
 * Sem RLS nova: são colunas em tabelas que já têm policy.
 */
return new class extends Migration
{
    /** Tabelas que ganham a trilha — mesma forma nas duas. */
    private const TABELAS = ['clientes', 'colaboradores'];

    public function up(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->dateTime('desativado_em')->nullable();
                // nullOnDelete: o registro do usuário pode sair; a data e o
                // motivo da desativação continuam valendo mesmo assim.
                $t->foreignId('desativado_por')->nullable()->constrained('users')->nullOnDelete();
                $t->string('motivo_desativacao', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->dropConstrainedForeignId('desativado_por');
                $t->dropColumn(['desativado_em', 'motivo_desativacao']);
            });
        }
    }
};
