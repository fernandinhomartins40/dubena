<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Empresa;
use App\Models\Grupo;

/**
 * N1 — migra grupos e empresas (a entidade-tenant) do legado.
 *
 * Grupos primeiro (FK de empresas). Latitude/longitude viram decimal nativo;
 * flags matriz/ativo viram boolean. Config detalhada e certificado A1 ficam para
 * o EmpresaConfigMigrator/fase fiscal. Sem dump: 0 lidos/gravados.
 */
final class EmpresasMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'empresas';
    }

    public function dependeDe(): array
    {
        return [];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $grupos = $this->lerGrupos($ctx);
        $empresas = $this->lerEmpresas($ctx);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($grupos as $g) {
                Grupo::updateOrCreate(['id' => $g['id']], $g);
                $gravados++;
            }
            foreach ($empresas as $e) {
                Empresa::updateOrCreate(['id' => $e['id']], $e);
                $gravados++;
            }
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($grupos) + count($empresas),
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: 0,
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->legadoDisponivel($ctx)) {
            return []; // sem dump não há o que comparar (ambiente dev/CI)
        }

        return [
            new CountInvariant($ctx, 'empresasgrupos', 'grupos'),
            new CountInvariant($ctx, 'empresas', 'empresas'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lerGrupos(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('empresasgrupos')->get(['id', 'descricao', 'ativo']);
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'descricao' => trim((string) $r->descricao),
            'ativo' => (bool) $r->ativo,
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function lerEmpresas(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('empresas')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'grupo_id' => (int) ($r->grupo_id ?? $r->empresasgrupo_id ?? 0),
            'razao_social' => trim((string) ($r->razaosocial ?? $r->razao_social ?? '')),
            'nome_fantasia' => $r->nomefantasia ?? $r->nome_fantasia ?? null,
            'nome_informal' => $r->nomeinformal ?? $r->nome_informal ?? null,
            'cnpj' => $r->cnpj ?? null,
            'inscricao_estadual' => $r->inscricaoestadual ?? null,
            'inscricao_municipal' => $r->inscricaomunicipal ?? null,
            'cep' => $r->cep ?? null,
            'uf' => $r->uf ?? null,
            'cidade' => $r->cidade ?? null,
            'bairro' => $r->bairro ?? null,
            'endereco' => $r->endereco ?? null,
            'numero' => $r->numero ?? null,
            'complemento' => $r->complemento ?? null,
            'telefone1' => $r->telefone1 ?? $r->telefone ?? null,
            'telefone2' => $r->telefone2 ?? null,
            'latitude' => isset($r->latitude) ? (float) $r->latitude : null,
            'longitude' => isset($r->longitude) ? (float) $r->longitude : null,
            'matriz' => (bool) ($r->matriz ?? false),
            'ativo' => (bool) ($r->ativo ?? true),
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
