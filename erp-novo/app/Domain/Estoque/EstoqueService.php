<?php

namespace App\Domain\Estoque;

use App\Models\Estoque\EstoqueFechamento;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\EstoqueInventario;
use App\Models\Estoque\EstoqueRequisicao;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * EstoqueService — reescrito limpo (caminho crítico do plano, baseline obrigatório).
 *
 * INVARIANTE central (princípio #5): o saldo é DERIVÁVEL do histórico.
 * Toda mutação passa por movimentar(), que num MESMO lock/transação:
 *   1) grava em estoquehistorico (quantidade assinada + saldo_resultante);
 *   2) atualiza estoquesaldos.quantidade (= saldo anterior + quantidade);
 *   3) recalcula o custo médio ponderado em ENTRADAS.
 * Logo, sempre vale: Σ estoquehistorico.quantidade = estoquesaldos.quantidade.
 */
class EstoqueService
{
    public const ENTRADA = 'ENTRADA';

    public const SAIDA = 'SAIDA';

    public const TRANSFERENCIA = 'TRANSFERENCIA';

    public const INVENTARIO = 'INVENTARIO';

    public const ACERTO = 'ACERTO';

    /**
     * Movimento atômico de estoque. `quantidade` ASSINADA (+entrada / -saída).
     * Custo médio ponderado recalculado só em entradas com custo informado.
     */
    public function movimentar(
        int $setorId,
        int $produtoId,
        float $quantidade,
        string $tipo,
        ?float $custoUnitario = null,
        ?string $origem = null,
        ?int $origemId = null,
        ?int $userId = null,
    ): EstoqueHistorico {
        if ($quantidade == 0.0) {
            throw ValidationException::withMessages(['quantidade' => 'Quantidade não pode ser zero.']);
        }

        // empresa_id derivado do setor (não depende do TenantContext: o service é
        // chamado via HTTP, testes e ETL). Garante a coerência do escopo.
        $empresaId = (int) Setor::withoutTenant()->whereKey($setorId)->value('empresa_id');

        return DB::transaction(function () use ($setorId, $produtoId, $quantidade, $tipo, $custoUnitario, $origem, $origemId, $userId, $empresaId) {
            // Lock pessimista do saldo (cria se não existir) — base anti-corrida.
            $saldo = EstoqueSaldo::withoutTenant()
                ->where('setor_id', $setorId)->where('produto_id', $produtoId)
                ->lockForUpdate()->first();

            if (! $saldo) {
                $saldo = EstoqueSaldo::create([
                    'empresa_id' => $empresaId,
                    'setor_id' => $setorId, 'produto_id' => $produtoId,
                    'quantidade' => 0, 'custo_medio' => 0,
                ]);
                $saldo = EstoqueSaldo::withoutTenant()->whereKey($saldo->id)->lockForUpdate()->first();
            }

            $qtdAnterior = (float) $saldo->quantidade;
            $novoSaldo = $qtdAnterior + $quantidade;

            if ($novoSaldo < 0) {
                throw ValidationException::withMessages([
                    'quantidade' => "Saldo insuficiente (atual {$qtdAnterior}, movimento {$quantidade}).",
                ]);
            }

            // Custo médio ponderado: só recalcula em ENTRADA com custo informado.
            if ($quantidade > 0 && $custoUnitario !== null && $novoSaldo > 0) {
                $custoAnterior = (float) $saldo->custo_medio;
                $valorAnterior = $qtdAnterior * $custoAnterior;
                $valorEntrada = $quantidade * $custoUnitario;
                $saldo->custo_medio = round(($valorAnterior + $valorEntrada) / $novoSaldo, 4);
            }

            $saldo->quantidade = $novoSaldo;
            $saldo->save();

            return EstoqueHistorico::create([
                'empresa_id' => $empresaId,
                'setor_id' => $setorId,
                'produto_id' => $produtoId,
                'tipo' => $tipo,
                'quantidade' => $quantidade,
                'custo_unitario' => $custoUnitario,
                'saldo_resultante' => $novoSaldo,
                'origem' => $origem,
                'origem_id' => $origemId,
                'user_id' => $userId,
            ]);
        });
    }

    public function entrada(int $setorId, int $produtoId, float $qtd, ?float $custo = null, ?string $origem = null, ?int $origemId = null, ?int $userId = null): EstoqueHistorico
    {
        return $this->movimentar($setorId, $produtoId, abs($qtd), self::ENTRADA, $custo, $origem, $origemId, $userId);
    }

    public function saida(int $setorId, int $produtoId, float $qtd, ?string $origem = null, ?int $origemId = null, ?int $userId = null): EstoqueHistorico
    {
        return $this->movimentar($setorId, $produtoId, -abs($qtd), self::SAIDA, null, $origem, $origemId, $userId);
    }

