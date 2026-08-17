<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use Illuminate\Support\Facades\DB;

/**
 * IBPT — `PRODUTOLEIIMPOSTOS` (317 mil linhas) → `ibpt_aliquotas`.
 *
 * Fecha parte da pendência consciente #1 da AUDITORIA_MIGRACAO_DADOS_LEGADOS.md,
 * com uma correção de premissa: o relatório tratava `PRODUTOLEIIMPOSTOS` como
 * "matriz de alíquota fiscal" sem destino no schema novo. Ela não é matriz de
 * tributação — é a tabela do IBPT (Lei 12.741/2012, o "De olho no imposto"): carga
 * tributária aproximada por UF × NCM, o que sai no rodapé do cupom. E o destino JÁ
 * existia: `ibpt_aliquotas`, lida pelo IbptService e alimentada pelo command
 * `ibpt:atualizar` (que depende de um CSV externo que ninguém baixou). Migrar do
 * legado dispensa esse gate.
 *
 * Mapeamento (colunas do legado → destino):
 *   uf, ncm, ex, aliqnac→nacional, aliqimp→importado,
 *   aliqestadual→estadual, aliqmunicipal→municipal, versao, inicio/fim→vigência.
 *
 * Volume exige lote: 317 mil linhas em chunks, com upsert idempotente na chave
 * (ncm, uf, ex) — a mesma UNIQUE do destino.
 */
final class IbptMigrator implements Migrator
{
    /** Linhas por lote — equilíbrio entre memória e round-trips. */
    private const LOTE = 2000;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'ibpt';
    }

    public function dependeDe(): array
    {
        return []; // tabela global (não-tenant): não depende de empresa/grupo
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        if (! $this->tabelaExiste($ctx, 'produtoleiimpostos')) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['tabela `produtoleiimpostos` ausente no espelho — IBPT não migrado '
                    .'(o rodapé fiscal cai na alíquota-padrão do IbptService)']);
        }

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;
        $vistas = [];

        $ctx->legado()->table('produtoleiimpostos')
            ->orderBy('id')
            ->chunk(self::LOTE, function ($linhas) use (&$lidos, &$gravados, &$pulados, &$vistas, $ctx) {
                $lote = [];

                foreach ($linhas as $r) {
                    $lidos++;

                    $ncm = preg_replace('/\D/', '', (string) ($r->ncm ?? ''));
                    $uf = strtoupper(trim((string) ($r->uf ?? ''))) ?: null;
                    // `ex` vazio vira STRING VAZIA, nunca null: em SQL
                    // `null != null`, então o UNIQUE (ncm, uf, ex) não impede a
                    // repetição de linhas com ex nulo e o upsert nunca casa —
                    // cada execução insere tudo de novo. Como `ex` é vazio em
                    // 304.236 das 317.520 linhas da origem (a exceção fiscal só
                    // existe para poucos NCMs), isso dobrava a tabela a cada
                    // recarga. Mesmo padrão do defeito da T2.1, em outra tabela.
                    $ex = trim((string) ($r->ex ?? ''));

                    if ($ncm === '') {
                        $pulados++;

                        continue;
                    }

                    // A UNIQUE do destino é (ncm, uf, ex): dedup dentro da carga
                    // para o upsert não receber a mesma chave duas vezes no lote.
                    $chave = $ncm.'|'.($uf ?? '').'|'.$ex;
                    if (isset($vistas[$chave])) {
                        $pulados++;

                        continue;
                    }
                    $vistas[$chave] = true;

                    $lote[] = [
                        'ncm' => mb_substr($ncm, 0, 10),
                        'uf' => $uf ? mb_substr($uf, 0, 2) : null,
                        'ex' => mb_substr($ex, 0, 3),
                        'nacional' => $this->dec($r->aliqnac ?? 0),
                        'importado' => $this->dec($r->aliqimp ?? 0),
                        'estadual' => $this->dec($r->aliqestadual ?? 0),
                        'municipal' => $this->dec($r->aliqmunicipal ?? 0),
                        'versao' => mb_substr(trim((string) ($r->versao ?? '')), 0, 20) ?: null,
                        'vigencia_inicio' => $this->data($r->inicio ?? null),
                        'vigencia_fim' => $this->data($r->fim ?? null),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($ctx->dryRun || $lote === []) {
                    return;
                }

                DB::table('ibpt_aliquotas')->upsert(
                    $lote,
                    ['ncm', 'uf', 'ex'],
                    ['nacional', 'importado', 'estadual', 'municipal',
                        'versao', 'vigencia_inicio', 'vigencia_fim', 'updated_at'],
                );
                $gravados += count($lote);
            });

        $avisos = [];
        if ($gravados > 0) {
            $versao = DB::table('ibpt_aliquotas')->max('versao');
            $avisos[] = "IBPT carregado do legado (versão {$versao}) — tabela do IBPT "
                .'é mensal: rodar `ibpt:atualizar` para a vigência corrente';
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
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->tabelaExiste($ctx, 'produtoleiimpostos')) {
            return [];
        }

        return [
            new CountInvariant($ctx, 'produtoleiimpostos', 'ibpt_aliquotas'),
        ];
    }

    private function dec(mixed $v): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', (string) $v);
    }

    private function data(mixed $v): ?string
    {
        $v = trim((string) ($v ?? ''));
        if ($v === '') {
            return null;
        }

        return preg_match('/^(\d{4}-\d{2}-\d{2})/', $v, $m) ? $m[1] : null;
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
