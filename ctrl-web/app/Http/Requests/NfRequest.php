<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\Nfoperacao;
use Session;

class NfRequest extends Request
{

    /**
     * Mensagens de validação
     * @var array
     */
    private $messages = Array(
        'nfoperacao_id.required'             => 'A campo Operação das Definições é obrigatório.',
        'fretecliente_id.required'           => 'O campo Transportadora do Frete é obrigatório.',
        'freteplacauf.required'              => 'O campo UF Placa do Frete é obrigatório.',
        'freteplaca.required'                => 'O campo Placa do Frete é obrigatório.',
        'vfrete.required'                    => 'O campo Total Frete é obrigatório.',
        'fretecondicaopagamento_id.required' => 'O campo Condição de Pagamento do Frete é obrigatório.',
        'fretecentrocusto_id.required'       => 'O campo Centro Custo do Frete é obrigatório.',
        'freteplanoconta_id.required'        => 'O campo Plano Conta do Frete é obrigatório.',
        'condicaopagamento_id.required'      => 'O campo Condição de Pagamento do Financeiro é obrigatório.',
        'centrocusto_id.required'            => 'O campo Centro de Custo do Financeiro é obrigatório.',
        'planoconta_id.required'             => 'O campo Plano de Conta do Financeiro é obrigatório.',
        'nfmodelo.required'                  => "O campo Modelo é obrigatório.",
        'chaveacesso.required'               => "O campo Chave Acesso é obrigatório.",
        'nfserie.required'                   => "O campo Série é obrigatório.",
        'nfnumero.required'                  => "O campo Número é obrigatório.",
        'nftipoemissao.required'             => "O campo Tipo Emissão é obrigatório.",
        'chaveacesso.max'                    => "O campo Chave Acesso não pode ter mais que 44 caracteres.",
        'chaveacesso.unique'                 => "A Chave Acesso já foi utilizada em outro lançamento.",
        'fretemodalidade.max'                => "Não deve existir frete para NFC-e com Presença do Comprador 1",
        'fretemodalidade.min'                => "Não deve existir frete para NFC-e com Presença do Comprador 1",
        'nfserie_complementar.required'      => "O campo Número Série é obrigatório",
        'produtos_complementar.required'     => "Ao menos um produto é necessário",
        'numnfe_complementar.required'       => "O campo Número NFe é obrigatório"
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

        $complementar = $this->request->get('nfcomplementar');
        if ($complementar) {
            return [
                'nfserie_complementar'  => 'required',
                'produtos_complementar' => 'required',
                'numnfe_complementar'   => 'required'
            ];
        }
        $rulesbasico = [
            'empresa_id'    => 'required',
            'nfoperacao_id' => 'required',
            'cliente_id'    => 'required'
        ];

        $tiponf = $this->request->get('tiponf');

        //muda a descrição dependendo do tipo de nota fiscal
        $tipoCliente = $tiponf === "emitida" ? "Destinatário" : "Emitente";
        $this->addMessage('cliente_id.required', "O $tipoCliente das Definições é obrigatório.");

        $tipoEmpresa = $tiponf !== "emitida" ? "Destinatário" : "Emitente";
        $this->addMessage('empresa_id.required', "O campo $tipoEmpresa das Definições é obrigatório.");

        if ($tiponf !== "emitida")
            $rulesbasico = array_merge($rulesbasico, $this->rulesRecebida());
        else
            $rulesbasico = array_merge($rulesbasico, $this->rulesPresencaComprador());

        $freteModalidade = (int) $this->request->get('fretemodalidade');

        if ($tiponf === "emitida" && $freteModalidade !== 9) {
            $rulesbasico = array_merge($rulesbasico, $this->rulesFrete());
        } elseif ($tiponf === "recebida") {
            if ($freteModalidade !== 9 && (($tiponf === "recebida" && $freteModalidade !== 1) || $tiponf === "emitida")) {
                $rulesbasico = array_merge($rulesbasico, $this->rulesFrete());
            }
        }

        if ((int) $this->request->get('nfmodelo') === 65 && $freteModalidade !== 9 && $this->request->get('presencacomprador') != 4) {
            $rulesbasico['fretemodalidade'] = 'min:9|max:9';
        }

        $rulesfinanceiro = $this->rulesFinanceiro();
        $rules = array_merge($rulesbasico, $rulesfinanceiro);

        if (Session::get('empresa_padrao')->spedemite) {
            $msg = 'O campo Plano de Conta do Financeiro é obrigatório quando a empresa emite SPED.';
            $this->addMessage('planoconta_id.required', $msg);
            $rules['planoconta_id'] = 'required';
        }

        return $rules;
    }

