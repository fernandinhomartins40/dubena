<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\SumInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\DB;

/**
 * N5 — contas a pagar/receber: títulos (`financeiros`) e parcelas.
 *
 * Volume real do dump: ~444 mil títulos e ~475 mil parcelas — por isso a carga
 * é em blocos, nunca em memória.
 *
 * Conversões que o legado exige:
 *  - `pagarreceber` é 'P'/'R' (mantido como char(1) no destino);
 *  - `baixado` vem como CHAR '1'/'0'/'S' → boolean;
 *  - plano de contas e centro de custo NÃO estão no título no legado: vivem em
 *    `financeirorateios` (um título pode ser rateado entre vários). O destino
 *    tem uma coluna só, então adota-se o rateio de MAIOR valor como o
 *    principal — e isso é reportado como aviso, para não passar por exato.
 */
final class FinanceiroMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'financeiro';
    }

    public function dependeDe(): array
    {
        return ['clientes', 'cadastros-apoio'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        if (! $this->tabelaExiste($ctx, 'financeiros')) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['legado indisponível ou sem as tabelas de financeiro']);
        }

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;
        $avisos = [];

        $idsEmpresa = $this->idsDe('empresas');
        $idsCliente = $this->idsDe('clientes');
        $idsPlano = $this->tabelaDestinoExiste('planocontas') ? $this->idsDe('planocontas') : [];
        $idsCentro = $this->tabelaDestinoExiste('centrocustos') ? $this->idsDe('centrocustos') : [];
        $rateio = $this->rateioPrincipal($ctx);

        // ── Títulos ──
        $ctx->legado()->table('financeiros')->orderBy('id')->chunk(5000,
            function ($rows) use (
                &$lidos, &$gravados, &$pulados, $ctx,
                $idsEmpresa, $idsCliente, $idsPlano, $idsCentro, $rateio
            ) {
                $lote = [];
                foreach ($rows as $r) {
                    $lidos++;
                    $empresa = (int) $r->empresa_id;
                    if (! isset($idsEmpresa[$empresa])) {
                        $pulados++;

                        continue;
                    }

                    $cliente = (int) ($r->cliente_id ?? 0);
                    [$plano, $centro] = $rateio[(int) $r->id] ?? [null, null];

                    $lote[] = [
                        'id' => (int) $r->id,
                        'empresa_id' => $empresa,
                        'grupo_id' => (int) $r->grupo_id,
                        'cliente_id' => isset($idsCliente[$cliente]) ? $cliente : null,
                        'planoconta_id' => ($plano !== null && isset($idsPlano[$plano])) ? $plano : null,
                        'centrocusto_id' => ($centro !== null && isset($idsCentro[$centro])) ? $centro : null,
                        'pagarreceber' => $this->pagarReceber((string) $r->pagarreceber),
                        'documento' => mb_substr((string) ($r->documento ?? ''), 0, 60) ?: null,
                        'descricao' => mb_substr((string) ($r->descricao ?? ''), 0, 255) ?: null,
                        'valor' => round((float) $r->valor, 2),
                        'data_emissao' => $r->dataemissao ?? null,
                        'cancelado' => false,
                        'created_at' => $r->created_at ?? null,
                    ];
                }
                if ($lote !== [] && ! $ctx->dryRun) {
                    $gravados += $this->gravarPreservandoId('financeiros', $lote, ['id'], 1000);
                }
            });

        // ── Parcelas (precisam do título já gravado) ──
        if ($this->tabelaExiste($ctx, 'financeiroparcelas')) {
            $idsTitulo = $this->idsDe('financeiros');
            $empresaDoTitulo = $this->empresaPorTitulo();

            $ctx->legado()->table('financeiroparcelas')->orderBy('id')->chunk(5000,
                function ($rows) use (&$lidos, &$gravados, &$pulados, $ctx, $idsTitulo, $empresaDoTitulo) {
                    $lote = [];
                    $vistos = [];
                    foreach ($rows as $r) {
                        $lidos++;
                        $titulo = (int) $r->financeiro_id;
                        if (! isset($idsTitulo[$titulo])) {
                            $pulados++;

                            continue;
                        }
                        // O destino tem unique (financeiro_id, numero): o legado
                        // repete a combinação em alguns títulos antigos.
                        $chave = $titulo.':'.(int) $r->numero;
                        if (isset($vistos[$chave])) {
                            $pulados++;

                            continue;
                        }
                        $vistos[$chave] = true;

                        $lote[] = [
                            'id' => (int) $r->id,
                            'financeiro_id' => $titulo,
                            'empresa_id' => $empresaDoTitulo[$titulo] ?? (int) $r->empresa_id,
                            'numero' => (int) $r->numero,
                            'vencimento' => $r->datavencimento,
                            'valor' => round((float) $r->valor, 2),
                            'desconto' => round((float) ($r->desconto ?? 0), 2),
                            'valor_efetivado' => round((float) ($r->valorefetivado ?? 0), 2),
                            'baixado' => $this->booleano($r->baixado ?? null),
                            'datahora_baixa' => $r->datahorabaixa ?? null,
                            'created_at' => $r->created_at ?? null,
                        ];
                    }
                    if ($lote !== [] && ! $ctx->dryRun) {
                        $gravados += $this->gravarPreservandoId('financeiroparcelas', $lote, ['id'], 1000);
                    }
                });
        }

        if ($rateio !== []) {
            $avisos[] = 'plano de contas/centro de custo vieram do rateio de MAIOR '
                .'valor de cada título (o legado permite rateio múltiplo; o schema '
                .'novo guarda um por título)';
        }
        if ($pulados > 0) {
            $avisos[] = "{$pulados} registro(s) descartado(s): empresa/título ausente "
                .'ou parcela duplicada (financeiro_id + numero)';
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

        return [
            new CountInvariant($ctx, 'financeiros', 'financeiros'),
            new SumInvariant($ctx, 'financeiros', 'valor', 'financeiros', 'valor', 0.05),
        ];
    }

    /**
     * Rateio de maior valor por título: financeiro_id => [planoconta, centrocusto].
     *
     * @return array<int, array{0:?int, 1:?int}>
     */
    private function rateioPrincipal(MigrationContext $ctx): array
    {
        if (! $this->tabelaExiste($ctx, 'financeirorateios')) {
            return [];
        }

        $out = [];
        $maior = [];
        $ctx->legado()->table('financeirorateios')
            ->select('financeiro_id', 'planoconta_id', 'centrocusto_id', 'valor')
            ->orderBy('financeiro_id')
            ->chunk(20000, function ($rows) use (&$out, &$maior) {
                foreach ($rows as $r) {
                    $fid = (int) $r->financeiro_id;
                    $valor = (float) ($r->valor ?? 0);
                    if (! isset($maior[$fid]) || $valor > $maior[$fid]) {
                        $maior[$fid] = $valor;
                        $out[$fid] = [
                            isset($r->planoconta_id) ? (int) $r->planoconta_id : null,
                            isset($r->centrocusto_id) ? (int) $r->centrocusto_id : null,
                        ];
                    }
                }
            });

        return $out;
    }

    /** @return array<int,int> */
    private function empresaPorTitulo(): array
    {
        $out = [];
        foreach (DB::table('financeiros')->select('id', 'empresa_id')->cursor() as $f) {
            $out[(int) $f->id] = (int) $f->empresa_id;
        }

        return $out;
    }

    /** O destino é char(1); qualquer coisa que não seja 'P' vira 'R'. */
    private function pagarReceber(string $v): string
    {
        return str_starts_with(mb_strtoupper(trim($v)), 'P') ? 'P' : 'R';
    }

    private function booleano(mixed $v): bool
    {
        $v = mb_strtoupper(trim((string) ($v ?? '')));

        return in_array($v, ['1', 'S', 'T', 'TRUE', 'Y'], true);
    }

    /** @return array<int,true> */
    private function idsDe(string $tabela): array
    {
        $ids = [];
        foreach (DB::table($tabela)->select('id')->cursor() as $r) {
            $ids[(int) $r->id] = true;
        }

        return $ids;
    }

    private function tabelaDestinoExiste(string $tabela): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
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
