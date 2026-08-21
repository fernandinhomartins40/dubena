<?php

namespace App\Domain\Venda;

use App\Domain\Rh\ComissaoService;
use App\Models\Rh\ColaboradorComissao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Extrato de remuneração do campo (F5) — o que o franqueado/vendedor ganhou.
 *
 * **O que já existia e é reusado.** `ColaboradorComissao` suporta os dois modelos
 * (`tipo_comissao` 1=percentual, 2=repasse — "valor que fica para a empresa",
 * que é exatamente a franquia), com variante por canal (`percentual_app`) e
 * exceção por segmento. O `ComissaoService::calcularItem` já resolve tudo isso.
 * Nada de cálculo novo aqui.
 *
 * **O que faltava.** O `RelatorioService::comissoes` produz o consolidado da
 * empresa, para o administrativo. Quem está em campo não tem como ver o próprio
 * ganho — e é o que faz o franqueado confiar (ou não) no acerto. Este serviço é
 * a mesma conta, recortada por pessoa e detalhada por pedido.
 *
 * **O que NÃO está decidido.** Qual modelo o cliente usa hoje — percentual,
 * repasse por botijão, ou misto — é pendência de negócio (§8.1 do documento). Não
 * é preciso saber para entregar o extrato: ele mostra o que as regras cadastradas
 * produzirem, seja qual for o modelo.
 */
class ExtratoRemuneracaoService
{
    public function __construct(private ComissaoService $comissao) {}

    /**
     * Extrato de um colaborador no período, pedido a pedido.
     *
     * @return array{
     *   periodo: array{inicio:string, fim:string},
     *   total: array{percentual:float, repasse:float, total:float},
     *   pedidos: list<array<string,mixed>>
     * }
     */
    public function doColaborador(int $empresaId, int $userId, string $inicio, string $fim): array
    {
        $dtInicio = Carbon::parse($inicio)->startOfDay();
        $dtFim = Carbon::parse($fim)->endOfDay();

        // Só pedido CONCRETIZADO conta: `estoque_movimentado` é a marca de que a
        // venda aconteceu de fato (a máquina de estados baixou o estoque). Contar
        // pendente inflaria o extrato com venda que ainda pode ser cancelada.
        $itens = DB::table('pedidoitens as pi')
            ->join('pedidos as p', 'p.id', '=', 'pi.pedido_id')
            ->where('p.empresa_id', $empresaId)
            ->where('p.entregador_user_id', $userId)
            ->where('p.estoque_movimentado', true)
            ->whereBetween('p.datahora', [$dtInicio, $dtFim])
            ->selectRaw('p.id as pedido_id, p.datahora, p.setor_id, pi.produto_id,
                         pi.quantidade, pi.preco_unitario,
                         pi.valor_total as valor_venda, pi.desconto as valor_desconto')
            ->orderBy('p.datahora')
            ->get();

        $vazio = [
            'periodo' => ['inicio' => $dtInicio->toDateString(), 'fim' => $dtFim->toDateString()],
            'total' => ['percentual' => 0.0, 'repasse' => 0.0, 'total' => 0.0],
            'pedidos' => [],
        ];

        if ($itens->isEmpty()) {
            return $vazio;
        }

        $regras = ColaboradorComissao::withoutTenant()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->with('excecoes')
            ->get();

        if ($regras->isEmpty()) {
            // Sem regra cadastrada não há o que pagar — e mostrar zero é mais
            // honesto que omitir a seção, porque o franqueado precisa perceber
            // que falta configuração, não achar que vendeu nada.
            return $vazio;
        }

        $porPedido = [];
        $todosOsItens = [];

        foreach ($itens as $it) {
            $regra = $this->regraDoItem($regras, (int) $it->produto_id, (int) ($it->setor_id ?? 0));
            if ($regra === null) {
                continue;
            }

            $item = [
                'quantidade' => $it->quantidade,
                'preco_unitario' => $it->preco_unitario,
                'valor_venda' => $it->valor_venda,
                'valor_desconto' => $it->valor_desconto,
            ];

            $calc = $this->comissao->calcularItem($item, $regra);
            $todosOsItens[] = [$item, $regra];

            $id = (int) $it->pedido_id;
            if (! isset($porPedido[$id])) {
                $porPedido[$id] = [
                    'pedido_id' => $id,
                    'datahora' => $it->datahora,
                    'itens' => 0,
                    'valor_venda' => 0.0,
                    'comissao' => 0.0,
                ];
            }
            $porPedido[$id]['itens']++;
            $porPedido[$id]['valor_venda'] = round($porPedido[$id]['valor_venda'] + (float) $it->valor_venda, 2);
            $porPedido[$id]['comissao'] = round(
                $porPedido[$id]['comissao'] + $calc['percentual'] + $calc['repasse'],
                2
            );
        }

        if ($todosOsItens === []) {
            return $vazio;
        }

        $total = $this->comissao->totalColaborador($todosOsItens);

        return [
            'periodo' => ['inicio' => $dtInicio->toDateString(), 'fim' => $dtFim->toDateString()],
            'total' => [
                'percentual' => $total['percentual'],
                'repasse' => $total['repasse'],
                'total' => $total['total'],
            ],
            'pedidos' => array_values($porPedido),
        ];
    }

    /**
     * A regra que se aplica ao item — mesma precedência do
     * `RelatorioService::comissoes`, para que extrato e relatório do
     * administrativo nunca divirjam.
     *
     * @param  \Illuminate\Support\Collection<int, ColaboradorComissao>  $regras
     */
    private function regraDoItem($regras, int $produtoId, int $setorId): ?ColaboradorComissao
    {
        return $regras->first(
            fn (ColaboradorComissao $r) => ($r->produto_id === null || (int) $r->produto_id === $produtoId)
                && ($r->setor_id === null || (int) $r->setor_id === $setorId)
        );
    }
}
