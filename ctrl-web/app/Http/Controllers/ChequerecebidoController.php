<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Banco;
use App\Chequerecebido;
use App\Chequesituacao;
use App\Contamovimento;
use App\Financeiroparcela;
use Illuminate\Http\Request;
use App\Chequerecebidofinanceiro;
use App\Processors\caixaProcessor;
use App\Processors\ChequeProcessor;
use App\Services\CarbonCustom as Carbon;

class ChequerecebidoController extends Controller
{

    private $chequeProcessor;
    private $errors;

    public function __construct()
    {
        $this->chequeProcessor = new ChequeProcessor();
    }

    private function setErrors($error)
    {
        $this->errors .= " $error";
    }

    private function getErrors()
    {
        return $this->errors;
    }

    public function index(Chequerecebido $cheq)
    {
        $this->authorize('view',$cheq);
        try {
            $empresa_id = Session::get('empresa_padrao')->id;
            $chequesrecebidos = Chequerecebido::where('chequerecebidos.empresa_id', $empresa_id);
            $chequesituacaos = Chequesituacao::where('chequerecebido', 1)->pluck('descricao', 'id')->prepend('Selecione', '');

            if (count($_GET) > 0) {
                $chequesrecebidos = $this->getFiltrosCheque($_GET, $chequesrecebidos);
                if($chequesrecebidos == false)
                    return $this->getErrors();
            }

            $contas = \Auth::user()->contasAtivas()->with('conta')->where('empresa_id', $empresa_id)
            ->where('operar', '=', 1)->orderBy('descricao')->get()->pluck('descricao', 'id')->prepend('Selecione','');

            $chequesrecebidos = $chequesrecebidos->select('chequerecebidos.id', 'chequerecebidos.numerocheque', 'chequerecebidos.banco_id',
                    'chequerecebidos.agencia', 'chequerecebidos.numeroconta', 'chequerecebidos.dataemissao', 'chequerecebidos.datavencimento',
                    'chequerecebidos.datapagamento', 'chequerecebidos.valor', 'chequerecebidos.chequesituacao_id', 'chequerecebidos.observacao',
                    'chequerecebidos.depositoconta_id', 'chequerecebidos.baixaconta_id')
                ->orderBy('dataemissao', 'desc')
                ->get()
                ->unique('id');

            $cliente_nome = '';
            $cliente_id = '';
            if (count($_GET) > 0) {
                $cliente_id = $_GET['cliente_id'];
                $cliente_nome = DB::table('clientes')->where('id', $cliente_id)->select('nome')->get()->pluck('nome')->first();
            }
            $total = 0;
            foreach ($chequesrecebidos as $cheq) {
                $total += $cheq->valor;
            }
            $total = requestNumeroDecimalOracle($total);
        } catch (\Exception $e) {
            return getErrorsException($e);
        }
        return View('financeiro.cheque.recebido.chequerecebido', compact('chequesrecebidos', 'chequesituacaos', 'contas', 'cliente_nome', 'cliente_id', 'total'));
    }

    private function getFiltrosCheque($get, $chequesrecebidos)
    {
        if (strlen($get['cliente_id']) > 0){
                $chequesrecebidos = $chequesrecebidos->join('chequerecebidofinanceiros', 'chequerecebidofinanceiros.chequerecebido_id', 'chequerecebidos.id')
                                    ->join('financeiros', 'financeiros.id', 'chequerecebidofinanceiros.financeiro_id')
                                    ->where('financeiros.cliente_id', $get['cliente_id']);
        }

        if (strlen($get['numerochequeinicial']) > 0)
            $chequesrecebidos = $chequesrecebidos->where('chequerecebidos.numerocheque', '>=' , $get['numerochequeinicial']);

        if (strlen($get['numerochequefinal']) > 0)
            $chequesrecebidos = $chequesrecebidos->where('chequerecebidos.numerocheque', '<=' , $get['numerochequefinal']);

        if (strlen($get['chequesituacao_id']) > 0)
            $chequesrecebidos = $chequesrecebidos->where('chequerecebidos.chequesituacao_id', $get['chequesituacao_id']);

        $datainicio = insertDataOracle($get['datainicio']);
        $datafim = insertDataOracle($get['datafim']);

        if ($get['tipodata'] != '0' && (empty($datainicio) || empty($datafim))) {
            $this->setErrors('Os campos de data não podem estar vazios!');
            return false;
        }
        $arrayDatas = [$datainicio, $datafim];
        switch ($get['tipodata']) {
            case '1':
                $chequesrecebidos = $chequesrecebidos->whereBetween(DB::raw("TO_CHAR(chequerecebidos.datavencimento, 'yyyy-mm-dd')"), $arrayDatas);
                break;
            case '2':
                $chequesrecebidos = $chequesrecebidos->whereBetween(DB::raw("TO_CHAR(chequerecebidos.datapagamento, 'yyyy-mm-dd')"), $arrayDatas);
                break;
            case '3':
                $chequesrecebidos = $chequesrecebidos->whereBetween(DB::raw("TO_CHAR(chequerecebidos.dataemissao, 'yyyy-mm-dd')"), $arrayDatas);
                break;
        }
        return $chequesrecebidos;
    }

