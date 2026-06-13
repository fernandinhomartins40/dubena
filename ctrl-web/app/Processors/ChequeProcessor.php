<?php

namespace App\Processors;

use DB;
use Session;
use App\Chequerecebido;
use App\Contamovimento;
use App\Contafechamento;
use App\Financeiroparcela;
use App\Contatransferencia;
use App\Contamovimentotipo;
use App\Chequesituacaohistorico;
use App\Processors\caixaProcessor;
use App\Chequerecebidoencontrocontas;
use App\Chequeemitidoencontrocontas;
use App\Chequerecebidotransferencia;
use App\Services\CarbonCustom as Carbon;

class ChequeProcessor
{
    private $contatransferencia_id;
    private $errors = '';


    public function getContaTransferenciaId()
    {
        return $this->contatransferencia_id;
    }

    public function getSituacaoAnterior($cheque_id, $salto = 0)
    {
        $situacao_id = Chequesituacaohistorico::where('chequerecebido_id', $cheque_id)
            ->join('chequerecebidos recebido','chequesituacaohistoricos.chequerecebido_id','recebido.id')
            ->whereRaw('chequesituacaohistoricos.chequesituacao_id <> recebido.chequesituacao_id')
            ->orderBy('datahoraprocesso', 'DESC')->select('chequesituacaohistoricos.chequesituacao_id')
            ->limit(2 + $salto)->get()->pluck('chequesituacao_id');

        if ($situacao_id->count() > 0) {
            $situacao_id = $situacao_id->first();
            return (int) $situacao_id;
        }
        return 4;
    }

    public function insertChequeEncontrocontas($parcelas, $cheque, $tipoCheque)
    {
        if (count($parcelas) > 0) {
            foreach ($parcelas as $parcela_id) {
                $dados = [];
                $dados['financeiroparcela_id'] = $parcela_id;
                $dados['valortotal'] = Financeiroparcela::where('id', $parcela_id)->pluck('valorefetivado')->first();
                if($tipoCheque == 'emitido'){
                    $dados['chequeemitido_id'] = $cheque->id;
                    Chequeemitidoencontrocontas::create($dados);
                } else{
                    $dados['chequerecebido_id'] = $cheque->id;
                    Chequerecebidoencontrocontas::create($dados);
                }
            }
        } else {
            return "Nenhuma parcela foi encontrada para gerar o encontro de contas!";
        }
        return true;
    }

    public function getErrors($e = null)
    {
        if(is_null($e)) {
            return $this->errors;
        } else {
            $errors = '';
            if(isset($e->validator)){
                $errorsValidation = $e->validator->messages()->toArray();
                foreach ($errorsValidation as $errorsArray) {
                    $errors .= ' ' . implode(' ', $errorsArray);
                }
            } else {
                $errors = $e->getMessage();
            }
            return $errors;
        }
    }

    protected function setErrors($error)
    {
        $this->errors .= ' ' . $error;
        return;
    }

    public function estorno($parcelas, $motivo, $cheque = null)
    {
        $movtos = Array();

        foreach ($parcelas as $parc) {
            $mov = Contamovimento::where('financeiroparcela_id', $parc->id)
            ->where('pagarreceber', $parc->pagarreceber)
            ->orderBy('datahorabaixa', 'desc')
            ->with('fechamento')
            ->get();
            foreach($mov AS $m){
                array_push($movtos, $m);
            }
        }


        if(!is_null($cheque)){
            $transferencia = Chequerecebidotransferencia::whereRaw("chequerecebido_id = $cheque->id and "
                                        . " (tipotransferencia like '%TROCOTRANSF%' or tipotransferencia like '%ADIANTAMENTOTRANSF%')")->get()->first();

            if(!is_null($transferencia)){
                $numerocheque = $cheque->numerocheque;
                $contatransferencia_id = $transferencia->contatransferencia_id;

                $transf = Chequerecebidotransferencia::find($transferencia->id)->delete();

                $mov = Contamovimento::where('contatransferencia_id', $contatransferencia_id)->with('fechamento')->get()->first();

                $lancarfechado = $mov->fechamento->fechado;
                $caixaProcessor = new caixaProcessor($mov->conta_id);
                $caixaProcessor->setLancarFechado($lancarfechado);
                $caixaProcessor->setMotivoEstorno("Estorno de cheque nº $numerocheque");
                $caixaProcessor->estornarTransferenciaCaixa($mov);

                array_push($movtos, $mov);
            }
        }

        $contas = Array();

        foreach ($movtos as $movto) {
            if (!array_key_exists($movto->conta_id, $contas)) {
                $contas[$movto->conta_id] = [$movto];
            } else {
                array_push($contas[$movto->conta_id], $movto);
            }
        }
        foreach ($contas as $key => $value) {
            $lancarfechado = collect($value)->first()->fechamento->fechado;
            $processor = new caixaProcessor($key);
            $processor->setMovimentosEstorno($value);
            $processor->setMotivoEstorno($motivo);
            $processor->setLancarFechado($lancarfechado);
            if (!$processor->validarEstornoCaixa())
                throw new \Exception(implode(' ', $processor->getErrors()));
            if (!$processor->EstornarCaixa())
                throw new \Exception(implode(' ', $processor->getErrors()));
        }

        return true;
    }

