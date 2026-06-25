<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Frota\Veiculo;
use App\Models\Frota\VeiculoAbastecimento;
use App\Models\Frota\VeiculoPneu;
use App\Models\Frota\VeiculoTrocaOleo;

/**
 * F15 — migra Frota: veículos + abastecimentos, pneus e trocas de óleo.
 * Conversões: kmatual→km_atual, etc.; flags → boolean. Filhas herdam empresa_id.
 */
final class FrotaMigrator implements Migrator
{
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
        $this->ctxAtual = $ctx;

        $veiculos = $this->ler($ctx, 'veiculos', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'veiculotipo_id' => $r->veiculotipo_id ?? null,
            'tipocombustivel_id' => $r->tipocombustivel_id ?? null,
            'placa' => trim((string) ($r->placa ?? '')),
            'descricao' => $r->descricao ?? null,
            'renavam' => $r->renavam ?? null,
            'km_atual' => isset($r->kmatual) ? (float) $r->kmatual : null,
            'km_troca_oleo' => isset($r->kmtrocaoleo) ? (float) $r->kmtrocaoleo : null,
            'km_ultima_troca_oleo' => isset($r->kmultimatrocaoleo) ? (float) $r->kmultimatrocaoleo : null,
            'ativo' => (bool) ($r->ativo ?? true),
        ]);
        $abastecimentos = $this->ler($ctx, 'veiculoabastecimentos', fn ($r) => [
            'id' => (int) $r->id,
            'veiculo_id' => (int) $r->veiculo_id,
            'data' => $r->data ?? null,
            'km' => isset($r->km) ? (float) $r->km : null,
            'litros' => isset($r->litros) ? (float) $r->litros : null,
            'valor_litro' => isset($r->valorlitro) ? (float) $r->valorlitro : null,
            'valor_total' => isset($r->valortotal) ? (float) $r->valortotal : null,
            'tanque_cheio' => (bool) ($r->tanquecheio ?? false),
        ]);
        $pneus = $this->ler($ctx, 'veiculopneus', fn ($r) => [
            'id' => (int) $r->id,
            'veiculo_id' => (int) $r->veiculo_id,
            'posicao' => $r->posicao ?? null,
            'marca' => $r->marca ?? null,
            'data_instalacao' => $r->datainstalacao ?? null,
            'km_instalacao' => isset($r->kminstalacao) ? (float) $r->kminstalacao : null,
        ]);
        $trocasOleo = $this->ler($ctx, 'veiculotrocaoleos', fn ($r) => [
            'id' => (int) $r->id,
            'veiculo_id' => (int) $r->veiculo_id,
            'data' => $r->data ?? null,
            'km' => isset($r->km) ? (float) $r->km : null,
            'valor' => isset($r->valor) ? (float) $r->valor : null,
            'observacao' => $r->observacao ?? null,
        ]);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($veiculos as $v) {
                $this->upsert(Veiculo::withoutTenant(), $this->semNulos($v));
                $gravados++;
            }
            $gravados += $this->gravar(VeiculoAbastecimento::class, $abastecimentos);
            $gravados += $this->gravar(VeiculoPneu::class, $pneus);
            $gravados += $this->gravar(VeiculoTrocaOleo::class, $trocasOleo);
        }

        $lidos = count($veiculos) + count($abastecimentos) + count($pneus) + count($trocasOleo);

        return new MigrationResult($this->nome(), $lidos, $ctx->dryRun ? 0 : $gravados, 0);
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [
            new CountInvariant($ctx, 'veiculos', 'veiculos'),
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
            return $ctx->legado()->table($tabela)->get()->map($map)->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  list<array<string,mixed>>  $rows
     */
    private function gravar(string $model, array $rows): int
    {
        $n = 0;
        foreach ($rows as $row) {
            $this->upsert($model::query(), $this->semNulos($row));
            $n++;
        }

        return $n;
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

    /**
     * Upsert PRESERVANDO o id do legado (forceFill ignora $fillable). Essencial
     * para manter as FKs entre tabelas após a migração.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<string,mixed>  $row
     */
    private function upsert(\Illuminate\Database\Eloquent\Builder $query, array $row): void
    {
        $model = $query->firstWhere('id', $row['id']) ?? $query->getModel()->newInstance();
        $model->forceFill($row)->save();
    }
    /**
     * Remove chaves null (exceto id) p/ colunas NOT NULL com DEFAULT usarem o default.
     *
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function semNulos(array $row): array
    {
        return array_filter($row, fn ($v, $k) => $v !== null || $k === 'id', ARRAY_FILTER_USE_BOTH);
    }
}
