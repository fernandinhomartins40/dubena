<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F02.5 — Row-Level Security (PostgreSQL) como SEGUNDA barreira de isolamento.
 *
 * O global scope da aplicação (BelongsToTenant) já filtra por empresa_id, mas é
 * burlável por query crua / DB::table / withoutGlobalScope indevido. O RLS aplica
 * o filtro NO BANCO: mesmo um SELECT cru só enxerga as linhas da empresa ativa.
 *
 * Mecanismo: cada tabela com empresa_id ganha uma policy
 *   USING (empresa_id = current_setting('app.empresa_id', true)::int)
 * e o middleware ResolveTenant faz `SET app.empresa_id = <id>` na conexão por
 * requisição. Sem a variável setada (CLI/ETL), `current_setting(..., true)` é NULL
 * e a policy não restringe — coerente com o comportamento do scope sem tenant.
 *
 * NO-OP fora do PostgreSQL (sqlite em teste, mysql): RLS é específico do pg.
 * O dono da tabela (superuser/owner) ignora RLS por padrão; FORCE garante que a
 * policy também valha para o owner usado pela app.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tabelasComEmpresaId() as $tabela) {
            DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
            // A policy permite acesso quando:
            //  - há tenant ativo e a linha é dele; OU
            //  - NÃO há `app.empresa_id` setado (NULL/'' em CLI/ETL/crons admin) —
            //    espelha o comportamento do global scope, que não filtra sem tenant.
            // Isso mantém os crons globais (inconsistências, venda diária) funcionando
            // sem furar o isolamento das requisições web (onde a var SEMPRE é setada).
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
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tabelasComEmpresaId() as $tabela) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
            DB::statement("ALTER TABLE {$tabela} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} DISABLE ROW LEVEL SECURITY");
        }
    }

    /**
     * Tabelas existentes que possuem coluna empresa_id (fonte da verdade em runtime,
     * evita lista hard-coded desatualizar). Exclui a própria `empresas`.
     *
     * @return list<string>
     */
    private function tabelasComEmpresaId(): array
    {
        // Lista canônica das tabelas escopadas por empresa (espelha os models com
        // BelongsToTenant). Filtrada por existência + coluna em runtime.
        $candidatas = [
            'clientes', 'clientetelefones', 'clienteinteracoes', 'clientedependentes', 'clienteprecos',
            'produtos', 'produtoorigens', 'setores', 'estoquesaldos', 'estoquehistorico',
            'estoquefechamentos', 'estoque_inventarios', 'estoque_inventario_itens', 'estoque_requisicoes',
            'pedidos', 'pedidoitens', 'pedidosituacaohistorico',
            'financeiros', 'financeiroparcelas', 'financeirorateios',
            'contas', 'contamovimentos', 'contafechamentos', 'cheques',
            'boletos', 'boleto_ocorrencias', 'remessas_cnab', 'pix_cobrancas',
            'notas_fiscais', 'nota_itens', 'nf_recebidas', 'nf_recebida_itens',
            'cupons_fiscais', 'cupom_fiscal_itens', 'mcmms', 'documentos', 'empresa_bens',
            'colaboradores', 'colaborador_comissoes', 'colaborador_familias', 'colaborador_recessos',
            'colaborador_exames', 'colaborador_pontos', 'colaborador_turnos', 'comissao_excecoes',
            'veiculos', 'veiculo_abastecimentos', 'veiculo_pneus', 'veiculo_trocas_oleo',
            'comodatos', 'convenios', 'convenio_fechamentos', 'vale_gas',
            'monitora_veiculos', 'monitora_cercas', 'monitora_posicoes', 'monitora_ultima_posicao',
            'cartao_transacoes', 'gasdopovo_beneficios', 'pagamentos_online',
            'pos_vendas', 'meta_vendas', 'checklist_execucoes', 'checklist_respostas',
        ];

        return array_values(array_filter(
            $candidatas,
            fn (string $t) => Schema::hasTable($t) && Schema::hasColumn($t, 'empresa_id'),
        ));
    }
};
