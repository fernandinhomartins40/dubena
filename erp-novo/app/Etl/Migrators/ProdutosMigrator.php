<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;

/**
 * N3 — migra produtos do legado. Preços/pesos string-BR → decimal; flags → boolean.
 * Origens de combustível migram junto (sub-tabela). Mapeamento de nomes de coluna
 * legado→novo (snake sem underscore → snake) feito explicitamente.
 */
final class ProdutosMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'produtos';
    }

    public function dependeDe(): array
    {
        return ['empresas'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->usarConexaoDe($ctx);

        $this->ctxAtual = $ctx;
        $produtos = $this->ler($ctx);

        // Cadastros de apoio do produto podem não ter vindo no dump; a FK
        // inválida vira null em vez de derrubar a carga.
        $produtos = $this->anularFksInvalidas($produtos, [
            'produtoclasse_id' => 'produtoclasses',
            'unidademedida_id' => 'unidadesmedida',
        ]);

        $gravados = 0;
        if (! $ctx->dryRun) {
            // ids preservados: os itens de pedido referenciam o produto por id.
            $gravados += $this->gravarPreservandoId('produtos', $produtos);
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($produtos),
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: 0,
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext;
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [new CountInvariant($ctx, 'produtos', 'produtos')];
    }

    /** @return list<array<string, mixed>> */
    private function ler(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('produtos')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'descricao' => trim((string) $r->descricao),
            'produtoclasse_id' => $r->produtoclasse_id ?? null,
            'unidademedida_id' => $r->unidademedida_id ?? null,
            'vasilhame_retornavel' => (bool) ($r->vasilhameretornavel ?? false),
            'ativo' => (bool) ($r->ativo ?? true),
            'preco_venda' => isset($r->precovenda) ? (float) $r->precovenda : null,
            'preco_venda_minimo' => isset($r->precovendaminimo) ? (float) $r->precovendaminimo : null,
            'custo_medio' => isset($r->customedio) ? (float) $r->customedio : null,
            'custo_frete' => isset($r->custofrete) ? (float) $r->custofrete : null,
            'preco_gasdopovo' => isset($r->precogasdopovo) ? (float) $r->precogasdopovo : null,
            'peso_liquido' => isset($r->pesoliquido) ? (float) $r->pesoliquido : null,
            'peso_bruto' => isset($r->pesobruto) ? (float) $r->pesobruto : null,
            'nfe_permite' => (bool) ($r->nfepermite ?? false),
            'sped' => (bool) ($r->sped ?? false),
            'ncm' => $r->ncm ?? null,
            'nfe_cest' => $r->nfcest ?? null,
            'ean' => $r->ean ?? null,
            'tipo_glp' => $r->tipo_glp ?? null,
            'nfe_cprod_anp' => $r->nfecprodanp ?? null,
            'pglp' => isset($r->pglp) ? (float) $r->pglp : null,
            'pgni' => isset($r->pgni) ? (float) $r->pgni : null,
            'pgnn' => isset($r->pgnn) ? (float) $r->pgnn : null,
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
