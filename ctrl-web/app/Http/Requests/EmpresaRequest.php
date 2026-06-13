<?php

namespace App\Http\Requests;

use Session;
use App\Http\Requests\Request;

class EmpresaRequest extends Request
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id_up = !$this->request->get('empresa_up') ? "null" : $this->request->get('empresa_up');
        $rulesdefault = [
            'razao_social'        => 'required',
            'nome_fantasia'       => 'required',
            'nome_informal'       => 'required',
            'cnpj'                => 'required',
            'cnpj'                => "unique:empresas,cnpj,$id_up,id,ativo,1,grupo_id," . Session::get('empresa_padrao')->grupo_id,
            'rua_id'              => 'required',
            'suframa'             => 'max:9',
            'inscricao_municipal' => 'max:15'
        ];
        $rulescontingencia = [];
        if ($this->request->get('contingenciaemissao') === '1') {
            $rulescontingenciabase = [
                'contingenciadatahora'      => 'required',
                'contingenciajustificativa' => 'required'
            ];
            $rulescontingencia = array_merge($rulescontingenciabase);
        }
        $rulesnfe = [];
        if ($this->request->get('nfeemite') === '1') {
            $rulesnfebase = [
                'nfeserie'         => 'required',
                'nfemodelo'        => 'required',
                'nfecrt'           => 'required',
                'nfetipoemissao'   => 'required',
                'nfetipoambiente'  => 'required'
            ];
            $rulesnfeproducao = [];
            if ($this->request->get('nfetipoambiente') === '1') {
                $rulesnfeproducao = [
                    'nfenumero' => 'required'
                ];
            }
            $rulesnfehomologacao = [];
            if ($this->request->get('nfetipoambiente') === '2') {
                $rulesnfehomologacao = [
                    'nfenumerohomologacao' => 'required'
                ];
            }
            $rulesnfecrt = [];
            if ($this->request->get('nfecrt') === '1' || $this->request->get('nfecrt') === '2') {
                $rulesnfecrt = [
                    'nfecreditosimplesnacional' => 'required'
                ];
            }
            $rulesnfe = array_merge($rulesnfebase, $rulesnfeproducao, $rulesnfehomologacao, $rulesnfecrt);
        }
        $rulesnfce = [];
        if ($this->request->get('nfceemite') === '1') {
            $rulesnfcebase = [
                'nfceserie'         => 'required',
                'nfcemodelo'        => 'required',
                'nfcetipoemissao'   => 'required',
                'nfcetipoambiente'  => 'required',
                'nfcevalorlimite'   => 'required'
            ];
            $rulesnfceproducao = [];
            if ($this->request->get('nfcetipoambiente') === '1') {
                $rulesnfceproducao = [
                    'nfcenumero' => 'required'
                ];
            }
            $rulesnfcehomologacao = [];
            if ($this->request->get('nfcetipoambiente') === '2') {
                $rulesnfcehomologacao = [
                    'nfcenumerohomologacao' => 'required'
                ];
            }
            $rulesnfce = array_merge($rulesnfcebase, $rulesnfceproducao, $rulesnfcehomologacao);
        }
        $rulessped = [];
        if ($this->request->get('spedemite') === '1') {
            $rulesspedbase = [
                'spedincidenciatributaria' => 'required',
                'spedperfil'               => 'required',
                'spedregistro1010'         => 'required',
                'spedapropriacaocredito'   => 'required',
                'spedatividade'            => 'required',
                'spedtipocontribuicao'     => 'required',
                'spedregimecumulativo'     => 'required',
                'contnome'                 => 'required',
                'contcpf'                  => 'required',
                'contcnpj'                 => 'required',
                'contcrc'                  => 'required',
                'contcep'                  => 'required',
                'contuf'                   => 'required',
                'contcidade_id'            => 'required',
                'contbairro_id'            => 'required',
                'contrua_id'               => 'required',
                'contnumero'               => 'required'
            ];
            $rulessped = array_merge($rulesspedbase);
        }
        $ruletoken = [];
        if ($this->request->get('nfeemitemodelos') == '2' || $this->request->get('nfeemitemodelos') == '3') {
            if ($this->request->get('nfcetipoambiente') == '1') {
                $rulecsc = [
                    "nfcetokenid_prod"  => "required",
                    "nfcetoken_prod"    => "required"
                ];
            } else {
                $rulecsc = [
                    "nfcetokenid" => "required",
                    "nfcetoken"   => "required"
                ];
            }
            $ruletoken = array_merge($rulecsc);
        }
        $rules = array_merge($rulesdefault, $rulescontingencia, $rulesnfe, $rulesnfce, $rulessped, $ruletoken);
        return $rules;
    }

    /**
     * Get the validation rules that apply to the request.
     *  
     * @return array
     */
    public function messages() {
        $msg = array(
            'cnpj.required'                       => 'O campo CNPJ é obrigatório.',
            'cnpj.unique'                         => 'O CNPJ já esta em uso nesse grupo.',
            'contingenciadatahora.required'       => 'O campo Data/Hora da contingência é obrigatório.',
            'contingenciajustificativa.required'  => 'O campo Justificativa da contingência é obrigatório.',
            'nfeserie.required'                   => 'O campo NF-e Série é obrigatório.',
            'nfemodelo.required'                  => 'O campo NF-e Modelo é obrigatório.',
            'nfenumero.required'                  => 'O campo NF-e Número é obrigatório.',
            'nfenumerohomologacao.required'       => 'O campo NF-e Número Homologação é obrigatório.',
            'nfecreditosimplesnacional.required'  => 'O campo NF-e Crédito Simples Nacional é obrigatório.',
            'nfceserie.required'                  => 'O campo NFC-e Série é obrigatório.',
            'nfcemodelo.required'                 => 'O campo NFC-e Modelo é obrigatório.',
            'nfcenumerohomologacao.required'      => 'O campo NFC-e Número Homologação é obrigatório.',
            'nfcenumero.required'                 => 'O campo NFC-e Número é obrigatório.',
            'nfcecreditosimplesnacional.required' => 'O campo NFC-e Crédito Simples Nacional é obrigatório.',
            'nfcevalorlimite.required'            => 'O campo NFC-e Valor Limite é obrigatório.',
            'spedincidenciatributaria.required'   => 'O campo SPED Incidência Tributária é obrigatório.',
            'spedperfil.required'                 => 'O campo SPED Perfil é obrigatório.',
            'spedregistro1010.required'           => 'O campo SPED Registro 1010 é obrigatório.',
            'spedapropriacaocredito.required'     => 'O campo SPED Apropriação de Crédito é obrigatório.',
            'spedatividade.required'              => 'O campo SPED Atividade é obrigatório.',
            'spedtipocontribuicao.required'       => 'O campo SPED Tipo Contribuição é obrigatório.',
            'spedregimecumulativo.required'       => 'O campo SPED Regime Cumulativo é obrigatório.',
            'contnome.required'                   => 'O campo Contabilista Nome é obrigatório.',
            'contcpf.required'                    => 'O campo Contabilista CPF é obrigatório.',
            'contcnpj.required'                   => 'O campo Contabilista CNPJ é obrigatório.',
            'contcrc.required'                    => 'O campo Contabilista CRC é obrigatório.',
            'contcep.required'                    => 'O campo Contabilista CEP é obrigatório.',
            'contuf.required'                     => 'O campo Contabilista UF é obrigatório.',
            'contcidade_id.required'              => 'O campo Contabilista Cidade é obrigatório.',
            'contbairro_id.required'              => 'O campo Contabilista Bairro é obrigatório.',
            'contrua_id.required'                 => 'O campo Contabilista Rua é obrigatório.',
            'contnumero.required'                 => 'O campo Contabilista Número é obrigatório.',
            'nome_informal.required'              => 'O campo Nome Informal é obrigatório.',
            'suframa.max'                         => 'O campo Suframa não pode ter mais que 9 caracteres.',
            'inscricao_municipal.max'             => 'O campo Inscrição Municipal não pode ter mais que 15 caracteres.',
            'nfesenhapfx.required'                => 'O campo Senha do Certificado é obrigatório.',
            'nfcetokenid.required'                => 'O campo Token ID é obrigatório.',
            'nfcetoken.required'                  => 'O campo CSC é obrigatório.',
            'nfcetokenid_prod.required'           => 'O campo Token ID é obrigatório.',
            'nfcetoken_prod.required'             => 'O campo CSC é obrigatório.',
        );
        return $msg;
    }
}
