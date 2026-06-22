<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Monitora\Cerca;
use App\Models\Monitora\UltimaPosicao;
use App\Models\Monitora\Veiculo;

/**
 * N11 — migra o schema isolado do Monitora (veículos, cercas, última posição).
 * O histórico completo de posições (volumoso) fica fora do ETL de cutover por
 * padrão; migra-se a ÚLTIMA posição (suficiente para o mapa pós-cutover).
 */
final class MonitoraMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'monitora';
    }

    public function dependeDe(): array
    {
        return ['empresas'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $veiculos = $this->ler($ctx, 'monitora_veiculos', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'placa' => (string) $r->placa,
            'descricao' => $r->descricao ?? null,
            'imei' => $r->imei ?? null,
            'ativo' => (bool) ($r->ativo ?? true),
        ]);

        $cercas = $this->ler($ctx, 'monitora_cercas', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'descricao' => (string) ($r->descricao ?? 'Cerca'),
            'centro_lat' => (float) $r->centro_lat,
            'centro_lng' => (float) $r->centro_lng,
            'raio_metros' => (float) ($r->raio_metros ?? 0),
            'ativo' => (bool) ($r->ativo ?? true),
        ]);

        $ultimas = $this->ler($ctx, 'monitora_ultima_posicao', fn ($r) => [
            'veiculo_id' => (int) $r->veiculo_id,
            'latitude' => (float) $r->latitude,
            'longitude' => (float) $r->longitude,
            'velocidade' => (float) ($r->velocidade ?? 0),
            'ignicao' => (bool) ($r->ignicao ?? false),
            'registrado_em' => $r->registrado_em ?? now(),
        ]);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($veiculos as $v) {
                Veiculo::withoutTenant()->updateOrCreate(['id' => $v['id']], $v);
                $gravados++;
            }
            foreach ($cercas as $c) {
                Cerca::withoutTenant()->updateOrCreate(['id' => $c['id']], $c);
                $gravados++;
            }
            foreach ($ultimas as $u) {
                UltimaPosicao::updateOrCreate(['veiculo_id' => $u['veiculo_id']], $u);
                $gravados++;
            }
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($veiculos) + count($cercas) + count($ultimas),
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

        return [new CountInvariant($ctx, 'monitora_veiculos', 'monitora_veiculos')];
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