    /**
     * Transferência entre setores (saída de um, entrada no outro) — atômica.
     *
     * @return array{saida: EstoqueHistorico, entrada: EstoqueHistorico}
     */
    public function transferir(int $setorOrigem, int $setorDestino, int $produtoId, float $qtd, ?int $userId = null): array
    {
        if ($setorOrigem === $setorDestino) {
            throw ValidationException::withMessages(['setor_destino' => 'Setores de origem e destino devem ser diferentes.']);
        }

        return DB::transaction(function () use ($setorOrigem, $setorDestino, $produtoId, $qtd, $userId) {
            $origem = EstoqueSaldo::withoutTenant()->where('setor_id', $setorOrigem)->where('produto_id', $produtoId)->first();
            $custo = $origem ? (float) $origem->custo_medio : null;

            $saidaMov = $this->movimentar($setorOrigem, $produtoId, -abs($qtd), self::TRANSFERENCIA, null, 'transferencia', $setorDestino, $userId);
            $entradaMov = $this->movimentar($setorDestino, $produtoId, abs($qtd), self::TRANSFERENCIA, $custo, 'transferencia', $setorOrigem, $userId);

            return ['saida' => $saidaMov, 'entrada' => $entradaMov];
        });
    }

    /**
     * Acerto/inventário: ajusta o saldo para a quantidade CONTADA, gerando o
     * movimento de diferença (mantém o histórico auditável).
     */
    public function acertar(int $setorId, int $produtoId, float $quantidadeContada, ?int $userId = null): ?EstoqueHistorico
    {
        return DB::transaction(function () use ($setorId, $produtoId, $quantidadeContada, $userId) {
            $saldo = EstoqueSaldo::withoutTenant()
                ->where('setor_id', $setorId)->where('produto_id', $produtoId)
                ->lockForUpdate()->first();

            $atual = $saldo ? (float) $saldo->quantidade : 0.0;
            $diferenca = $quantidadeContada - $atual;

            if ($diferenca == 0.0) {
                return null; // nada a ajustar
            }

            return $this->movimentar($setorId, $produtoId, $diferenca, self::INVENTARIO, null, 'acerto', null, $userId);
        });
    }

    /**
     * Fecha o período de um setor×produto: registra saldo inicial/final.
     * O saldo final é o saldo atual; o inicial é o final do fechamento anterior.
     */
    public function fechar(int $setorId, int $produtoId, string $dataFechamento): EstoqueFechamento
    {
        $empresaId = (int) Setor::withoutTenant()->whereKey($setorId)->value('empresa_id');

        return DB::transaction(function () use ($setorId, $produtoId, $dataFechamento, $empresaId) {
            $saldo = EstoqueSaldo::withoutTenant()->where('setor_id', $setorId)->where('produto_id', $produtoId)->first();
            $saldoFinal = $saldo ? (float) $saldo->quantidade : 0.0;

            $anterior = EstoqueFechamento::withoutTenant()
                ->where('setor_id', $setorId)->where('produto_id', $produtoId)
                ->orderByDesc('data_fechamento')->first();
            $saldoInicial = $anterior ? (float) $anterior->saldo_final : 0.0;

            return EstoqueFechamento::create([
                'empresa_id' => $empresaId,
                'setor_id' => $setorId,
                'produto_id' => $produtoId,
                'data_fechamento' => $dataFechamento,
                'saldo_inicial' => $saldoInicial,
                'saldo_final' => $saldoFinal,
                'aberto' => false,
            ]);
        });
    }

    /** Saldo derivado do histórico (a fonte da verdade) — usado em testes/invariantes. */
    public function saldoDerivado(int $setorId, int $produtoId): float
    {
        return (float) EstoqueHistorico::withoutTenant()
            ->where('setor_id', $setorId)->where('produto_id', $produtoId)
            ->sum('quantidade');
    }

    /**
     * Efetiva um inventário (C11): para cada item contado, ACERTA o saldo do setor
     * para a quantidade contada (gera o movimento de diferença → saldo auditável).
     * Grava a quantidade do sistema no momento e marca o inventário como efetivado.
     */
    public function efetivarInventario(EstoqueInventario $inventario, ?int $userId = null): EstoqueInventario
    {
        if ($inventario->situacao === 'efetivado') {
            return $inventario;
        }

        return DB::transaction(function () use ($inventario, $userId) {
            foreach ($inventario->itens as $item) {
                $sistema = $this->saldoDerivado($inventario->setor_id, $item->produto_id);
                $item->update(['quantidade_sistema' => $sistema]);
                $this->acertar($inventario->setor_id, $item->produto_id, (float) $item->quantidade_contada, $userId);
            }

            $inventario->update(['situacao' => 'efetivado']);

            return $inventario->refresh()->load('itens');
        });
    }

    /**
     * Atende uma requisição (C11): transfere a quantidade do setor de origem para o
     * de destino (via transferir → mantém o saldo auditável) e marca como atendida.
     */
    public function atenderRequisicao(EstoqueRequisicao $req, ?int $userId = null): EstoqueRequisicao
    {
        if ($req->situacao !== 'pendente') {
            throw ValidationException::withMessages(['requisicao' => 'Requisição já processada.']);
        }
        if (! $req->setor_origem_id) {
            throw ValidationException::withMessages(['setor_origem_id' => 'Defina o setor de origem para atender.']);
        }

        return DB::transaction(function () use ($req, $userId) {
            $this->transferir($req->setor_origem_id, $req->setor_destino_id, $req->produto_id, (float) $req->quantidade, $userId);
            $req->update(['situacao' => 'atendida']);

            return $req->refresh();
        });
    }
}
