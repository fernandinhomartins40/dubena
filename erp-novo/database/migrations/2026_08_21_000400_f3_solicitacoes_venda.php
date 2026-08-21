<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * F3 — solicitação de venda do campo para a Central.
 *
 * **A regra do cliente.** O franqueado não fecha pedido: ele solicita, e a
 * central de vendas cria, aprova o desconto e fatura. O industrial idem quando
 * o desconto passa da alçada dele.
 *
 * **Por que uma entidade própria e não um Pedido em rascunho.** Pedido tem
 * efeito patrimonial: `PedidoService::criar` já dispara máquina de estados,
 * estoque e financeiro (aplicarEfeito), além de entrar na fila de distribuição
 * (PedidoEntrouNaFila). Uma solicitação que o atendente ainda vai ajustar não
 * pode movimentar nada disso — nasceria sujando estoque e a bandeja da logística.
 * O Pedido nasce no ACEITE, e a solicitação guarda o id gerado.
 *
 * **Itens em JSON, não em tabela filha.** A solicitação é um rascunho de curta
 * vida: nasce, é decidida e vira pedido (ou morre). Os itens só interessam
 * inteiros, nunca são consultados isoladamente, e depois do aceite a verdade
 * passa a ser `pedido_itens`. Uma tabela filha aqui só criaria FK e ciclo de
 * vida para dado que ninguém consulta por item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_solicitacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();

            // Quem pediu — o vendedor/franqueado em campo.
            $t->foreignId('solicitante_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('colaborador_id')->nullable()->constrained('colaboradores')->nullOnDelete();

            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->foreignId('setor_id')->nullable()->constrained('setores')->nullOnDelete();
            $t->foreignId('condicaopagamento_id')->nullable()->constrained('condicaopagamentos')->nullOnDelete();

            // Rascunho do pedido: [{produto_id, quantidade, preco_unitario, desconto}]
            $t->json('itens');

            // O que ele pediu de desconto, e por quê. A justificativa é o que o
            // atendente lê para decidir — sem ela a aprovação é um chute.
            $t->decimal('desconto_solicitado', 12, 2)->default(0);
            $t->text('justificativa')->nullable();
            $t->text('observacao')->nullable();

            // pendente → aprovada | recusada | cancelada
            $t->string('situacao', 20)->default('pendente');

            // Decisão da Central.
            $t->foreignId('decidido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decidido_em')->nullable();
            $t->decimal('desconto_aprovado', 12, 2)->nullable();  // pode aprovar MENOS do que pediu
            $t->text('motivo_decisao')->nullable();

            // Preenchido no aceite — a ponte para o pedido que nasceu daqui.
            $t->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();

            $t->timestamps();

            // A consulta da Central: fila da empresa por situação, mais antigas primeiro.
            $t->index(['empresa_id', 'situacao', 'created_at']);
            $t->index(['empresa_id', 'solicitante_user_id']);
        });

        $this->aplicarRls('pedido_solicitacoes');
        $this->conceder('pedido_solicitacoes');
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_solicitacoes');
    }

    private function aplicarRls(string $tabela): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$tabela}
             USING (
                 nullif(current_setting('app.empresa_id', true), '') IS NULL
                 OR empresa_id = current_setting('app.empresa_id', true)::int
             )
             WITH CHECK (
                 nullif(current_setting('app.empresa_id', true), '') IS NULL
                 OR empresa_id = current_setting('app.empresa_id', true)::int
             )"
        );
    }

    private function conceder(string $tabela): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $role = 'erp_app';
        if (DB::selectOne('SELECT 1 AS ok FROM pg_roles WHERE rolname = ?', [$role]) === null) {
            return;
        }

        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$tabela} TO {$role}");
        DB::statement("GRANT USAGE, SELECT, UPDATE ON SEQUENCE {$tabela}_id_seq TO {$role}");
    }
};
