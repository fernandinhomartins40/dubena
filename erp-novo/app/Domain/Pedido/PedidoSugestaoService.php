<?php

namespace App\Domain\Pedido;

use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClientePreco;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\Produto\ProdutoCondicaoPreco;

/**
 * Prepara o "Novo pedido" a partir do que o sistema já sabe do cliente.
 *
 * Numa revenda de gás o cliente pede quase sempre o mesmo, no mesmo endereço,
 * do mesmo jeito. O atendente digitava tudo de novo a cada ligação: condição,
 * operação, setor, itens e preço. Aqui tudo isso vem do último pedido do
 * cliente e do cadastro de preços — como SUGESTÃO. Nada é gravado; o
 * `PedidoService` continua decidindo preço final, alçada e estoque quando o
 * pedido é salvo.
 */
class PedidoSugestaoService
{
    /** @return array<string, mixed> */
    public function paraCliente(int $clienteId): array
    {
        $cliente = Cliente::query()
            ->with(['cidade:id,descricao,uf', 'bairro:id,descricao', 'rua:id,descricao', 'telefones'])
            ->findOrFail($clienteId);

        $ultimo = Pedido::query()
            ->where('cliente_id', $cliente->id)
            ->with(['itens:id,pedido_id,produto_id,quantidade', 'itens.produto:id,descricao,ativo', 'condicao:id,descricao', 'operacao:id,descricao', 'setor:id,descricao'])
            ->latest('datahora')->latest('id')
            ->first();

        $itens = [];
        if ($ultimo !== null) {
            $precos = $this->precos($cliente->id, $ultimo->condicaopagamento_id, $ultimo->itens->pluck('produto_id')->all());
            foreach ($ultimo->itens as $item) {
                // Produto desativado desde então não é sugerido: repetir o
                // pedido não pode ressuscitar o que a revenda tirou de linha.
                if ($item->produto === null || $item->produto->ativo === false) {
                    continue;
                }
                $itens[] = [
                    'produto_id' => $item->produto_id,
                    'produto' => $item->produto->descricao,
                    'quantidade' => (float) $item->quantidade,
                    'preco_unitario' => $precos[$item->produto_id]['valor'] ?? null,
                    'origem_preco' => $precos[$item->produto_id]['origem'] ?? null,
                ];
            }
        }

        return [
            'cliente' => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'telefone' => $cliente->telefones->first()?->telefone,
                'endereco' => $this->endereco($cliente),
            ],
            'situacao_inicial' => $this->situacaoInicial(),
            'ultimo_pedido' => $ultimo === null ? null : [
                'id' => $ultimo->id,
                'datahora' => $ultimo->datahora?->toIso8601String(),
                'condicaopagamento' => $this->opcao($ultimo->condicao),
                'pedidooperacao' => $this->opcao($ultimo->operacao),
                'setor' => $this->opcao($ultimo->setor),
                'itens' => $itens,
            ],
        ];
    }

    /**
     * Preço sugerido de cada produto, com a ORIGEM — o atendente precisa saber
     * se está vendo o preço combinado com aquele cliente ou o de tabela.
     *
     * Ordem: combinado com o cliente → tabela da condição de pagamento → preço
     * de venda do produto. É a mesma precedência que o app do cliente usa
     * (condição → venda), com o combinado na frente porque ele é exceção
     * negociada. O desconto combinado não é aplicado aqui: o cadastro não diz
     * se é valor ou percentual, e adivinhar mudaria o preço cobrado.
     *
     * @param  list<int>  $produtoIds
     * @return array<int, array{valor: float, origem: string}>
     */
    public function precos(int $clienteId, ?int $condicaoId, array $produtoIds): array
    {
        $produtoIds = array_values(array_unique(array_map('intval', $produtoIds)));
        if ($produtoIds === []) {
            return [];
        }

        $precos = [];
        foreach (Produto::query()->whereIn('id', $produtoIds)->get(['id', 'preco_venda']) as $p) {
            if ($p->preco_venda !== null) {
                $precos[$p->id] = ['valor' => round((float) $p->preco_venda, 2), 'origem' => 'Preço de venda'];
            }
        }

        if ($condicaoId !== null) {
            foreach (ProdutoCondicaoPreco::query()->where('condicaopagamento_id', $condicaoId)->where('gasdopovo', false)
                ->whereIn('produto_id', $produtoIds)->get(['produto_id', 'valor']) as $p) {
                $precos[$p->produto_id] = ['valor' => round((float) $p->valor, 2), 'origem' => 'Tabela da condição de pagamento'];
            }
        }

        foreach (ClientePreco::query()->where('cliente_id', $clienteId)->whereIn('produto_id', $produtoIds)
            ->whereNotNull('preco')->where('preco', '>', 0)->get(['produto_id', 'preco']) as $p) {
            $precos[$p->produto_id] = ['valor' => round((float) $p->preco, 2), 'origem' => 'Preço combinado com o cliente'];
        }

        return $precos;
    }

    /**
     * A situação que a revenda marcou como padrão da tela de pedido; sem
     * marcação, a primeira ativa que deixa o pedido em aberto. É configuração
     * do tenant, não suposição do código.
     *
     * @return array{id: int, label: string}|null
     */
    private function situacaoInicial(): ?array
    {
        $situacao = PedidoSituacao::query()->where('ativo', true)->where('padrao_tela_pedido', true)->orderBy('ordem')->orderBy('id')->first()
            ?? PedidoSituacao::query()->where('ativo', true)->where('efeito', EfeitoPedido::PENDENTE->value)->orderBy('ordem')->orderBy('id')->first();

        return $this->opcao($situacao);
    }

    /** @return array{texto: string|null, referencia: string|null, completo: bool} */
    private function endereco(Cliente $cliente): array
    {
        // O texto é o do acessor do model — o ponto único de endereço
        // (`EnderecoPontoUnicoTest`), com a regra de que o texto do cadastro
        // vence a FK da rua. Montar aqui de novo era divergir dela.
        //
        // "Completo" pergunta só se HÁ logradouro — FK da rua ou o texto
        // legado. `endereco_linha` não serve aqui: ele inclui o número, e um
        // cadastro só com número passaria por completo.
        $temLogradouro = $cliente->rua_id !== null || filled($cliente->getAttribute('endereco'));

        return [
            'texto' => $cliente->endereco_completo,
            'referencia' => $cliente->ponto_referencia,
            // Sem logradouro ou sem cidade o entregador não chega: a tela avisa
            // antes de salvar, em vez de o problema aparecer na rota.
            'completo' => $temLogradouro && $cliente->cidade !== null,
        ];
    }

    /** @return array{id: int, label: string}|null */
    private function opcao(mixed $model): ?array
    {
        return $model === null ? null : ['id' => (int) $model->id, 'label' => (string) $model->descricao];
    }
}