    function baixarParcelasCheque($parcelas, $conta_id, $grupo_id, $cheque, $datahorabaixa, $pagarreceber)
    {
        $parcs = [];
        $movtos = [];
        $contamovimentotipo = Contamovimentotipo::where([
            ['cheque', 1],
            ['grupo_id', $grupo_id]
            ]);

        if($contamovimentotipo->get()->first() == null)
            return "Não existe um tipo de movimentação de conta do tipo cheque para sua empresa, vá até o cadastro de Tipos de Movimento de Contas e insira um registro.";

        $contamovimentotipo_id = $contamovimentotipo->where(DB::raw('upper(descricao)'), 'LIKE', '%CHEQUE%')->get()->first();

        if(is_null($contamovimentotipo_id))
            $contamovimentotipo_id = $contamovimentotipo->first();
        $contamovimentotipo_id = $contamovimentotipo_id->id;
        $totalDesc = 0;
        foreach ($parcelas as $parc) {
            array_push($parcs, $parc->financeiroparcela_id);
            $parcela = Financeiroparcela::find($parc->financeiroparcela_id);
            if($parcela->baixado)
                $parcela->update(['baixado' => 0]);
            $movto = new Contamovimento();
            $movto->financeiroparcela_id = $parc->financeiroparcela_id;
            $movto->datahorabaixa = $datahorabaixa;
            $movto->contamovimentotipo_id = $contamovimentotipo_id;
            $movto->pagarreceber = $pagarreceber;
            $movto->conta_id = $conta_id;
            $movto->valor = $parcela->valor;
            $movto->multa = $parcela->multa;
            $movto->juros = $parcela->juros;
            $movto->desconto = $parcela->desconto;
            $movto->valorefetivado = $parcela->valorefetivado;
            $movto->contatransferencia_id = null;
            // if($tipo === 'E'){
            // 	$movto->chequerecebido_id = null;
            // 	$movto->chequeemitido_id = $cheque->id;
            // } else {
            // 	$movto->chequeemitido_id = null;
            // 	$movto->chequerecebido_id = $cheque->id;
            // }
            $movto->origem ='CHQ';
            $movto->descricao = 'Baixado pelo cheque nº' . $cheque->numerocheque . ', conta nº: ' . $conta_id;
            $totalDesc += $parcela->desconto;
            array_push($movtos, $movto);
        }
        $processor = new caixaProcessor($conta_id);

        $contafechamento = Contafechamento::where('conta_id', $conta_id)
                    ->where('datahoraabertura', '<=', $datahorabaixa)
                    ->orderBy('datahoraabertura', 'DESC')
                    ->first();

        if (is_null($contafechamento)) {
            return "Conta/Caixa não está aberto ou não possui um fechamento, favor verificar e atualizar a página.";
        }

        $processor->setContaFechamento($contafechamento);

        $processor->setLancarFechado($contafechamento->fechado);
        $processor->setMovimentos($movtos);
        $processor->setValorTotal($cheque->valor);
        $processor->setValorDesconto($totalDesc);

        if(!$processor->validarBaixaTitulos())
            return implode(' ', $processor->getErrors());

        if (!$processor->baixarTitulos())
            return implode(' ', $processor->getErrors());

        return true;
    }

    public function transfereCaixaChequeRecebido($data, $tipo = 'troco', $chequedevolvido = false, $transfExtra = false)
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $grupo_id 	= Session::get('empresa_padrao')->grupo_id;

        $transf = new Contatransferencia();

        $config = Session::get("empresa_config");

        if($config == false)
            return 'As configurações ad empresa ainda não foram definidas para realizar esta ação!';

        if(isset($data['databaixa']) || isset($data['datadevolucao']))
            $datahoraprocesso = isset($data['databaixa']) ? $data['databaixa'] : $data['datadevolucao'];

        if(isset($data['datadeposito']) && !isset($datahoraprocesso))
            $datahoraprocesso = $data['datadeposito'];

