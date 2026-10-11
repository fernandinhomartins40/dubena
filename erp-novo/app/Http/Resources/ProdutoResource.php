<?php

namespace App\Http\Resources;

use App\Domain\Acesso\CamposPermitidos;
use App\Models\Produto\Produto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Produto
 */
class ProdutoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $dados = [
            'id' => $this->id,
            'descricao' => $this->descricao,
            // A tela precisa saber a natureza para esconder estoque/NCM de
            // servico e taxa, que nao tem armazem nem classificacao fiscal.
            'natureza' => $this->natureza?->value ?? 'produto',
            'natureza_rotulo' => $this->natureza?->rotulo() ?? 'Produto',
            'produtoclasse_id' => $this->produtoclasse_id,
            'unidademedida_id' => $this->unidademedida_id,
            'vasilhame_retornavel' => (bool) $this->vasilhame_retornavel,
            'produto_retornavel_id' => $this->produto_retornavel_id,
            // Rotulos das FKs: o AsyncSelect so carrega a lista quando o popover
            // abre, entao sem eles o campo preenchido exibe "Selecione...".
            'classe_label' => $this->whenLoaded('classe', fn () => $this->classe?->descricao),
            'unidade_label' => $this->whenLoaded('unidade', fn () => $this->unidade?->descricao),
            'vasilhame_label' => $this->whenLoaded('retornavel', fn () => $this->retornavel?->descricao),
            'ativo' => (bool) $this->ativo,
            'envia_app_nf' => (bool) $this->envia_app_nf,
            'dias_giro' => $this->dias_giro,
            'observacao' => $this->observacao,
            // Preços / pesos (número cru)
            //
            // A SPA lê `precovenda`/`precovendaminimo` (grafia do legado) — sem
            // esses aliases a lista de produtos mostrava R$ 0,00 em tudo, embora
            // o preço estivesse gravado. Os dois nomes viajam para não quebrar
            // quem já consome a forma com underscore.
            'precovenda' => $this->num($this->preco_venda),
            'precovendaminimo' => $this->num($this->preco_venda_minimo),
            'preco_venda' => $this->num($this->preco_venda),
            'preco_venda_minimo' => $this->num($this->preco_venda_minimo),
            'customedio' => $this->num($this->custo_medio),
            'custofrete' => $this->num($this->custo_frete),
            'precogasdopovo' => $this->num($this->preco_gasdopovo),
            'pesoliquido' => $this->num($this->peso_liquido),
            'pesobruto' => $this->num($this->peso_bruto),
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
            // Estes ficavam de FORA da resposta: o formulário abria em branco
            // e, ao salvar, mandaria o branco por cima do dado migrado.
            'grupo_fiscal_id' => $this->grupo_fiscal_id,
            'grupofiscal_label' => $this->whenLoaded('grupoFiscal', fn () => $this->grupoFiscal?->descricao),
            'ean_trib' => $this->ean_trib,
            'nfe_descricao_fiscal' => $this->nfe_descricao_fiscal,
            'nfe_nat_rec' => $this->nfe_nat_rec,
            'nfe_cod_gen' => $this->nfe_cod_gen,
            'nfe_cod_lst' => $this->nfe_cod_lst,
            'nfe_aliq_ipi' => $this->num($this->nfe_aliq_ipi),
            'nfe_bc_ipi' => $this->num($this->nfe_bc_ipi),
            'nfe_cod_enquadramento_ipi' => $this->nfe_cod_enquadramento_ipi,
            'nfe_ex_tipi' => $this->nfe_ex_tipi,
            // GLP
            'tipo_glp' => $this->tipo_glp,
            'nfe_cprod_anp' => $this->nfe_cprod_anp,
            'nfe_desc_anp' => $this->nfe_desc_anp,
            'nfe_qbc_prod' => $this->num($this->nfe_qbc_prod),
            'nfe_valiq_prod' => $this->num($this->nfe_valiq_prod),
            'nfe_vcide' => $this->num($this->nfe_vcide),
            'pglp' => $this->num($this->pglp),
            'pgnn' => $this->num($this->pgnn),
            'pgni' => $this->num($this->pgni),
            // Origens aninhadas
            'origens' => $this->whenLoaded('origens', fn () => $this->origens->map(fn ($o) => [
                'id' => $o->id,
                'uf' => $o->uf,
                'ind_import' => $o->ind_import,
                'cuf_orig' => $o->cuf_orig,
                'p_orig' => $o->p_orig !== null ? (float) $o->p_orig : null,
            ])),
        ];

        if (! app(CamposPermitidos::class)->pode($request->user(), 'produto', 'custo', 'view')) {
            unset($dados['customedio'], $dados['custofrete'], $dados['custo_medio'], $dados['custo_frete']);
        }

        return $dados;
    }

    private function num(mixed $v): ?float
    {
        return $v !== null ? (float) $v : null;
    }
}
