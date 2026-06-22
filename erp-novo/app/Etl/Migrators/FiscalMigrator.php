<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Fiscal\NotaItem;
use Illuminate\Support\Facades\DB;

/**
 * N9 — migra notas fiscais + itens + a NUMERAÇÃO ATUAL da empresa (para a
 * próxima nota emitida no banco novo continuar a sequência sem duplicar).
 */
final class FiscalMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'fiscal';
    }

    public function dependeDe(): array
    {
        return ['clientes', 'produtos', 'pedidos'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;
        $notas = $this->lerNotas($ctx);
        $itens = $this->lerItens($ctx);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($notas as $n) {
                NotaFiscal::withoutTenant()->updateOrCreate(['id' => $n['id']], $n);
                $gravados++;
            }
            foreach ($itens as $i) {
                NotaItem::updateOrCreate(['id' => $i['id']], $i);
                $gravados++;
            }
            $this->semearNumeracao($notas);
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($notas) + count($itens),
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
            new CountInvariant($ctx, 'notas_fiscais', 'notas_fiscais'),
            new IntegrityInvariant($ctx, 'nota_itens', 'nota_fiscal_id', 'notas_fiscais'),
        ];
    }

    /**
     * Semeia a sequência de numeração com o MAIOR número já usado por
     * empresa+modelo+série, para o emitir() no banco novo continuar daí.
     *
     * @param list<array<string,mixed>> $notas
     */
    private function semearNumeracao(array $notas): void
    {
        $max = [];
        foreach ($notas as $n) {
            $chave = "nf:{$n['empresa_id']}:{$n['modelo']}:{$n['serie']}";
            $max[$chave] = max($max[$chave] ?? 0, (int) $n['numero']);
        }
        foreach ($max as $chave => $valor) {
            DB::table('sequencias')->updateOrInsert(['chave' => $chave], ['valor' => $valor]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function lerNotas(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('notas_fiscais')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'cliente_id' => $r->cliente_id ?? null,
            'pedido_id' => $r->pedido_id ?? null,
            'modelo' => (string) ($r->modelo ?? '55'),
            'tipo' => (string) ($r->tipo ?? 'S'),
            'serie' => (int) ($r->serie ?? 1),
            'numero' => (int) ($r->numero ?? 0),
            'chave' => $r->chave ?? null,
            'protocolo' => $r->protocolo ?? null,
            'valor_produtos' => (float) ($r->valor_produtos ?? 0),
            'valor_total' => (float) ($r->valor_total ?? 0),
            'situacao' => strtoupper((string) ($r->situacao ?? 'AUTORIZADA')),
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function lerItens(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('nota_itens')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'nota_fiscal_id' => (int) $r->nota_fiscal_id,
            'produto_id' => (int) $r->produto_id,
            'numero_item' => (int) ($r->numero_item ?? 1),
            'quantidade' => (float) ($r->quantidade ?? 0),
            'valor_unitario' => (float) ($r->valor_unitario ?? 0),
            'valor_total' => (float) ($r->valor_total ?? 0),
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
