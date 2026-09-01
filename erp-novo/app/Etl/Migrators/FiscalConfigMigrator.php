<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Fiscal\MalhaFiscal;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Migra os CATÁLOGOS fiscais do legado para a malha fiscal genérica (C12).
 *
 * O legado guarda a configuração tributária em tabelas próprias (NFGRUPOFISCALS,
 * NFICMS, NFPIS, ...); o schema novo tem `malha_fiscal` (grupo_id, tipo, codigo,
 * descricao) — os `tipo` abaixo são exatamente os que a tela de Config Fiscal
 * consome (frontend/features/fiscal/tabs/MalhaTab.tsx).
 *
 * As MATRIZES de alíquota já NÃO ficam aqui e já não estão pendentes: `nfimpostos`
 * e `nfimpostoestados` são carregadas pelo MatrizTributariaMigrator (destino
 * `nf_impostos`/`nf_imposto_estados`) e `produtoleiimpostos` pelo IbptMigrator
 * (destino `ibpt_aliquotas` — é a tabela do IBPT/Lei 12.741, não uma matriz de
 * tributação). Este migrator cuida só dos CATÁLOGOS.
 */
final class FiscalConfigMigrator implements Migrator
{
    /** tabela do espelho => tipo na malha_fiscal (os da tela + preservação). */
    private const CATALOGOS = [
        'nfgrupofiscals' => 'grupos-fiscais',
        'nficms' => 'cst-icms',
        'nfipis' => 'cst-ipi',
        'nfpis' => 'cst-pis',
        'nfcofins' => 'cst-cofins',
        'nfcsts' => 'cst',
        'nfcests' => 'cest',
        'nfclastribs' => 'classificacao-tributaria',
    ];

    /** colunas candidatas a `codigo`, na ordem de preferência. */
    private const COLUNAS_CODIGO = ['cst', 'cest', 'codigo', 'ncm', 'cclastrib', 'ctrib'];

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'fiscal-config';
    }

    public function dependeDe(): array
    {
        return ['empresas']; // precisa de grupos
    }

    /**
     * A conexão de DESTINO, vinda do contexto.
     *
     * Estas tabelas não estão sob RLS hoje, então o defeito não as atinge — mas
     * o guardião `EtlEnxergaODestinoTest` é absoluto de propósito: exceção em
     * guardião envelhece, e no dia em que a tabela ganhar policy ninguém
     * lembraria de rever a exceção.
     */
    private function destino(): ConnectionInterface
    {
        return $this->conexaoDoContexto ?? DB::connection();
    }

    private ?ConnectionInterface $conexaoDoContexto = null;

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->conexaoDoContexto = $ctx->novo();

        $this->ctxAtual = $ctx;

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;
        $avisos = [];
        $ausentes = [];

        $grupoPadrao = (int) ($this->destino()->table('grupos')->min('id') ?? 0);
        if ($grupoPadrao === 0) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['sem grupos no destino — rode o migrator de empresas antes']);
        }

        foreach (self::CATALOGOS as $tabela => $tipo) {
            if (! $this->tabelaExiste($ctx, $tabela)) {
                $ausentes[] = $tabela;

                continue;
            }

            foreach ($ctx->legado()->table($tabela)->orderBy('id')->get() as $r) {
                $lidos++;
                $linha = (array) $r;

                $descricao = trim((string) ($linha['descricao'] ?? ''));
                $codigo = $this->codigoDe($linha);
                if ($descricao === '' && $codigo === null) {
                    $pulados++;

                    continue;
                }

                if ($ctx->dryRun) {
                    continue;
                }

                MalhaFiscal::withoutGrupo()->updateOrCreate(
                    [
                        'grupo_id' => (int) ($linha['grupo_id'] ?? 0) ?: $grupoPadrao,
                        'tipo' => $tipo,
                        'codigo' => $codigo,
                        'descricao' => $descricao !== '' ? mb_substr($descricao, 0, 255) : (string) $codigo,
                    ],
                    ['ativo' => $this->booleano($linha['ativo'] ?? '1')],
                );
                $gravados++;
            }
        }

        if ($ausentes !== []) {
            $avisos[] = 'catálogo(s) ausente(s) no espelho: '.implode(', ', $ausentes)
                .' — re-rodar espelhar_oracle.py';
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: $lidos,
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: $pulados,
            avisos: $avisos,
        );
    }

    public function invariantes(): array
    {
        return []; // updateOrCreate deduplica catálogos: contagem 1:1 não se aplica.
    }

    /** @param array<string,mixed> $linha */
    private function codigoDe(array $linha): ?string
    {
        foreach (self::COLUNAS_CODIGO as $c) {
            $v = trim((string) ($linha[$c] ?? ''));
            if ($v !== '') {
                return mb_substr($v, 0, 20);
            }
        }

        return null;
    }

    private function booleano(mixed $v): bool
    {
        return in_array((string) $v, ['1', 'true', 't', 'S'], true) || $v === true || $v === 1;
    }

    private function tabelaExiste(MigrationContext $ctx, string $tabela): bool
    {
        try {
            return $ctx->legado()->getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }
}
