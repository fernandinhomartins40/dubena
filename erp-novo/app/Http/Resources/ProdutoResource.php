<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Produto\Produto
 */
class ProdutoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'descricao' => $this->descricao,
            'produtoclasse_id' => $this->produtoclasse_id,
            'unidademedida_id' => $this->unidademedida_id,
            'vasilhame_retornavel' => (bool) $this->vasilhame_retornavel,
            'produto_retornavel_id' => $this->produto_retornavel_id,
            'ativo' => (bool) $this->ativo,
            'envia_app_nf' => (bool) $this->envia_app_nf,
            'dias_giro' => $this->dias_giro,
            'observacao' => $this->observacao,
            // Preços / pesos (número cru)
            'preco_venda' => $this->num($this->preco_venda),
            'preco_venda_minimo' => $this->num($this->preco_venda_minimo),
            'custo_medio' => $this->num($this->custo_medio),
            'custo_frete' => $this->num($this->custo_frete),
            'preco_gasdopovo' => $this->num($this->preco_gasdopovo),
            'peso_liquido' => $this->num($this->peso_liquido),
            'peso_bruto' => $this->num($this->peso_bruto),
            // Fiscal
            'nfe_permite' => (bool) $this->nfe_permite,
            'sped' => (bool) $this->sped,
            'nfe_tipo_item' => $this->nfe_tipo_item,
            'ncm' => $this->ncm,
            'nfe_cest' => $this->nfe_cest,
            'ean' => $this->ean,
            'especie' => $this->especie,
            'marca' => $this->marca,
            // GLP
            'tipo_glp' => $this->tipo_glp,
            'nfe_cprod_anp' => $this->nfe_cprod_anp,
            'pglp' => $this->num($this->pglp),
            // Origens aninhadas
            'origens' => $this->whenLoaded('origens', fn () => $this->origens->map(fn ($o) => [
                'id' => $o->id,
                'uf' => $o->uf,
                'ind_import' => $o->ind_import,
                'cuf_orig' => $o->cuf_orig,
                'p_orig' => $o->p_orig !== null ? (float) $o->p_orig : null,
            ])),
        ];
    }

    private function num(mixed $v): ?float
    {
        return $v !== null ? (float) $v : null;
    }
}
