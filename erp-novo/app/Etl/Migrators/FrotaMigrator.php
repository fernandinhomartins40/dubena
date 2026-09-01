<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;

/**
 * F15 — migra Frota: tipos (veículo/combustível), veículos, abastecimentos,
 * pneus e trocas de óleo.
 *
 * REESCRITO após a auditoria 2026-08-14: a versão anterior migrava zero linhas
 * porque as tabelas não estavam no espelho; e as colunas assumidas não batiam
 * com o legado real. Diferenças de modelagem resolvidas aqui:
 *
 *  - abastecimento legado NÃO guarda valor/litro (só km e total de litros);
 *  - pneu legado é um EVENTO de troca (data/km/valor/medida), não uma posição
 *    instalada — medida vira `marca`, data/km viram instalação;
 *  - troca de óleo: km da troca = `kmultimatrocaoleo`.
 */
final class FrotaMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'frota';
    }

    public function dependeDe(): array
    {
        return ['empresas', 'cadastros-apoio'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->usarConexaoDe($ctx);

        $this->ctxAtual = $ctx;

        if (! $this->tabelaExiste($ctx, 'veiculos')) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['tabela `veiculos` ausente no espelho do legado — frota NÃO migrada '
                    .'(re-rodar espelhar_oracle.py)']);
        }

        $lidos = 0;
        $gravados = 0;

        // ── Catálogos (FK do veículo) ──
        $tipos = $this->ler($ctx, 'veiculotipos', fn ($r) => [
            'id' => (int) $r->id,
            'grupo_id' => (int) $r->grupo_id,
            'descricao' => trim((string) $r->descricao),
            'ativo' => $this->booleano($r->ativo ?? '1'),
            'created_at' => $r->created_at ?? null,
        ]);
        $combustiveis = $this->ler($ctx, 'tipocombustivels', fn ($r) => [
            'id' => (int) $r->id,
            'grupo_id' => (int) $r->grupo_id,
            'descricao' => trim((string) $r->descricao),
            'ativo' => $this->booleano($r->ativo ?? '1'),
            'created_at' => $r->created_at ?? null,
        ]);
        if (! $ctx->dryRun) {
            $gravados += $this->gravarPreservandoId('veiculo_tipos', $tipos);
            $gravados += $this->gravarPreservandoId('tipo_combustiveis', $combustiveis);
        }
        $lidos += count($tipos) + count($combustiveis);

        // ── Veículos ──
        $veiculos = $this->ler($ctx, 'veiculos', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'veiculotipo_id' => ($r->veiculotipo_id ?? null) !== null ? (int) $r->veiculotipo_id : null,
            'tipocombustivel_id' => ($r->tipocombustivel_id ?? null) !== null ? (int) $r->tipocombustivel_id : null,
            'placa' => mb_substr(trim((string) ($r->placa ?? '')), 0, 10) ?: 'S/PLACA',
            'descricao' => mb_substr(trim((string) ($r->descricao ?? '')), 0, 255)
                ?: mb_substr(trim((string) ($r->placa ?? '')), 0, 255) ?: "Veículo {$r->id}",
            'km_atual' => (float) ($r->kmatual ?? 0),
            'km_troca_oleo' => isset($r->kmtrocaoleo) ? (float) $r->kmtrocaoleo : null,
            'km_ultima_troca_oleo' => isset($r->kmultimatrocaoleo) ? (float) $r->kmultimatrocaoleo : null,
            'ativo' => $this->booleano($r->ativo ?? '1'),
            'created_at' => $r->created_at ?? null,
        ]);
        $veiculos = $this->anularFksInvalidas($veiculos, [
            'veiculotipo_id' => 'veiculo_tipos',
            'tipocombustivel_id' => 'tipo_combustiveis',
        ]);
        if (! $ctx->dryRun) {
            $gravados += $this->gravarPreservandoId('veiculos', $veiculos);
        }
        $lidos += count($veiculos);
        $idsVeiculo = array_flip(array_map(fn ($v) => $v['id'], $veiculos));
        $empresaDoVeiculo = [];
        foreach ($veiculos as $v) {
            $empresaDoVeiculo[$v['id']] = $v['empresa_id'];
        }

        $pulados = 0;
        $filhas = function (string $tabela, callable $map) use ($ctx, $idsVeiculo, $empresaDoVeiculo, &$lidos, &$pulados) {
            $out = [];
            foreach ($this->ler($ctx, $tabela, $map) as $row) {
                $lidos++;
                $veiculo = (int) $row['veiculo_id'];
                if (! isset($idsVeiculo[$veiculo])) {
                    $pulados++;

                    continue;
                }
                $row['empresa_id'] = $empresaDoVeiculo[$veiculo];
                $out[] = $row;
            }

            return $out;
        };

        // ── Abastecimentos (legado: km + total de litros, sem valor) ──
        $abastecimentos = $filhas('veiculoabastecimentos', fn ($r) => [
            'id' => (int) $r->id,
            'veiculo_id' => (int) $r->veiculo_id,
            'data' => $r->data ?? null,
            'km' => isset($r->kmatual) ? (float) $r->kmatual : null,
            'litros' => isset($r->totallitros) ? (float) $r->totallitros : null,
            'created_at' => $r->created_at ?? null,
        ]);

        // ── Pneus (evento de troca → registro de instalação) ──
        $pneus = $filhas('veiculopneus', fn ($r) => [
            'id' => (int) $r->id,
            'veiculo_id' => (int) $r->veiculo_id,
            'marca' => mb_substr(trim((string) ($r->medidapneus ?? '')), 0, 60) ?: null,
            'data_instalacao' => $r->data ?? null,
            'km_instalacao' => isset($r->km) ? (float) $r->km : null,
            'created_at' => $r->created_at ?? null,
        ]);

        // ── Trocas de óleo ──
        $trocasOleo = $filhas('veiculotrocaoleos', fn ($r) => [
            'id' => (int) $r->id,
            'veiculo_id' => (int) $r->veiculo_id,
            'data' => $r->data ?? null,
            'km' => isset($r->kmultimatrocaoleo) ? (float) $r->kmultimatrocaoleo : null,
            'created_at' => $r->created_at ?? null,
        ]);

        if (! $ctx->dryRun) {
            $gravados += $this->gravarPreservandoId('veiculo_abastecimentos', $abastecimentos);
            $gravados += $this->gravarPreservandoId('veiculo_pneus', $pneus);
            $gravados += $this->gravarPreservandoId('veiculo_trocas_oleo', $trocasOleo);
        }

        return new MigrationResult($this->nome(), $lidos, $ctx->dryRun ? 0 : $gravados, $pulados);
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext;
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [
            new CountInvariant($ctx, 'veiculos', 'veiculos'),
            new CountInvariant($ctx, 'veiculoabastecimentos', 'veiculo_abastecimentos'),
            new IntegrityInvariant($ctx, 'veiculos', 'empresa_id', 'empresas'),
            new IntegrityInvariant($ctx, 'veiculo_abastecimentos', 'veiculo_id', 'veiculos'),
        ];
    }

    /**
     * @param  callable(object):array<string,mixed>  $map
     * @return list<array<string,mixed>>
     */
    private function ler(MigrationContext $ctx, string $tabela, callable $map): array
    {
        try {
            return $ctx->legado()->table($tabela)->orderBy('id')->get()->map($map)->all();
        } catch (\Throwable) {
            return [];
        }
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

    private function tabelaExiste(MigrationContext $ctx, string $tabela): bool
    {
        try {
            return $ctx->legado()->getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }

    private function booleano(mixed $v): bool
    {
        return in_array(mb_strtoupper(trim((string) ($v ?? ''))), ['1', 'S', 'T', 'TRUE', 'Y'], true);
    }
}
