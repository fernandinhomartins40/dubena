<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class ProdutoRequest extends Request
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $sped = $this->request->get('sped');
        $nfepermite = $this->request->get('nfepermite');
        $vasilhameretornavel = $this->request->get('vasilhameretornavel');
        $produtoclasse = $this->request->get('produtoclasse_id');
        $classglp = $this->request->get('classeglp');
        $classeproduto = $classglp == null ? null : collect(json_decode($classglp));
        $contem = $classeproduto->contains($produtoclasse);
        $generalRules = [
            "produtoclasse_id"      => "required",
            "descricao"             => "required",
            "unidademedida_id"      => "required",
            "vasilhameretornavel"   => "required",
        ];

        if ($nfepermite === '1') {
            $generalRulesNfe = [
                "nfgrupofiscal_id"      => "required",
                "nfedescricaofiscal"    => "required",
            ];
            $rulesglp = [];

            if ($contem) {
                $rulesglp = [
                    "nfecprodanp"   => "required",
                    "nfedescanp"    => "required",
                    "nfeqbcprod"    => "required",
                    "nfevaliqprod"  => "required",
                    "nfevcide"      => "required",
                    "origensList"   => "required"
                ];
            }

            $rules = array_merge($generalRules, $generalRulesNfe, $rulesglp);
        } else {
            $rules = $generalRules;
        }

        if ($sped === '1') {
            $spedrules = [
                "nfetipoitem" => "required"
            ];
            $rules = array_merge($rules, $spedrules);
        } else {
            $rules = $rules;
        }

        if ($vasilhameretornavel === '1') {
            $generalRulesVas = [
                "produtoretornavel_id" => "required"
            ];
            $rules = array_merge($generalRulesVas, $rules);
        }

        return $rules;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'produtoclasse_id.not_in'           => 'Selecionar classe do produto.',
            'descricao.required'                => 'O campo Nome do Produto é obrigatório.',
            'unidademedida_id.not_in'           => 'Selecionar unidade de medida.',
            ///'pesoliquido.numeric'            => 'O campo peso liquido deve ser número.',
            ///'pesobruto.numeric'              => 'O campo peso bruto deve ser número.',
            'nfeqbcprod.numeric'                => 'O campo BC da CIDE deve ser número.',
            'nfeqbcprod.required'               => 'O campo BC da CIDE é obrigatório.',
            'nfevaliqprod.numeric'              => 'O campo Valor Aliq da Cide deve ser número.',
            'nfevaliqprod.required'             => 'O campo Valor Aliq da Cide é obrigatório.',
            'nfevcide.numeric'                  => 'O campo valor da cide deve ser número.',
            'nfevcide.required'                 => 'O campo valor da cide é obrigatório.',
            'nfecodlst.numeric'                 => 'O campo cód lst deve ser número.',
            'nfecodgen.numeric'                 => 'O campo cód gênero deve ser número.',
            'nfgrupofiscal_id.not_in'           => 'Selecionar um Grupo Fiscal.',
            'nfedescricaofiscal.required'       => 'O campo Nome Fiscal é obrigatório',
            'nfecodenquadramentoipi.required'   => 'O campo Cód. Enquadramento é obrigatório.',
            'nfecprodanp.required'              => 'O campo Código ANP é obrigatório.',
            'produtoretornavel_id.required'     => 'Um produto do tipo vasilhame deve ser selecionado.',
            'nfedescanp.required'               => 'O campo Descrição ANP é obrigatório.',
            'origens.required'                  => 'Tabela de Origem de Combustível é obrigatória para GLP.',
        ];
    }
}
