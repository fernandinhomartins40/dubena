<?php

namespace App\Domain\Financeiro;

use App\Models\Caixa\Conta;
use App\Models\Caixa\ContaMovimento;
use App\Models\Financeiro\ConciliacaoLancamento;
use App\Models\Financeiro\OrigemMatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ConciliacaoService (C8) — concilia o extrato bancário (OFX) com os movimentos
 * de conta do ERP. Casa por (valor, data) dentro de uma tolerância de dias; o que
 * casa vira "conciliado", o resto fica pendente dos dois lados para ação manual.
 */
class ConciliacaoService
{
    public function __construct(
        private OfxParser $ofx,
        private RegraExtratoService $regras,
    ) {}

    /**
     * Concilia o OFX contra os movimentos da conta no período.
     *
     * @return array{
     *   conciliados: list<array<string,mixed>>,
     *   ofx_pendentes: list<array<string,mixed>>,
     *   erp_pendentes: list<array<string,mixed>>,
     *   resumo: array{ofx:int, erp:int, conciliados:int}
     * }
     */
    public function conciliar(int $contaId, int $empresaId, string $ofxConteudo, string $inicio, string $fim, int $toleranciaDias = 2): array
    {
        if (! Conta::withoutTenant()->whereKey($contaId)->where('empresa_id', $empresaId)->exists()) {
            throw ValidationException::withMessages(['conta_id' => 'Conta invalida para a empresa ativa.']);
        }

        $transacoes = $this->ofx->transacoes($ofxConteudo);

        $movimentos = ContaMovimento::withoutTenant()
            ->where('empresa_id', $empresaId)
            ->where('conta_id', $contaId)
            ->whereBetween('datahora', [Carbon::parse($inicio)->startOfDay(), Carbon::parse($fim)->endOfDay()])
            ->get()
            ->map(fn (ContaMovimento $m) => [
                'id' => $m->id,
                'data' => $m->datahora?->toDateString(),
                'valor' => round((float) $m->valor, 2),
                'descricao' => $m->descricao,
                'usado' => false,
            ])->all();

        $conciliados = [];
        $ofxPendentes = [];

        foreach ($transacoes as $t) {
            $idx = $this->casar($t, $movimentos, $toleranciaDias);
            if ($idx !== null) {
                $movimentos[$idx]['usado'] = true;
                $conciliados[] = [
                    'ofx' => $t,
                    'movimento_id' => $movimentos[$idx]['id'],
                    'valor' => $t['valor'],
                ];
            } else {
                $ofxPendentes[] = $t;
            }
        }

        $erpPendentes = array_values(array_filter($movimentos, fn ($m) => ! $m['usado']));

        // As pendentes são as que o operador teria de classificar à mão — é
        // exatamente nelas que a regra economiza trabalho (T4.2). As já
        // conciliadas não precisam: elas casaram com um movimento existente.
        $ofxPendentes = $this->regras->aplicar($contaId, $empresaId, $ofxPendentes);

        // Persistir ANTES de devolver: sem isso a conciliacao inteira e efemera
        // — o proximo upload do mesmo extrato recomeca do zero e nao sabe que ja
        // viu aqueles lancamentos (F5-04).
        $this->persistir($contaId, $empresaId, $conciliados, $ofxPendentes, $toleranciaDias);

        return [
            'conciliados' => $conciliados,
            'ofx_pendentes' => $ofxPendentes,
            'erp_pendentes' => array_map(fn ($m) => [
                'id' => $m['id'], 'data' => $m['data'], 'valor' => $m['valor'], 'descricao' => $m['descricao'],
            ], $erpPendentes),
            'resumo' => [
                'ofx' => count($transacoes),
                'erp' => count($movimentos),
                'conciliados' => count($conciliados),
            ],
        ];
    }

    /**
     * Casamento MANUAL: uma pessoa afirma que aquele lancamento do extrato e
     * aquele movimento do ERP.
     *
     * O algoritmo casa por (valor, data). Ele nao alcanca o caso real de uma
     * tarifa que entrou com valor diferente do previsto, ou de um deposito que o
     * banco lancou tres dias depois. Quem resolve isso e o operador — e a
     * decisao dele precisa ficar registrada com nome e motivo, senao a
     * conciliacao vira um numero que ninguem consegue defender.
     */
    public function casarManualmente(int $lancamentoId, int $movimentoId, int $empresaId, ?int $userId, ?string $motivo = null): ConciliacaoLancamento
    {
        $lancamento = ConciliacaoLancamento::withoutTenant()
            ->whereKey($lancamentoId)->where('empresa_id', $empresaId)->first();

        if ($lancamento === null) {
            throw ValidationException::withMessages(['lancamento_id' => 'Lancamento invalido para a empresa ativa.']);
        }

        // O movimento tem de ser da MESMA empresa e da MESMA conta. Sem as duas
        // verificacoes, um id de outra empresa conciliaria dinheiro alheio — e a
        // fronteira aqui e por conta, nao so por empresa: casar um debito da
        // conta A com um movimento da conta B fecharia as duas erradas.
        $movimentoValido = ContaMovimento::withoutTenant()
            ->whereKey($movimentoId)
            ->where('empresa_id', $empresaId)
            ->where('conta_id', $lancamento->conta_id)
            ->exists();

        if (! $movimentoValido) {
            throw ValidationException::withMessages([
                'movimento_id' => 'Movimento invalido para a conta deste lancamento.',
            ]);
        }

        $lancamento->update([
            'conta_movimento_id' => $movimentoId,
            'origem_match' => OrigemMatch::MANUAL->value,
            'tolerancia_dias' => null,
            'decidido_por' => $userId,
            'decidido_em' => now(),
            'motivo' => $motivo,
        ]);

        return $lancamento->refresh();
    }

