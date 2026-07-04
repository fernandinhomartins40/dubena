<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MT-3/DB-4 (auditoria) — `empresa_id NOT NULL` nas tabelas-FILHAS já backfilladas.
 *
 * A F02 (2026_06_24_000100) adicionou empresa_id NULLABLE nas filhas e backfillou
 * a partir do pai. Enquanto a coluna aceita NULL, uma linha com empresa_id NULL é
 * VISÍVEL a todos os tenants pela policy de RLS (`nullif(...) IS NULL → true`), o
 * que reabre o vazamento que a F02 fechou. Aqui fixamos NOT NULL — mas só quando
 * é SEGURO: se ainda houver linha com empresa_id NULL (backfill incompleto no dump
 * real), a tabela é PULADA e um aviso é logado, em vez de a migration explodir.
 *
 * As tabelas de AUDITORIA (audit_logs/login_logs/security_events/platform_audit_logs)
 * e as de AUTH/RBAC (users/role_user/role_versions) NÃO entram: recebem empresa_id
 * NULL por design (estão na allowlist da RLS). app_devices também fica de fora
 * (device pode existir antes de resolver empresa).
 *
 * NO-OP fora do pgsql (sqlite de teste não tem o problema de RLS). Idempotente.
 */
return new class extends Migration
{
    /** @var list<string> Filhas tenant que devem ter empresa_id obrigatório. */
    private array $filhas = [
        'clientedependentes', 'clienteinteracoes', 'clienteprecos', 'clientetelefones',
        'financeiroparcelas', 'financeirorateios',
        'pedidoitens', 'pedidosituacaohistorico',
        'nota_itens', 'nf_recebida_itens',
        'estoque_inventario_itens', 'checklist_respostas',
        'veiculo_abastecimentos', 'veiculo_pneus', 'veiculo_trocas_oleo',
        'colaborador_exames', 'colaborador_pontos', 'colaborador_turnos',
        'colaborador_familias', 'colaborador_recessos',
        'comissao_excecoes', 'produtoorigens',
        'boleto_ocorrencias', 'cupom_fiscal_itens',
        'monitora_posicoes', 'monitora_ultima_posicao', 'monitora_cerca_pontos',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->filhas as $tabela) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'empresa_id')) {
                continue;
            }

            // Já é NOT NULL? Nada a fazer.
            $nullable = DB::selectOne(
                "SELECT is_nullable FROM information_schema.columns
                 WHERE table_schema='public' AND table_name=? AND column_name='empresa_id'",
                [$tabela],
            );
            if ($nullable === null || $nullable->is_nullable === 'NO') {
                continue;
            }

            // Segurança: só fixa NOT NULL se NÃO há linha órfã (empresa_id NULL).
            $orfas = DB::selectOne("SELECT count(*) AS n FROM {$tabela} WHERE empresa_id IS NULL");
            if ((int) $orfas->n > 0) {
                // Deixa NULLABLE e avisa: backfill incompleto — resolver antes do go-live.
                // (Sem isto, o ALTER falharia e abortaria o deploy inteiro.)
                echo "  [MT-3] PULADO {$tabela}: {$orfas->n} linha(s) com empresa_id NULL — rode o backfill da F02 e reaplique.\n";

                continue;
            }

            DB::statement("ALTER TABLE {$tabela} ALTER COLUMN empresa_id SET NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->filhas as $tabela) {
            if (Schema::hasTable($tabela) && Schema::hasColumn($tabela, 'empresa_id')) {
                DB::statement("ALTER TABLE {$tabela} ALTER COLUMN empresa_id DROP NOT NULL");
            }
        }
    }
};
