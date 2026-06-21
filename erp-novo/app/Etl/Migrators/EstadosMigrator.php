<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Estado;

/**
 * Migrador de exemplo (N0) — prova o pipeline ETL ponta-a-ponta:
 * Reader (legado.estados) → Transformer (normaliza) → Loader (Estado) → Invariante (contagem).
 *
 * Robusto a ambiente sem dump: se a conexão `legado` não responder, usa um
 * conjunto-semente dos 27 estados (fallback de desenvolvimento). Em produção
 * (cutover) lê do banco legado real.
 */
final class EstadosMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'estados';
    }

    public function dependeDe(): array
    {
        return [];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;
        $linhas = $this->lerLegado($ctx);
        $usouFallback = $linhas === null;
        if ($usouFallback) {
            $linhas = $this->semente();
        }

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($linhas as $l) {
                Estado::updateOrCreate(
                    ['uf' => $l['uf']],
                    ['descricao' => $l['descricao'], 'cod_ibge' => $l['cod_ibge']],
                );
                $gravados++;
            }
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($linhas),
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: 0,
            avisos: $usouFallback ? ['Banco legado indisponível — usando conjunto-semente dos 27 estados.'] : [],
        );
    }

    public function invariantes(): array
    {
        // Sem o legado disponível, comparamos contra o conjunto-semente (27).
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if ($this->lerLegado($ctx) === null) {
            return [new SementeCountInvariant(count($this->semente()))];
        }

        return [new CountInvariant($ctx, 'estados', 'estados')];
    }

    /** @return array<int,array{uf:string,descricao:string,cod_ibge:int}>|null */
    private function lerLegado(MigrationContext $ctx): ?array
    {
        try {
            $rows = $ctx->legado()->table('estados')->get(['uf', 'descricao', 'cod_ibge']);
            if ($rows->isEmpty()) {
                return null;
            }

            return $rows->map(fn ($r) => [
                'uf' => strtoupper(trim($r->uf)),
                'descricao' => trim($r->descricao),
                'cod_ibge' => (int) $r->cod_ibge,
            ])->all();
        } catch (\Throwable) {
            return null; // conexão legado indisponível neste ambiente
        }
    }

    /** @return array<int,array{uf:string,descricao:string,cod_ibge:int}> */
    private function semente(): array
    {
        return [
            ['uf' => 'AC', 'descricao' => 'Acre', 'cod_ibge' => 12],
            ['uf' => 'AL', 'descricao' => 'Alagoas', 'cod_ibge' => 27],
            ['uf' => 'AP', 'descricao' => 'Amapá', 'cod_ibge' => 16],
            ['uf' => 'AM', 'descricao' => 'Amazonas', 'cod_ibge' => 13],
            ['uf' => 'BA', 'descricao' => 'Bahia', 'cod_ibge' => 29],
            ['uf' => 'CE', 'descricao' => 'Ceará', 'cod_ibge' => 23],
            ['uf' => 'DF', 'descricao' => 'Distrito Federal', 'cod_ibge' => 53],
            ['uf' => 'ES', 'descricao' => 'Espírito Santo', 'cod_ibge' => 32],
            ['uf' => 'GO', 'descricao' => 'Goiás', 'cod_ibge' => 52],
            ['uf' => 'MA', 'descricao' => 'Maranhão', 'cod_ibge' => 21],
            ['uf' => 'MT', 'descricao' => 'Mato Grosso', 'cod_ibge' => 51],
            ['uf' => 'MS', 'descricao' => 'Mato Grosso do Sul', 'cod_ibge' => 50],
            ['uf' => 'MG', 'descricao' => 'Minas Gerais', 'cod_ibge' => 31],
            ['uf' => 'PA', 'descricao' => 'Pará', 'cod_ibge' => 15],
            ['uf' => 'PB', 'descricao' => 'Paraíba', 'cod_ibge' => 25],
            ['uf' => 'PR', 'descricao' => 'Paraná', 'cod_ibge' => 41],
            ['uf' => 'PE', 'descricao' => 'Pernambuco', 'cod_ibge' => 26],
            ['uf' => 'PI', 'descricao' => 'Piauí', 'cod_ibge' => 22],
            ['uf' => 'RJ', 'descricao' => 'Rio de Janeiro', 'cod_ibge' => 33],
            ['uf' => 'RN', 'descricao' => 'Rio Grande do Norte', 'cod_ibge' => 24],
            ['uf' => 'RS', 'descricao' => 'Rio Grande do Sul', 'cod_ibge' => 43],
            ['uf' => 'RO', 'descricao' => 'Rondônia', 'cod_ibge' => 11],
            ['uf' => 'RR', 'descricao' => 'Roraima', 'cod_ibge' => 14],
            ['uf' => 'SC', 'descricao' => 'Santa Catarina', 'cod_ibge' => 42],
            ['uf' => 'SP', 'descricao' => 'São Paulo', 'cod_ibge' => 35],
            ['uf' => 'SE', 'descricao' => 'Sergipe', 'cod_ibge' => 28],
            ['uf' => 'TO', 'descricao' => 'Tocantins', 'cod_ibge' => 17],
        ];
    }
}
