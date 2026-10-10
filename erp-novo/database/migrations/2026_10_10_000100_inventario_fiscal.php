<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventário fiscal — o estoque DECLARADO ao fisco (SPED Fiscal, Bloco H).
 *
 * No legado era a tela "Inventário" do menu SPED: data do inventário, o mês em
 * que ele é entregue e, por produto, quantidade e valor unitário. O `RegH005`
 * lia dali.
 *
 * No sistema novo isso não existia. A aba "Inventário (valoração)" da SPA
 * postava esses campos no endpoint da CONTAGEM física, que não os conhece, e
 * tomava 422. E o SPED monta o Bloco H todo mês a partir do saldo de AGORA —
 * não do saldo na data do inventário, nem com o valor que a revenda declarou.
 *
 * ## Por que tabela própria, e não o inventário físico
 *
 * São perguntas diferentes. O físico (`estoque_inventarios`) responde "quanto
 * contei neste setor?" e, ao efetivar, AJUSTA o saldo. O fiscal responde "o que
 * declarei ter em estoque naquela data, e por quanto?" — é por empresa (não por
 * setor), tem valor, e não mexe em saldo nenhum. Reusar a tabela obrigaria um
 * a carregar os campos do outro, e efetivar um inventário fiscal por engano
 * ajustaria o estoque.
 *
 * ## Um documento declarado não se recalcula
 *
 * Quantidade, valor unitário e descrição ficam GRAVADOS no item. Depois de
 * entregue ao fisco o inventário não pode mudar porque o custo médio mudou ou o
 * produto foi renomeado — mesma razão do snapshot do item da nota (F3-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventarios_fiscais', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            // A policy canônica decide por (tenant, empresa); sem esta coluna a
            // tabela nova ficaria de fora da fronteira SaaS.
            $t->unsignedBigInteger('tenant_account_id')->nullable()->index();
            // Primeiro dia do mês do SPED em que o inventário é ENTREGUE. Não é
            // o mês do inventário: o de 31/12 costuma ir na escrituração de
            // fevereiro. É por este campo que o SPED o encontra.
            $t->date('mes_entrega');
            $t->date('data_inventario');
            // MOT_INV do H005 (01 = final do período … 05 = determinação do
            // fisco). char(2): o sqlite não valida o tamanho, a validação da
            // porta é que garante.
            $t->char('motivo', 2)->default('01');
            // Soma dos itens, gravada: é o VL_INV declarado.
            $t->decimal('valor_total', 15, 2)->default(0);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            // Um inventário por motivo em cada entrega: dois com o mesmo motivo
            // no mesmo arquivo seriam dois H005 concorrentes, e o fisco não tem
            // como saber qual vale.
            $t->unique(['empresa_id', 'mes_entrega', 'motivo']);
        });

        Schema::create('inventario_fiscal_itens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->unsignedBigInteger('tenant_account_id')->nullable()->index();
            $t->foreignId('inventario_fiscal_id')->constrained('inventarios_fiscais')->cascadeOnDelete();
            // restrict, e não cascade: apagar um produto não pode apagar uma
            // linha de um inventário já entregue ao fisco.
            $t->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $t->string('descricao_snapshot')->nullable();
            $t->decimal('quantidade', 15, 3);
            $t->decimal('valor_unitario', 15, 4);
            $t->timestamps();

            $t->unique(['inventario_fiscal_id', 'produto_id']);
        });

        $this->aplicarRls('inventarios_fiscais');
        $this->aplicarRls('inventario_fiscal_itens');
    }

    /**
     * RLS para as tabelas novas — a descoberta automática varreu o banco uma
     * vez e não alcança o que nasce depois (armadilha registrada no CLAUDE.md).
     */
    private function aplicarRls(string $tabela): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
        // Funções canônicas, e não `current_setting` à mão: são elas que
        // decidem por (tenant, empresa) em todo o resto do banco.
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$tabela}
             USING (app_tenant_can_read(tenant_account_id, empresa_id))
             WITH CHECK (app_tenant_can_operate(tenant_account_id, empresa_id))"
        );

        // A role do runtime pode não existir num PostgreSQL descartável; GRANT
        // para role inexistente aborta a transação da migration inteira.
        if (DB::selectOne("select 1 as ok from pg_roles where rolname = 'erp_app'") !== null) {
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$tabela} TO erp_app");
            DB::statement("GRANT USAGE, SELECT ON SEQUENCE {$tabela}_id_seq TO erp_app");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_fiscal_itens');
        Schema::dropIfExists('inventarios_fiscais');
    }
};
