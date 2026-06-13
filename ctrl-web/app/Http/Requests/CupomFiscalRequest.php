<?php

namespace App\Http\Requests;

use Session;

class CupomFiscalRequest extends Request
{

    /**
     * Mensagens de validação
     * @var array
     */
    private $messages = Array(
        'nfoperacao_id.required'             => 'A campo Operação das Definições é obrigatório.',
        'condicaopagamento_id.required'      => 'O campo Condição de Pagamento do Financeiro é obrigatório.',
        'centrocusto_id.required'            => 'O campo Centro de Custo do Financeiro é obrigatório.',
        'planoconta_id.required'             => 'O campo Plano de Conta do Financeiro é obrigatório.',
        'cliente_id.required'                => 'O campo Destinatário é obrigatório.',
        'destxnome.required'                 => 'O campo Nome/Razão Social do destinatário é obrigatório.',
        'destxlgr.required'                  => 'O campo Rua do destinatário é obrigatório.',
        'destnro.required'                   => 'O campo Número do destinatário é obrigatório.',
        'destxbairro.required'               => 'O campo Bairro do destinatário é obrigatório.',
        'destxmun.required'                  => 'O campo Cidade do destinatário é obrigatório.',
        'destuf.required'                    => 'O campo UF do destinatário é obrigatório.',
    );

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

        $rules = [
            "cliente_id"        => "required",
            "destxnome"         => "required",
            "destxlgr"          => "required",
            "destnro"           => "required",
            "destxbairro"       => "required",
            "destxmun"          => "required",
            "destuf"            => "required",
            "nfoperacao_id"     => "required"
        ];
        return $rules;
    }

    /**
     *
     * @return array
     */
    function messages()
    {
        return $this->messages;
    }

    /**
     *  Add validation message
     * @param $key
     * @param $message
     */
    function addMessage($key, $message)
    {
        $this->messages[$key] = $message;
    }

}
