<?php

namespace App\Domain\Caixa;

use App\Domain\Financeiro\BaixaService;
use App\Models\Caixa\Conta;
use App\Models\Caixa\ContaFechamento;
use App\Models\Caixa\ContaMovimento;
use App\Models\Financeiro\Financeiro;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CaixaService (N6) — o componente de RISCO MÁXIMO. Saldo auditável.
 *
 * INVARIANTE (princípio #5, caso #1 do baseline): Σ contamovimentos.valor =
 * conta.saldo_atual. Toda mutação passa por movimentar(), que num MESMO lock:
 *   1) grava o movimento (valor assinado + saldo_resultante);
 *   2) atualiza conta.saldo_atual.
 * Baixa de título recebe valor líquido = parcela.valor + juros + multa - desconto;
 * o que entra no caixa é o líquido (sensível a centavo).
 */
class CaixaService
{
    public const BAIXA = 'BAIXA';

    public const TRANSFERENCIA = 'TRANSFERENCIA';

    public const ESTORNO = 'ESTORNO';

    public const AJUSTE = 'AJUSTE';

    public const ABERTURA = 'ABERTURA';

    public function __construct(private BaixaService $baixas) {}

    /**
     * Movimento atômico de conta. `valor` ASSINADO (+entrada / -saída).
     *
     * Regra do legado: NÃO se lança em caixa fechado, salvo "lançamento em caixa
     * fechado" explícito (com permissão). Aqui isso é o flag `permitir_fechado` em
     * `$extra` — só os callers internos legítimos (abertura, lançamento autorizado)
     * o setam; o caminho normal recusa o movimento num caixa fechado.
     *
     * @param  array<string,mixed>  $extra  juros/multa/desconto/origem/origem_id/permitir_fechado/...
     */
    public function movimentar(
        int $contaId,
        float $valor,
        string $tipo,
        int $empresaId,
        array $extra = [],
    ): ContaMovimento {
        $permitirFechado = (bool) ($extra['permitir_fechado'] ?? false);

        return DB::transaction(function () use ($contaId, $valor, $tipo, $empresaId, $extra, $permitirFechado) {
            $conta = Conta::withoutTenant()
                ->whereKey($contaId)
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->first();
            if (! $conta) {
                throw ValidationException::withMessages(['conta' => 'Conta não pertence à empresa da operação.']);
            }

            if ($conta->fechado && ! $permitirFechado) {
                throw ValidationException::withMessages([
                    'conta' => 'Caixa fechado: abra o caixa antes de lançar (ou use lançamento em caixa fechado autorizado).',
                ]);
            }

            $novoSaldo = round((float) $conta->saldo_atual + $valor, 2);
            $conta->saldo_atual = $novoSaldo;
            $conta->save();

            return ContaMovimento::create(array_merge([
                'empresa_id' => $conta->empresa_id,
                'conta_id' => $conta->id,
                'tipo' => $tipo,
                'valor' => round($valor, 2),
                'saldo_resultante' => $novoSaldo,
                'datahora' => $extra['datahora'] ?? now(),
            ], array_intersect_key($extra, array_flip([
                'contamovimentotipo_id', 'financeiroparcela_id', 'pagarreceber', 'juros',
                'multa', 'desconto', 'descricao', 'origem', 'origem_id', 'estorno_de_id', 'user_id',
            ]))));
        });
    }

    /** Abre o caixa de uma conta (registra fechamento aberto com saldo inicial). */
    public function abrir(int $contaId, int $empresaId, ?string $datahora = null, ?int $userId = null): ContaFechamento
    {
        return DB::transaction(function () use ($contaId, $empresaId, $datahora, $userId) {
            $conta = Conta::withoutTenant()->whereKey($contaId)->where('empresa_id', $empresaId)->lockForUpdate()->firstOrFail();
            if (! $conta->fechado && ContaFechamento::withoutTenant()->where('conta_id', $conta->id)->where('aberto', true)->exists()) {
                throw ValidationException::withMessages(['conta' => 'Caixa já está aberto.']);
            }

            $conta->update(['fechado' => false]);

            return ContaFechamento::create([
                'empresa_id' => $conta->empresa_id,
                'conta_id' => $conta->id,
                'user_id' => $userId,
                'abertura' => $datahora ?? now(),
                'saldo_inicial' => $conta->saldo_atual,
                'aberto' => true,
            ]);
        });
    }

