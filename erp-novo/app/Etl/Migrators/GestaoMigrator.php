<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Gestao\CupomFiscal;
use App\Models\Gestao\CupomFiscalItem;
use App\Models\Gestao\Documento;
use App\Models\Gestao\EmpresaBem;
use App\Models\Gestao\Mcmm;

/**
 * F15 — migra Gestão: cupons fiscais (+itens), MCMM (mapa ANP), documentos e bens.
 * Filhas herdam empresa_id; conversões legado→novo de nomes + flags.
 */
final class GestaoMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'gestao';
    }

    public function dependeDe(): array
    {
        return ['empresas', 'produtos', 'pedidos'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $cupons = $this->ler($ctx, 'cuponsfiscais', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'pedido_id' => $r->pedido_id ?? null,
            'numero' => $r->numero ?? null,
            'chave' => $r->chave ?? null,
            'valor_total' => isset($r->valortotal) ? (float) $r->valortotal : null,
            'situacao' => $r->situacao ?? null,
            'emitido_em' => $r->emitidoem ?? null,
        ]);
        $cupomItens = $this->ler($ctx, 'cupomfiscalitens', fn ($r) => [
            'id' => (int) $r->id,
            'cupom_fiscal_id' => (int) $r->cupomfiscal_id,
            'produto_id' => $r->produto_id ?? null,
            'quantidade' => isset($r->quantidade) ? (float) $r->quantidade : null,
            'valor_unitario' => isset($r->valorunitario) ? (float) $r->valorunitario : null,
            'valor_total' => isset($r->valortotal) ? (float) $r->valortotal : null,
        ]);
        $mcmms = $this->ler($ctx, 'mcmms', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'data' => $r->data ?? null,
            'descricao' => $r->descricao ?? null,
            'tipo' => $r->tipo ?? null,
            'quantidade' => isset($r->quantidade) ? (float) $r->quantidade : null,
            'produto_id' => $r->produto_id ?? null,
        ]);
        $bens = $this->ler($ctx, 'empresabens', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'descricao' => trim((string) ($r->descricao ?? '')),
            'data_aquisicao' => $r->dataaquisicao ?? null,
            'valor_aquisicao' => isset($r->valoraquisicao) ? (float) $r->valoraquisicao : null,
            'taxa_depreciacao_anual' => isset($r->taxadepreciacaoanual) ? (float) $r->taxadepreciacaoanual : null,
            'valor_residual' => isset($r->valorresidual) ? (float) $r->valorresidual : null,
            'ativo' => (bool) ($r->ativo ?? true),
        ]);
        $documentos = $this->ler($ctx, 'documentos', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) ($r->empresa_id ?? 0),
            'arquivo_path' => $r->arquivopath ?? ($r->arquivo ?? null),
        ]);

        $gravados = 0;
        if (! $ctx->dryRun) {
            $gravados += $this->gravarTenant(CupomFiscal::class, $cupons);
            $gravados += $this->gravar(CupomFiscalItem::class, $cupomItens);
            $gravados += $this->gravarTenant(Mcmm::class, $mcmms);
            $gravados += $this->gravarTenant(EmpresaBem::class, $bens);
            $gravados += $this->gravarTenant(Documento::class, $documentos);
        }

        $lidos = count($cupons) + count($cupomItens) + count($mcmms) + count($bens) + count($documentos);

        return new MigrationResult($this->nome(), $lidos, $ctx->dryRun ? 0 : $gravados, 0);
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [
            new CountInvariant($ctx, 'cuponsfiscais', 'cupons_fiscais'),
            new CountInvariant($ctx, 'mcmms', 'mcmms'),
            new IntegrityInvariant($ctx, 'cupons_fiscais', 'empresa_id', 'empresas'),
            new IntegrityInvariant($ctx, 'cupom_fiscal_itens', 'cupom_fiscal_id', 'cupons_fiscais'),
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

    /** @param class-string $model @param list<array<string,mixed>> $rows */
    private function gravarTenant(string $model, array $rows): int
    {
        $n = 0;
        foreach ($rows as $row) {
            $this->upsert($model::withoutTenant(), $this->semNulos($row));
            $n++;
        }

        return $n;
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model @param list<array<string,mixed>> $rows */
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
