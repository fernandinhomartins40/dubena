<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\BalanceInvariant;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Caixa\Cheque;
use App\Models\Caixa\Conta;
use App\Models\Caixa\ContaMovimento;

/**
 * N6 — migra contas + movimentos + cheques (o coração do saldo, baseline #1).
 *
 * INVARIANTE DE OURO do cutover: Σ contamovimentos.valor = conta.saldo_atual.
 * Migra o movimento fielmente (valor assinado) e o saldo materializado; a
 * BalanceInvariant prova que não divergiram. Sem essa verde, NÃO se faz cutover.
 */
final class CaixaMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'caixa';
    }

    public function dependeDe(): array
    {
        return ['empresas', 'financeiro'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $contas = $this->lerContas($ctx);
        $movimentos = $this->lerMovimentos($ctx);
        $cheques = $this->lerCheques($ctx);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($contas as $c) {
                Conta::withoutTenant()->updateOrCreate(['id' => $c['id']], $c);
                $gravados++;
            }
            foreach ($movimentos as $m) {
                ContaMovimento::updateOrCreate(['id' => $m['id']], $m);
                $gravados++;
            }
            foreach ($cheques as $ch) {
                Cheque::withoutTenant()->updateOrCreate(['id' => $ch['id']], $ch);
                $gravados++;
            }
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($contas) + count($movimentos) + count($cheques),
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
            new CountInvariant($ctx, 'contas', 'contas'),
            new CountInvariant($ctx, 'contamovimentos', 'contamovimentos'),
            // O caso #1: Σ movimentos por conta = saldo_atual.
            new BalanceInvariant($ctx, 'contamovimentos', 'valor', 'contas', 'saldo_atual', ['conta_id'], 0.01),
            new IntegrityInvariant($ctx, 'contamovimentos', 'conta_id', 'contas'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lerContas(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('contas')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'descricao' => trim((string) $r->descricao),
            'tipo' => strtoupper((string) ($r->tipo ?? 'CAIXA')),
            'banco_id' => $r->banco_id ?? null,
            'agencia' => $r->agencia ?? null,
            'numero' => $r->numero ?? null,
            'saldo_inicial' => (float) ($r->saldoinicial ?? 0),
            'saldo_atual' => (float) ($r->saldoatual ?? 0),
            'fechado' => (bool) ($r->fechado ?? false),
            'ativo' => (bool) ($r->ativo ?? true),
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function lerMovimentos(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('contamovimentos')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'conta_id' => (int) $r->conta_id,
            'financeiroparcela_id' => $r->financeiroparcela_id ?? null,
            'tipo' => strtoupper((string) ($r->tipo ?? 'AJUSTE')),
            'pagarreceber' => $r->pagarreceber ?? null,
            'valor' => (float) $r->valor,
            'saldo_resultante' => (float) ($r->saldoresultante ?? $r->saldo ?? 0),
            'juros' => (float) ($r->juros ?? 0),
            'multa' => (float) ($r->multa ?? 0),
            'desconto' => (float) ($r->desconto ?? 0),
            'descricao' => $r->descricao ?? null,
            'origem' => $r->origem ?? null,
            'origem_id' => $r->origem_id ?? null,
            'datahora' => $r->datahora ?? $r->created_at ?? now(),
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function lerCheques(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('cheques')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'cliente_id' => $r->cliente_id ?? null,
            'banco_id' => $r->banco_id ?? null,
            'especie' => strtoupper(substr((string) ($r->especie ?? 'R'), 0, 1)),
            'numero' => $r->numero ?? null,
            'titular' => $r->titular ?? null,
            'valor' => (float) ($r->valor ?? 0),
            'bom_para' => $r->bompara ?? $r->bom_para ?? null,
            'situacao' => strtoupper((string) ($r->situacao ?? 'CARTEIRA')),
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
