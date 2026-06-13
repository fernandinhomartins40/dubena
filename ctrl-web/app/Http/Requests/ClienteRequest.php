<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use Session;

class ClienteRequest extends Request
{

    protected $id;
    protected $complemento;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function generalRules()
    {
        if ($this->method === 'POST') {
            return [
                'tipopessoa_id'     => 'required',
                'nome'              => 'required|min:3',
                'uf'                => 'required',
                'cidade_id'         => 'required',
                'bairro_id'         => 'required',
                'numero'            => 'required|max:10',
                'rua_id'            => 'required',
            ];
        } else {
            return [
                'tipopessoa_id'    => 'required',
                'nome'             => 'required|min:3',
                'uf'               => 'required',
                'cidade_id'        => 'required',
                'bairro_id'        => 'required',
                'numero'           => 'required|max:10',
                'rua_id'           => 'required',
            ];
        }
    }

    public function pessoaJuridicaRules()
    {
        if ($this->method === 'POST') {
            $juridicaRules = [
                'cnpj'                  => 'required|unique:clientes,cnpj,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id,
                'inscricao_estadual'    => 'unique:clientes,inscricao_estadual,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id,
                'suframa'               => 'max:9'
            ];
        } else {
            $juridicaRules = [
                'cnpj'                  => 'required|unique:clientes,cnpj,' . $this->id . ',id,empresa_id,' . Session::get('empresa_padrao')->id,
                'inscricao_estadual'    => 'unique:clientes,inscricao_estadual,' . $this->id . ',id,empresa_id,' . Session::get('empresa_padrao')->id,
                'suframa'               => 'max:9'
            ];
        }
        return $juridicaRules;
    }

    public function pessoaFisicaRules()
    {
        if ($this->request->get('nfemite') === '1') {

            if ($this->method === 'POST') {
                $rules = [
                    'indicador_ie'  => 'required',
                    'rg'            => 'unique:clientes,rg,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id,
                    'cpf'           => 'required|unique:clientes,cpf,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id,
//                'cep'           => 'required'
                ];
            } else {
                $rules = [
                    'indicador_ie'  => 'required',
                    'cpf'           => 'required|unique:clientes,cpf,' . $this->id . ',id,empresa_id,' . Session::get('empresa_padrao')->id,
                    'rg'            => 'unique:clientes,rg,' . $this->id . ',id,empresa_id,' . Session::get('empresa_padrao')->id,
//                'cep'           => 'required'
                ];
            }
        } else {
            if ($this->method === 'POST') {
                $rules = [
                    'rg'    => 'unique:clientes,rg,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id,
                    'cpf'   => 'unique:clientes,cpf,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id
                ];
            } else {
                $rules = [
                    'rg'    => 'unique:clientes,rg,' . $this->id . ',id,empresa_id,' . Session::get('empresa_padrao')->id,
                    'cpf'   => 'unique:clientes,cpf,' . $this->id . ',id,empresa_id,' . Session::get('empresa_padrao')->id,
                ];
            }
        }
        return $rules;
    }

    public function rules()
    {
        $this->id = $this->request->get('cliente_id');
        $this->complemento = $this->request->get('complemento');
        $prodconv = $this->request->get('clienteprodutosconvenios');
        if ($this->request->get('tipopessoa_id') == '') {
            return $this->generalRules();
        } else {
            $tipoPessoaRules = [];
            $tipoPessoa = $this->request->get('tipopessoa_id');
            ///dd($this->request->get('nome'));
            if (str_contains($tipoPessoa, 'F')) { //// pessoa fisica
                $rules1 = $this->generalRules();
                $rules2 = $this->pessoaFisicaRules();
                $rules3 = $this->rulesTipo();
                $tipoPessoaRules = array_merge($rules1, $rules2, $rules3);
            } else {
                if (str_contains($tipoPessoa, 'J')) { //// pessoa juridica
                    $rules1 = $this->generalRules();
                    $rules2 = $this->pessoaJuridicaRules();
                    $rules3 = $this->rulesTipo();
                    $tipoPessoaRules = array_merge($rules1, $rules2, $rules3);
                }
            }
        }
        $rulesConvenio = $this->rulesConvenio();
        $rulesConveniado = $this->rulesConveniado();
        $rules = array_merge($tipoPessoaRules, $rulesConvenio, $rulesConveniado);
        $rulesSegmento = $this->rulesSegmento();
        $rules = array_merge($rules, $rulesSegmento);

        return $rules;
    }

    function rulesTipo()
    {
        if ($this->request->get('cliente') === null && $this->request->get('fornecedor') === null && $this->request->get('transportador') === null) {
            return ['tipo' => 'required'];
        }
        return [];
    }

    function rulesConvenio()
    {
        if ($this->request->get('convenioativo') === '1') {
            return [
                'datacontrato'              => 'required',
                'limitecompra'              => 'required|numeric',
                'diafechamento'             => 'required|numeric',
                'diavencimento'             => 'required|numeric',
                'clienteprodutosconvenios'  => 'required',
            ];
        }
        return [];
    }

    function rulesConveniado()
    {
        if ($this->request->get('convenio') === '1') {
            return [
                'convenio_id' => 'required'
            ];
        }
        return [];
    }
    function rulesSegmento()
    {
        if($this->request->get('cliente') !== null){
            return [
                'segmento_id' => 'required'
            ];
        }
        return [];
    }
    function messages()
    {
        $messages = [
            'numero.unique'                     => 'Já há um cliente cadastrado para esse endereço.',
            'tipo.required'                     => 'Você precisa selecionar se é cliente, fornecedor ou transportador.',
            'clienteprodutosconvenios.required' => 'Produto para o convênio é obrigatório.',
        ];
        if(str_contains($this->request->get('tipopessoa_id'), 'F'))
            return array_merge($messages, ['nome.required' => 'O campo Nome é obrigatório.']);
        else
            return array_merge($messages, ['nome.required' => 'O campo Razão Social é obrigatório.']);
    }

}
