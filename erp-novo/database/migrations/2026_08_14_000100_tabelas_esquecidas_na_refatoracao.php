<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas que existem no ERP legado (com dado real) e ficaram de fora da
 * reescrita.
 *
 * Levantadas cruzando as 179 tabelas COM DADOS do Oracle `CTRL2QTI` contra as
 * 168 do schema novo. A maioria das "faltas" era só nome diferente
 * (`ESTOQUESETORHISTORICOS` → `estoquehistorico`); o que sobrou aqui são
 * funcionalidades de verdade sem lugar para pousar:
 *
 *  - transferência de estoque entre setores (28,4 mil + 51,6 mil itens);
 *  - transferência entre contas e estorno de movimento (9 mil + 7,2 mil);
 *  - volumes da NF-e (241 mil) — obrigatório no XML de transporte;
 *  - histórico do boleto (69 mil) — a trilha de ocorrências do título;
 *  - acerto manual de estoque (376) — o ajuste de inventário do dia a dia;
 *  - venda do vale-gás (1,8 mil) — a venda que origina os vales;
 *  - parcelas da condição de pagamento (88) — o "30/60/90" do cadastro;
 *  - venda ativa/telemarketing (2,4 mil) — a carteira de prospecção;
 *  - promotor de vendas (18,2 mil) — visita do promotor ao cliente.
 *
 * Todas nascem multi-tenant: `empresa_id` NOT NULL + policy de RLS igual às
 * demais (a migração de RLS já rodou, então a policy é aplicada aqui).
 */
