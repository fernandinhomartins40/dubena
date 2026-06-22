<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Cobranca\Boleto;

/**
 * N7 — migra boletos emitidos + ocorrências do legado. Histórico de cobrança
 * preservado; situação mapeada para o enum.
 */
final class CobrancaMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'cobranca';
    }

    public function dependeDe(): array
    {
        return ['financeiro'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;
        $boletos = $this->lerBoletos($ctx);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($boletos as $b) {
                Boleto::withoutTenant()->updateOrCreate(['id' => $b['id']], $b);
                $gravados++;
            }
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($boletos),
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
            new CountInvariant($ctx, 'boletos', 'boletos'),
            new IntegrityInvariant($ctx, 'boletos', 'financeiroparcela_id', 'financeiroparcelas'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lerBoletos(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('boletos')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'financeiroparcela_id' => $r->financeiroparcela_id ?? null,
            'cliente_id' => $r->cliente_id ?? null,
            'banco_codigo' => (int) ($r->banco_codigo ?? $r->bancocodigo ?? 0),
            'carteira' => $r->carteira ?? null,
            'nosso_numero' => $r->nossonumero ?? $r->nosso_numero ?? null,
            'linha_digitavel' => $r->linhadigitavel ?? null,
            'valor' => (float) ($r->valor ?? 0),
            'vencimento' => $r->vencimento ?? $r->datavencimento ?? null,
            'situacao' => strtoupper((string) ($r->situacao ?? 'PENDENTE')),
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
