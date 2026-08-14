<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\DB;

/**
 * N3 — setores, saldos e histórico de estoque.
 *
 * Nomes REAIS do legado (o esqueleto anterior chutava `estoques`, que não
 * existe): `setors`, `estoquesetors` (saldo por setor×produto) e
 * `estoquesetorhistoricos` (o log, com ~507 mil linhas).
 *
 * Conversões que o schema novo exige:
 *  - `movimentacao` do legado ('E'/'S') → `tipo` ENTRADA/SAIDA;
 *  - `entidade`/`entidade_id` (polimórfico do legado) → `origem`/`origem_id`;
 *  - `saldo_resultante` é NOT NULL no destino e NÃO existe no legado: é
 *    RECONSTRUÍDO acumulando o histórico em ordem cronológica por
 *    setor×produto. É o que torna o extrato auditável linha a linha.
 */
final class EstoqueMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'estoque';
    }

    public function dependeDe(): array
    {
        return ['produtos', 'empresas'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        if (! $this->tabelaExiste($ctx, 'setors')) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['legado indisponível ou sem as tabelas de estoque']);
        }

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;
        $avisos = [];

        // ── Setores (locais de estoque) ──
        $setores = $this->lerSetores($ctx);
        $lidos += count($setores);
        if (! $ctx->dryRun) {
            $gravados += $this->gravarPreservandoId('setores', $setores);
        }

        $idsSetor = $this->idsDe('setores');
        $idsProduto = $this->idsDe('produtos');
        $empresaDoSetor = $this->empresaPorSetor();

        // ── Saldos por setor×produto ──
        [$saldos, $puladosSaldo] = $this->lerSaldos($ctx, $idsSetor, $idsProduto, $empresaDoSetor);
        $lidos += count($saldos) + $puladosSaldo;
        $pulados += $puladosSaldo;
        if (! $ctx->dryRun) {
            $gravados += $this->gravarPreservandoId('estoquesaldos', $saldos);
        }

        // ── Histórico (volumoso: vai em blocos, com saldo reconstruído) ──
        [$n, $g, $p] = $this->migrarHistorico($ctx, $idsSetor, $idsProduto, $empresaDoSetor);
        $lidos += $n;
        $gravados += $g;
        $pulados += $p;

        if ($pulados > 0) {
            $avisos[] = "{$pulados} linha(s) de estoque descartada(s): setor ou "
                .'produto ausente no destino';
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
            new CountInvariant($ctx, 'setors', 'setores'),
            new CountInvariant($ctx, 'estoquesetors', 'estoquesaldos'),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function lerSetores(MigrationContext $ctx): array
    {
        $empresas = $this->idsDe('empresas');
        $out = [];

        foreach ($ctx->legado()->table('setors')->orderBy('id')->get() as $r) {
            if (! isset($empresas[(int) $r->empresa_id])) {
                continue;
            }
            $out[] = [
                'id' => (int) $r->id,
                'empresa_id' => (int) $r->empresa_id,
                'grupo_id' => (int) $r->grupo_id,
                'descricao' => mb_substr(trim((string) $r->descricao), 0, 255),
                'ativo' => $this->booleano($r->ativo ?? '1', true),
            ];
        }

        return $out;
    }

    /**
     * @return array{0: list<array<string,mixed>>, 1: int}
     */
    private function lerSaldos(
        MigrationContext $ctx, array $idsSetor, array $idsProduto, array $empresaDoSetor
    ): array {
        $out = [];
        $pulados = 0;

        foreach ($ctx->legado()->table('estoquesetors')->orderBy('id')->get() as $r) {
            $setor = (int) $r->setor_id;
            $produto = (int) $r->produto_id;
            if (! isset($idsSetor[$setor]) || ! isset($idsProduto[$produto])) {
                $pulados++;

                continue;
            }
            $out[] = [
                'id' => (int) $r->id,
                'empresa_id' => $empresaDoSetor[$setor] ?? (int) $r->empresa_id,
                'setor_id' => $setor,
                'produto_id' => $produto,
                'quantidade' => (float) $r->quantidade,
                'quantidade_minima' => isset($r->quantidademinima) ? (float) $r->quantidademinima : null,
                'quantidade_maxima' => isset($r->quantidademaxima) ? (float) $r->quantidademaxima : null,
                'custo_medio' => 0,
            ];
        }

        return [$out, $pulados];
    }

    /**
     * Histórico em blocos, reconstruindo `saldo_resultante`.
     *
     * O legado não guarda o saldo após cada movimento; o schema novo exige
     * (é o que permite auditar o extrato). Acumula-se em memória o saldo
     * corrente por setor×produto — são ~115 pares, cabe folgado — lendo o
     * histórico em ordem cronológica.
     *
     * @return array{0:int, 1:int, 2:int} lidos, gravados, pulados
     */
    private function migrarHistorico(
        MigrationContext $ctx, array $idsSetor, array $idsProduto, array $empresaDoSetor
    ): array {
        if (! $this->tabelaExiste($ctx, 'estoquesetorhistoricos')) {
            return [0, 0, 0];
        }

        $idsUser = $this->idsDe('users');
        $corrente = [];   // "setor:produto" => saldo acumulado
        $lidos = 0;
        $gravados = 0;
        $pulados = 0;

        $ctx->legado()->table('estoquesetorhistoricos')
            ->orderBy('datahora')
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (
                &$corrente, &$lidos, &$gravados, &$pulados,
                $idsSetor, $idsProduto, $empresaDoSetor, $idsUser, $ctx
            ) {
                $lote = [];
                foreach ($rows as $r) {
                    $lidos++;
                    $setor = (int) $r->setor_id;
                    $produto = (int) $r->produto_id;
                    if (! isset($idsSetor[$setor]) || ! isset($idsProduto[$produto])) {
                        $pulados++;

                        continue;
                    }

                    $tipo = $this->tipoMovimento((string) $r->movimentacao);
                    $qtd = abs((float) $r->quantidade);
                    $chave = $setor.':'.$produto;
                    $corrente[$chave] = ($corrente[$chave] ?? 0.0)
                        + ($tipo === 'ENTRADA' ? $qtd : -$qtd);

                    $userId = (int) ($r->user_id ?? 0);

                    $lote[] = [
                        'id' => (int) $r->id,
                        'empresa_id' => $empresaDoSetor[$setor] ?? (int) ($r->empresa_id ?? 0),
                        'setor_id' => $setor,
                        'produto_id' => $produto,
                        'tipo' => $tipo,
                        'quantidade' => $qtd,
                        'custo_unitario' => isset($r->customedio) ? (float) $r->customedio : null,
                        'saldo_resultante' => round($corrente[$chave], 3),
                        // `entidade`/`entidade_id` do legado é a origem polimórfica
                        // do movimento (Pedido, Transferencia, ...).
                        'origem' => mb_substr((string) ($r->entidade ?? ''), 0, 60) ?: null,
                        'origem_id' => isset($r->entidade_id) ? (int) $r->entidade_id : null,
                        'user_id' => isset($idsUser[$userId]) ? $userId : null,
                        'created_at' => $r->datahora ?? null,
                    ];
                }

                if ($lote !== [] && ! $ctx->dryRun) {
                    $gravados += $this->gravarPreservandoId('estoquehistorico', $lote, ['id'], 1000);
                }
            });

        return [$lidos, $gravados, $pulados];
    }

    /** 'E'/'ENTRADA' → ENTRADA; o resto é SAIDA. */
    private function tipoMovimento(string $v): string
    {
        $v = mb_strtoupper(trim($v));

        return str_starts_with($v, 'E') ? 'ENTRADA' : 'SAIDA';
    }

    /** Flags do legado vêm como '1'/'0'/'S'/'N' em CHAR. */
    private function booleano(mixed $v, bool $padrao = false): bool
    {
        if ($v === null) {
            return $padrao;
        }
        $v = mb_strtoupper(trim((string) $v));

        return in_array($v, ['1', 'S', 'T', 'TRUE', 'Y'], true);
    }

    /** @return array<int,int> setor_id => empresa_id (no destino) */
    private function empresaPorSetor(): array
    {
        $out = [];
        foreach (DB::table('setores')->select('id', 'empresa_id')->get() as $s) {
            $out[(int) $s->id] = (int) $s->empresa_id;
        }

        return $out;
    }

    /** @return array<int,true> */
    private function idsDe(string $tabela): array
    {
        $ids = [];
        foreach (DB::table($tabela)->pluck('id') as $id) {
            $ids[(int) $id] = true;
        }

        return $ids;
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