    public function show($id, Chequerecebido $cheq)
    {
        $this->authorize('view',$cheq);
        $chequerecebido = Chequerecebido::find($id);
        $this->authorize('igualdade',$chequerecebido);
        $arrayParcelas_id = Chequerecebidofinanceiro::where('chequerecebido_id', $id)->pluck('financeiroparcela_id')->toArray();
        $parcelas = Financeiroparcela::whereIn('id', $arrayParcelas_id)->get();

        $bancos = $this->getBancos();
        $chequesituacao = Chequesituacao::where('chequerecebido', 1)->pluck('descricao', 'id');
        $contas = \Auth::user()->contasAtivas()->with('conta')->where('empresa_id', Session::get('empresa_padrao')->id)
        ->where('operar', '=', 1)->get()->pluck('descricao', 'id')->prepend('Selecione','');

        return View('financeiro.cheque.recebido.chequerecebido_show', compact('chequerecebido', 'bancos', 'parcelas', 'contas', 'chequesituacao'));
    }

    public function create(Chequerecebido $cheq)
    {
        $this->authorize('create',$cheq);
        $getParams = explode('&',\Request::getQueryString());

        $get = [];

        foreach ($getParams as $param) {
            $row = explode('=', $param);
            $get[$row[0]] = isset($row[1]) ? $row[1] : '';
        }

        $arrayParcelas_id = isset($get['parcelas']) ? urldecode($get['parcelas']) : '[]';
        $arrayParcelas_id = json_decode($arrayParcelas_id);
        $parcelas = Financeiroparcela::with('financeiro.condicaoPagamento')->whereIn('id', $arrayParcelas_id)->get();
        foreach ($parcelas as $parcela) {
            $cond = $parcela->financeiro->condicaoPagamento;
            if(is_null($cond))
                return "Condição de pagamento não encontrada para a parcela $parcela->id";
            if($cond->tipo == 2 || $cond->tipo == 3)
                return "A parcela $parcela->id está com a condição de pagamento do tipo cartão e não pode ser vinculada a um cheque";
        }

        $arrayParcelasEncontrocontas_id = isset($get['encontrocontas']) && !empty($get['encontrocontas']) ? urldecode($get['encontrocontas']): '[]';
        $encontrocontas = $arrayParcelasEncontrocontas_id == '[]' ? false : true;
        $arrayParcelasEncontrocontas_id = json_decode($arrayParcelasEncontrocontas_id);
        $parcelasEncontroContas = Financeiroparcela::whereIn('id', $arrayParcelasEncontrocontas_id)->get();
        $parcelasContasPagar = json_encode($arrayParcelasEncontrocontas_id);

        $valorCredito = $parcelasEncontroContas->sum('valorefetivado');
        $valorTotal = Financeiroparcela::whereIn('id', $arrayParcelas_id)->select('valorefetivado')->sum('valorefetivado');

        $valorCheque = requestNumeroDecimalOracle($valorTotal - $valorCredito);
        $valorTotal = requestNumeroDecimalOracle(!is_null($valorTotal) ? $valorTotal : 0);
        $valorCredito = requestNumeroDecimalOracle($valorCredito);

        $bancos = $this->getBancos();
        $contas = \Auth::user()->contasAtivas()->with('conta')->where('empresa_id', Session::get('empresa_padrao')->id)
        ->where('operar', '=', 1)->get()->pluck('descricao', 'id')->prepend('Selecione','');

        return View('financeiro.cheque.recebido.chequerecebido_form', compact('bancos', 'parcelas', 'valorTotal', 'contas', 'parcelasContasPagar', 'valorCredito', 'valorCheque', 'encontrocontas'));
    }

