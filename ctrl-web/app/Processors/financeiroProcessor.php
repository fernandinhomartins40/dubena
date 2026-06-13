<?php

namespace App\Processors;

use App\Financeiro;
use App\Financeiroparcela;
use App\Financeirorateio;
use App\Contamovimento;
use App\Contafechamento;
use Session;
use DB;
use App\Processors\caixaProcessor;
use Redirect;
use App\Services\CarbonCustom as Carbon;

class financeiroProcessor
{

    protected $financeiro;
    protected $rateios;
    protected $parcelas;
    protected $planoconta_id;
    protected $centrocusto_id;
    protected $datavencimento;
    protected $datahorabaixa;
    protected $documento;
    protected $baixar;
    protected $contamovimentotipo_id;
    protected $conta_id;
    protected $errors = Array();
    protected $tipo_retorno = 'redirect';  //REDIRECT ou TEXTO
    protected $tipo_cliente = '';
    protected $agrupar = false;
    protected $parcelas_origem = Array();
    protected $parcelas_baixadas = Array();
    protected $contafechamento_id;
    protected $descricao;
    protected $ofxuniqueid = null;

    public function __construct()
    {

    }

    public function setRecebimentoTipoId($id)
    {
        $this->contamovimentotipo_id = $id;
    }

    public function setContaId($id)
    {
        $this->conta_id = $id;
    }

    public function setContaFechamentoId($id)
    {
        $this->contafechamento_id = $id;
    }

    public function setAgrupar($agrupar)
    {
        $this->agrupar = $agrupar;
    }

    public function setParcelasOrigem($parcelas)
    {
        $this->parcelas_origem = $parcelas;
    }

    public function setParcelasBaixadas($parcelas)
    {
        $this->parcelas_baixadas = $parcelas;
    }

    public function addParcelasBaixadas($parcela)
    {
        array_push($this->parcelas_baixadas, $parcela);
    }

    public function getParcelasBaixadas()
    {
        return $this->parcelas_baixadas;
    }

    public function setTipoCliente($tipo)
    {
        $this->tipo_cliente = $tipo;
    }

    public function setRateios($rateios)
    {
        $this->rateios = $rateios;
    }

    public function getRateios()
    {
        return $this->rateios;
    }

    public function setParcelas($parcelas)
    {
        $this->parcelas = $parcelas;
    }

    public function getParcelas()
    {
        return $this->parcelas;
    }

    public function setFinanceiro($financeiro)
    {
        $this->financeiro = $financeiro;
    }

    public function getFinanceiro()
    {
        return $this->financeiro;
    }

    public function setCentrocustoId($id)
    {
        $this->centrocusto_id = $id;
    }

    public function getCentrocustoId()
    {
        return $this->centrocusto_id;
    }

    public function setPlanocontaId($id)
    {
        $this->planoconta_id = $id;
    }

    public function getPlanocontaId()
    {
        return $this->planoconta_id;
    }

    public function setDataVencimento($id)
    {
        $this->datavencimento = $id;
    }

    public function getDataVencimento()
    {
        return $this->datavencimento;
    }

    public function setDataPagamento($id)
    {
        $this->datahorabaixa = $id;
    }

    public function getDataPagamento()
    {
        return $this->datahorabaixa;
    }

    public function addError($error)
    {
        array_push($this->errors, $error);
    }

