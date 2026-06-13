<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Conta;
use App\Services\CarbonCustom as Carbon;
use App\Financeiro;
use App\Contatalao;
use App\Chequeemitido;
use App\Chequesituacao;
use App\Financeiroparcela;
use Illuminate\Http\Request;
use App\Chequeemitidofinanceiro;
use App\Processors\ChequeProcessor;

class ChequeemitidoController extends Controller
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

    public function index(Chequeemitido $cheq)
    {
        $this->authorize('view',$cheq);
        try {
            $chequesemitidos = Chequeemitido::with('conta', 'chequeEmitidoFinanceiro', 'chequeEmitidoEncontroContas')->where('empresa_id', Session::get('empresa_padrao')->id);
            $contas = $this->getContas();
            $chequesituacaos = Chequesituacao::where('chequeemitido', 1)->pluck('descricao', 'id')->prepend('Selecione', '');

            if (count($_GET) > 0) {
                $chequesemitidos = $this->getFiltrosCheque($_GET, $contas, $chequesituacaos, $chequesemitidos);
                if($chequesemitidos == false)
                    return $this->getErrors();
            }

            $chequesemitidos = $chequesemitidos->orderBy('dataemissao')->get();
        } catch (\Exception $e) {
            return getErrorsException($e);
        }
        return View('financeiro.cheque.emitido.chequeemitido', compact('chequesemitidos', 'contas', 'chequesituacaos'));
    }

    private function getFiltrosCheque($get, $contas, $chequesituacaos, $chequesemitidos)
    {
        $filters = $chequesemitidos;

        if (strlen($get['conta_id']) > 0)
            $filters = $filters->where('conta_id', $get['conta_id']);

        if (strlen($get['numerochequeinicial']) > 0)
            $filters = $filters->where('numerocheque', '>=' , $get['numerochequeinicial']);

        if (strlen($get['numerochequefinal']) > 0)
            $filters = $filters->where('numerocheque', '<=' , $get['numerochequefinal']);

        if (strlen($get['chequesituacao_id']) > 0)
            $filters = $filters->where('chequesituacao_id', $get['chequesituacao_id']);

        $datainicio = Carbon::parse(insertDataOracle($get['datainicio']))->startOfDay();
        $datafim = Carbon::parse(insertDataOracle($get['datafim']))->endOfDay();

        if ($get['tipodata'] != '0' && (empty($datainicio) || empty($datafim))) {
            $this->setErrors('Os campos de data não podem estar vazios!');
            return false;
        }
        switch ($get['tipodata']) {
            case '1':
                $filters = $filters->whereBetween('datavencimento', [$datainicio, $datafim]);
                break;
            case '2':
                $filters = $filters->whereBetween('datapagamento', [$datainicio, $datafim]);
                break;
            case '3':
                $filters = $filters->whereBetween('dataemissao', [$datainicio, $datafim]);
                break;
        }
        return $filters;
    }

    public function show($id, Chequeemitido $cheq)
    {
        $this->authorize('view',$cheq);
        $chequeemitido = Chequeemitido::find($id);
        $this->authorize('igualdade',$chequeemitido);
        $arrayParcelas_id = Chequeemitidofinanceiro::where('chequeemitido_id', $id)->pluck('financeiroparcela_id')->toArray();
        $parcelas = Financeiroparcela::whereIn('id', $arrayParcelas_id)->get();

        $chequesituacao = Chequesituacao::where('chequeemitido', 1)->pluck('descricao', 'id');
        $contas = \Auth::user()->contasAtivas()->with('conta')->where('empresa_id', Session::get('empresa_padrao')->id)
        ->where('operar', '=', 1)->get()->pluck('descricao', 'id')->prepend('Selecione','');

        return View('financeiro.cheque.emitido.chequeemitido_show', compact('chequeemitido', 'parcelas', 'contas', 'chequesituacao'));
    }

    public function create(Chequeemitido $cheq)
    {
        $this->authorize('create',$cheq);
        $variasParcelas = false;
        if(isset($_GET['qdeDuplicatas']) && $_GET['qdeDuplicatas'] > 1)
            $variasParcelas = true;
        $getParams = explode('&',\Request::getQueryString());

        $get = [];
        foreach ($getParams as $param) {
            $row = explode('=', $param);
            $get[$row[0]] = isset($row[1]) ? $row[1] : '';
        }

        $arrayParcelas_id = isset($get['parcelas']) ? urldecode($get['parcelas']) : '[]';
        $arrayParcelas_id = json_decode($arrayParcelas_id);
        $parcelas = Financeiroparcela::whereIn('id', $arrayParcelas_id)->get();
        foreach ($parcelas as $parcela) {
            $cond = $parcela->financeiro->condicaoPagamento;
            if(is_null($cond)) {
                return "Condição de pagamento não encontrada para a parcela $parcela->id";
            }
            if($cond->tipo == 2 || $cond->tipo == 3) {
                return "A parcela $parcela->id está com a condição de pagamento do tipo cartão e não pode ser vinculada a um cheque";
            }
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

        $financeiro_id = !is_null($parcelas->first()) ? $parcelas->first()->financeiro_id : '';

        $valorTotal = Financeiroparcela::whereIn('id', $arrayParcelas_id)->select(DB::raw('SUM(valorefetivado) as valor'))->get()->pluck('valor')->first();
        $valorTotal = !is_null($valorTotal) ? $valorTotal : 0;
        $valorLiquido = requestNumeroDecimalOracle($valorTotal - $valorCredito);
        $valorCredito = requestNumeroDecimalOracle($valorCredito);
        $valorTotal = requestNumeroDecimalOracle($valorTotal);
        $contas = $this->getContas();

        return View('financeiro.cheque.emitido.chequeemitido_form', compact('contas', 'variasParcelas', 'parcelas', 'valorTotal', 'financeiro_id', 'encontrocontas', 'valorCredito', 'parcelasContasPagar', 'valorLiquido'));
    }

    public function store(Request $request, Chequeemitido $cheq)
    {
        $this->authorize('create',$cheq);
        DB::beginTransaction();
        $this->validate($request, [
            'numerocheque' => 'required',
            'conta_id' => 'required'
        ]);
        try {
            $data = $request->all();
            $data = array_merge($data, $this->dadosExtras($data));

            $validarGravar = $this->validarGravar($data);

            if (!is_bool($validarGravar))
                throw new \Exception($validarGravar);

        } catch (\Exception $e) {
            DB::rollback();
            return getErrorsException($e);
        }
        DB::commit();
        return "OK|";
    }

    private function validarGravar($data)
    {
        $unicocheque = false;
        if(isset($data['unicocheque']) && $data['unicocheque'] == 1)
            $unicocheque = true;

        $parcelas = json_decode($data['parcelas']);

        $contaTalao = $this->getContaTalao($data['numerocheque'], $data['conta_id']);

        //função count() do php não funcionou para saber o tamanho desse array
        $count = 0;
        foreach ($parcelas as $parcela) {
            $count++;
        }

        if ($unicocheque) {
            $contaTalao = $contaTalao->get()->first();
        } else {
            $contaTalao = $contaTalao->where('chequenumfinal', '>=', $count + $data['numerocheque'])->get()->first();
        }

        if(is_null($contaTalao))
            throw new \Exception("Não foram encontrados cheques disponíveis para essa conta!");

        $numerochequeoriginal = $data['numerocheque'];
        $buscaCheque = $this->buscaChequeEmitidoPorNumero($contaTalao->id, $data['numerocheque']);
        $valorTotal = $data['valor'];
        $dadosChequeEmitidos = [];
        if(is_bool($buscaCheque)) {
            if($count > 0){
                foreach ($parcelas as $parcela) {
                    $data = array_merge($data, $this->buscaTalao($data['numerocheque'], $contaTalao, $unicocheque));
                    if (!$unicocheque) 
                        $data['numerocheque'] = $data['numerocheque'] - 1;

                    if(!$unicocheque)
                        $data['valor'] = insertNumeroDecimalOracle($parcela[4]);
                    else
                        $data['valor'] = insertNumeroDecimalOracle($valorTotal);

                    if(!isset($data['contatalao_id']))
                        throw new \Exception("Não foi encontrado nenhum cheque disponível iniciado com este número para essa conta!");

                    $financeiro_id = Financeiroparcela::find($parcela[0])->financeiro_id;
                    $data['financeiro_id'] = $financeiro_id;
                    if(!$unicocheque || ($unicocheque && !isset($chequeemitido))){
                        $chequeemitido = Chequeemitido::create($data);
                        if (isset($data['parcelasContasPagar'])) {
                            $parcelasContasPagar = json_decode($data['parcelasContasPagar']);
                            $insertChequeEncontrocontas = $this->insertChequeEncontrocontas($parcelasContasPagar, $chequeemitido, 'emitido');
                            if(!is_bool($insertChequeEncontrocontas)) {
                                throw new \Exception($insertChequeEncontrocontas);
                            }
                        }
                    }


                    $dadosChequeEmitidos = [
                        'financeiroparcela_id'  => $parcela[0],
                        'financeiro_id'         => $financeiro_id,
                        'numerocheque'          => $data['numerocheque'],
                        'chequeemitido_id'      => $chequeemitido->id
                    ];

                    if(!$unicocheque && $numerochequeoriginal + $count < $data['numerocheque'])
                        throw new \Exception("A sequencia de cheques disponíveis não é suficiente para gravar!");

                    Chequeemitidofinanceiro::create($dadosChequeEmitidos);
                }
            } else {
                throw new \Exception(" Não foram encontradas parcelas para pagar!");
            }
        } else {
            throw new \Exception(" O número de cheque já está sendo usando, tente outro número!");
        }

        return true;
    }

    private function buscaTalao($numerocheque, $contaTalao, $unicocheque)
    {
        $data = [];

        if(!$unicocheque){
            for ($i = $numerocheque; $i <= $contaTalao->chequenumfinal; $i++) {
                $chequeemitido = $this->buscaChequeEmitidoPorNumero($contaTalao->id, $numerocheque);

                $numerocheque++;
                $data['numerocheque'] = $numerocheque;

                // não mude
                if(is_bool($chequeemitido))
                    break;
            }
        } else {
            $chequeemitido = $this->buscaChequeEmitidoPorNumero($contaTalao->id, $numerocheque);

            if(!is_bool($chequeemitido))
                return [];
        }

        if(is_bool($chequeemitido) && $contaTalao->chequenumatual <= $contaTalao->chequenumfinal) {
            $data['contatalao_id'] = $contaTalao->id;
        }
        return $data;
    }

    private function buscaChequeEmitidoPorNumero($talao_id, $numerocheque)
    {
        $chequeemitido = Chequeemitido::where([
            ['numerocheque', $numerocheque],
            ['contatalao_id', $talao_id]
            ])->get()->first();
        if(is_null($chequeemitido))
            return true;
        return $chequeemitido;
    }

    private function dadosExtras($data)
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $grupo_id = Session::get('empresa_padrao')->grupo_id;

        $data['unicocheque'] = isset($data['unicocheque']) ? 1 : 0;
        $data['grupo_id'] = $grupo_id;
        $data['empresa_id'] = $empresa_id;

        if(DB::table('chequesituacaos')->find(1) == null)
            throw new \Exception("Não foi encontrado a situação de cheques cadastrado, contate a administração.");

        $data['chequesituacao_id'] = 1;
        $data['datacompetencia'] = Carbon::now();
        $data['dataemissao'] = insertDataOracle($data['dataemissao']);
        $data['datavencimento'] = insertDataOracle($data['datavencimento']);
        return $data;
    }

    private function getContas()
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        return Conta::where([
            ['contas.empresa_id', $empresa_id],
            ['contas.ativo', 1],
            ['contas.contatipo_id', 1]
            ])->join('contatalaos', 'contatalaos.conta_id', '=', 'contas.id')->select('contas.id as id', 'contas.descricao as descricao')->get()->unique('id')->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    public function editar($acao, $id, $data = null, Chequeemitido $cheq)
    {
        switch ($acao) {
            case 'cancelar':
                $this->authorize('excluir',$cheq);
                $chequeemitido = Chequeemitido::find($id);
                $this->authorize('igualdade',$chequeemitido);
                $chequeemitido->delete();
                return "OK|";
                break;
            case 'cancelarinutilizar':
                $chequeemitido = Chequeemitido::find($id);
                $this->authorize('igualdade',$chequeemitido);
                $chequeemitido->chequeEmitidoEncontroContas()->delete();
                $chequeemitido->update(['chequesituacao_id' => 3]);
                $this->authorize('create',$cheq);
                $chequeemitido->chequeEmitidoFinanceiro()->delete();
                $this->authorize('excluir',$cheq);
                return "OK|";
            case 'baixar':
                return $this->baixarCheque($id, $data);
                break;
            default:
                return "Operação não encontrada: " . $acao;
                break;
        }
    }
    public function inutilizarCheque($numerocheque, $conta_id, Chequeemitido $cheq)
    {
        $this->authorize('create',$cheq);
        try {
            $contatalao = $this->getContaTalao($numerocheque, $conta_id)->get()->first();

            if(is_null($contatalao))
                return 'Não foi encontrado nenhum cheque com este número nesta conta!';

            $chequeemitido = $this->buscaChequeEmitidoPorNumero($contatalao->id, $numerocheque);

            if(!is_bool($chequeemitido))
                return "Este cheque está sendo usado e não é possível inutiliza-lo!";
            else{
                $empresa_id = Session::get('empresa_padrao')->id;
                $grupo_id = Session::get('empresa_padrao')->grupo_id;
                $today = Carbon::today()->toDateString();
                $inutilizado = Chequeemitido::create([
                        'valor'                 => 0,
                        'grupo_id'              => $grupo_id,
                        'empresa_id'            => $empresa_id,
                        'datavencimento'        => $today,
                        'datacompetencia'       => $today,
                        'dataemissao'           => $today,
                        'contatalao_id'         => $contatalao->id,
                        'conta_id'              => $conta_id,
                        'chequesituacao_id'     => 1,
                        'numerocheque'          => $numerocheque
                    ]);
                $inutilizado->update(["chequesituacao_id" => 3]);
            }
        } catch (\Exception $e) {
            // return $e->getMessage() . ". Line: " . $e->getLine() . " File:" . $e->getFile();
            return getErrorsException($e);
        }
        return "OK|";
    }

    public function baixarCheque(Request $request, $id, Chequeemitido $cheq)
    {
        $this->authorize('baixar',$cheq);
        DB::beginTransaction();
        try {
            $chequeemitido = Chequeemitido::find($id);
            $this->authorize('igualdade',$chequeemitido);
            $grupo_id = Session::get('empresa_padrao')->grupo_id;
            $databaixa = insertDataOracle($request->only('databaixa')['databaixa']);
            $chequeemitido->update(['chequesituacao_id' => 2, 'datapagamento' => $databaixa]);
            $parcelas = $chequeemitido->chequeEmitidoFinanceiro;
            $conta_id = $chequeemitido->conta_id;
            $baixarParcelasCheque = $this->chequeProcessor->baixarParcelasCheque($parcelas, $conta_id, $grupo_id, $chequeemitido, $databaixa, "P");

            if(!is_bool($baixarParcelasCheque))
                return $baixarParcelasCheque;
            if(!is_null($chequeemitido->chequeEmitidoEncontrocontas->first())) {
                $parcelas_id = $chequeemitido->chequeEmitidoEncontrocontas->pluck('financeiroparcela_id')->toArray();
                $parcelas = Financeiroparcela::whereIn('id', $parcelas_id)->get();
                $baixaParcEnc = $this->chequeProcessor->baixarParcelasCheque($chequeemitido->chequeEmitidoEncontrocontas, $conta_id, $grupo_id, $chequeemitido, $databaixa, "R");
                if(!is_bool($baixaParcEnc))
                    return $baixaParcEnc;
            }

        } catch (\Exception $e) {
            DB::rollback();
            // return $e->getMessage() . ". Line: " . $e->getLine() . " File:" . $e->getFile();
            return getErrorsException($e);
        }
        DB::commit();
        return 'OK|';
    }

    public function estornarCheque(Request $request, $id, Chequeemitido $cheq, Financeiro $fin)
    {
        $this->authorize('viewUpdate',$cheq);
        $this->authorize('estornarPagamento',$fin);
        DB::beginTransaction();
        try {
            $chequeemitido = Chequeemitido::find($id);
            $this->authorize('igualdade',$chequeemitido);
            $chequeemitido->update(['chequesituacao_id' => 1, 'datapagamento' =>  null]);
            $parcelas_id = $chequeemitido->chequeEmitidoFinanceiro->pluck('financeiroparcela_id')->toArray();
            $parcelas = Financeiroparcela::whereIn('id', $parcelas_id)->get();

            $motivo = $request->only('motivo')['motivo'];

            $estorno = $this->chequeProcessor->estorno($parcelas, $motivo);
            if(!is_bool($estorno))
                return $this->chequeProcessor->getErrors();

            $parcelas_id = $chequeemitido->chequeEmitidoEncontrocontas->pluck('financeiroparcela_id')->toArray();
            $parcelas = Financeiroparcela::whereIn('id', $parcelas_id)->get();

            $estorno = $this->chequeProcessor->estorno($parcelas, $motivo);
            if(!is_bool($estorno))
                return $this->chequeProcessor->getErrors();

        } catch (\Exception $e) {
            DB::rollback();
            // echo $e->getMessage() . ". Line: " . $e->getLine() . " File:" . $e->getFile();
            echo getErrorsException($e);;
            return;
        }
        DB::commit();
        return "OK|";
    }

    private function getContaTalao($numerocheque, $conta_id)
    {
        return Contatalao::where([
            ['conta_id', $conta_id],
            ['chequenuminicial', '<=', $numerocheque],
            ['chequenumfinal', '>=', $numerocheque],
        ]);
    }
}
