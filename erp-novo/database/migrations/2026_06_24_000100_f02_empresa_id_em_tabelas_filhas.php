<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F02.2 — Multi-tenancy: denormaliza `empresa_id` nas tabelas-FILHAS que herdavam
 * o tenant apenas do pai. Sem essa coluna, o trait BelongsToTenant e o RLS não
 * conseguem escopar a filha — origem do IDOR apontado na auditoria (§5/§8).
 *
 * Para cada filha: adiciona empresa_id (nullable p/ backfill), backfilla a partir
 * da coluna empresa_id do PAI, e indexa. Idempotente (só age se a coluna faltar).
 */
return new class extends Migration
{
    /**
     * filha => [tabela_pai, fk_na_filha, coluna_id_do_pai]
     *
     * @var array<string, array{0:string,1:string,2:string}>
     */
    private array $mapa = [
        'clientedependentes' => ['clientes', 'cliente_id', 'id'],
        'clienteinteracoes' => ['clientes', 'cliente_id', 'id'],
        'clienteprecos' => ['clientes', 'cliente_id', 'id'],
        'clientetelefones' => ['clientes', 'cliente_id', 'id'],
        'financeiroparcelas' => ['financeiros', 'financeiro_id', 'id'],
        'financeirorateios' => ['financeiros', 'financeiro_id', 'id'],
        'pedidoitens' => ['pedidos', 'pedido_id', 'id'],
        'pedidosituacaohistorico' => ['pedidos', 'pedido_id', 'id'],
        'nota_itens' => ['notas_fiscais', 'nota_fiscal_id', 'id'],
        'nf_recebida_itens' => ['nf_recebidas', 'nf_recebida_id', 'id'],
        'estoque_inventario_itens' => ['estoque_inventarios', 'estoque_inventario_id', 'id'],
        'checklist_respostas' => ['checklist_execucoes', 'checklist_execucao_id', 'id'],
        'veiculo_abastecimentos' => ['veiculos', 'veiculo_id', 'id'],
        'veiculo_pneus' => ['veiculos', 'veiculo_id', 'id'],
        'veiculo_trocas_oleo' => ['veiculos', 'veiculo_id', 'id'],
        'colaborador_exames' => ['colaboradores', 'colaborador_id', 'id'],
        'colaborador_pontos' => ['colaboradores', 'colaborador_id', 'id'],
        'colaborador_turnos' => ['colaboradores', 'colaborador_id', 'id'],
        'comissao_excecoes' => ['colaborador_comissoes', 'colaborador_comissao_id', 'id'],
        'produtoorigens' => ['produtos', 'produto_id', 'id'],
        // Filhas que a triagem inicial marcou como "já tinham" mas NÃO tinham
        // (confirmado por Schema::hasColumn). Pais são empresa-scoped.
        'boleto_ocorrencias' => ['boletos', 'boleto_id', 'id'],
        'cupom_fiscal_itens' => ['cupons_fiscais', 'cupom_fiscal_id', 'id'],
        'monitora_posicoes' => ['monitora_veiculos', 'veiculo_id', 'id'],
        'monitora_ultima_posicao' => ['monitora_veiculos', 'veiculo_id', 'id'],
        'colaborador_familias' => ['colaboradores', 'colaborador_id', 'id'],
        'colaborador_recessos' => ['colaboradores', 'colaborador_id', 'id'],
    ];

    public function up(): void
    {
        foreach ($this->mapa as $filha => [$pai, $fk, $paiId]) {
            if (! Schema::hasTable($filha) || Schema::hasColumn($filha, 'empresa_id')) {
                continue;
            }

            Schema::table($filha, function (Blueprint $t) {
                $t->unsignedBigInteger('empresa_id')->nullable()->after('id');
            });

            // Backfill a partir do pai (UPDATE ... FROM no pgsql; subquery nos demais).
            $this->backfill($filha, $pai, $fk, $paiId);

            Schema::table($filha, function (Blueprint $t) {
                $t->index('empresa_id');
            });
        }
    }

    private function backfill(string $filha, string $pai, string $fk, string $paiId): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                "UPDATE {$filha} AS f SET empresa_id = p.empresa_id
                 FROM {$pai} AS p WHERE p.{$paiId} = f.{$fk} AND f.empresa_id IS NULL"
            );

            return;
        }

        // sqlite/mysql: subconsulta correlacionada.
        DB::statement(
            "UPDATE {$filha} SET empresa_id = (
                SELECT p.empresa_id FROM {$pai} p WHERE p.{$paiId} = {$filha}.{$fk}
             ) WHERE empresa_id IS NULL"
        );
    }

    public function down(): void
    {
        foreach (array_keys($this->mapa) as $filha) {
            if (Schema::hasTable($filha) && Schema::hasColumn($filha, 'empresa_id')) {
                Schema::table($filha, function (Blueprint $t) {
                    $t->dropColumn('empresa_id');
                });
            }
        }
    }
};
