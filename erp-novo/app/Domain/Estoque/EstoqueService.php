<?php

namespace App\Domain\Estoque;

use App\Models\Empresa;
use App\Models\Estoque\EstoqueFechamento;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\EstoqueInventario;
use App\Models\Estoque\EstoqueRequisicao;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
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
        ?int $empresaEsperada = null,
        // F4-01: quando informada, o mesmo movimento nunca e gravado duas
        // vezes. Nula por padrao — exigir de todos os chamadores num unico
        // lote quebraria os que ainda nao a informam.
        ?string $chaveIdempotencia = null,
    ): EstoqueHistorico {
        if ($quantidade == 0.0) {
            throw ValidationException::withMessages(['quantidade' => 'Quantidade não pode ser zero.']);
        }

        // empresa_id derivado do setor (não depende do TenantContext: o service é
        // chamado via HTTP, testes e ETL). Garante a coerência do escopo.
        $empresaId = $this->validarParEstoque($setorId, $produtoId, $empresaEsperada);

        return DB::transaction(function () use ($setorId, $produtoId, $quantidade, $tipo, $custoUnitario, $origem, $origemId, $userId, $empresaId, $chaveIdempotencia) {
            // F4-01: reprocessar devolve o movimento JA GRAVADO, sem repetir o
            // efeito no saldo.
            //
            // A checagem vem antes do lock de proposito: se o movimento ja
            // existe, nao ha o que travar. O indice unico parcial e a garantia
            // real — esta consulta so evita a excecao no caso comum, e uma
            // corrida entre duas chamadas simultaneas ainda bate no banco.
            if ($chaveIdempotencia !== null) {
                $existente = EstoqueHistorico::withoutTenant()
                    ->where('empresa_id', $empresaId)
                    ->where('chave_idempotencia', $chaveIdempotencia)
                    ->first();

                if ($existente !== null) {
                    return $existente;
                }
            }

            // Lock pessimista do saldo (cria se não existir) — base anti-corrida.
            $saldo = EstoqueSaldo::withoutTenant()
                ->where('empresa_id', $empresaId)->where('setor_id', $setorId)->where('produto_id', $produtoId)
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
                // F4-01: o ledger entra na fronteira SaaS. Sem o tenant ele
                // ficaria de fora da policy canonica, que decide por
                // (tenant, empresa) em todo o resto do banco. Vem da empresa
                // porque e dela que o movimento sempre foi.
                'tenant_account_id' => Empresa::withoutGlobalScopes()
                    ->whereKey($empresaId)->value('tenant_account_id'),
                'setor_id' => $setorId,
                'produto_id' => $produtoId,
                'tipo' => $tipo,
                'quantidade' => $quantidade,
                'custo_unitario' => $custoUnitario,
                'saldo_resultante' => $novoSaldo,
                'origem' => $origem,
                'origem_id' => $origemId,
                'chave_idempotencia' => $chaveIdempotencia,
                'user_id' => $userId,
            ]);
        });
    }

    public function entrada(int $setorId, int $produtoId, float $qtd, ?float $custo = null, ?string $origem = null, ?int $origemId = null, ?int $userId = null, ?int $empresaEsperada = null, ?string $chaveIdempotencia = null): EstoqueHistorico
    {
        return $this->movimentar($setorId, $produtoId, abs($qtd), self::ENTRADA, $custo, $origem, $origemId, $userId, $empresaEsperada, $chaveIdempotencia);
    }

    public function saida(int $setorId, int $produtoId, float $qtd, ?string $origem = null, ?int $origemId = null, ?int $userId = null, ?int $empresaEsperada = null, ?string $chaveIdempotencia = null): EstoqueHistorico
    {
        return $this->movimentar($setorId, $produtoId, -abs($qtd), self::SAIDA, null, $origem, $origemId, $userId, $empresaEsperada, $chaveIdempotencia);
    }

    /**
     * Transferência entre setores (saída de um, entrada no outro) — atômica.
     *
     * @return array{saida: EstoqueHistorico, entrada: EstoqueHistorico}
     */
    public function transferir(int $setorOrigem, int $setorDestino, int $produtoId, float $qtd, ?int $userId = null, ?int $empresaEsperada = null): array
    {
        if ($setorOrigem === $setorDestino) {
            throw ValidationException::withMessages(['setor_destino' => 'Setores de origem e destino devem ser diferentes.']);
        }

        // F05 — transferência só DENTRO da mesma empresa: mover estoque entre
        // empresas diferentes violaria o isolamento de tenant (e o patrimônio).
        $empOrigem = $this->validarParEstoque($setorOrigem, $produtoId, $empresaEsperada);
        $empDestino = (int) Setor::withoutTenant()->whereKey($setorDestino)->value('empresa_id');
        if ($empDestino === 0 || $empOrigem !== $empDestino) {
            throw ValidationException::withMessages(['setor_destino' => 'Transferência só é permitida entre setores da mesma empresa.']);
        }

        return DB::transaction(function () use ($setorOrigem, $setorDestino, $produtoId, $qtd, $userId, $empOrigem) {
            $origem = EstoqueSaldo::withoutTenant()->where('empresa_id', $empOrigem)->where('setor_id', $setorOrigem)->where('produto_id', $produtoId)->first();
            $custo = $origem ? (float) $origem->custo_medio : null;

            $saidaMov = $this->movimentar($setorOrigem, $produtoId, -abs($qtd), self::TRANSFERENCIA, null, 'transferencia', $setorDestino, $userId, $empOrigem);
            $entradaMov = $this->movimentar($setorDestino, $produtoId, abs($qtd), self::TRANSFERENCIA, $custo, 'transferencia', $setorOrigem, $userId, $empOrigem);

            return ['saida' => $saidaMov, 'entrada' => $entradaMov];
        });
    }

    /**
     * Acerto/inventário: ajusta o saldo para a quantidade CONTADA, gerando o
     * movimento de diferença (mantém o histórico auditável).
     */
    public function acertar(int $setorId, int $produtoId, float $quantidadeContada, ?int $userId = null, ?int $empresaEsperada = null): ?EstoqueHistorico
    {
        $empresaId = $this->validarParEstoque($setorId, $produtoId, $empresaEsperada);

        return DB::transaction(function () use ($setorId, $produtoId, $quantidadeContada, $userId, $empresaId) {
            $saldo = EstoqueSaldo::withoutTenant()
                ->where('empresa_id', $empresaId)->where('setor_id', $setorId)->where('produto_id', $produtoId)
                ->lockForUpdate()->first();

            $atual = $saldo ? (float) $saldo->quantidade : 0.0;
            $diferenca = $quantidadeContada - $atual;

            if ($diferenca == 0.0) {
                return null; // nada a ajustar
            }

            return $this->movimentar($setorId, $produtoId, $diferenca, self::INVENTARIO, null, 'acerto', null, $userId, $empresaId);
        });
    }

    /**
     * Fecha o período de um setor×produto: registra saldo inicial/final.
     * O saldo final é o saldo atual; o inicial é o final do fechamento anterior.
     */
    public function fechar(int $setorId, int $produtoId, string $dataFechamento, ?int $empresaEsperada = null): EstoqueFechamento
    {
        $empresaId = $this->validarParEstoque($setorId, $produtoId, $empresaEsperada);

        return DB::transaction(function () use ($setorId, $produtoId, $dataFechamento, $empresaId) {
            $saldo = EstoqueSaldo::withoutTenant()->where('empresa_id', $empresaId)->where('setor_id', $setorId)->where('produto_id', $produtoId)->first();
            $saldoFinal = $saldo ? (float) $saldo->quantidade : 0.0;

            $anterior = EstoqueFechamento::withoutTenant()
                ->where('empresa_id', $empresaId)->where('setor_id', $setorId)->where('produto_id', $produtoId)
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
    public function saldoDerivado(int $setorId, int $produtoId, ?int $empresaEsperada = null): float
    {
        $empresaId = $this->validarParEstoque($setorId, $produtoId, $empresaEsperada);

        return (float) EstoqueHistorico::withoutTenant()
            ->where('empresa_id', $empresaId)
            ->where('setor_id', $setorId)->where('produto_id', $produtoId)
            ->sum('quantidade');
    }

    /**
     * Efetiva um inventário (C11): para cada item contado, ACERTA o saldo do setor
     * para a quantidade contada (gera o movimento de diferença → saldo auditável).
     * Grava a quantidade do sistema no momento e marca o inventário como efetivado.
     */
    public function efetivarInventario(EstoqueInventario $inventario, ?int $userId = null, ?int $empresaEsperada = null): EstoqueInventario
    {
        if ($empresaEsperada !== null && (int) $inventario->empresa_id !== $empresaEsperada) {
            throw ValidationException::withMessages(['inventario' => 'Inventario invalido para a empresa ativa.']);
        }
        if ($inventario->situacao === 'efetivado') {
            return $inventario;
        }

        return DB::transaction(function () use ($inventario, $userId) {
            foreach ($inventario->itens as $item) {
                $sistema = $this->saldoDerivado($inventario->setor_id, $item->produto_id, (int) $inventario->empresa_id);
                $item->update(['quantidade_sistema' => $sistema]);
                $this->acertar($inventario->setor_id, $item->produto_id, (float) $item->quantidade_contada, $userId, (int) $inventario->empresa_id);
            }

            // F4-03: efetivar E aprovar. Quem apertou o botao autorizou o
            // ajuste — e essa e uma informacao diferente de quem CONTOU, que
            // e registrada quando o inventario e aberto/preenchido.
            $inventario->update([
                'situacao' => 'efetivado',
                'aprovado_por' => $userId,
                'aprovado_em' => now(),
            ]);

            return $inventario->refresh()->load('itens');
        });
    }

    /**
     * Atende uma requisição (C11): transfere a quantidade do setor de origem para o
     * de destino (via transferir → mantém o saldo auditável) e marca como atendida.
     */
    public function atenderRequisicao(EstoqueRequisicao $req, ?int $userId = null, ?int $empresaEsperada = null): EstoqueRequisicao
    {
        if ($empresaEsperada !== null && (int) $req->empresa_id !== $empresaEsperada) {
            throw ValidationException::withMessages(['requisicao' => 'Requisicao invalida para a empresa ativa.']);
        }
        if ($req->situacao !== 'pendente') {
            throw ValidationException::withMessages(['requisicao' => 'Requisição já processada.']);
        }
        if (! $req->setor_origem_id) {
            throw ValidationException::withMessages(['setor_origem_id' => 'Defina o setor de origem para atender.']);
        }

        return DB::transaction(function () use ($req, $userId) {
            $this->transferir($req->setor_origem_id, $req->setor_destino_id, $req->produto_id, (float) $req->quantidade, $userId, (int) $req->empresa_id);
            $req->update(['situacao' => 'atendida']);

            return $req->refresh();
        });
    }

    private function validarParEstoque(int $setorId, int $produtoId, ?int $empresaEsperada): int
    {
        $empresaId = (int) Setor::withoutTenant()->whereKey($setorId)->value('empresa_id');
        $valido = $empresaId > 0
            && ($empresaEsperada === null || $empresaId === $empresaEsperada)
            && Produto::withoutTenant()->whereKey($produtoId)->where('empresa_id', $empresaId)->exists();

        if (! $valido) {
            throw ValidationException::withMessages(['estoque' => 'Setor ou produto invalido para a empresa ativa.']);
        }

        return $empresaId;
    }
}