return new class extends Migration
{
    /** Tabelas criadas aqui que precisam de RLS por empresa. */
    private const COM_TENANT = [
        'estoque_transferencias',
        'estoque_transferencia_itens',
        'estoque_acertos',
        'conta_transferencias',
        'contamovimento_estornos',
        'nota_volumes',
        'boleto_historicos',
        'vale_gas_vendas',
        'venda_ativas',
        'venda_ativa_clientes',
        'promotor_visitas',
    ];

    public function up(): void
    {
        // ── Estoque: transferência entre setores ──
        Schema::create('estoque_transferencias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('origem_setor_id')->constrained('setores')->restrictOnDelete();
            $t->foreignId('destino_setor_id')->constrained('setores')->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('datahora');
            $t->date('data_competencia')->nullable();
            $t->text('observacoes')->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'datahora']);
        });

        Schema::create('estoque_transferencia_itens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('estoque_transferencia_id')
                ->constrained('estoque_transferencias')->cascadeOnDelete();
            $t->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $t->decimal('quantidade', 14, 3);
            $t->timestamps();
            $t->index('estoque_transferencia_id', 'etr_itens_transf_idx');
        });

        // Acerto manual de saldo (ajuste de inventário): guarda o antes e o
        // depois, que é o que torna o ajuste auditável.
        Schema::create('estoque_acertos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('setor_id')->constrained('setores')->cascadeOnDelete();
            $t->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('quantidade_anterior', 14, 3);
            $t->decimal('quantidade_nova', 14, 3);
            $t->string('observacao', 255)->nullable();
            $t->dateTime('datahora');
            $t->timestamps();
            $t->index(['empresa_id', 'setor_id', 'produto_id']);
        });

        // ── Caixa/conta: transferência e estorno ──
        Schema::create('conta_transferencias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('origem_conta_id')->constrained('contas')->restrictOnDelete();
            $t->foreignId('destino_conta_id')->constrained('contas')->restrictOnDelete();
            $t->foreignId('contamovimentotipo_id')->nullable()
                ->constrained('contamovimentotipos')->nullOnDelete();
            $t->decimal('valor', 14, 2);
            $t->dateTime('datahora_processo');
            $t->timestamps();
            $t->index(['empresa_id', 'datahora_processo']);
        });

        // Estorno de movimento: no legado é uma tabela espelho do movimento,
        // preservando o lançamento revertido em vez de apagá-lo.
        Schema::create('contamovimento_estornos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('conta_id')->constrained('contas')->restrictOnDelete();
            $t->foreignId('financeiroparcela_id')->nullable();
            $t->foreignId('conta_transferencia_id')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('valor', 14, 2);
            $t->decimal('valor_efetivado', 14, 2)->default(0);
            $t->decimal('juros', 14, 2)->default(0);
            $t->decimal('multa', 14, 2)->default(0);
            $t->decimal('desconto', 14, 2)->default(0);
            $t->char('pagarreceber', 1)->nullable();
            $t->string('motivo', 255)->nullable();
            $t->string('descricao', 255)->nullable();
            $t->dateTime('datahora_baixa')->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'conta_id']);
        });

        // ── Fiscal: volumes da NF-e (grupo <transp><vol> do XML) ──
        Schema::create('nota_volumes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('nota_fiscal_id')->constrained('notas_fiscais')->cascadeOnDelete();
            $t->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $t->decimal('quantidade', 14, 3)->default(0);
            $t->string('especie', 60)->nullable();
            $t->string('marca', 60)->nullable();
            $t->decimal('peso_liquido', 14, 3)->default(0);
            $t->decimal('peso_bruto', 14, 3)->default(0);
            $t->timestamps();
            $t->index('nota_fiscal_id');
        });

        // ── Cobrança: trilha de ocorrências do boleto ──
        Schema::create('boleto_historicos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('boleto_id')->constrained('boletos')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('nosso_numero', 30)->nullable();
            $t->boolean('cancelado')->default(false);
            $t->boolean('gerou_boleto')->default(false);
            $t->boolean('gerou_remessa')->default(false);
            $t->boolean('alterou_vencimento')->default(false);
            $t->string('info_cancelamento', 255)->nullable();
            $t->dateTime('datahora');
            $t->timestamps();
            $t->index(['empresa_id', 'boleto_id']);
        });

        // ── Vale-gás: a venda que origina os vales ──
        Schema::create('vale_gas_vendas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $t->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $t->foreignId('condicaopagamento_id')->nullable();
            $t->foreignId('financeiro_id')->nullable();
            $t->decimal('quantidade', 14, 3)->default(0);
            $t->decimal('valor_unitario', 12, 2)->default(0);
            $t->decimal('valor_total', 12, 2)->default(0);
            $t->date('data_venda')->nullable();
            $t->boolean('cancelado')->default(false);
            $t->timestamps();
            $t->index(['empresa_id', 'cliente_id']);
        });

        // ── Venda ativa (telemarketing) ──
        Schema::create('venda_ativas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->string('descricao', 255);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        Schema::create('venda_ativa_clientes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('venda_ativa_id')->constrained('venda_ativas')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $t->dateTime('datahora')->nullable();
            $t->boolean('ligar_novamente')->default(false);
            $t->date('previsao_proxima_compra')->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'cliente_id']);
        });

        // ── Promotor de vendas: visita/prospecção em campo ──
        Schema::create('promotor_visitas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->foreignId('cidade_id')->nullable()->constrained('cidades')->nullOnDelete();
            $t->foreignId('bairro_id')->nullable()->constrained('bairros')->nullOnDelete();
            $t->foreignId('rua_id')->nullable()->constrained('ruas')->nullOnDelete();
            $t->foreignId('setor_id')->nullable()->constrained('setores')->nullOnDelete();
            $t->boolean('ausente')->default(false);
            $t->char('uf', 2)->nullable();
            $t->string('numero', 20)->nullable();
            $t->string('complemento', 120)->nullable();
            $t->string('ponto_referencia', 160)->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'user_id']);
        });

        // ── Parcelas da condição de pagamento (o "30/60/90") ──
        Schema::create('condicaopagamento_parcelas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('condicaopagamento_id')
                ->constrained('condicaopagamentos')->cascadeOnDelete();
            $t->unsignedSmallInteger('dias')->default(0);
            $t->decimal('percentual_valor', 7, 4)->default(0);
            $t->timestamps();
            $t->unique(['condicaopagamento_id', 'dias'], 'cond_pag_parcela_unica');
        });

        $this->aplicarRls();
    }

    /**
     * Mesma policy `tenant_isolation` das demais tabelas escopadas por empresa.
     * NO-OP fora do Postgres (a suíte roda em sqlite).
     */
    private function aplicarRls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::COM_TENANT as $tabela) {
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
    }

    public function down(): void
    {
        foreach ([
            'condicaopagamento_parcelas', 'promotor_visitas', 'venda_ativa_clientes',
            'venda_ativas', 'vale_gas_vendas', 'boleto_historicos', 'nota_volumes',
            'contamovimento_estornos', 'conta_transferencias', 'estoque_acertos',
            'estoque_transferencia_itens', 'estoque_transferencias',
        ] as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};
