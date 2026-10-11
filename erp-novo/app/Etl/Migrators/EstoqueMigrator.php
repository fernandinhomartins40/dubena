<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\BalanceInvariant;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;

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
        $this->usarConexaoDe($ctx);

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
        [$n, $g, $p, $somaDoHistorico] = $this->migrarHistorico($ctx, $idsSetor, $idsProduto, $empresaDoSetor);
        $lidos += $n;
        $gravados += $g;
        $pulados += $p;

        // O legado não garante saldo = soma do histórico; a aplicação nova
        // garante, e `estoque:conferir` cobra. Onde o legado já não fechava, a
        // diferença vem junto — e tem de chegar ao operador AQUI, não aparecer
        // semanas depois como "o sistema novo bagunçou o estoque".
        if ($n > 0) {
            $naoFecham = 0;
            foreach ($saldos as $s) {
                $soma = $somaDoHistorico[$s['setor_id'].':'.$s['produto_id']] ?? 0.0;
                if (abs($s['quantidade'] - $soma) >= 0.001) {
                    $naoFecham++;
                }
            }
            if ($naoFecham > 0) {
                $avisos[] = "{$naoFecham} de ".count($saldos).' saldo(s) setor×produto não fecham com a soma '
                    .'do histórico JÁ NO LEGADO — `estoque:conferir` lista cada um; o ajuste é decisão do dono';
            }
        }

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
        $ctx = $this->ctxAtual ?? new MigrationContext;

        return [
            new CountInvariant($ctx, 'setors', 'setores'),
            new CountInvariant($ctx, 'estoquesetors', 'estoquesaldos'),
            // T5.1 — ver CaixaMigrator: a invariante do principio #5 existia e
            // nunca rodava. Aqui um saldo divergente do historico e estoque
            // fantasma: o sistema vende o que nao tem, ou esconde o que tem.
            // Mesmo recorte do CaixaMigrator: o saldo herdado do legado não
            // deriva do histórico, e cobrar isso reprovaria 121 chaves de
            // partida. Só o que nasce aqui responde pela igualdade.
            new BalanceInvariant(
                $ctx, 'estoquehistorico', 'quantidade', 'estoquesaldos', 'quantidade',
                ['setor_id', 'produto_id'], 0.001, [], $this->fronteiraSaldos($ctx),
            ),
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
     * `quantidade` vai ASSINADA (+entrada / −saída), que é o contrato do ledger
     * da aplicação (`EstoqueService::movimentar`): extrato, conferência e Bloco
     * H somam a coluna direto. O legado guarda o módulo e o sentido à parte, em
     * `movimentacao`; copiar o módulo fez a carga real de 2026-10-11 somar
     * 2,38 milhões de botijões num setor que tem 3.484.
     *
     * @return array{0:int, 1:int, 2:int, 3:array<string,float>} lidos, gravados, pulados, soma por "setor:produto"
     */
    private function migrarHistorico(
        MigrationContext $ctx, array $idsSetor, array $idsProduto, array $empresaDoSetor
    ): array {
        if (! $this->tabelaExiste($ctx, 'estoquesetorhistoricos')) {
            return [0, 0, 0, []];
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
                    $qtd = $tipo === 'ENTRADA' ? $qtd : -$qtd;
                    $chave = $setor.':'.$produto;
                    $corrente[$chave] = ($corrente[$chave] ?? 0.0) + $qtd;

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

        return [$lidos, $gravados, $pulados, $corrente];
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
        foreach ($this->destino()->table('setores')->select('id', 'empresa_id')->get() as $s) {
            $out[(int) $s->id] = (int) $s->empresa_id;
        }

        return $out;
    }

    /** @return array<int,true> */
    private function idsDe(string $tabela): array
    {
        $ids = [];
        foreach ($this->destino()->table($tabela)->pluck('id') as $id) {
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

    /**
     * Primeiro id de `estoquesaldos` que não veio do legado. Ver a explicação
     * em CaixaMigrator::fronteiraLegado().
     */
    private function fronteiraSaldos(MigrationContext $ctx): ?int
    {
        try {
            if (! $ctx->legado()->getSchemaBuilder()->hasTable('estoquesetors')) {
                return null;
            }

            $max = (int) ($ctx->legado()->table('estoquesetors')->max('id') ?? 0);

            return $max > 0 ? $max + 1 : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
