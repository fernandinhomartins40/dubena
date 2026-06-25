<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Pagamento\CartaoTransacao;
use App\Models\Pagamento\GasDoPovoBeneficio;

/**
 * F15 — migra Pagamentos: transações de cartão (NSU/autorização/taxa) e benefícios
 * Gás do Povo. Ambos empresa-scoped; conversões legado→novo + flags.
 */
final class PagamentoMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'pagamentos';
    }

    public function dependeDe(): array
    {
        return ['empresas', 'clientes', 'pedidos'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $cartoes = $this->ler($ctx, 'cartaotransacoes', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'pedido_id' => $r->pedido_id ?? null,
            'conta_id' => $r->conta_id ?? null,
            'bandeira' => $r->bandeira ?? null,
            'tipo' => $r->tipo ?? null,
            'nsu' => $r->nsu ?? null,
            'autorizacao' => $r->autorizacao ?? null,
            'parcelas' => isset($r->parcelas) ? (int) $r->parcelas : null,
            'valor_bruto' => isset($r->valorbruto) ? (float) $r->valorbruto : null,
            'taxa_percentual' => isset($r->taxapercentual) ? (float) $r->taxapercentual : null,
            'valor_liquido' => isset($r->valorliquido) ? (float) $r->valorliquido : null,
            'situacao' => $r->situacao ?? null,
        ]);
        $beneficios = $this->ler($ctx, 'gasdopovobeneficios', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'cliente_id' => $r->cliente_id ?? null,
            'nis' => $r->nis ?? null,
            'competencia' => $r->competencia ?? null,
            'valor' => isset($r->valor) ? (float) $r->valor : null,
            'situacao' => $r->situacao ?? null,
            'pedido_id' => $r->pedido_id ?? null,
        ]);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($cartoes as $c) {
                $this->upsert(CartaoTransacao::withoutTenant(), $this->semNulos($c));
                $gravados++;
            }
            foreach ($beneficios as $b) {
                $this->upsert(GasDoPovoBeneficio::withoutTenant(), $this->semNulos($b));
                $gravados++;
            }
        }

        return new MigrationResult($this->nome(), count($cartoes) + count($beneficios), $ctx->dryRun ? 0 : $gravados, 0);
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [
            new CountInvariant($ctx, 'cartaotransacoes', 'cartao_transacoes'),
            new CountInvariant($ctx, 'gasdopovobeneficios', 'gasdopovo_beneficios'),
            new IntegrityInvariant($ctx, 'cartao_transacoes', 'empresa_id', 'empresas'),
            new IntegrityInvariant($ctx, 'gasdopovo_beneficios', 'empresa_id', 'empresas'),
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
     * Remove chaves com valor null (exceto id) para que colunas NOT NULL com
     * DEFAULT no banco usem o default em vez de receber null do legado.
     *
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
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
    private function semNulos(array $row): array
    {
        return array_filter($row, fn ($v, $k) => $v !== null || $k === 'id', ARRAY_FILTER_USE_BOTH);
    }
}