    private function getBancos()
    {
        return Banco::where('grupo_id', Session::get('empresa_padrao')->id)->orWhere('grupo_id', null)->get()->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    public function store(Request $request, Chequerecebido $cheq)
    {
        $this->authorize('create',$cheq);
        DB::beginTransaction();
        try {
            $this->validate($request, [
                'numerocheque' => 'required',
                'banco_id_erro' => 'required',
                'agencia' => 'required',
                'numeroconta' => 'required'
                ]);
            $data = $request->all();
            if(!isset($data['dataemissao']))
                $data['dataemissao'] = Carbon::now()->format('d/m/Y');
            $data = array_merge($data, $this->dadosExtras($data));
            $validarGravar = $this->validarGravar($data);

            if (!is_bool($validarGravar))
                throw new \Exception($validarGravar);


        } catch (\Exception $e) {
            DB::rollback();
            return getErrorsException($e);
        }
        DB::commit();
        return 'OK|';
    }

    private function validarGravar($data)
    {
        $parcelas = json_decode($data['parcelas']);

        $numerochequeoriginal = $data['numerocheque'];
        $valorTotalParcelas = $data['valor'];
        $data['valor'] = $data['valorcheque'];
        $data['diferencavalor'] = $data['valor'] - $valorTotalParcelas;

        $transferencia = false;
        if($valorTotalParcelas < $data['valor'] && (isset($data['troco']))){
            $transferencia = true;
        }

        $chequerecebido = Chequerecebido::create($data);
        $this->chequeProcessor->insertHistoricoSituacao($chequerecebido->id, 4);

        if (isset($data['parcelasContasPagar'])) {
            $parcelasContasPagar = json_decode($data['parcelasContasPagar']);
            $insertChequeEncontrocontas = $this->chequeProcessor->insertChequeEncontrocontas($parcelasContasPagar, $chequerecebido, 'recebido');
            if(!is_bool($insertChequeEncontrocontas))
                throw new \Exception($insertChequeEncontrocontas);
        }

        foreach ($parcelas as $parcela) {
            $financeiro_id = Financeiroparcela::find($parcela[0])->financeiro_id;
            $dadosChequeRecebidos = [
            'financeiroparcela_id' => $parcela[0],
            'financeiro_id' => $financeiro_id,
            'numerocheque' => $data['numerocheque'],
            'chequerecebido_id' => $chequerecebido->id
            ];

            Chequerecebidofinanceiro::create($dadosChequeRecebidos);
        }

        if($transferencia){
            $transferir = $this->chequeProcessor->transfereCaixaChequeRecebido($data);
            if (!is_bool($transferir))
                return $transferir;
            $this->chequeProcessor->insertChequeTransferencia($chequerecebido->id, $this->chequeProcessor->getContaTransferenciaId(), 'TROCO');
        }
        return true;
    }

    private function dadosExtras($data)
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $grupo_id = Session::get('empresa_padrao')->grupo_id;

        $data['grupo_id'] = $grupo_id;
        $dada['banco_id'] = $data['banco_id_erro'];
        $data['empresa_id'] = $empresa_id;
        if(DB::table('chequesituacaos')->find(4) == null)
            throw new \Exception("Não foi encontrado a situação de cheques cadastrado, contate a administração.");
        $data['chequesituacao_id'] = 4;
        $data['valorcheque'] = insertNumeroDecimalOracle($data['valorcheque']);
        $data['valor'] = insertNumeroDecimalOracle($data['valor']);
        $data['datacompetencia'] = Carbon::now()->toDateTimeString();
        $data['dataemissao'] = insertDataOracle($data['dataemissao']);
        $data['datavencimento'] = insertDataOracle($data['datavencimento']);
        $data = emptyToNull($data);
        return $data;
    }

    public function editar($acao, $id)
    {
        $cheq = new Chequerecebido();
        switch ($acao) {
            case 'excluir':
                return $this->excluirCheque($id, $cheq);
                break;
        }
        return 'OK|';
    }

