<?php

namespace App\Http\Requests;

use App\Domain\Produto\TipoProduto;
use App\Models\Produto\Produto;
use App\Models\Produto\ProdutoClasse;
use App\Models\Produto\UnidadeMedida;
use App\Rules\ExisteNoTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação de produto. Origens como array ANINHADO. Valores numéricos como
 * número (decimal), nunca string-BR.
 */
class ProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A SPA envia `precovenda`/`precovendaminimo` (grafia do legado); as colunas
     * são `preco_venda`/`preco_venda_minimo`. Sem esta normalização o
     * `validated()` descartava os dois campos e **editar o preço de um produto
     * não gravava nada** — a tela salvava "com sucesso" e o valor continuava o
     * mesmo. Normalizar aqui, e não em `rules()`, evita duplicar as regras.
     */
    protected function prepareForValidation(): void
    {
        foreach ([
            'precovenda' => 'preco_venda',
            'precovendaminimo' => 'preco_venda_minimo',
            'customedio' => 'custo_medio',
            'custofrete' => 'custo_frete',
            'precogasdopovo' => 'preco_gasdopovo',
            'pesoliquido' => 'peso_liquido',
            'pesobruto' => 'peso_bruto',
        ] as $alias => $coluna) {
            if ($this->has($alias) && ! $this->filled($coluna)) {
                $this->merge([$coluna => $this->input($alias)]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'descricao' => 'required|string|max:255',
            // produto | servico | taxa. Governa estoque e fiscal: servico nao
            // movimenta armazem e nao exige NCM (ver NaturezaItem).
            'natureza' => 'nullable|in:produto,servico,taxa',
            // F3-02: papel no ciclo de custodia. Declarado aqui, em vez de
            // inferido da descricao pela vigilancia de comodato.
            'tipo' => ['nullable', Rule::enum(TipoProduto::class)],
            'produtoclasse_id' => ['nullable', 'integer', new ExisteNoTenant(ProdutoClasse::class)],
            'unidademedida_id' => ['nullable', 'integer', new ExisteNoTenant(UnidadeMedida::class)],
            'vasilhame_retornavel' => 'nullable|boolean',
            'produto_retornavel_id' => ['nullable', 'integer', new ExisteNoTenant(Produto::class)],
            'ativo' => 'nullable|boolean',
            'envia_app_nf' => 'nullable|boolean',
            'dias_giro' => 'nullable|integer',
            'observacao' => 'nullable|string',

            'preco_venda' => 'nullable|numeric',
            'preco_venda_minimo' => 'nullable|numeric',
            'custo_medio' => 'nullable|numeric',
            'custo_frete' => 'nullable|numeric',
            'preco_gasdopovo' => 'nullable|numeric',
            'peso_liquido' => 'nullable|numeric',
            'peso_bruto' => 'nullable|numeric',

            'nfe_permite' => 'nullable|boolean',
            'sped' => 'nullable|boolean',
            'nfe_tipo_item' => 'nullable|integer',
            'ncm' => 'nullable|string|max:10',
            'nfe_cest' => 'nullable|string|max:10',
            'ean' => 'nullable|string|max:20',
            'especie' => 'nullable|string|max:60',
            'marca' => 'nullable|string|max:60',

            'tipo_glp' => 'nullable|integer',
            'nfe_cprod_anp' => 'nullable|string|max:20',
            'pglp' => 'nullable|numeric',

            'origens' => 'nullable|array',
            'origens.*.uf' => 'nullable|string|size:2',
            'origens.*.ind_import' => 'nullable|integer',
            'origens.*.cuf_orig' => 'nullable|integer',
            'origens.*.p_orig' => 'nullable|numeric',
        ];
    }
}
