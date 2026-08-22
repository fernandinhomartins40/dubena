<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Serviço deixa de ser "produto", e a taxa de entrega ganha regra.
 *
 * Dois problemas medidos em produção:
 *
 * 1. SERVIÇO COMO PRODUTO. O item "Manutenção e Instalação" ficou com saldo de
 *    estoque de −2 unidades: o PedidoService dava baixa em TODOS os itens sem
 *    perguntar a natureza deles. Serviço não tem armazém, não tem NCM e não é
 *    ICMS — mas o sistema tratava igual a um botijão.
 *
 * 2. TAXA DE ENTREGA SEM REGRA. O campo `pedidos.entrega_taxa` existe e é
 *    usado (40 pedidos), mas o valor é digitado à mão em cada venda: não há
 *    tabela por bairro, isenção por valor mínimo, nem CUSTO — então não há como
 *    responder "a entrega neste bairro dá lucro?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $t) {
            // Default 'produto': todo o cadastro existente É mercadoria, e
            // classificar por engano como serviço tiraria item do estoque.
            $t->string('natureza', 20)->default('produto')->index();
        });

        $this->classificarServicosConhecidos();

        // Regra de cobrança da entrega. Uma LINHA POR REGRA, avaliadas por
        // prioridade — assim a revenda combina "grátis acima de R$ 150" com
        // "bairro X custa R$ 5" sem que uma anule a outra.
        Schema::create('taxas_entrega', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();

            $t->string('descricao', 120);

            // bairro | cidade | distancia | valor_pedido | padrao
            //
            // REGIÃO não entra: `clientes` não tem `regiao_id` — a coluna
            // existe em `empresas`, para outra finalidade. Criar a FK aqui
            // seria um critério que nunca casaria.
            $t->string('criterio', 20);

            // Alvo do critério (só um preenchido, conforme o critério).
            $t->foreignId('bairro_id')->nullable()->constrained('bairros')->cascadeOnDelete();
            $t->foreignId('cidade_id')->nullable()->constrained('cidades')->cascadeOnDelete();

            // Faixa para os critérios numéricos (distância em km, valor em R$).
            $t->decimal('faixa_de', 12, 2)->nullable();
            $t->decimal('faixa_ate', 12, 2)->nullable();

            // O que se cobra do cliente.
            $t->decimal('valor', 12, 2)->default(0);
            $t->boolean('isenta')->default(false); // "entrega grátis" explícita

            /**
             * CUSTO da entrega para a revenda.
             *
             * É o que falta hoje para medir margem: sem ele, dá para saber
             * quanto se cobrou, mas não se a entrega deu lucro. Combustível,
             * tempo do entregador e desgaste entram aqui.
             */
            $t->decimal('custo_estimado', 12, 2)->nullable();

            // Regra mais específica vence: bairro (100) antes de cidade (50)
            // antes do padrão (0). Empate resolve pelo id.
            $t->integer('prioridade')->default(0);
            $t->boolean('ativo')->default(true);

            $t->timestamps();

            $t->index(['empresa_id', 'criterio', 'ativo']);
            $t->index(['empresa_id', 'bairro_id']);
        });

        // Rastro no pedido: qual regra decidiu a taxa e quanto custou.
        // Sem isto, mudar a tabela de preços reescreveria o passado — o
        // histórico tem de dizer o que valia NAQUELE momento.
        Schema::table('pedidos', function (Blueprint $t) {
            $t->foreignId('taxa_entrega_id')->nullable()->constrained('taxas_entrega')->nullOnDelete();
            $t->decimal('entrega_custo', 12, 2)->nullable();
        });

        $this->aplicarRls('taxas_entrega');
        $this->conceder('taxas_entrega');
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('taxa_entrega_id');
            $t->dropColumn('entrega_custo');
        });

        Schema::dropIfExists('taxas_entrega');

        Schema::table('produtos', function (Blueprint $t) {
            $t->dropColumn('natureza');
        });
    }

    /**
     * Marca como serviço o que claramente já é serviço no cadastro atual.
     *
     * Conservador de propósito: só o que o nome deixa evidente. Classificar
     * produto como serviço por engano o tiraria do controle de estoque, o que
     * é pior do que deixar um serviço marcado como produto.
     */
    private function classificarServicosConhecidos(): void
    {
        // Classificação em PHP, não em SQL: `REGEXP` não existe no sqlite (a
        // suíte roda nele) e `~*` só no Postgres. O conjunto é pequeno — 30
        // produtos na base real — então o custo é irrelevante.
        $servico = '/(manuten|instala|conserto|assist|m[ãa]o de obra|servi)/i';
        $taxa = '/(^frete|taxa de|entrega|deslocamento)/i';

        DB::table('produtos')->select('id', 'descricao')->orderBy('id')
            ->chunk(500, function ($produtos) use ($servico, $taxa) {
                foreach ($produtos as $p) {
                    $natureza = match (true) {
                        (bool) preg_match($servico, (string) $p->descricao) => 'servico',
                        (bool) preg_match($taxa, (string) $p->descricao) => 'taxa',
                        default => null,
                    };

                    if ($natureza !== null) {
                        DB::table('produtos')->where('id', $p->id)->update(['natureza' => $natureza]);
                    }
                }
            });
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