    public function setErrors($errors)
    {
        $this->errors = $errors;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function setBaixar($baixar)
    {
        $this->baixar = $baixar;
    }

    public function getBaixar()
    {
        return $this->baixar;
    }

    public function setTipoRetorno($tipo)
    {
        $this->tipo_retorno = $tipo;
    }

    public function getTipoRetorno()
    {
        return $this->tipo_retorno;
    }

    public function getContaFechamentoId()
    {
        return $this->contafechamento_id;
    }

    public function setDescricao($value)
    {
        $this->descricao = $value;
    }

    public function setDocumento($value)
    {
        $this->documento = $value;
    }

    public function setCartaoAutorizacao($value)
    {
        $this->cartaoautorizacao = $value;
    }

    public function setCartaoNsu($value)
    {
        $this->cartaonsu = $value;
    }

    public function getDescricao()
    {
        return $this->descricao;
    }

    public function setOfxuniqueid($value){
        $this->ofxuniqueid = $value;
    }

    public function getOfxuniqueid(){
        return $this->ofxuniqueid;
    }

    public function setFinanceiroRequest($request)
    {
        $data = $request->only('cliente_id', 'dataemissao', 'datacompetencia', 'datavencimento', 'planoconta_id', 'centrocusto_id', 'pagarreceber', 'descricao', 'documento', 'valor', 'contamovimentotipo_id', 'baixar', 'datahorabaixa', 'conta_id', 'origemAgrupar', 'parcelasOrigem', 'condicaopagamento_id', 'cartaonsu', 'cartaoautorizacao');


        $financeiro = new Financeiro();
        $financeiro->cliente_id = $data["cliente_id"];
        $financeiro->pagarreceber = $data["pagarreceber"];
        $financeiro->descricao = $data["descricao"];
        $financeiro->documento = $data["documento"];
        $financeiro->cartaonsu = $data["cartaonsu"];
        $financeiro->cartaoautorizacao = $data["cartaoautorizacao"];
        $financeiro->valor = insertNumeroDecimalOracle($data["valor"]);
        if (isset($data['condicaopagamento_id']) && $data['condicaopagamento_id'] != '') {
            $financeiro->condicaopagamento_id = $data['condicaopagamento_id'];
        }

        $financeiro->datacompetencia = null;
        if (strlen($data["datacompetencia"]) > 0) {
            $dt = explode('/', $data["datacompetencia"]);
            $financeiro->datacompetencia = $dt[2] . "-" . $dt[1] . "-" . $dt[0];
        }
        $financeiro->dataemissao = null;
        if (strlen($data["dataemissao"]) > 0) {
            $dt = explode('/', $data["dataemissao"]);
            $financeiro->dataemissao = $dt[2] . "-" . $dt[1] . "-" . $dt[0];
        }
        $datavencimento = null;
        if (strlen($data["datavencimento"]) > 0) {
            $datavencimento = insertDataOracle($data['datavencimento']);
        }
        $this->setDataVencimento($datavencimento);
        $datahorabaixa = null;

        if (strlen($data["datahorabaixa"]) > 0) {
            $datahorabaixa = Carbon::createFromFormat('d/m/Y H:i:s', $data["datahorabaixa"]);
        }
        $this->setDataPagamento($datahorabaixa);
        $financeiro->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $financeiro->empresa_id = Session::get('empresa_padrao')->id;
        $this->setFinanceiro($financeiro);
        $this->setCentrocustoId($data["centrocusto_id"]);
        $this->setPlanocontaId($data["planoconta_id"]);
        $this->conta_id = $data["conta_id"];
        $this->contamovimentotipo_id = $data["contamovimentotipo_id"];
        //PARA AGRUPAMENTO
        $this->setAgrupar($data["origemAgrupar"] != null);
        if ($this->agrupar)
            $this->setParcelasOrigem(explode(',', $data["parcelasOrigem"]));
    }

    public function setRateiosRequest($requestRateio)
    {
        $rateionovo = [];

        if ($requestRateio != null) {
            $rateio = json_decode($requestRateio);
            foreach ($rateio as $rat) {
                $ratn = new Financeirorateio();
                //$ratn["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                //$ratn["empresa_id"] = Session::get('empresa_padrao')->id;
                $ratn["centrocusto_id"] = $rat[0];
                $ratn["planoconta_id"] = $rat[2];
                $ratn["valor"] = insertNumeroDecimalOracle($rat[4]);
                $ratn["percentual"] = $ratn["valor"] / $this->getFinanceiro()->valor;
                //$ratn["pagarreceber"] = $this->getFinanceiro()->pagarreceber;
                array_push($rateionovo, $ratn);
            }
            if (count($rateionovo) == 0 && $this->getCentrocustoId() != '' && $this->getPlanocontaId() != '') {
                $ratn = new Financeirorateio();
                //$ratn["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                //$ratn["empresa_id"] = Session::get('empresa_padrao')->id;
                $ratn["centrocusto_id"] = $this->getCentrocustoId();
                $ratn["planoconta_id"] = $this->getPlanocontaId();
                $ratn["valor"] = $this->getFinanceiro()->valor;
                $ratn["percentual"] = 1;
                //$ratn["pagarreceber"] = $this->getFinanceiro()->pagarreceber;
                array_push($rateionovo, $ratn);
            }
            $this->setRateios($rateionovo);
        }
    }

    public function setParcelasRequest($requestParcelas)
    {
        $parcelasnovo = [];
        if ($requestParcelas != null) {
            $parcelas = json_decode($requestParcelas);
            $qt = 0;
            $datacompetencia = $this->getFinanceiro()->datacompetencia;
            $datavencimento = $this->getDataVencimento();
            $desconto = isset($parcelas->desconto) ? (float) insertNumeroDecimalOracle($parcelas->desconto) : 0;
            foreach ($parcelas->data as $parc) {
                if ($parc[1] > 0) {
                    $qt++;
                    if (strlen($parc[0]) > 0) {
                        $dt = explode('/', $parc[0]);
                        if (is_nan((int) $dt[0]))
                            throw new \Exception("Erro ao validar a data de parcelamento: " . $parc[0]);
                        $parc[0] = $dt[2] . "-" . $dt[1] . "-" . $dt[0];
                    }
                    $parcn = new Financeiroparcela();
                    $parcn["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                    $parcn["empresa_id"] = Session::get('empresa_padrao')->id;
                    $parcn["numero"] = $qt;
                    $parcn["datacompetencia"] = $this->getFinanceiro()->datacompetencia;
                    $parcn["datavencimento"] = $parc[0];
                    $parcn["datahorabaixa"] = null; //$datahorabaixa;
                    $parcn["valor"] = ($parc[1]);
                    $parcn["multa"] = 0;
                    $parcn["juros"] = 0;
                    $parcn["desconto"] = $desconto * $parc[2];
                    $parcn["valorefetivado"] = $parc[1] - ($desconto * $parc[2]);
                    $parcn["pagarreceber"] = $this->getFinanceiro()->pagarreceber;
                    $parcn["baixado"] = false;
                    array_push($parcelasnovo, $parcn);
                }
            }
            if (count($parcelasnovo) == 0 && $datavencimento != '') {
                $parcn = new Financeiroparcela();
                $parcn["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                $parcn["empresa_id"] = Session::get('empresa_padrao')->id;
                $parcn["numero"] = 1;
                $parcn["datacompetencia"] = $this->getFinanceiro()->datacompetencia;
                $parcn["datavencimento"] = $this->getDataVencimento();
                $parcn["datahorabaixa"] = null; //$datahorabaixa;
                $parcn["valor"] = $this->getFinanceiro()->valor;
                $parcn["multa"] = 0;
                $parcn["juros"] = 0;
                $parcn["desconto"] = $desconto;
                $parcn["valorefetivado"] = $this->getFinanceiro()->valor;
                $parcn["pagarreceber"] = $this->getFinanceiro()->pagarreceber;
                $parcn["baixado"] = false;
                array_push($parcelasnovo, $parcn);
            }
            $this->setParcelas($parcelasnovo);
        }
    }

    public function validar($request, $fromCaixa = false)
    {
        $retorno = true;

        $data = $request->only('cliente_id', 'dataemissao', 'datacompetencia', 'datavencimento', 'planoconta_id', 'centrocusto_id', 'pagarreceber', 'descricao', 'documento', 'valor', 'contamovimentotipo_id', 'baixar', 'datahorabaixa', 'conta_id', 'contafechamento_id', 'condicaopagamento_id');

        $this->baixar = $data["baixar"];
        if ($data["baixar"]) {
            if (strlen($data["datahorabaixa"]) == 0) {
                $this->addError('A data de pagamento deve ser informada.');
                $retorno = false;
            }
        }
        if (strlen($data["cliente_id"]) == 0) {
            $this->addError('O cliente/fornecedor deve ser informado.');
            $retorno = false;
        }
        if (strlen($data["dataemissao"]) == 0) {
            $this->addError('A data de emissao deve ser informada.');
            $retorno = false;
        }
        if (strlen($data["datacompetencia"]) == 0) {
            $this->addError('A data de competência deve ser informada.');
            $retorno = false;
        }
        if (strlen($data["valor"]) == 0) {
            $this->addError('O valor deve ser informado.');
            $retorno = false;
        }
        if (count($this->rateios) == 0) {
            $this->addError('Plano de contas/centro de custos e/ou rateio devem ser informados.');
            $retorno = false;
        }
        if (count($this->parcelas) == 0) {
            $this->addError('Data de vencimento e/ou parcelamento devem ser informados.');
            $retorno = false;
        }
        if (!$fromCaixa && $data['condicaopagamento_id'] == 0) {
            $this->addError('A condição de pagamento é obrigatória.');
            $retorno = false;
        }

        if ($this->baixar) {
            $processor = new caixaProcessor($this->conta_id);
            $contafechamento_id = $data["contafechamento_id"];
            $processor->setLancarFechado($contafechamento_id != -1);
            $contafechamento = null;
            if ($contafechamento_id != -1) {
                $contafechamento = Contafechamento::find($contafechamento_id);
            }
            $processor->setContaFechamento($contafechamento);

            if (!$processor->isUserAllowed()) {
                $this->addError('usuário não tem permissão para operar este caixa.');
                $retorno = false;
            }
            if (!$processor->getLancarFechado() && $processor->isCaixaFechado()) {
                $this->addError('Este caixa já está fechado.');
                $retorno = false;
            }
            if (!$processor->getLancarFechado() && $processor->isCaixaFechadoData($this->getDataPagamento())) {
                $this->addError('Este caixa já está fechado na data de pagamento informada.');
                $retorno = false;
            }
            if (!$this->validateDataBaixa($this->getDataPagamento())) {
                $this->addError('Lançamentos com datas futuras tem um limite de 2 minutos.');
                $retorno = false;
            }
            if ($processor->getLancarFechado()) {
                if (!$processor->isUserAllowedFechado()) {
                    $this->addError('usuário não tem permissão para operar caixa já fechado: ' . $processor->getConta()->descricao);
                    return false;
                }
                if ($this->getDataPagamento() < $processor->getContaFechamento()->datahoraabertura) {
                    $this->addError('Data/hora da baixa não está no intervalo desta abertura/fechamento de caixa: ' . $processor->getConta()->descricao);
                    return false;
                }
                if ($this->getDataPagamento() > $processor->getContaFechamento()->datahorafechamento) {
                    $this->addError('Data/hora da baixa não está no intervalo desta abertura/fechamento de caixa: ' . $processor->getConta()->descricao);
                    return false;
                }
            }
        }
        /*
          if ($this->agrupar) {
          $parcorigem = Financeiroparcela::whereIn('id', $this->parcelas_origem)->get();
          foreach ($parcorigem as $parcor) {
          if ($parcor->agrupamento_status == 1) {
          $this->addError('Parcela já agrupada não pode ser agrupada novamente.');
          $retorno = false;
          }
          }
          }
         */
        return $retorno;
    }

    public function gravar()
    {
        DB::beginTransaction();
        try {
            $this->financeiro->save();
            foreach ($this->parcelas as $parc) {
                $parc['financeiro_id'] = $this->financeiro->id;
                $parc['agrupamento_status'] = ($this->agrupar ? '1' : '0');
                $parc->save();
                if ($this->agrupar) {
                    $parcorigem = Financeiroparcela::whereIn('id', $this->parcelas_origem)->get();
                    foreach ($parcorigem as $parcor) {
                        $parcor['agrupamento_status'] = 2;
                        $parcor['agrupador_financeiro_id'] = $this->financeiro->id;
                        $parcor['baixado'] = true;
                        $parcor['valorefetivado'] = 0;
                        $parcor['datahorabaixa'] = Carbon::now();
                        $parcor->save();
                    }
                }
                if ($this->baixar) {
                    $movto = new Contamovimento();
                    $movto->financeiroparcela_id = $parc->id;
                    //$movto->grupo_id = Session::get('empresa_padrao')->grupo_id;
                    //$movto->empresa_id = Session::get('empresa_padrao')->id;
                    $movto->datahorabaixa = $this->getDataPagamento();
                    $movto->contamovimentotipo_id = $this->contamovimentotipo_id;
                    $movto->pagarreceber = $this->financeiro->pagarreceber;
                    $movto->conta_id = $this->conta_id;
                    $movto->valor = $parc["valor"];
                    $movto->multa = $parc["multa"];
                    $movto->juros = $parc["juros"];
                    $movto->desconto = $parc["desconto"];
                    $movto->valorefetivado = $parc["valorefetivado"];
                    $movto->contatransferencia_id = null;
                    $movto->origem = 'FIN';
                    $movto->descricao = null;
                    if($this->getOfxuniqueid()!=null){
                        $movto->ofxuniqueid = $this->getOfxuniqueid();
                    }
                    $processor = new caixaProcessor($this->conta_id);
                    $processor->setLancarFechado($this->getContaFechamentoId() != -1);
                    $processor->setMovimentos([$movto]);
                    $contafechamento = null;
                    if ($this->getContaFechamentoId() != -1) {
                        $contafechamento = Contafechamento::find($this->getContaFechamentoId());
                    }
                    $processor->setContaFechamento($contafechamento);
                    if (!$processor->validarBaixaTitulos()) {
                        DB::rollback();
                        $this->setErrors($processor->getErrors());
                    }
                    if (!$processor->receberCaixa($movto)) {
                        DB::rollback();
                        $this->setErrors($processor->getErrors());
                        return false;
                    }
                }
            }
            $this->financeiro->rateios()->delete();
            $this->financeiro->rateios()->saveMany($this->rateios);
        } catch (ValidationException $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        }
        DB::commit();
        if ($this->getTipoRetorno() != 'redirect') {
            return 'OK|';
        } else
            return true;
    }

    public function reparcelar()
    {
        DB::beginTransaction();
        try {
            //$this->financeiro->save();
            //$processor->setMovimentos([]);
            foreach ($this->parcelas as $parc) {
                $parc['financeiro_id'] = $this->financeiro->id;
                $parc['agrupamento_status'] = ($this->agrupar ? '1' : '0');
                $baixar = false;
                if ($parc['baixado']) {
                    $baixar = true;
                    $parc['baixado'] = false;
                }
                //$baixar = false;
                //$parc['baixado'] = false;
                //dd($parc);
                $parc->save();
                if ($this->agrupar) {
                    $parcorigem = Financeiroparcela::whereIn('id', $this->parcelas_origem)->get();
                    foreach ($parcorigem as $parcor) {
                        $parcor['agrupamento_status'] = 2;
                        $parcor['agrupador_financeiro_id'] = $this->financeiro->id;
                        $parcor['baixado'] = true;
                        $parcor['valorefetivado'] = 0;
                        $parcor['datahorabaixa'] = Carbon::now();
                        $parcor->save();
                    }
                }
                if ($baixar) {
                    $this->addParcelasBaixadas($parc->id);
                    $movto = new Contamovimento();
                    $movto->financeiroparcela_id = $parc->id;
                    //$movto->grupo_id = Session::get('empresa_padrao')->grupo_id;
                    //$movto->empresa_id = Session::get('empresa_padrao')->id;
                    $movto->datahorabaixa = $this->getDataPagamento();
                    $movto->contamovimentotipo_id = $this->contamovimentotipo_id;
                    $movto->pagarreceber = $this->financeiro->pagarreceber;
                    $movto->conta_id = $this->conta_id;
                    $movto->valor = $parc["valor"];
                    $movto->multa = $parc["multa"];
                    $movto->juros = $parc["juros"];
                    $movto->desconto = $parc["desconto"];
                    $movto->valorefetivado = $parc["valorefetivado"];
                    $movto->contatransferencia_id = null;
                    $movto->origem = 'FIN';
                    $movto->descricao = $this->descricao . ' (PARCIAL)';
                    $processor = new caixaProcessor($this->conta_id);
                    $processor->setMovimentos([$movto]);
                    if (!$processor->validarBaixaTitulos()) {
                        DB::rollback();
                        if ($this->getTipoRetorno() == 'redirect') {
                            return Redirect::back()
                                            ->withErrors(implode(' ', $processor->getErrors()))
                                            ->withInput();
                        } else {
                            foreach ($processor->getErrors() as $error) {
                                $this->addError($error);
                                return false;
                            }
                        }
                    }
                    if (!$processor->receberCaixa($movto)) {
                        DB::rollback();
                        if ($this->getTipoRetorno() == 'redirect') {
                            return Redirect::back()
                                            ->withErrors(implode(' ', $processor->getErrors()))
                                            ->withInput();
                        } else {
                            foreach ($processor->getErrors() as $error) {
                                $this->addError($error);
                                return false;
                            }
                        }
                    }
                }
            }
        } catch (ValidationException $e) {
            DB::rollback();
            if ($this->getTipoRetorno() == 'redirect') {
                return Redirect::back()
                                ->withErrors($e->getMessage())
                                ->withInput();
            } else {
                $this->addError($e->getMessage());
                return false;
            }
        } catch (\Exception $e) {
            //dd($this->getTipoRetorno());
            DB::rollback();
            if ($this->getTipoRetorno() == 'redirect') {
                return Redirect::back()
                                ->withErrors($e->getMessage())
                                ->withInput();
            } else {
                $this->addError($e->getMessage());
                return false;
            }
        }
        DB::commit();
        return true;
    }

    public function alterarDescricao()
    {
        DB::beginTransaction();
        try {
            foreach ($this->parcelas as $parc) {
                $financeiro = $parc->financeiro;
                if ($financeiro != null) {
                    if ($financeiro->condicaoPagamento->tipo == 2 || $financeiro->condicaoPagamento->tipo == 3) {
                        // if (is_null($this->cartaonsu) || empty($this->cartaonsu))
                        //     throw new \Exception("Informe o Nº Documento Cartão");
                        if (is_null($this->cartaoautorizacao) || empty($this->cartaoautorizacao))
                            throw new \Exception("Informe a Autorização");
                    }
                    $financeiro->descricao = $this->descricao;
                    $financeiro->cartaonsu = $this->cartaonsu;
                    $financeiro->cartaoautorizacao = $this->cartaoautorizacao;
                    $financeiro->documento = $this->documento;
                    $financeiro->save();
                }
                $parc->datavencimento = $this->getDataVencimento();
                $parc->save();
            }
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        }
        DB::commit();
        if ($this->getTipoRetorno() != 'redirect') {
            return 'OK|';
        } else
            return true;
    }

    public function cancelarParcelas($motivo)
    {
        DB::beginTransaction();
        try {
            foreach ($this->parcelas as $parcela) {
                $parcela->agrupamento_status = 3;
                $parcela->valorefetivado = 0;
                $parcela->baixado = true;
                $parcela->datahorabaixa = Carbon::now();
                $parcela->motivocancelamento = $motivo;
                $parcela->save();
            }
        } catch (ValidationException $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        }
        DB::commit();
        return true;
    }

    public function validarCancelamentoParcelas()
    {
        foreach ($this->parcelas as $parcela) {
            if ($parcela->baixado) {
                $this->addError('esta parcela já está baixada.');
                return false;
            }
        }
        return true;
    }

    public function cancelarFinanceiro($motivo)
    {
        DB::beginTransaction();
        try {
            foreach ($this->financeiro->parcelas as $parcela) {
                $parcela->agrupamento_status = 3;
                $parcela->valorefetivado = 0;
                $parcela->baixado = true;
                $parcela->datahorabaixa = Carbon::now();
                $parcela->motivocancelamento = $motivo;
                $parcela->save();
            }
        } catch (ValidationException $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        }
        DB::commit();
        return true;
    }

    public function validarCancelamentoFinanceiro()
    {
        foreach ($this->financeiro->parcelas as $parcela) {
            if ($parcela->baixado) {
                $this->addError('esta parcela já está baixada.');
                return false;
            }
        }
        return true;
    }

    //Processso de edicao do fechamento de convenio
    public function desagruparParcelas($motivocancelamento)
    {
        $parcelas_origem = $this->parcelas_origem;
        DB::beginTransaction();
        try {
            if (count($parcelas_origem) > 0) {
                $parcelas = Financeiroparcela::whereIn('id', $parcelas_origem)->get();
                foreach ($parcelas as $parcela) {
                    $parcela->valorefetivado = $parcela->valor;
                    $parcela->baixado = "0";
                    $parcela->agrupamento_status = "0";
                    $parcela->agrupador_financeiro_id = null;
                    $parcela->datahorabaixa = null;
                    $parcela->save();
                }
            }
            $cancelamento = $this->cancelarFinanceiro($motivocancelamento);
            if (!$cancelamento) {
                throw new \Exception($cancelamento);
            }
        } catch (\Exception $ex) {
            DB::rollback();
            $this->addError($ex->getMessage());
            return false;
        }
        DB::commit();
        return true;
    }

    private function validateDataBaixa($data)
    {
        $now = Carbon::now()->addMinute(2);
        $greaterOrEqual = $now->greaterThanOrEqualTo($data);

        return $greaterOrEqual;
    }

}