    /** Fecha o caixa: grava saldo final no fechamento aberto. */
    public function fechar(int $contaId, int $empresaId, ?string $datahora = null): ContaFechamento
    {
        return DB::transaction(function () use ($contaId, $empresaId, $datahora) {
            $conta = Conta::withoutTenant()->whereKey($contaId)->where('empresa_id', $empresaId)->lockForUpdate()->firstOrFail();
            $fechamento = ContaFechamento::withoutTenant()
                ->where('conta_id', $conta->id)->where('aberto', true)
                ->orderByDesc('abertura')->first();

            if (! $fechamento) {
                throw ValidationException::withMessages(['conta' => 'Não há caixa aberto para fechar.']);
            }

            $fechamento->update([
                'fechamento' => $datahora ?? now(),
                'saldo_final' => $conta->saldo_atual,
                'aberto' => false,
            ]);
            $conta->update(['fechado' => true]);

            return $fechamento->refresh();
        });
    }

    /**
     * Baixa uma parcela de financeiro numa conta. O líquido que entra no caixa é
     * valor + juros + multa - desconto. Atualiza a parcela (baixado/efetivado).
     */
    public function baixarParcela(
        int $contaId,
        int $parcelaId,
        int $empresaId,
        float $juros = 0,
        float $multa = 0,
        float $desconto = 0,
        ?int $userId = null,
    ): ContaMovimento {
        return DB::transaction(function () use ($contaId, $parcelaId, $empresaId, $juros, $multa, $desconto, $userId) {
            $parcela = FinanceiroParcela::query()->with('financeiro')->lockForUpdate()->findOrFail($parcelaId);

            // F00.5 — revalidação de tenant: `financeiroparcelas` não tem empresa_id
            // próprio (é filha de `financeiros`). Como o id vem do request, garantimos
            // que o título-pai pertence à empresa ativa (Financeiro é tenant-scoped),
            // evitando baixar parcela de OUTRA empresa (IDOR apontado na auditoria).
            if (! Financeiro::withoutTenant()->whereKey($parcela->financeiro_id)->where('empresa_id', $empresaId)->exists()) {
                throw ValidationException::withMessages(['parcela' => 'Parcela não pertence à empresa ativa.']);
            }

            if ($parcela->baixado) {
                throw ValidationException::withMessages(['parcela' => 'Parcela já baixada.']);
            }

            $pagarReceber = $parcela->financeiro->pagarreceber; // P / R
            $liquido = round((float) $parcela->valor + $juros + $multa - $desconto, 2);
            // Recebimento entra (+) no caixa; pagamento sai (-).
            $valorAssinado = $pagarReceber === 'R' ? $liquido : -$liquido;

            $mov = $this->movimentar($contaId, $valorAssinado, self::BAIXA, $empresaId, [
                'financeiroparcela_id' => $parcela->id,
                'pagarreceber' => $pagarReceber,
                'juros' => $juros,
                'multa' => $multa,
                'desconto' => $desconto,
                'origem' => 'baixa',
                'origem_id' => $parcela->id,
                'descricao' => "Baixa parcela #{$parcela->numero} do título #{$parcela->financeiro_id}",
                'user_id' => $userId,
            ]);

            $this->baixas->baixar($parcela->id, $empresaId, $liquido, 'caixa');

            return $mov;
        });
    }

    /**
     * Baixa VÁRIAS parcelas numa conta, em UMA transação (tudo-ou-nada). Espelha
     * o `baixarTitulos` do legado: o operador seleciona N títulos e baixa em lote.
     *
     * @param  list<array{parcela_id:int, juros?:float, multa?:float, desconto?:float}>  $itens
     * @return list<ContaMovimento>
     */
    public function baixarTitulos(int $contaId, array $itens, int $empresaId, ?int $userId = null): array
    {
        if ($itens === []) {
            throw ValidationException::withMessages(['itens' => 'Selecione ao menos um título para baixar.']);
        }

        return DB::transaction(function () use ($contaId, $itens, $empresaId, $userId) {
            $movimentos = [];
            foreach ($itens as $i) {
                $movimentos[] = $this->baixarParcela(
                    $contaId,
                    (int) $i['parcela_id'],
                    $empresaId,
                    (float) ($i['juros'] ?? 0),
                    (float) ($i['multa'] ?? 0),
                    (float) ($i['desconto'] ?? 0),
                    $userId,
                );
            }

            return $movimentos;
        });
    }

    /**
     * Lançamento AUTORIZADO em caixa fechado (retroativo) — espelha o
     * `movimentarCaixaFechado` do legado. Exige decisão de quem chama (controller
     * valida a permissão 'caixa.edit' + a intenção explícita).
     *
     * @param  array<string,mixed>  $extra
     */
    public function lancarEmCaixaFechado(int $contaId, float $valor, string $tipo, int $empresaId, array $extra = []): ContaMovimento
    {
        return $this->movimentar($contaId, $valor, $tipo, $empresaId, array_merge($extra, ['permitir_fechado' => true]));
    }