        if (is_null($config->contadevolucaocheque) || is_null($config->contachecktroco))
                return "Acesse as configurações da empresa e defina a conta padrão para a o troco e devolução de cheque";

        if($chequedevolvido){
            $data['transferidoconta_id'] = $data['baixaconta_id'];
            $transf->destinoconta_id = $config->contadevolucaocheque;
        } else if($tipo == 'troco') {
            $transf->destinoconta_id = $config->contachecktroco;
        } else if($tipo == 'baixa') {
            $data['transferidoconta_id'] = $config->contachecktroco;
            $transf->destinoconta_id = $data['baixaconta_id'];
        } else if ($tipo == 'adiantamento') {
            $transf->destinoconta_id = $data['baixaconta_id'];
        } else if($tipo == 'segundabaixa') {
            $data['transferidoconta_id'] = $config->contadevolucaocheque;
            $transf->destinoconta_id = $data['baixaconta_id'];
        } else {
            $transf->destinoconta_id = $config->contadevolucaocheque;
        }

        // if($tipo === 'E'){
        // 	$transf->chequerecebido_id = null;
        // 	$transf->chequeemitido_id = $cheque->id;
        // } else {
        // 	$transf->chequeemitido_id = null;
        // 	$transf->chequerecebido_id = $cheque->id;
        // }

        $transf->origemconta_id = $data['transferidoconta_id'];
        $transf->datahoraprocesso = isset($datahoraprocesso) ? $datahoraprocesso : Carbon::now()->toDateTimeString();
        $transf->valor = $data['diferencavalor'];
        $transf->grupo_id = $grupo_id;
        $transf->empresa_id = $empresa_id;

        $contafechamentoOrigem = Contafechamento::where('conta_id', $transf->origemconta_id)
        ->where('datahoraabertura', '<=', $transf->datahoraprocesso)
        ->orderBy('datahoraabertura', 'DESC')
        ->first();

        $contafechamentoDestino = Contafechamento::where('conta_id', $transf->destinoconta_id)
        ->where('datahoraabertura', '<=', $transf->datahoraprocesso)
        ->orderBy('datahoraabertura', 'DESC')
        ->first();

        $processorOrigem = new caixaProcessor($transf->origemconta_id);
        $processorOrigem->setLancarFechado((int) $contafechamentoOrigem->fechado);
        $processorOrigem->setContaFechamento($contafechamentoOrigem);
        $processorOrigem->setLancarFechadoDestino((int) $contafechamentoDestino->fechado);
        $processorOrigem->setContaFechamentoDestino($contafechamentoDestino);

        if (!$processorOrigem->validarTransferenciaCaixa($transf))
            return implode(' ', $processorOrigem->getErrors());

        if (!$processorOrigem->transferirCaixa($transf, $data['numerocheque']))
            return implode(' ', $processorOrigem->getErrors());
        else
            $this->contatransferencia_id = $processorOrigem->getUltimaContaTransferencia();