    /**
     * 
     * @return array
     */
    private function rulesPresencaComprador()
    {
        if ($this->request->get('nfmodelo') == "55") {
            $this->addMessage('presencacomprador.in', 'A Presença do Comprador deve ser: 0, 1, 2, 3, 5 ou 9 para o modelo 55.');
            return [
                'presencacomprador' => 'required|in:0,1,2,3,5, 9'
            ];
        } else {
            $this->addMessage('presencacomprador.in', 'A Presença do Comprador deve ser: 1 ou 4 para o modelo 65.');
            return [
                'presencacomprador' => 'required|in:1,4'
            ];
        }
    }

    /**
     *  regras de específicas de Lançamento de Documentos (nfrecebida)
     * @return array
     */
    private function rulesRecebida()
    {
        $rules = [
            'nfnumero'      => 'required',
            'nfserie'       => 'required',
            'nfmodelo'      => 'required',
            'nftipoemissao' => 'required'
        ];

        if (hasStrIn($this->request->get('nfmodelo'), ['55', '57', '59', '60', '65', '67', '63'])) {
            if ($this->method === 'POST' || $this->request->get('id') === "")
                $rules['chaveacesso'] = 'required|max:44|unique:nfrecebidas,chaveacesso,NULL,id';
            else
                $rules['chaveacesso'] = 'required|max:44|unique:nfrecebidas,chaveacesso,' . $this->request->get('id') . ',id';
        }

        return $rules;
    }

    /**
     *  regras de validação para de frete
     * @return array
     */
    private function rulesFrete()
    {
        $rulesfrete = [
            'fretecliente_id' => 'required'
        ];
        if ($this->request->get("emituf") === $this->request->get("destuf")) {
            $rulesfrete['freteplacauf'] = 'required';
            $rulesfrete['freteplaca'] = 'required';
        }

        $vfrete = floatval(insertNumeroDecimalOracle($this->request->get('vfrete')));

        if ($vfrete > 0 && $this->request->get('formapagamento')) {
            $rules = [
                'fretecondicaopagamento_id' => 'required',
                'fretecentrocusto_id'       => 'required',
                'freteplanoconta_id'        => 'required'
            ];
            $rulesfrete = array_merge($rulesfrete, $rules);
        }

        return $rulesfrete;
    }

    /**
     *  regras de validação para finaceiro
     * @return array
     */
    private function rulesFinanceiro()
    {
        $rules = [];
        // Adicionado em 28-06 devido a sugestão do flavio para que a Nf não saia com a descrição "Sem pagamento" na Danfe
        if ($this->request->get('tiponf') === "emitida") {
            $rules['condicaopagamento_id'] = 'required';
        }

        $nfoperacao = Nfoperacao::find($this->request->get('nfoperacao_id'));

        if (is_null($nfoperacao))
            return $rules;
        if ($nfoperacao->movimentafinanceiro) {

            $rules['condicaopagamento_id'] = 'required';

            if (count(json_decode($this->request->get("rateios"))) === 0) {
                $rules['centrocusto_id'] = 'required';
                $rules['planoconta_id'] = 'required';
            }
        }

// isso foi removido devido a sugestão do flavio para que a Nf não saia com a descrição "Sem pagamento" na Danfe
//        if (!$nfoperacao->movimentafinanceiro && $this->request->get('nfmodelo') == 65)
//            $rules['condicaopagamento_id'] = 'required';

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
