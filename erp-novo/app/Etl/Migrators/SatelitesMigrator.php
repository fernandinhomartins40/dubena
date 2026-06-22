<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\Convenio;
use App\Models\Satelite\ValeGas;

/**
 * N8 — migra convênios, vale-gás e comodatos do legado.
 */
final class SatelitesMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'satelites';
    }

    public function dependeDe(): array
    {
        return ['clientes', 'produtos', 'financeiro'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $convenios = $this->ler($ctx, 'convenios', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'cliente_id' => (int) $r->cliente_id,
            'descricao' => trim((string) ($r->descricao ?? 'Convênio')),
            'dia_fechamento' => (int) ($r->dia_fechamento ?? 1),
            'dia_vencimento' => (int) ($r->dia_vencimento ?? 10),
            'ativo' => (bool) ($r->ativo ?? true),
        ]);

        $vales = $this->ler($ctx, 'valegas', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'cliente_id' => $r->cliente_id ?? null,
            'codigo' => (string) ($r->codigo ?? ('VG-'.$r->id)),
            'valor' => (float) ($r->valor ?? 0),
            'validade' => $r->validade ?? null,
            'situacao' => strtoupper((string) ($r->situacao ?? 'EMITIDO')),
        ]);

        $comodatos = $this->ler($ctx, 'comodatos', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'cliente_id' => (int) $r->cliente_id,
            'produto_id' => (int) $r->produto_id,
            'quantidade' => (float) ($r->quantidade ?? 0),
            'quantidade_devolvida' => (float) ($r->quantidadedevolvida ?? 0),
            'situacao' => strtoupper((string) ($r->situacao ?? 'ATIVO')),
            'data_emprestimo' => $r->dataemprestimo ?? $r->data_emprestimo ?? now()->toDateString(),
        ]);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($convenios as $c) {
                Convenio::withoutTenant()->updateOrCreate(['id' => $c['id']], $c);
                $gravados++;
            }
            foreach ($vales as $v) {
                ValeGas::withoutTenant()->updateOrCreate(['id' => $v['id']], $v);
                $gravados++;
            }
            foreach ($comodatos as $cm) {
                Comodato::withoutTenant()->updateOrCreate(['id' => $cm['id']], $cm);
                $gravados++;
            }
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($convenios) + count($vales) + count($comodatos),
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
            new CountInvariant($ctx, 'valegas', 'vale_gas'),
            new CountInvariant($ctx, 'comodatos', 'comodatos'),
        ];
    }

    /**
     * @param \Closure(object):array<string,mixed> $map
     * @return list<array<string, mixed>>
     */
    private function ler(MigrationContext $ctx, string $tabela, \Closure $map): array
    {
        try {
            $rows = $ctx->legado()->table($tabela)->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map($map)->all();
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
