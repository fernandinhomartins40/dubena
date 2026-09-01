<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\BalanceInvariant;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;

/**
 * N6 — contas (caixa/banco) e seus movimentos (~410 mil).
 *
 * Não existe tabela `caixas` no legado: o caixa É uma conta, distinguida por
 * `contatipo_id`. O que o destino chama de `tipo` (CAIXA/BANCO) é derivado da
 * existência de `banco_id`.
 *
 * `saldo_resultante` é NOT NULL no destino e não existe no legado — é
 * reconstruído acumulando os movimentos por conta em ordem cronológica, a
 * partir do `saldoinicial` da conta. É o que torna o extrato conferível.
 */
final class CaixaMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'caixa';
    }

    public function dependeDe(): array
    {
        return ['financeiro'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->usarConexaoDe($ctx);

        $this->ctxAtual = $ctx;

        if (! $this->tabelaExiste($ctx, 'contas')) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['legado indisponível ou sem as tabelas de conta/caixa']);
        }

        $idsEmpresa = $this->idsDe('empresas');
        $lidos = 0;
        $gravados = 0;
        $pulados = 0;
        $avisos = [];

        // ── Contas ──
        $contas = [];
        $saldoInicial = [];
        foreach ($ctx->legado()->table('contas')->orderBy('id')->get() as $r) {
            $lidos++;
            $empresa = (int) $r->empresa_id;
            if (! isset($idsEmpresa[$empresa])) {
                $pulados++;

                continue;
            }
            $saldoInicial[(int) $r->id] = (float) ($r->saldoinicial ?? 0);
            $contas[] = [
                'id' => (int) $r->id,
                'empresa_id' => $empresa,
                'grupo_id' => (int) $r->grupo_id,
                'descricao' => mb_substr(trim((string) $r->descricao), 0, 255),
                // O legado não rotula caixa/banco: quem tem banco é BANCO.
                'tipo' => ! empty($r->banco_id) ? 'BANCO' : 'CAIXA',
                // O `banco_id` era lido para derivar o tipo, mas não gravado — a
                // conta chegava marcada como BANCO sem dizer QUAL banco, e a tela
                // de contas exibia o campo vazio.
                'banco_id' => $r->banco_id ?? null,
                'agencia' => mb_substr((string) ($r->agencia ?? ''), 0, 20) ?: null,
                'numero' => mb_substr((string) ($r->conta ?? ''), 0, 30) ?: null,
                'saldo_inicial' => round((float) ($r->saldoinicial ?? 0), 2),
                'saldo_atual' => round((float) ($r->saldoatual ?? 0), 2),
                'fechado' => $this->booleano($r->fechado ?? null),
                'created_at' => $r->created_at ?? null,
            ];
        }

        if (! $ctx->dryRun) {
            // `bancos` vem do migrator de apoio, que pode não ter rodado (ou ter
            // vindo incompleto do dump). FK nullable: referência sem destino vira
            // null em vez de derrubar a carga inteira das contas.
            $contas = $this->anularFksInvalidas($contas, ['banco_id' => 'bancos']);
            $gravados += $this->gravarPreservandoId('contas', $contas);
        }

        // ── Movimentos (volumosos; saldo reconstruído por conta) ──
        if ($this->tabelaExiste($ctx, 'contamovimentos')) {
            $idsConta = $this->idsDe('contas');
            $empresaDaConta = $this->empresaPorConta();
            $idsParcela = $this->idsDe('financeiroparcelas');
            $this->idsUser = $this->idsDe('users');
            $corrente = $saldoInicial;

            $ctx->legado()->table('contamovimentos')
                ->orderBy('datahorabaixa')
                ->orderBy('id')
                ->chunk(5000, function ($rows) use (
                    &$lidos, &$gravados, &$pulados, &$corrente,
                    $ctx, $idsConta, $empresaDaConta, $idsParcela
                ) {
                    $lote = [];
                    foreach ($rows as $r) {
                        $lidos++;
                        $conta = (int) $r->conta_id;
                        if (! isset($idsConta[$conta])) {
                            $pulados++;

                            continue;
                        }

                        $pr = mb_strtoupper(trim((string) $r->pagarreceber));
                        $entrada = str_starts_with($pr, 'R');
                        $valor = round((float) ($r->valorefetivado ?: $r->valor), 2);
                        $corrente[$conta] = ($corrente[$conta] ?? 0.0)
                            + ($entrada ? $valor : -$valor);

                        $parcela = (int) ($r->financeiroparcela_id ?? 0);

                        $lote[] = [
                            'id' => (int) $r->id,
                            'empresa_id' => $empresaDaConta[$conta] ?? 0,
                            'conta_id' => $conta,
                            'financeiroparcela_id' => isset($idsParcela[$parcela]) ? $parcela : null,
                            'tipo' => $entrada ? 'ENTRADA' : 'SAIDA',
                            'pagarreceber' => $entrada ? 'R' : 'P',
                            'valor' => $valor,
                            'saldo_resultante' => round($corrente[$conta], 2),
                            'juros' => round((float) ($r->juros ?? 0), 2),
                            'multa' => round((float) ($r->multa ?? 0), 2),
                            'desconto' => round((float) ($r->desconto ?? 0), 2),
                            'descricao' => mb_substr((string) ($r->descricao ?? ''), 0, 255) ?: null,
                            'origem' => mb_substr((string) ($r->origem ?? ''), 0, 40) ?: null,
                            'user_id' => $this->userValido($r->user_id ?? null),
                            // NOT NULL no destino: a data da baixa é o momento
                            // do movimento; sem ela, cai no created_at.
                            'datahora' => $r->datahorabaixa ?? $r->created_at ?? now(),
                            'created_at' => $r->datahorabaixa ?? $r->created_at ?? null,
                        ];
                    }
                    if ($lote !== [] && ! $ctx->dryRun) {
                        $gravados += $this->gravarPreservandoId('contamovimentos', $lote, ['id'], 1000);
                    }
                });
        }

        if ($pulados > 0) {
            $avisos[] = "{$pulados} registro(s) descartado(s): empresa ou conta "
                .'ausente no destino';
        }
        $avisos[] = 'saldo_resultante reconstruído a partir do saldo inicial da conta '
            .'mais os movimentos em ordem cronológica (o legado não o armazena)';

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
            new CountInvariant($ctx, 'contas', 'contas'),
            new CountInvariant($ctx, 'contamovimentos', 'contamovimentos'),
            // T5.1 — a invariante do principio #5, que existia como classe e
            // nunca era registrada: nenhum migrator a instanciava, entao ela
            // jamais aparecia no `cutover:check`. Contar linhas prova que os
            // movimentos vieram; so esta prova que o SALDO bate com eles.
            // Um saldo materializado divergente do log de movimentos e dinheiro
            // errado na tela, do tipo que ninguem descobre ate fechar o caixa.
            // Recorte por id: só contas NASCIDAS no novo respondem pelo saldo.
            // O legado nunca manteve Σmovimentos = saldoatual (conta 692: saldo
            // 0 na origem com R$ 26,5 mi em movimentos), então cobrar isso do
            // histórico herdado daria falha em 28 de 28 contas e ensinaria a
            // ignorar o portão. Ver docs/gauntlet/T5.1_ACHADOS.md.
            new BalanceInvariant(
                $ctx, 'contamovimentos', 'valor', 'contas', 'saldo_atual',
                ['conta_id'], 0.001, ['conta_id' => 'id'], $this->fronteiraLegado($ctx, 'contas'),
            ),
        ];
    }

    /** @var array<int,true> ids de users válidos (FK opcional do movimento). */
    private array $idsUser = [];

    /** id do usuário se existir no destino, senão null. */
    private function userValido(mixed $v): ?int
    {
        $id = (int) ($v ?? 0);

        return isset($this->idsUser[$id]) ? $id : null;
    }

    /** @return array<int,int> */
    private function empresaPorConta(): array
    {
        $out = [];
        foreach ($this->destino()->table('contas')->select('id', 'empresa_id')->get() as $c) {
            $out[(int) $c->id] = (int) $c->empresa_id;
        }

        return $out;
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
        foreach ($this->destino()->table($tabela)->select('id')->cursor() as $r) {
            $ids[(int) $r->id] = true;
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
     * Primeiro id que NÃO veio do legado.
     *
     * O ETL preserva os ids da origem; tudo acima do maior id migrado nasceu
     * aqui. É a fronteira que separa "dado que herdamos como estava" de "dado
     * pelo qual este sistema responde".
     */
    private function fronteiraLegado(MigrationContext $ctx, string $tabelaLegado): ?int
    {
        try {
            if (! $ctx->legado()->getSchemaBuilder()->hasTable($tabelaLegado)) {
                return null;
            }

            $max = (int) ($ctx->legado()->table($tabelaLegado)->max('id') ?? 0);

            return $max > 0 ? $max + 1 : null;
        } catch (\Throwable) {
            // Legado indisponível (dev/CI): sem fronteira, verifica tudo — que
            // é o comportamento correto num banco sem histórico herdado.
            return null;
        }
    }
}