    private function excluirCheque($id, Chequerecebido $cheq)
    {
        $this->authorize('delete',$cheq);
        $chequerecebido = Chequerecebido::find($id);
        $this->authorize('igualdade',$chequerecebido);
        DB::beginTransaction();
        try {
            $movtos = Array();
            $transferencia = $chequerecebido->chequeRecebidoTransferencia()->get()->first();
            $chequerecebido->chequeSituacaoHistorico()->delete();
            if(!is_null($transferencia)){
                $numerocheque = $chequerecebido->numerocheque;
                $contatransferencia_id = $transferencia->contatransferencia_id;
                $transferencia->delete();

                $mov = Contamovimento::where('contatransferencia_id', $contatransferencia_id)->where('pagarreceber', 'R')->get()->first();
                if(!is_null($mov)){
                    $caixaProcessor = new caixaProcessor($mov->conta_id);
                    $caixaProcessor->setMotivoEstorno("Exclusão de cheque nº $numerocheque");
                    $caixaProcessor->estornarTransferenciaCaixa($mov);
                }
            }
            $chequerecebido->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return $e->getMessage() . ". Line: " . $e->getLine() . " File:" . $e->getFile();
        }
        DB::commit();
        return 'OK|';

    }

    public function depositarCheque(Request $request, $id, Chequerecebido $cheq)
    {
        $this->authorize('editar',$cheq);
        $data = $request->all();
        $cheque = Chequerecebido::find($id);
        $this->authorize('igualdade',$cheque);
        $data['datadeposito'] = insertDataOracle($data['datadeposito']);

        if($cheque->chequesituacao_id != "6")
            $data['chequesituacao_id'] = 5;
        else
            $data['chequesituacao_id'] = 7;

        if(strtotime($cheque->dataemissao) > strtotime($data['datadeposito']))
            return "A data de depósito não pode ser menor que a data de emissão do cheque";


        $cheque->update($data);
        $this->chequeProcessor->insertHistoricoSituacao($cheque->id, $data['chequesituacao_id']);
        return "OK|";
    }

    public function devolverCheque(Request $request, $id, Chequerecebido $cheq)
    {
        $this->authorize('devolver',$cheq);
        $this->chequeProcessor = new ChequeProcessor();
        DB::beginTransaction();
        try {
            $data = $request->all();

            $empresa_id = Session::get('empresa_padrao')->id;
            $cheque = Chequerecebido::find($id);
            //"chequerecebido.devolver"
            $this->authorize('igualdade',$cheque);
            $situacaoAnterior = $cheque->chequesituacao_id;

            if(strtotime($cheque->dataemissao) > strtotime(insertDataOracle($data['datadevolucao'])))
                return "A data de devolução não pode ser menor que a data de emissão do cheque";

            if(!is_null($cheque->baixaconta_id) && strtotime($cheque->datapagamento) > strtotime(insertDataOracle($data['datadevolucao'])))
                return "A data de devolução não pode ser menor que a data de pagamento.";

            if(!is_null($cheque->depositoconta_id) && strtotime($cheque->datadevolucao) > strtotime(insertDataOracle($data['datadevolucao'])))
                return "A data de devolução não pode ser menor que a data de depósito.";

            $transferencias = $cheque->chequeRecebidoTransferencia
                                    ->filter(function ($transf) { return $transf->tipotransferencia == 'BAIXA' || $transf->tipotransferencia == 'DEVOLUCAO';});
            $segundaBaixa = $transferencias->count() > 0;

            $baixar = is_null($cheque->datapagamento);

            //caso um cheque nunca tenha sido baixado ao fazer a devolução, primeiro baixa as parcelas e faz as transferências de baixa
            if($baixar){
                $request->request->add(['databaixa' => $data['datadevolucao'], 'baixaconta_id' => $data['baixaconta_id']]);
                $baixarCheque = $this->baixarCheque($request, $id, true, $cheq);
                if($baixarCheque != "OK|")
                    return $baixarCheque;
            } else {
                $data['databaixa'] = null;
                $data['datapagamento'] = null;
            }

            $data['chequesituacao_id'] = 6;
            $data['datadevolucao'] = insertDataOracle($data['datadevolucao']);
            $cheque->update($data);

            ////// VERIFICAR
            // if((!$segundaBaixa || $situacaoAnterior == 2) || ($segundaBaixa || $situacaoAnterior == 2)) {
            $data['numerocheque'] = $cheque->numerocheque;
            $data['transferidoconta_id'] = $cheque->baixaconta_id;
            $data['diferencavalor'] = $cheque->valor;

            $transferir = $this->chequeProcessor->transfereCaixaChequeRecebido($data, 'devol');
            if(!is_bool($transferir))
                return $transferir;
            $this->chequeProcessor->insertChequeTransferencia($cheque->id, $this->chequeProcessor->getContaTransferenciaId(), "DEVOLUCAO");
            // }
            $this->chequeProcessor->insertHistoricoSituacao($cheque->id, 6);
        } catch (\Exception $e) {
            DB::rollback();
            return $e->getMessage() . ". Line: " . $e->getLine() . " File:" . $e->getFile();
        }
        DB::commit();
        return "OK|";
    }

