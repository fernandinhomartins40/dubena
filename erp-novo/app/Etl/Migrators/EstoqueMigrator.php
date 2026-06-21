<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\BalanceInvariant;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;

/**
 * N3 — migra setores + saldos + histórico de estoque.
 *
 * INVARIANTE-CHAVE (baseline do plano): Σ estoquehistorico.quantidade (por
 * setor×produto) = estoquesaldos.quantidade. Migra o histórico fielmente
 * (quantidade assinada) e o saldo materializado; a BalanceInvariant prova que
 * a materialização não divergiu do log.
 */
final class EstoqueMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'estoque';
    }

    public function dependeDe(): array
    {
        return ['produtos'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $setores = $this->lerSetores($ctx);
        $saldos = $this->lerSaldos($ctx);
        $historico = $this->lerHistorico($ctx);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($setores as $s) {
                Setor::withoutTenant()->updateOrCreate(['id' => $s['id']], $s);
                $gravados++;
            }
            foreach ($saldos as $s) {
                EstoqueSaldo::withoutTenant()->updateOrCreate(
                    ['setor_id' => $s['setor_id'], 'produto_id' => $s['produto_id']],
                    $s,
                );
                $gravados++;
            }
            foreach ($historico as $h) {
                EstoqueHistorico::withoutTenant()->updateOrCreate(['id' => $h['id']], $h);
                $gravados++;
            }
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($setores) + count($saldos) + count($historico),
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
            new CountInvariant($ctx, 'estoquesetorhistorico', 'estoquehistorico'),
            // A invariante de ouro do estoque (princípio #5).
            new BalanceInvariant($ctx, 'estoquehistorico', 'quantidade', 'estoquesaldos', 'quantidade', ['setor_id', 'produto_id']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lerSetores(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('setors')->get(['id', 'empresa_id', 'grupo_id', 'descricao', 'ativo']);
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) ($r->grupo_id ?? 0),
            'descricao' => trim((string) $r->descricao),
            'ativo' => (bool) ($r->ativo ?? true),
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function lerSaldos(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('estoquesetor')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'empresa_id' => (int) $r->empresa_id,
            'setor_id' => (int) $r->setor_id,
            'produto_id' => (int) $r->produto_id,
            'quantidade' => (float) ($r->quantidade ?? 0),
            'quantidade_minima' => isset($r->quantidademinima) ? (float) $r->quantidademinima : null,
            'quantidade_maxima' => isset($r->quantidademaxima) ? (float) $r->quantidademaxima : null,
            'custo_medio' => isset($r->customedio) ? (float) $r->customedio : 0,
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function lerHistorico(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('estoquesetorhistorico')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'setor_id' => (int) $r->setor_id,
            'produto_id' => (int) $r->produto_id,
            'tipo' => strtoupper(trim((string) ($r->tipo ?? 'AJUSTE'))),
            'quantidade' => (float) $r->quantidade,
            'custo_unitario' => isset($r->custounitario) ? (float) $r->custounitario : null,
            'saldo_resultante' => (float) ($r->saldoresultante ?? $r->saldo ?? 0),
            'origem' => $r->origem ?? null,
            'origem_id' => $r->origem_id ?? null,
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
