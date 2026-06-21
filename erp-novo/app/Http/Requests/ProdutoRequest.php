<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'descricao' => 'required|string|max:255',
            'produtoclasse_id' => 'nullable|integer|exists:produtoclasses,id',
            'unidademedida_id' => 'nullable|integer|exists:unidadesmedida,id',
            'vasilhame_retornavel' => 'nullable|boolean',
            'produto_retornavel_id' => 'nullable|integer|exists:produtos,id',
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