    /**
     * Transferência entre contas (saída de uma, entrada na outra) — atômica.
     *
     * @return array{saida: ContaMovimento, entrada: ContaMovimento}
     */
    public function transferir(
        int $contaOrigem,
        int $contaDestino,
        float $valor,
        int $empresaId,
        ?int $userId = null,
    ): array {
        if ($contaOrigem === $contaDestino) {
            throw ValidationException::withMessages(['conta_destino' => 'Contas de origem e destino devem ser diferentes.']);
        }
        $valor = abs(round($valor, 2));

        // Contenção F0-04/A-12.2: validar o owner no serviço, antes do primeiro
        // movimento. `movimentar()` usa withoutTenant para o lock; sem esta
        // pré-condição, dois ids globais permitiriam transferir em outro tenant.
        $contasValidas = Conta::withoutTenant()
            ->where('empresa_id', $empresaId)
            ->whereIn('id', [$contaOrigem, $contaDestino])
            ->count();
        if ($contasValidas !== 2) {
            throw ValidationException::withMessages([
                'contas' => 'As contas de origem e destino devem pertencer à empresa ativa.',
            ]);
        }

        return DB::transaction(function () use ($contaOrigem, $contaDestino, $valor, $empresaId, $userId) {
            $saida = $this->movimentar($contaOrigem, -$valor, self::TRANSFERENCIA, $empresaId, [
                'origem' => 'transferencia', 'origem_id' => $contaDestino, 'descricao' => 'Transferência enviada', 'user_id' => $userId,
            ]);
            $entrada = $this->movimentar($contaDestino, $valor, self::TRANSFERENCIA, $empresaId, [
                'origem' => 'transferencia', 'origem_id' => $contaOrigem, 'descricao' => 'Transferência recebida', 'user_id' => $userId,
            ]);

            return ['saida' => $saida, 'entrada' => $entrada];
        });
    }

    /**
     * Estorna um movimento: gera o movimento inverso (mantém o histórico) e, se for
     * baixa de parcela, reabre a parcela.
     */
    public function estornar(int $movimentoId, int $empresaId, ?int $userId = null): ContaMovimento
    {
        return DB::transaction(function () use ($movimentoId, $empresaId, $userId) {
            $original = ContaMovimento::withoutTenant()
                ->whereKey($movimentoId)
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($original->tipo === self::ESTORNO || $original->estorno_de_id) {
                throw ValidationException::withMessages(['movimento' => 'Movimento de estorno não pode ser estornado.']);
            }

            // Estorno é correção: permitido mesmo com o caixa fechado.
            $inverso = $this->movimentar($original->conta_id, -(float) $original->valor, self::ESTORNO, $empresaId, [
                'estorno_de_id' => $original->id,
                'origem' => 'estorno',
                'origem_id' => $original->id,
                'descricao' => "Estorno do movimento #{$original->id}",
                'user_id' => $userId,
                'permitir_fechado' => true,
            ]);

            // Reabre a parcela pela PORTA UNICA, se a origem era uma baixa.
            //
            // Antes esta escrita era direta no model, e sem a verificacao de
            // empresa que a baixa faz — a unica protecao era o global scope de
            // tenant, que nao vale em job nem em comando de console, que e
            // exatamente onde um estorno em lote roda (F5-02).
            if ($original->financeiroparcela_id) {
                $this->baixas->reabrir((int) $original->financeiroparcela_id, $empresaId, 'estorno');
            }

            return $inverso;
        });
    }

    /**
     * Cria uma conta com o saldo inicial registrado como movimento de ABERTURA,
     * de modo que a invariante Σ movimentos = saldo_atual valha desde o início.
     *
     * @param  array<string,mixed>  $dados
     */
    public function criarConta(array $dados): Conta
    {
        $inicial = round((float) ($dados['saldo_inicial'] ?? 0), 2);

        return DB::transaction(function () use ($dados, $inicial) {
            $conta = Conta::create(array_merge($dados, ['saldo_inicial' => $inicial, 'saldo_atual' => 0]));

            if ($inicial != 0.0) {
                $this->movimentar($conta->id, $inicial, self::ABERTURA, (int) $conta->empresa_id, [
                    'origem' => 'abertura', 'descricao' => 'Saldo inicial',
                ]);
            }

            return $conta->refresh();
        });
    }

    /** Saldo derivado dos movimentos (a fonte da verdade). Invariante: == saldo_atual. */
    public function saldoDerivado(int $contaId): float
    {
        return round((float) ContaMovimento::query()->where('conta_id', $contaId)->sum('valor'), 2);
    }
}
