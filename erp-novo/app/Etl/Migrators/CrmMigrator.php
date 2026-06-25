<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Crm\Checklist;
use App\Models\Crm\ChecklistExecucao;
use App\Models\Crm\MetaVenda;
use App\Models\Crm\PosVenda;
use App\Models\Crm\Promocao;
use App\Models\Crm\Sorteio;
use App\Models\Crm\SorteioNumero;

/**
 * F15 — migra CRM: pós-venda, promoções, sorteios (+números), metas e checklists
 * (+execuções). Promoção/sorteio/checklist são grupo-scoped; pós-venda/meta/execução
 * empresa-scoped. Conversões legado→novo de nomes + flags.
 */
final class CrmMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'crm';
    }

    public function dependeDe(): array
    {
        return ['empresas', 'clientes', 'pedidos'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $posVendas = $this->ler($ctx, 'posvendas', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'cliente_id' => $r->cliente_id ?? null,
            'pedido_id' => $r->pedido_id ?? null,
            'data' => $r->data ?? null,
            'nota' => isset($r->nota) ? (int) $r->nota : null,
            'canal' => $r->canal ?? null,
            'observacao' => $r->observacao ?? null,
            'situacao' => $r->situacao ?? null,
        ]);
        $promocoes = $this->ler($ctx, 'promocoes', fn ($r) => [
            'id' => (int) $r->id,
            'grupo_id' => (int) $r->grupo_id,
            'descricao' => trim((string) ($r->descricao ?? '')),
            'inicio' => $r->inicio ?? ($r->datainicio ?? null),
            'fim' => $r->fim ?? ($r->datafim ?? null),
            'desconto_percentual' => isset($r->descontopercentual) ? (float) $r->descontopercentual : null,
            'ativo' => (bool) ($r->ativo ?? true),
        ]);
        $sorteios = $this->ler($ctx, 'sorteios', fn ($r) => [
            'id' => (int) $r->id,
            'grupo_id' => (int) $r->grupo_id,
            'descricao' => trim((string) ($r->descricao ?? '')),
            'data_sorteio' => $r->datasorteio ?? null,
            'situacao' => $r->situacao ?? null,
            'numero_sorteado' => $r->numerosorteado ?? null,
        ]);
        $sorteioNumeros = $this->ler($ctx, 'sorteionumeros', fn ($r) => [
            'id' => (int) $r->id,
            'sorteio_id' => (int) $r->sorteio_id,
            'cliente_id' => $r->cliente_id ?? null,
            'numero' => $r->numero ?? null,
        ]);
        $metas = $this->ler($ctx, 'metavendas', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'colaborador_id' => $r->colaborador_id ?? null,
            'competencia' => $r->competencia ?? null,
            'meta_valor' => isset($r->metavalor) ? (float) $r->metavalor : null,
            'realizado_valor' => isset($r->realizadovalor) ? (float) $r->realizadovalor : null,
        ]);
        $checklists = $this->ler($ctx, 'checklists', fn ($r) => [
            'id' => (int) $r->id,
            'grupo_id' => (int) $r->grupo_id,
            'descricao' => trim((string) ($r->descricao ?? '')),
            'ativo' => (bool) ($r->ativo ?? true),
        ]);
        $execucoes = $this->ler($ctx, 'checklistexecucoes', fn ($r) => [
            'id' => (int) $r->id,
            'checklist_id' => (int) $r->checklist_id,
            'empresa_id' => (int) $r->empresa_id,
            'user_id' => $r->user_id ?? null,
            'data' => $r->data ?? null,
        ]);

        $gravados = 0;
        if (! $ctx->dryRun) {
            $gravados += $this->gravarTenant(PosVenda::class, $posVendas);
            $gravados += $this->gravarGrupo(Promocao::class, $promocoes);
            $gravados += $this->gravarGrupo(Sorteio::class, $sorteios);
            $gravados += $this->gravar(SorteioNumero::class, $sorteioNumeros);
            $gravados += $this->gravarTenant(MetaVenda::class, $metas);
            $gravados += $this->gravarGrupo(Checklist::class, $checklists);
            $gravados += $this->gravarTenant(ChecklistExecucao::class, $execucoes);
        }

        $lidos = count($posVendas) + count($promocoes) + count($sorteios) + count($sorteioNumeros)
            + count($metas) + count($checklists) + count($execucoes);

        return new MigrationResult($this->nome(), $lidos, $ctx->dryRun ? 0 : $gravados, 0);
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [
            new CountInvariant($ctx, 'posvendas', 'pos_vendas'),
            new CountInvariant($ctx, 'promocoes', 'promocoes'),
            new CountInvariant($ctx, 'sorteios', 'sorteios'),
            new IntegrityInvariant($ctx, 'pos_vendas', 'empresa_id', 'empresas'),
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

    /** @param class-string $model @param list<array<string,mixed>> $rows */
    private function gravarGrupo(string $model, array $rows): int
    {
        $n = 0;
        foreach ($rows as $row) {
            $this->upsert($model::withoutGrupo(), $this->semNulos($row));
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
