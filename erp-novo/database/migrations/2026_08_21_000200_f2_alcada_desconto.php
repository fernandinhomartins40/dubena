<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * F2 — alçada de desconto (teto por perfil/produto/segmento).
 *
 * **Por que uma tabela nova e não `permission_conditions`.** O ABAC (A4) já sabe
 * avaliar `limite` (PolicyEvaluator::avaliarLimite), mas a condição de lá é
 * amarrada a (papel, permissão) e não tem dimensão de PRODUTO. Desconto de gás
 * se negocia por item — 2% no P13, nada no P45 — então o teto precisa de
 * produto/setor/condição de pagamento. Reusar o motor sem essa dimensão faria a
 * política mentir.
 *
 * **A dimensão que faltava: sobre qual BASE o teto incide.** O legado tem três
 * caminhos de preço (MobileRepository::getPreco): tabela, preço especial do
 * cliente (clienteProduto) e convênio. Se o teto sempre incidisse sobre o preço
 * cheio, um cliente com preço especial ganharia desconto em cascata — negociado
 * na tabela e de novo na mão do vendedor. `base_calculo` resolve isso.
 *
 * **Fail-closed.** Sem regra cadastrada não há desconto (AlcadaDescontoService).
 * É o que o CLAUDE.md manda para dinheiro, e é o oposto do legado, onde o campo
 * de preço é livre (PedidoFragment2.java:80, e no backend
 * MobileRepository::getPreco:602 `if ($isAppNf) return $preco`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alcada_descontos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            // A quem a regra se aplica. Papel (RBAC) cobre o caso comum
            // ("todo franqueado"); colaborador_id abre a exceção nominal, que o
            // negócio sempre acaba pedindo. Ambos nulos = regra padrão da empresa.
            $t->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $t->foreignId('colaborador_id')->nullable()->constrained('colaboradores')->cascadeOnDelete();

            // Sobre o quê. Nulo = vale para todos.
            $t->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $t->foreignId('setor_id')->nullable()->constrained('setores')->nullOnDelete();
            $t->foreignId('condicaopagamento_id')->nullable()->constrained('condicaopagamentos')->nullOnDelete();

            // O teto. Os dois podem coexistir: vence o MENOR resultado.
            $t->decimal('percentual_max', 8, 4)->default(0);
            $t->decimal('valor_max', 12, 2)->nullable();

            // Base sobre a qual o percentual incide (ver cabeçalho):
            //  'tabela'   = preco_venda do produto (padrão);
            //  'praticado'= preço já negociado do item (preço especial/convênio).
            $t->string('base_calculo', 16)->default('tabela');

            // Acima do teto: recusa seca (false) ou vai para aprovação da Central (true).
            $t->boolean('permite_solicitar')->default(true);

            $t->date('data_inicio')->nullable();
            $t->date('data_fim')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();

            $t->index(['empresa_id', 'ativo']);
            $t->index(['empresa_id', 'role_id', 'produto_id']);
            $t->index(['empresa_id', 'colaborador_id']);
        });

        $this->aplicarRls('alcada_descontos');
        $this->conceder('alcada_descontos');
    }

    public function down(): void
    {
        Schema::dropIfExists('alcada_descontos');
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
