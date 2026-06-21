<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;

/**
 * N2 — migra geográfico (cidades/bairros/ruas) do legado. Base do endereço.
 */
final class GeograficoMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'geografico';
    }

    public function dependeDe(): array
    {
        return ['empresas']; // precisa de grupos
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $cidades = $this->ler($ctx, 'cidades', ['id', 'grupo_id', 'descricao', 'uf', 'cod_ibge', 'ativo']);
        $bairros = $this->ler($ctx, 'bairros', ['id', 'grupo_id', 'cidade_id', 'descricao', 'ativo']);
        $ruas = $this->ler($ctx, 'ruas', ['id', 'grupo_id', 'cidade_id', 'descricao', 'cep', 'ativo']);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($cidades as $c) {
                Cidade::withoutGrupo()->updateOrCreate(['id' => $c['id']], $c);
                $gravados++;
            }
            foreach ($bairros as $b) {
                Bairro::withoutGrupo()->updateOrCreate(['id' => $b['id']], $b);
                $gravados++;
            }
            foreach ($ruas as $r) {
                Rua::withoutGrupo()->updateOrCreate(['id' => $r['id']], $r);
                $gravados++;
            }
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($cidades) + count($bairros) + count($ruas),
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
            new CountInvariant($ctx, 'cidades', 'cidades'),
            new CountInvariant($ctx, 'bairros', 'bairros'),
            new CountInvariant($ctx, 'ruas', 'ruas'),
        ];
    }

    /**
     * @param list<string> $colunas
     * @return list<array<string, mixed>>
     */
    private function ler(MigrationContext $ctx, string $tabela, array $colunas): array
    {
        try {
            $rows = $ctx->legado()->table($tabela)->get($colunas);
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(function ($r) {
            $linha = (array) $r;
            if (isset($linha['ativo'])) {
                $linha['ativo'] = (bool) $linha['ativo'];
            }
            if (isset($linha['cod_ibge'])) {
                $linha['cod_ibge'] = $linha['cod_ibge'] !== null ? (int) $linha['cod_ibge'] : null;
            }

            return $linha;
        })->all();
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