    public function baixarCheque(Request $request, $id, $devolucao = false, Chequerecebido $cheq)
    {
        $this->authorize('baixarambos',$cheq);
        DB::beginTransaction();
        try {
            $grupo_id = Session::get('empresa_padrao')->grupo_id;
            $data = $request->all();
            $cheque = Chequerecebido::find($id);
            $this->authorize('igualdade',$cheque);
            $data['datapagamento'] = isset($data['datapagamento']) ? insertDataOracle($data['datapagamento']) : null;
            $data['databaixa'] = insertDataOracle($data['databaixa']);

            $baixouAnterior = !is_null($cheque->baixaconta_id);

            if(strtotime($cheque->dataemissao) > strtotime($data['databaixa']))
                return "A data de baixa não pode ser menor que a data de emissão do cheque";

            if(isset($data['datadevolucao']))
                $data['datadevolucao'] = insertDataOracle($data['datadevolucao']);

            $data['chequesituacao_id'] = 2;
            $data['numerocheque'] = $cheque->numerocheque;

            $cheque->update($data);

            //se a parcela nunca foi baixada, então faz a baixa das parcelas e realiza as transferências referenta a troco ou adiantamento.
            if(!$baixouAnterior) {
                $parcelas = $cheque->chequeRecebidoFinanceiro;
                $conta_id = $cheque->baixaconta_id;
                $baixarParcelasCheque = $this->chequeProcessor->baixarParcelasCheque($parcelas, $conta_id, $grupo_id, $cheque, $data['databaixa'], "R");
                if(!is_bool($baixarParcelasCheque))
                    return $baixarParcelasCheque;

                if(!is_null($cheque->chequeRecebidoEncontrocontas->first())) {
                    $parcelas_id = $cheque->chequeRecebidoEncontrocontas->pluck('financeiroparcela_id')->toArray();
                    $parcelas = Financeiroparcela::whereIn('id', $parcelas_id)->get();
                    $baixaParcEnc = $this->chequeProcessor->baixarParcelasCheque($cheque->chequeRecebidoEncontrocontas, $conta_id, $grupo_id, $cheque, $data['databaixa'], "P");
                    if(!is_bool($baixaParcEnc))
                        return $baixaParcEnc;
                }

                //este valor vale para a adiantamento ou troco
                $data['diferencavalor'] = $cheque->diferencavalor;
                //realiza transferêncais das cotas de adiantamento ou troco somente se a parcela está sendo baixada pela primeira vez
                if (!is_null($cheque->chequeRecebidoTransferencia->where('tipotransferencia', 'TROCO')->first())) {
                    $transferir = $this->chequeProcessor->transfereCaixaChequeRecebido($data, 'baixa');
                    $tipoTransferencia = 'TROCOTRANSF';
                } else if (!is_null($cheque->adiantamentoconta_id)) {
                    $data['transferidoconta_id'] = $cheque->adiantamentoconta_id;
                    $transferir = $this->chequeProcessor->transfereCaixaChequeRecebido($data, 'adiantamento');
                    $tipoTransferencia = 'TROCOTRANSF';
                }
                if(isset($transferir) && !is_bool($transferir))
                    return $transferir;
            }

            //este valor vale para a baixaAnterioir ou para devolução
            $data['diferencavalor'] = $cheque->valor;

            if($baixouAnterior) {
                $transferir = $this->chequeProcessor->transfereCaixaChequeRecebido($data, 'segundabaixa');
                if(!is_bool($transferir))
                    return $transferir;
                $tipoTransferencia = 'BAIXA';
            }

            $this->chequeProcessor->insertHistoricoSituacao($cheque->id, 2);

        } catch (\Exception $e) {
            DB::rollback();
            return $e->getMessage() . ". Line: " . $e->getLine() . " File:" . $e->getFile();
        }
        DB::commit();
        return "OK|";
    }

    public function estornarCheque(Request $request, $id, Chequerecebido $cheq)
    {
        $this->authorize('estornar',$cheq);
        $cheque = Chequerecebido::find($id);
        $this->authorize('igualdade',$cheque);
        $chequeProcessor = new ChequeProcessor();

        if($chequeProcessor->estornarChequeRecebido($request, $id) != "OK|")
            return $chequeProcessor->getErrors();
        return "OK|";
    }
}
