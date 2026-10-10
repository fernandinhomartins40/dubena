<?php

namespace App\Domain\Fiscal;

use App\Models\Empresa;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Fiscal\InventarioFiscal;
use App\Models\Produto\Produto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inventário fiscal — o estoque declarado ao fisco (SPED Fiscal, Bloco H).
 *
 * Um documento, não uma operação de estoque: gravar ou excluir um inventário
 * fiscal não move saldo nenhum. Quem ajusta saldo é o inventário FÍSICO.
 */
class InventarioFiscalService
{
    /** MOT_INV do registro H005. */
    public const MOTIVOS = [
        '01' => 'No final do período',
        '02' => 'Na mudança de forma de tributação da mercadoria (ICMS)',
        '03' => 'Na solicitação da baixa cadastral ou paralisação temporária',
        '04' => 'Na alteração de regime de pagamento',
        '05' => 'Por determinação do fisco',
    ];

    /**
     * Grava o inventário com os itens DECLARADOS.
     *
     * @param  array{mes_entrega: string, data_inventario: string, motivo?: string|null}  $cabecalho
     * @param  list<array{produto_id: int, quantidade: float|int|string, valor_unitario: float|int|string}>  $itens
     */
    public function criar(int $empresaId, array $cabecalho, array $itens, ?int $userId = null): InventarioFiscal
    {
        $mesEntrega = Carbon::parse($cabecalho['mes_entrega'])->startOfMonth();
        $dataInventario = Carbon::parse($cabecalho['data_inventario'])->startOfDay();
        $motivo = $cabecalho['motivo'] ?? '01';

        // O inventário é a posição numa data, declarada numa escrituração
        // posterior ou do mesmo mês. Entregar antes da data inventariada é
        // quase sempre os dois campos trocados na digitação.
        if ($dataInventario->gt($mesEntrega->copy()->endOfMonth())) {
            throw ValidationException::withMessages([
                'data_inventario' => 'A data do inventário não pode ser posterior ao mês de entrega.',
            ]);
        }

        $produtoIds = array_map(fn (array $i) => (int) $i['produto_id'], $itens);
        if (count($produtoIds) !== count(array_unique($produtoIds))) {
            throw ValidationException::withMessages(['itens' => 'O mesmo produto aparece mais de uma vez.']);
        }

        // Só produto DESTA empresa — a validação da porta já recusa, e o
        // serviço confere de novo porque também é chamado fora de HTTP.
        $produtos = Produto::withoutTenant()
            ->where('empresa_id', $empresaId)->whereIn('id', $produtoIds)
            ->get(['id', 'descricao'])->keyBy('id');
        if ($produtos->count() !== count($produtoIds)) {
            throw ValidationException::withMessages(['itens' => 'Produto invalido para a empresa ativa.']);
        }

        return DB::transaction(function () use ($empresaId, $mesEntrega, $dataInventario, $motivo, $itens, $produtos, $userId) {
            $jaExiste = InventarioFiscal::withoutTenant()
                ->where('empresa_id', $empresaId)
                ->whereDate('mes_entrega', $mesEntrega->toDateString())
                ->where('motivo', $motivo)
                ->exists();
            if ($jaExiste) {
                throw ValidationException::withMessages([
                    'mes_entrega' => 'Já existe inventário com este motivo para a entrega de '.$mesEntrega->format('m/Y').'. Exclua-o antes de gravar outro.',
                ]);
            }

            $tenantAccountId = Empresa::withoutGlobalScopes()->whereKey($empresaId)->value('tenant_account_id');

            // Arredonda item a item e soma os arredondados: o VL_INV do H005
            // tem de ser igual à soma dos VL_ITEM do H010, que saem com duas
            // casas. Somar bruto e arredondar no fim dá centavo de diferença,
            // e o validador do SPED acusa.
            $total = 0.0;
            foreach ($itens as $i) {
                $total += round((float) $i['quantidade'] * (float) $i['valor_unitario'], 2);
            }

            $inventario = InventarioFiscal::create([
                'empresa_id' => $empresaId,
                'tenant_account_id' => $tenantAccountId,
                'mes_entrega' => $mesEntrega->toDateString(),
                'data_inventario' => $dataInventario->toDateString(),
                'motivo' => $motivo,
                'valor_total' => round($total, 2),
                'user_id' => $userId,
            ]);

            foreach ($itens as $i) {
                $inventario->itens()->create([
                    'empresa_id' => $empresaId,
                    'tenant_account_id' => $tenantAccountId,
                    'produto_id' => (int) $i['produto_id'],
                    // Congelado: o documento entregue não muda se o produto
                    // for renomeado depois.
                    'descricao_snapshot' => $produtos[(int) $i['produto_id']]->descricao,
                    'quantidade' => $i['quantidade'],
                    'valor_unitario' => $i['valor_unitario'],
                ]);
            }

            return $inventario->load('itens');
        });
    }

    /**
     * Ponto de partida para preencher o inventário: saldo e custo médio de
     * AGORA, somando os setores da empresa.
     *
     * É sugestão, e a tela diz isso: o sistema não reconstrói o saldo de uma
     * data passada, então para um inventário de 31/12 lançado em fevereiro os
     * números precisam ser conferidos. Produto sem saldo fica de fora — o
     * inventário declara o que existe.
     *
     * @return list<array{produto_id: int, descricao: string, quantidade: float, valor_unitario: float|null}>
     */
    public function sugestao(int $empresaId, bool $comCusto): array
    {
        return EstoqueSaldo::withoutTenant()
            ->join('produtos', 'produtos.id', '=', 'estoquesaldos.produto_id')
            ->where('estoquesaldos.empresa_id', $empresaId)
            ->groupBy('estoquesaldos.produto_id', 'produtos.descricao')
            // Custo ponderado pela quantidade de cada setor: a média simples
            // dos custos daria o mesmo peso a um setor com 1 unidade e a outro
            // com 1.000.
            ->selectRaw('estoquesaldos.produto_id as produto_id, produtos.descricao as descricao, '
                .'sum(estoquesaldos.quantidade) as quantidade, '
                .'sum(estoquesaldos.quantidade * estoquesaldos.custo_medio) as valor')
            ->havingRaw('sum(estoquesaldos.quantidade) > 0')
            ->orderBy('produtos.descricao')
            ->get()
            ->map(fn ($r) => [
                'produto_id' => (int) $r->produto_id,
                'descricao' => (string) $r->descricao,
                'quantidade' => round((float) $r->quantidade, 3),
                'valor_unitario' => $comCusto ? round((float) $r->valor / (float) $r->quantidade, 4) : null,
            ])
            ->all();
    }
}