        return true;
    }

    public function estornarChequeRecebido($request, $id)
    {
        $data = [];
        $cheque = Chequerecebido::find($id);
        $situacaoAnterior = $this->getSituacaoAnterior($id);
        $data['chequesituacao_id'] = $situacaoAnterior;
        $data['datapagamento'] = null;
        // dd($situacaoAnterior);
        $baixaconta_id = $cheque->baixaconta_id;
        if($cheque->datadevolucao == null)
            $data['baixaconta_id'] = null;
        if($cheque->chequesituacao_id == 5) {
            $cheque->update($data);
        } else if ($cheque->chequesituacao_id == 7) {
            $data['chequesituacao_id'] = 6;
            $cheque->update($data);
        } else {
            DB::beginTransaction();
            try {

                $cheque->update($data);
                $situacao = (int) $data['chequesituacao_id'];

                //Se o cheque não foi devolvido nenhuma vez, permite extornar a baixa direto, senão, permite estornar as transferências somente
                $estornarBaixa = $situacaoAnterior == 4 || is_null($cheque->datadevolucao);

                $transferencia = $cheque->chequeRecebidoTransferencia->whereNotIn('tipotransferencia', ['TROCO'])->sortByDesc('id')->first();
                $numerocheque = $cheque->numerocheque;

                if((!is_null($transferencia) && is_null($cheque->datadevolucao)) || (!$estornarBaixa)){

                    if(!$estornarBaixa) {
                        $mov = Contamovimento::where('conta_id', $baixaconta_id)->orderBy('id', 'DESC')->get()->first();
                    } else {
                        $contatransferencia_id = $transferencia->contatransferencia_id;
                        $transferencia->delete();
                        $mov = Contamovimento::where('contatransferencia_id', $contatransferencia_id)->orderBy('id', 'DESC')->get()->first();
                    }

                    if($mov !== null) {
                        $this->checkPermissiones($mov->conta_id);

                        $caixaProcessor = new caixaProcessor($mov->conta_id);
                        $caixaProcessor->setMotivoEstorno("Estorno de cheque nº $numerocheque");
                        $caixaProcessor->estornarTransferenciaCaixa($mov);
                    }
                }

                //Quando a parcela já foi baixada uma vez, só estorna a ultima transferência referente a essa baixa
                if($estornarBaixa) {
                    if($cheque->chequeRecebidoTransferencia->where('tipotransferencia', 'TROCO')->count() > 0) {
                        if(!$this->estornarAdiantamentoOuTroco($baixaconta_id, $numerocheque))
                            throw new \Exception($this->getErrors());
                    }
                    if(!is_null($cheque->adiantamentoconta_id)) {
                        if(!$this->estornarAdiantamentoOuTroco($cheque->adiantamentoconta_id, $numerocheque))
                            throw new \Exception($this->getErrors());
                    }
                    $motivo = $request->only('motivo')['motivo'];
                    $parcelas_id = $cheque->chequeRecebidoFinanceiro->pluck('financeiroparcela_id')->toArray();
                    $parcelas = Financeiroparcela::whereIn('id', $parcelas_id)->get();
                    $estorno = $this->estorno($parcelas, $motivo, $cheque->diferencavalor > 0 ? $cheque : null);

                    if(!is_bool($estorno))
                        throw new \Exception($estorno);

                    $parcelas_id = $cheque->chequeRecebidoEncontrocontas->pluck('financeiroparcela_id')->toArray();
                    $parcelas = Financeiroparcela::whereIn('id', $parcelas_id)->get();

                    $estorno = $this->estorno($parcelas, $motivo);
                    if(!is_bool($estorno))
                        throw new \Exception($estorno);
                }

                Chequesituacaohistorico::where('chequerecebido_id', $id)->orderBy('datahoraprocesso', 'DESC')->get()->first()->delete();
            } catch (\Exception $e) {
                DB::rollback();
                $this->setErrors($e->getMessage() . ". Line: " . $e->getLine() . " File:" . $e->getFile());
                return false;
            }
            DB::commit();
        }
        return "OK|";
    }

    private function estornarAdiantamentoOuTroco($conta_id, $numerocheque)
    {
        try {
            $mov = Contamovimento::where('conta_id', $conta_id)->orderBy('id', 'DESC')->get()->first();
            $caixaProcessor = new caixaProcessor($mov->conta_id);
            $caixaProcessor->setMotivoEstorno("Estorno de cheque nº $numerocheque");
            $caixaProcessor->estornarTransferenciaCaixa($mov);
        } catch (\Exception $e) {
            $this->setErrors($e->getMessage());
            return false;
        }
        return true;
    }

    public function insertChequeTransferencia($cheque_id, $contatransferencia_id, $tipo)
    {
        Chequerecebidotransferencia::create(['chequerecebido_id' => $cheque_id, 'contatransferencia_id' => $contatransferencia_id, 'tipotransferencia' => $tipo]);
    }

    public function insertHistoricoSituacao($cheque_id, $situacao_id)
    {
        Chequesituacaohistorico::create(['chequerecebido_id' => $cheque_id, 'chequesituacao_id' => $situacao_id, 'datahoraprocesso' => Carbon::now()]);
    }


    private function checkPermissiones($conta_id)
    {
        $config = Session::get('empresa_config');
        if(is_null($config))
            throw new \Exception("Não foi possível encontrar as configurações da empresa");
        $contas = \Auth::user()->contasAtivas()->with('conta')->where('empresa_id', $config->empresa_id)
                            ->where('conta_id', $config->contadevolucaocheque)
                            ->get()->first();
        if(is_null($contas))
            throw new \Exception("Não foi possível localizar a conta padrão para devolução de cheques");

        if($contas->toArray()['estornar'] == 0)
            throw new \Exception("Usuário não tem permissões para a conta: " . $contas->descricao);

        $contas = \Auth::user()->contasAtivas()->with('conta')->where('empresa_id', $config->empresa_id)
                            ->where('conta_id', $conta_id)
                            ->get()->first();
        if(is_null($contas))
            throw new \Exception("Não foi possível localizar a conta do estorno");

        if($contas->toArray()['estornar'] == 0)
            throw new \Exception("Usuário não tem permissões para estornar o caixa: " . $contas->descricao);
        return true;
    }
}
