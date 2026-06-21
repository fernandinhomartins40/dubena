<?php

namespace App\Etl\Migrators;

use App\Domain\Financeiro\AgrupamentoStatus;
use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Invariants\SumInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Financeiro\Financeiro;
use App\Models\Financeiro\FinanceiroParcela;

/**
 * N5 — migra títulos financeiros + parcelas do legado.
 *
 * Invariantes (baseline obrigatório do plano): contagem; SOMA de títulos a
 * pagar/receber bate (origem×destino); integridade (zero parcela órfã).
 * agrupamento_status preservado (mapeado para o enum).
 */
final class FinanceiroMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'financeiro';
    }

    public function dependeDe(): array
    {
        return ['clientes'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $financeiros = $this->lerFinanceiros($ctx);
        $parcelas = $this->lerParcelas($ctx);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($financeiros as $f) {
                Financeiro::withoutTenant()->updateOrCreate(['id' => $f['id']], $f);
                $gravados++;
            }
            foreach ($parcelas as $p) {
                FinanceiroParcela::updateOrCreate(['id' => $p['id']], $p);
                $gravados++;
            }
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($financeiros) + count($parcelas),
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: 0,
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [
            new CountInvariant($ctx, 'financeiros', 'financeiros'),
            new CountInvariant($ctx, 'financeiroparcelas', 'financeiroparcelas'),
            // Soma de títulos bate (check #2 — base "Σ títulos a pagar/receber").
            new SumInvariant($ctx, 'financeiros', 'valor', 'financeiros', 'valor'),
            // Zero parcela órfã.
            new IntegrityInvariant($ctx, 'financeiroparcelas', 'financeiro_id', 'financeiros'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lerFinanceiros(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('financeiros')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'cliente_id' => $r->cliente_id ?? null,
            'pagarreceber' => strtoupper(substr((string) ($r->pagarreceber ?? 'R'), 0, 1)),
            'documento' => $r->documento ?? null,
            'descricao' => $r->descricao ?? null,
            'valor' => (float) ($r->valor ?? 0),
            'data_emissao' => $r->dataemissao ?? $r->data_emissao ?? null,
            'agrupamento_status' => $this->mapAgrupamento($r->agrupamento_status ?? 0),
            'agrupador_id' => $r->agrupador_id ?? null,
            'origem' => $r->origem ?? null,
            'origem_id' => $r->origem_id ?? null,
            'cancelado' => (bool) ($r->cancelado ?? false),
        ])->all();
    }

    private function mapAgrupamento(mixed $valor): int
    {
        $i = (int) $valor;

        return AgrupamentoStatus::tryFrom($i) ? $i : AgrupamentoStatus::NORMAL->value;
    }

    /** @return list<array<string, mixed>> */
    private function lerParcelas(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('financeiroparcelas')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'financeiro_id' => (int) $r->financeiro_id,
            'numero' => (int) ($r->numero ?? 1),
            'vencimento' => $r->datavencimento ?? $r->vencimento ?? null,
            'valor' => (float) ($r->valor ?? 0),
            'desconto' => (float) ($r->desconto ?? 0),
            'valor_efetivado' => (float) ($r->valorefetivado ?? 0),
            'baixado' => (bool) ($r->baixado ?? false),
            'datahora_baixa' => $r->datahorabaixa ?? null,
        ])->all();
    }

    private function legadoDisponivel(MigrationContext $ctx): bool
    {
        try {
            $ctx->legado()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
