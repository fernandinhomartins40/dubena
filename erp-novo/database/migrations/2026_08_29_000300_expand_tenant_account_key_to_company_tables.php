<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1-03: expansao aditiva e deterministica da chave SaaS nas tabelas COMPANY.
 *
 * A coluna e nullable no periodo de conversao. Nenhuma migration faz backfill:
 * F1-10 so podera preencher a partir de tenant_companies aprovado.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $companyTables = [
        'alcada_descontos',
        'alertas',
        'app_devices',
        'audit_logs',
        'boleto_historicos',
        'boleto_ocorrencias',
        'boletos',
        'cargos',
        'cartao_transacoes',
        'cartas_correcao',
        'centros_custo',
        'checklist_execucoes',
        'checklist_perguntas',
        'checklist_respostas',
        'checklists',
        'cheques',
        'cliente_enderecos',
        'cliente_identidades',
        'cliente_revisoes',
        'cliente_vinculos',
        'clientecontatosituacoes',
        'clientecontatotipos',
        'clientedependentes',
        'clienteinteracoes',
        'clienteprecos',
        'clientes',
        'clientetelefones',
        'colaborador_comissoes',
        'colaborador_exames',
        'colaborador_familias',
        'colaborador_pontos',
        'colaborador_recessos',
        'colaborador_turnos',
        'colaboradores',
        'comissao_excecoes',
        'comodato_avaliacoes',
        'comodato_config',
        'comodato_contratos',
        'comodato_movimentos',
        'comodatos',
        'condicaopagamento_parcelas',
        'condicaopagamentos',
        'config_fiscais',
        'config_globais',
        'conta_extrato_regras',
        'conta_transferencias',
        'contafechamentos',
        'contamovimento_estornos',
        'contamovimentos',
        'contamovimentotipos',
        'contas',
        'convenio_fechamento_pedidos',
        'convenio_fechamentos',
        'convenios',
        'cupom_fiscal_itens',
        'cupons_fiscais',
        'departamentos',
        'documentos',
        'empresa_bens',
        'empresa_cidade',
        'empresa_configs',
        'empresa_user',
        'empresas',
        'entregador_bloqueios',
        'estoque_acertos',
        'estoque_inventario_itens',
        'estoque_inventarios',
        'estoque_requisicoes',
        'estoque_transferencia_itens',
        'estoque_transferencias',
        'estoquehistorico',
        'estoquesaldos',
        'financeiroparcelas',
        'financeirorateios',
        'financeiros',
        'gasdopovo_beneficios',
        'grupos',
        'inutilizacoes_fiscais',
        'jornadas',
        'login_logs',
        'logistica_configs',
        'malha_fiscal',
        'mcmms',
        'meta_vendas',
        'missao_atribuicoes',
        'missao_evidencias',
        'missao_trilha',
        'missao_visitas',
        'missoes',
        'monitora_cerca_pontos',
        'monitora_cercas',
        'monitora_posicoes',
        'monitora_rotas',
        'monitora_veiculo_tipos',
        'monitora_veiculos',
        'motivos_nao_venda',
        'nf_imposto_estados',
        'nf_impostos',
        'nf_recebida_itens',
        'nf_recebidas',
        'nota_itens',
        'nota_volumes',
        'notas_fiscais',
        'pagamentos_online',
        'pedido_atribuicoes',
        'pedido_avaliacoes',
        'pedido_comprovacoes',
        'pedido_motivos_atraso',
        'pedido_ocorrencias',
        'pedido_solicitacoes',
        'pedidoitens',
        'pedidooperacoes',
        'pedidos',
        'pedidosituacaohistorico',
        'pedidosituacoes',
        'pix_cobrancas',
        'pos_vendas',
        'produto_condicao_precos',
        'produto_operacao_fiscal',
        'produtos',
        'promocoes',
        'promotor_visitas',
        'remessas_cnab',
        'requisicoes_idempotentes',
        'security_events',
        'sequencias',
        'setores',
        'setores_org',
        'sorteio_numeros',
        'sorteios',
        'taxas_entrega',
        'telefonia_chamadas',
        'telefonia_ligacoes',
        'transportadoras',
        'vale_gas',
        'vale_gas_vendas',
        'veiculo_abastecimentos',
        'veiculo_documentos',
        'veiculo_entradas_saidas',
        'veiculo_pneus',
        'veiculo_tipos',
        'veiculo_trocas_oleo',
        'veiculos',
        'venda_ativas',
    ];

    public function up(): void
    {
        foreach ($this->companyTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('tenant_account_id')
                    ->nullable()
                    ->constrained('tenant_accounts')
                    ->restrictOnDelete();
                $table->index('tenant_account_id');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->companyTables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['tenant_account_id']);
                $table->dropConstrainedForeignId('tenant_account_id');
            });
        }
    }
};