    /**
     * Desfaz um par — automatico ou manual.
     *
     * Vai para DESFEITO, e nao de volta para PENDENTE, porque "nunca casou" e
     * "casou e alguem desfez" sao fatos diferentes. O segundo e o interessante:
     * e o rastro de que o algoritmo errou ali, ou de que alguem discordou.
     *
     * O efeito colateral util e que a proxima rodada respeita a decisao —
     * `persistir()` nao toca em DESFEITO.
     */
    public function desfazer(int $lancamentoId, int $empresaId, ?int $userId, ?string $motivo = null): ConciliacaoLancamento
    {
        $lancamento = ConciliacaoLancamento::withoutTenant()
            ->whereKey($lancamentoId)->where('empresa_id', $empresaId)->first();

        if ($lancamento === null) {
            throw ValidationException::withMessages(['lancamento_id' => 'Lancamento invalido para a empresa ativa.']);
        }

        $lancamento->update([
            'conta_movimento_id' => null,
            'origem_match' => OrigemMatch::DESFEITO->value,
            'tolerancia_dias' => null,
            'decidido_por' => $userId,
            'decidido_em' => now(),
            'motivo' => $motivo,
        ]);

        return $lancamento->refresh();
    }

    /**
     * Grava o resultado da rodada, preservando o que uma PESSOA decidiu.
     *
     * ## A regra que importa
     *
     * Reprocessar o extrato do mes e rotina, nao acidente. Entao o upsert por
     * `(conta_id, fitid)` tem de ser **idempotente** — e, mais que isso, tem de
     * ser humilde: se um operador casou aquele lancamento a mao, ou desfez um
     * par automatico, o algoritmo **nao pode reverter a decisao dele** na
     * proxima rodada. Ele veria o par voltar sozinho e nao teria como saber por
     * que.
     *
     * Por isso MANUAL e DESFEITO sao intocaveis aqui. O automatico se atualiza a
     * vontade: e resultado de calculo, e recalcular e o proposito.
     *
     * @param  list<array<string,mixed>>  $conciliados
     * @param  list<array<string,mixed>>  $pendentes
     */
    private function persistir(int $contaId, int $empresaId, array $conciliados, array $pendentes, int $toleranciaDias): void
    {
        $linhas = [];

        foreach ($conciliados as $c) {
            $linhas[] = [$c['ofx'], $c['movimento_id'], OrigemMatch::AUTOMATICO];
        }
        foreach ($pendentes as $p) {
            $linhas[] = [$p, null, OrigemMatch::PENDENTE];
        }

        DB::transaction(function () use ($linhas, $contaId, $empresaId, $toleranciaDias) {
            foreach ($linhas as [$ofx, $movimentoId, $origem]) {
                $fitid = (string) ($ofx['fitid'] ?? '');

                // Sem FITID nao ha chave de idempotencia: gravar produziria uma
                // linha nova a cada rodada, que e pior que nao gravar. O
                // lancamento continua visivel na resposta.
                if ($fitid === '') {
                    continue;
                }

                $existente = ConciliacaoLancamento::withoutTenant()
                    ->where('conta_id', $contaId)->where('fitid', $fitid)->first();

                if ($existente !== null && in_array($existente->origem_match, [
                    OrigemMatch::MANUAL->value, OrigemMatch::DESFEITO->value,
                ], true)) {
                    continue; // decisao humana nao se sobrescreve
                }

                ConciliacaoLancamento::withoutTenant()->updateOrCreate(
                    ['conta_id' => $contaId, 'fitid' => $fitid],
                    [
                        'empresa_id' => $empresaId,
                        'data_banco' => $ofx['data'] ?? null,
                        'valor_banco' => round((float) ($ofx['valor'] ?? 0), 2),
                        'descricao_banco' => $ofx['descricao'] ?? null,
                        'tipo_banco' => $ofx['tipo'] ?? null,
                        'conta_movimento_id' => $movimentoId,
                        'origem_match' => $origem->value,
                        'tolerancia_dias' => $origem === OrigemMatch::AUTOMATICO ? $toleranciaDias : null,
                    ],
                );
            }
        });
    }

    /**
     * Acha o índice de um movimento do ERP que casa com a transação OFX:
     * mesmo valor (centavo) e data dentro da tolerância. Retorna null se nenhum.
     *
     * @param  list<array<string,mixed>>  $movimentos
     */
    private function casar(array $transacao, array $movimentos, int $toleranciaDias): ?int
    {
        $valorOfx = round((float) $transacao['valor'], 2);
        $dataOfx = $transacao['data'] ? Carbon::parse($transacao['data']) : null;

        foreach ($movimentos as $i => $m) {
            if ($m['usado'] || round((float) $m['valor'], 2) !== $valorOfx) {
                continue;
            }
            if ($dataOfx === null || $m['data'] === null) {
                return $i; // sem data confiável: casa só pelo valor
            }
            if (abs(Carbon::parse($m['data'])->diffInDays($dataOfx)) <= $toleranciaDias) {
                return $i;
            }
        }

        return null;
    }
}
