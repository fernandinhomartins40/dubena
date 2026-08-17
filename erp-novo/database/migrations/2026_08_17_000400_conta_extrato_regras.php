<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regras de classificação automática do extrato bancário (T4.2 do PLANO_PRODUCAO).
 *
 * **A lacuna.** O novo importa OFX (`ConciliacaoService`), mas sem as regras do
 * legado (`Contaextratoconfig`) **cada linha do extrato precisa ser classificada
 * à mão**. Com `contamovimentos` em 410.417 linhas, isso não é inconveniência —
 * é impedimento. `grep extratoconfig|contaextrato` no erp-novo retornava zero.
 *
 * Cada regra associa um PADRÃO DE DESCRIÇÃO a uma ação (lançar, lançar+baixar,
 * transferir) e aos ids que o lançamento precisa. O casamento é por conta
 * bancária: "PIX RECEBIDO" pode significar coisas diferentes em contas
 * diferentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conta_extrato_regras')) {
            return;
        }

        Schema::create('conta_extrato_regras', function (Blueprint $t) {
            $t->id();

            $t->unsignedBigInteger('empresa_id')->nullable();
            $t->unsignedBigInteger('grupo_id')->nullable();

            // Regra por CONTA: a mesma descrição significa coisas diferentes em
            // contas diferentes (a conta do PIX não é a da folha).
            $t->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();

            // Padrão a casar contra a descrição/memo da linha do OFX.
            $t->string('descricao');

            $t->string('acao', 20);   // enum ContaExtratoAcao

            // Campos usados por LANCAR / LANCAR_BAIXAR.
            $t->unsignedBigInteger('condicaopagamento_id')->nullable();
            $t->unsignedBigInteger('contamovimentotipo_id')->nullable();
            $t->unsignedBigInteger('plano_conta_id')->nullable();
            $t->unsignedBigInteger('centro_custo_id')->nullable();
            $t->unsignedBigInteger('cliente_id')->nullable();

            // Campo usado por TRANSFERIR.
            $t->unsignedBigInteger('conta_origem_id')->nullable();

            $t->boolean('ativo')->default(true);

            // Regras mais específicas devem ser avaliadas antes das genéricas:
            // "PIX RECEBIDO JOAO" tem de vencer de "PIX".
            $t->unsignedInteger('prioridade')->default(0);

            $t->timestamps();

            $t->index(['conta_id', 'ativo']);
            $t->unique(['conta_id', 'descricao'], 'conta_extrato_regras_conta_desc_unique');
        });

        $this->aplicarRls();
    }

    public function down(): void
    {
        Schema::dropIfExists('conta_extrato_regras');
    }

    /**
     * RLS por empresa + GRANT para a role de runtime.
     *
     * Aqui o isolamento é por `empresa_id` (não `grupo_id`): conta bancária é da
     * empresa, e uma filial não pode ver as regras de outra.
     */
    private function aplicarRls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tabela = 'conta_extrato_regras';

        DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$tabela}
             USING (
                 nullif(current_setting('app.empresa_id', true), '') IS NULL
                 OR empresa_id = nullif(current_setting('app.empresa_id', true), '')::int
             )
             WITH CHECK (
                 nullif(current_setting('app.empresa_id', true), '') IS NULL
                 OR empresa_id = nullif(current_setting('app.empresa_id', true), '')::int
             )"
        );

        $role = 'erp_app';
        if (DB::selectOne('SELECT 1 AS ok FROM pg_roles WHERE rolname = ?', [$role]) === null) {
            return;
        }

        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$tabela} TO {$role}");
        DB::statement("GRANT USAGE, SELECT, UPDATE ON SEQUENCE {$tabela}_id_seq TO {$role}");
    }
};
