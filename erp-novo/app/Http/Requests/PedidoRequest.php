<?php

namespace App\Http\Requests;

use App\Domain\Pedido\QuantidadeDoItem;
use App\Models\Cliente\Cliente;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Pedido\PedidoOperacao;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Rules\ExisteNoTenant;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de pedido. Itens como array ANINHADO de DTO nomeado.
 */
class PedidoRequest extends FormRequest
{
    use AutorizaPorPermissao;

    public function authorize(): bool
    {
        // Autoriza ANTES de validar (PIE R3-F-05): sem isto o usuário sem
        // permissão recebia 422 com as regras do formulário em vez de 403.
        // O controller repete a checagem; aqui só antecipa.
        $this->autorizar($this, $this->isMethod('post') ? 'pedido.create' : 'pedido.edit');

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $criando = $this->isMethod('post');

        return [
            // `ExisteNoTenant` no lugar de `exists:tabela,id`: a regra nativa
            // valida contra a tabela inteira, e um pedido da empresa A era
            // aceito com `cliente_id` da empresa B — inclusive entre tenants.
            'cliente_id' => [$criando ? 'required' : 'sometimes', 'integer', new ExisteNoTenant(Cliente::class)],
            'pedidosituacao_id' => [$criando ? 'required' : 'sometimes', 'integer', new ExisteNoTenant(PedidoSituacao::class)],
            'pedidooperacao_id' => ['nullable', 'integer', new ExisteNoTenant(PedidoOperacao::class)],
            'setor_id' => ['nullable', 'integer', new ExisteNoTenant(Setor::class)],
            // A coluna existia, o `PedidoService` a lê (financeiro e alçada por
            // condição) e o formulário do painel a exigia — mas, sem regra
            // aqui, o `safe()` a descartava e todo pedido lançado pelo painel
            // nascia sem condição de pagamento.
            'condicaopagamento_id' => ['nullable', 'integer', new ExisteNoTenant(CondicaoPagamento::class)],
            'atendente_user_id' => 'nullable|integer|exists:users,id',
            'entregador_user_id' => 'nullable|integer|exists:users,id',
            'datahora' => 'nullable|date',
            'entrega_urgente' => 'nullable|boolean',
            'entrega_telefone' => 'nullable|string|max:30',
            'entrega_taxa' => 'nullable|numeric|gte:0',
            'entrega_troco_para' => 'nullable|numeric|gte:0',
            'observacao' => 'nullable|string',

            'itens' => ($criando ? 'required|array|min:1' : 'nullable|array'),
            'itens.*.produto_id' => ['required_with:itens', 'integer', new ExisteNoTenant(Produto::class)],
            'itens.*.quantidade' => 'required_with:itens|numeric|gt:0',
            'itens.*.preco_unitario' => 'nullable|numeric|gte:0',
            'itens.*.desconto' => 'nullable|numeric|gte:0',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Escolha o cliente.',
            'pedidosituacao_id.required' => 'Escolha a situação do pedido.',
            'itens.required' => 'Adicione ao menos um item.',
            'itens.min' => 'Adicione ao menos um item.',
            'itens.*.produto_id.required_with' => 'Escolha o produto de cada item.',
            'itens.*.quantidade.required_with' => 'Informe a quantidade de cada item.',
            'itens.*.quantidade.numeric' => 'A quantidade precisa ser um número.',
            'itens.*.quantidade.gt' => 'A quantidade precisa ser maior que zero.',
            'itens.*.preco_unitario.numeric' => 'O preço precisa ser um número.',
            'itens.*.preco_unitario.gte' => 'O preço não pode ser negativo.',
        ];
    }

    /**
     * P2-D-12: quantidade fracionada de produto contável e acima do teto que a
     * revenda configurou. Roda depois das regras de campo, só sobre itens que
     * já passaram por elas.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $itens = $this->input('itens');
            if (! is_array($itens) || $v->errors()->hasAny(['itens', 'itens.*'])) {
                return;
            }

            $teto = QuantidadeDoItem::teto($this->user()?->empresa_id);

            foreach ($itens as $i => $item) {
                if (! is_array($item) || ! is_numeric($item['quantidade'] ?? null) || ! is_numeric($item['produto_id'] ?? null)) {
                    continue;
                }
                $produto = Produto::query()->with('unidade')->find((int) $item['produto_id']);
                if ($produto && ($erro = QuantidadeDoItem::problema($produto, (float) $item['quantidade'], $teto))) {
                    $v->errors()->add("itens.{$i}.quantidade", $erro);
                }
            }
        });
    }
}
