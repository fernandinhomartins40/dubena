<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Mobile\AppDevice;
use App\Models\Mobile\PagamentoOnline;

/**
 * N10 — migra devices do app e transações de pagamento online do legado.
 */
final class MobileMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'mobile';
    }

    public function dependeDe(): array
    {
        return ['empresas', 'pedidos'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $devices = $this->ler($ctx, 'app_devices', fn ($r) => [
            'id' => (int) $r->id,
            'user_id' => (int) $r->user_id,
            'empresa_id' => $r->empresa_id ?? null,
            'plataforma' => $r->plataforma ?? null,
            'push_token' => $r->push_token ?? $r->pushtoken ?? null,
            'device_id' => $r->device_id ?? null,
            'ativo' => (bool) ($r->ativo ?? true),
        ]);

        $pagamentos = $this->ler($ctx, 'pagamentos_online', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'pedido_id' => $r->pedido_id ?? null,
            'cliente_id' => $r->cliente_id ?? null,
            'gateway' => (string) ($r->gateway ?? 'rede'),
            'tid' => $r->tid ?? null,
            'nsu' => $r->nsu ?? null,
            'valor' => (float) ($r->valor ?? 0),
            'parcelas' => (int) ($r->parcelas ?? 1),
            'situacao' => strtoupper((string) ($r->situacao ?? 'CAPTURADO')),
        ]);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($devices as $d) {
                AppDevice::updateOrCreate(['id' => $d['id']], $d);
                $gravados++;
            }
            foreach ($pagamentos as $p) {
                PagamentoOnline::withoutTenant()->updateOrCreate(['id' => $p['id']], $p);
                $gravados++;
            }
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($devices) + count($pagamentos),
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
            new CountInvariant($ctx, 'pagamentos_online', 'pagamentos_online'),
            new IntegrityInvariant($ctx, 'app_devices', 'user_id', 'users'),
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
