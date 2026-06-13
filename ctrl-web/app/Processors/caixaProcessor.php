<?php

namespace App\Processors;

use App\Conta;
use App\Contafechamento;
use App\Contamovimento;
use App\Contamovimentoestorno;
use App\Financeiroparcela;
use DB;
use App\Condicaopagamento;
use App\Financeiro;
use App\Empresaconfig;
use App\Contamovimentotipo;
use App\Financeirorateio;
use App\Services\CarbonCustom as Carbon;

class caixaProcessor
{

    protected $conta;
    protected $data_fechamento;
    protected $data_abertura;
    protected $desconto;
    protected $multa;
    protected $juros;
    protected $valor_total;
    protected $movimentos;
    protected $movimentos_estorno;
    protected $motivo_estorno;
    protected $errors = Array();
    protected $movimento_gerado;
    protected $ultimo_fechamentoid;
    protected $cancelar = false;
    protected $lancarfechado = false;
    protected $lancarfechadodestino = null;
    protected $reopen = false;
    protected $contafechamento;
    protected $contafechamentodestino;
    protected $ultimacontatransferencia_id;

    public function getIsReopenCaixa()
    {
        return $this->reopen;
    }

    public function __construct($conta_id)
    {
        $this->conta = Conta::findOrFail($conta_id);
    }

    public function setUltimoFechamentoId($id)
    {
        $this->ultimo_fechamentoid = $id;
    }

    public function getUltimoFechamentoId()
    {
        return $this->ultimo_fechamentoid;
    }

    public function setMovimentoGerado($id)
    {
        $this->movimento_gerado = $id;
    }

    public function getMovimentoGerado()
    {
        return $this->movimento_gerado;
    }

    public function setDataFechamento($dt)
    {
        $this->data_fechamento = $dt;
    }

    public function getDataFechamento()
    {
        return $this->data_fechamento;
    }

    public function setDataAbertura($dt)
    {
        $this->data_abertura = $dt;
    }

    public function getDataAbertura()
    {
        return $this->data_abertura;
    }

    public function setValorDesconto($value)
    {
        $this->desconto = $value;
    }

    public function getValorDesconto()
    {
        return $this->desconto;
    }

    public function setValorMulta($value)
    {
        $this->multa = $value;
    }

    public function getValorMulta()
    {
        return $this->multa;
    }

    public function setValorJuros($value)
    {
        $this->juros = $value;
    }

    public function getValorJuros()
    {
        return $this->juros;
    }

    public function setValorTotal($value)
    {
        $this->valor_total = $value;
    }

    public function getValorTotal()
    {
        return $this->valor_total;
    }

    public function setMovimentos($value)
    {
        $this->movimentos = $value;
    }

    public function addMovimento($value)
    {
        array_push($this->movimentos, $value);
    }

    public function getMovimentos()
    {
        return $this->movimentos;
    }

    public function addError($error)
    {
        array_push($this->errors, $error);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function setMovimentosEstorno($movto)
    {
        $this->movimentos_estorno = $movto;
    }

    public function setMotivoEstorno($movto)
    {
        $this->motivo_estorno = $movto;
    }

    public function setCancelar($value)
    {
        $this->cancelar = $value;
    }

    public function setLancarFechado($value)
    {
        $this->lancarfechado = $value;
    }

    public function setLancarFechadoDestino($value)
    {
        $this->lancarfechadodestino = $value;
    }

    public function getLancarFechado()
    {
        return $this->lancarfechado;
    }

    public function getLancarFechadoDestino()
    {
        return $this->lancarfechadodestino;
    }

    public function setContaFechamento($value)
    {
        $this->contafechamento = $value;
    }

    public function getContaFechamento()
    {
        return $this->contafechamento;
    }

    public function getConta()
    {
        return $this->conta;
    }

    public function setContaFechamentoDestino($value)
    {
        $this->contafechamentodestino = $value;
    }

    public function getContaFechamentoDestino()
    {
        return $this->contafechamentodestino;
    }

    public function setUltimaContaTransferencia($value)
    {
        $this->ultimacontatransferencia_id = $value;
    }

    public function getUltimaContaTransferencia()
    {
        return $this->ultimacontatransferencia_id;
    }

    public function abrirCaixa()
    {
        $permitido = \Auth::user()->contas()->where('conta_id', $this->conta->id)->get();
        if (count($permitido) == 0) {
            $this->addError('usuário não tem permissão para este caixa.');
            return false;
        }
        if ($permitido->first()->operar != 1) {
            $this->addError('usuário não tem permissão para OPERAR este caixa.');
            return false;
        }
        //$conta = Conta::findOrFail($conta_id);
        if ($this->conta->fechado == 0) {
            $this->addError('este caixa já está aberto.');
            return false;
        }
        if ($this->isCaixaFechadoData($this->getDataAbertura())) {
            $this->addError('Já existe um fechamento de caixa após esta data.');
            return false;
        }
        $this->conta->fechado = 0;
        DB::beginTransaction();
        try {
            $this->conta->save();
            $fechamentos = Contafechamento::where('conta_id', $this->conta->id)
                    ->where('fechado', 0);
            $fechamentos->update(array("fechado" => 1));
            $fechamento = New Contafechamento();
            $fechamento["conta_id"] = $this->conta->id;
            $fechamento["datahoraabertura"] = $this->getDataAbertura();
            $fechamento["saldoinicial"] = $this->conta->saldoatual;
            $fechamento["datahorafechamento"] = null;
            $fechamento["saldofinal"] = null;
            $fechamento["fechado"] = 0;
            $fechamento["abertura_user_id"] = \Auth::user()->id;
            $fechamento->save();
            $this->setUltimoFechamentoId($fechamento->id);
            $this->setContaFechamento($fechamento);
        } catch (ValidationException $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        }
        DB::commit();
        return true;
    }

    public function isCaixaFechado()
    {
        return $this->conta->fechado == 1;
    }

    public function isCaixaFechadoData($datahorabaixa, $conta_id = null)
    {
        $fechamentos = Contafechamento::where('datahorafechamento', '>=', $datahorabaixa);
        if (is_null($conta_id))
            $fechamentos->where('conta_id', $this->conta->id);
        else
            $fechamentos->where('conta_id', $conta_id);
        $fechamentos = $fechamentos->get();
        if (count($fechamentos) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function isUserAllowed()
    {
        $permitido = \Auth::user()->contas()->where('conta_id', $this->conta->id)->get();
        if (count($permitido) == 0) {
            return false;
        }
        if ($permitido->first()->operar != 1) {
            return false;
        }
        return true;
    }

    public function isUserAllowedEstornar()
    {
        $permitido = \Auth::user()->contas()->where('conta_id', $this->conta->id)->get();
        if (count($permitido) == 0) {
            return false;
        }
        if ($permitido->first()->estornar != 1) {
            return false;
        }
        return true;
    }

    public function isUserAllowedTransfer()
    {
        $permitido = \Auth::user()->contas()->where('conta_id', $this->conta->id)->get();
        if (count($permitido) == 0) {
            return false;
        }
        if ($permitido->first()->transferir != 1) {
            return false;
        }
        return true;
    }

    public function isUserAllowedFechado()
    {
        $permitido = \Auth::user()->contas()->where('conta_id', $this->conta->id)->get();
        if (count($permitido) == 0) {
            return false;
        }
        if ($permitido->first()->lancarfechado != 1) {
            return false;
        }
        return true;
    }

    public function fecharCaixa()
    {
        if (!$this->isUserAllowed()) {
            $this->addError('usuário não tem permissão para operar e/ou transferir este caixa.');
            return false;
        }
        //$conta = Conta::findOrFail($conta_id);
        if ($this->isCaixaFechado()) {
            $this->addError('este caixa já está fechado.');
            return false;
        }
        if ($this->isCaixaFechadoData($this->getDataFechamento())) {
            $this->addError('já existe um fechamento anterior a esta data/hora.');
            return false;
        }

        $this->conta->fechado = 1;
        DB::beginTransaction();
        try {
            $this->conta->save();
            $fechamentos = Contafechamento::where('conta_id', $this->conta->id)
                    ->where('fechado', 0);
            $id = $fechamentos->first()->id;

            $operator = ">=";
            $raw = "(datahorabaixa :operator TO_DATE('" . $this->getDataFechamento() . "', 'yyyy-mm-dd hh24:mi:ss') "
                    . " AND (CONTATRANSFERENCIA_ID IS NOT NULL OR FINANCEIROPARCELA_ID IS NOT NULL))";
            $selectRaw = "SUM(CASE WHEN PAGARRECEBER = 'R' THEN valorefetivado * 1 ELSE valorefetivado * -1 end) as valorefetivado";

            $movs = Contamovimento::where('conta_id', $this->conta->id)
                    ->where('contafechamento_id', $id)
                    ->whereRaw(str_replace(':operator', $operator, $raw))
                    ->selectRaw($selectRaw);

            if (is_null($movs->first()) || (!is_null($movs->first()) && $movs->first()->valorefetivado == 0)) {
                $novoSaldo = $this->conta->saldoatual;
                $hasApos = false;
            } else {
                throw new \Exception("A conta possui lançamento posterior a data do fechamento!");
                // $hasApos = true;
                // $valueMovsAfter = $movs->first()->valorefetivado;
                // //no caso do saldo atual da conta ser negativo, faz movimentações
                // //opostas para o novo saldo final do fechamento não ficar errado
                // if ($this->conta->saldoatual < 0) {
                //     if ($valueMovsAfter >= 0)
                //         $novoSaldo = $this->conta->saldoatual + $valueMovsAfter;
                //     else
                //         $novoSaldo = $this->conta->saldoatual - $valueMovsAfter;
                // } else {
                //     if ($valueMovsAfter >= 0)
                //         $novoSaldo = $this->conta->saldoatual - $valueMovsAfter;
                //     else
                //         $novoSaldo = $this->conta->saldoatual + $valueMovsAfter;
                // }
            }
            $novoSaldo = sprintf('%0.2f', $novoSaldo);

            $fechamentos->update(array("fechado"            => 1,
                "datahorafechamento" => $this->getDataFechamento(),
                "fechamento_user_id" => \Auth::user()->id,
                "saldofinal"         => $novoSaldo));

            $this->setUltimoFechamentoId($id);
            if ($hasApos) {
                $this->reopen = true;
                $processor = new caixaProcessor($this->conta->id);

                if (is_string($this->getDataFechamento()))
                    $strDate = Carbon::parse($this->getDataFechamento())->format('Y-m-d H:i') . ':00';
                else
                    $strDate = $this->getDataFechamento()->format('Y-m-d H:i') . ':00';

                $date = Carbon::parse($strDate)->addMinute();
                $processor->setDataAbertura($date);
                if ($processor->abrirCaixa()) {
                    $movs->update(['contafechamento_id' => $processor->getUltimoFechamentoId()]);
                    $processor->getContaFechamento()->update(['saldoinicial' => $novoSaldo]);
                } else {
                    DB::rollback();
                    $this->addError(implode(' ', $processor->getErrors()));
                    return false;
                }
            }
        } catch (ValidationException $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        }
        DB::commit();
        return true;
    }

    public function validarBaixaTitulos()
    {
        if (!$this->isUserAllowed()) {
            $this->addError('usuário não tem permissão para operar e/ou transferir este caixa.');
            return false;
        }
        if (!$this->getLancarFechado() && $this->isCaixaFechado()) {
            $this->addError('este caixa já está fechado.');
            return false;
        }

        if (!is_null($this->getContaFechamentoDestino())) {
            $conta = \Auth::user()->contas()->where('conta_id', $this->getContaFechamentoDestino()->conta_id)->get();

            if (count($conta) == 0 || $conta->first()->operar == 0) {
                $this->addError('usuário não tem permissão para operar caixa: ' . $this->getContaFechamentoDestino()->conta->descricao);
                return false;
            }

            if (!$this->getLancarFechadoDestino() && $this->getContaFechamentoDestino()->fechado == 1) {
                $this->addError('este caixa já está fechado.');
                return false;
            }
        }

        foreach ($this->movimentos as $movto) {
            if (!$this->getLancarFechado() && $this->isCaixaFechadoData($movto->datahorabaixa)) {
                $this->addError('caixa já está fechado na data/hora.');
                return false;
            }
            $parcela = Financeiroparcela::findOrFail($movto->financeiroparcela_id);

            if ($parcela != null) {
                if ($parcela->baixado) {
                    $this->addError('esta parcela já está baixada.');
                    return false;
                }
            }

            if ($this->getLancarFechado()) {
                if (!$this->isUserAllowedFechado()) {
                    $this->addError('usuário não tem permissão para operar caixa já fechado: ' . $this->conta->descricao);
                    return false;
                }
                if ($movto->datahorabaixa < $this->getContaFechamento()->datahoraabertura) {
                    $this->addError('Data/hora da baixa não está no intervalo desta abertura/fechamento de caixa: ' . $this->conta->descricao);
                    return false;
                }
                if ($movto->datahorabaixa > $this->getContaFechamento()->datahorafechamento) {
                    $this->addError('Data/hora da baixa não está no intervalo desta abertura/fechamento de caixa: ' . $this->conta->descricao);
                    return false;
                }
            }

            if ($this->getLancarFechadoDestino() && !is_null($this->getContaFechamentoDestino())) {
                if ($this->getLancarFechado()) {

                    if (count($conta) == 0 || $conta->first()->operar == 0) {
                        $this->addError('usuário não tem permissão para operar caixa: ' . $this->getContaFechamentoDestino()->conta->descricao);
                        return false;
                    }
                    if ($movto->datahorabaixa < $this->getContaFechamentoDestino()->datahoraabertura) {
                        $this->addError('Data/hora da baixa não está no intervalo desta abertura/fechamento de caixa: ' . $this->getContaFechamentoDestino()->conta->descricao);
                        return false;
                    }
                    if ($movto->datahorabaixa > $this->getContaFechamentoDestino()->datahorafechamento) {
                        $this->addError('Data/hora da baixa não está no intervalo desta abertura/fechamento de caixa: ' . $this->getContaFechamentoDestino()->conta->descricao);
                        return false;
                    }
                }
            }
        }
        return true;
    }

    public function receberCaixa($movto)
    {
        $parcela = Financeiroparcela::with('financeiro.condicaoPagamento')->findOrFail($movto->financeiroparcela_id);
        $parcela->baixado = true;
        if ($this->cancelar) {
            //$parcela->cancelamento_user_id = \Auth::user()->id;
            $parcela->agrupamento_status = 3;
        }
        $parcela->multa = $movto->multa;
        $parcela->juros = $movto->juros;
        $parcela->desconto = $movto->desconto;
        $parcela->valorefetivado = $movto->valorefetivado;
        $parcela->datahorabaixa = $movto->datahorabaixa;
        if ($movto->descricao == '' || $movto->descricao == null) {
            $movto->descricao = $parcela->financeiro->descricao;
        } else if ($movto->descricao == 'PARCIAL') {
            $movto->descricao = $parcela->financeiro->descricao . ' (PARCIAL)';
        }
        DB::beginTransaction();
        try {
            $parcela->update();

            if ($parcela->pagarreceber == 'R') {
                $pgto = $parcela->financeiro->condicaoPagamento;
                if (is_null($pgto)) {
                    $this->addError("Condição de pagamento da parcela: $parcela->id não encontrada.");
                    return false;
                }
                if (is_null($pgto)) {
                    $this->addError("Condição de pagamento da parcela: $parcela->id não possui fornecedor correspondente.");
                    return false;
                }
                if (($pgto->tipo == 2 || $pgto->tipo == 3) && !is_null($pgto->fornecedor_id)) {
                    $processor2 = new caixaProcessor($movto->conta_id);
                    $cp_cartao = $processor2->createCPCartao($parcela, $pgto, $movto);
                    if (!is_int($cp_cartao))
                        return false;
                    $parcela->update(['financeirotaxa_id' => $cp_cartao]);
                }
            }

            if (!$this->movimentarCaixa($movto)) {
                return false;
            }
        } catch (ValidationException $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        }
        DB::commit();
        return true;
    }

    /*
     *
     * cria um contas a pagar com o valor dos juros quando a condição de pagamento é cartão
     *
     */

    private function createCPCartao($parcela, $pgtoParcela, $movto)
    {
        $datahorabaixa = $movto->datahorabaixa;
        $conta_id = $movto->conta_id;
        $financeiroProcessor = new financeiroProcessor();

        $valorTaxa = ($pgtoParcela->taxa / 100) * ($parcela->valorefetivado);
        $pgtoVista = Condicaopagamento::where('tipo', 0)->where('ativo', true)
                        ->whereRaw("grupo_id IN(SELECT grupo_id FROM EMPRESAS WHERE ID = $parcela->empresa_id)")->get()->first();
        if (is_null($pgtoVista)) {
            $this->addError("Não foi encontrada uma condição de pagamento à vista para gerar a parcela de multa do cartão!");
            return false;
        }

        if (is_null($pgtoParcela->fornecedor_id)) {
            $this->addError("Não foi encontrado um fornecedor pra a condição de pagamento selecionada!");
            return false;
        }
        $financeiro = new Financeiro();
        $financeiro->cliente_id = $pgtoParcela->fornecedor_id;
        $financeiro->pagarreceber = 'P';
        $financeiro->descricao = 'Taxa cartão, parcela: ' . $parcela->id;
        $financeiro->documento = "TX" . $parcela->id . "-" . $parcela->financeiro_id;
        $financeiro->valor = $valorTaxa;
        $financeiro->datacompetencia = $parcela->financeiro->datacompetencia;
        $financeiro->dataemissao = $parcela->financeiro->dataemissao;
        $financeiro->grupo_id = $parcela->grupo_id;
        $financeiro->empresa_id = $parcela->empresa_id;
        $financeiro->condicaopagamento_id = $pgtoVista->id;
        $financeiroProcessor->setFinanceiro($financeiro);

        $config = Empresaconfig::where('empresa_id', $parcela->empresa_id)->select('pccartao_id', 'cccartao_id')->get()->first();
        if (is_null($config)) {
            $this->addError('Por favor, informe o Centro de Custos e Plano de Contas nas configurações da empresa!');
            return false;
        }

        $rateio = [];
        $ratn = new Financeirorateio();

        if (strlen($config->pccartao_id) > 0 && strlen($config->cccartao_id) > 0) {
            $ratn["centrocusto_id"] = $config->cccartao_id;
            $ratn["planoconta_id"] = $config->pccartao_id;
        } else {
            $this->addError('Por favor, informe o Centro de Custos e Plano de Contas nas configurações da empresa!');
            return false;
        }

        $ratn["valor"] = $valorTaxa;
        $ratn["financeiro_id"] = $financeiroProcessor->getFinanceiro()->id;
        $ratn["percentual"] = 1;
        array_push($rateio, $ratn);
        $financeiroProcessor->setRateios($rateio);

        $parcelasnovo = [];

        $parcn = new Financeiroparcela();
        $parcn["grupo_id"] = $parcela->grupo_id;
        $parcn["empresa_id"] = $parcela->empresa_id;
        $parcn["numero"] = 1;
        $parcn["datacompetencia"] = $parcela->datacompetencia;
        $parcn["datavencimento"] = $parcela->datavencimento;
        $parcn["datahorabaixa"] = null;
        $parcn["valor"] = $valorTaxa;
        $parcn["multa"] = 0;
        $parcn["juros"] = 0;
        $parcn["desconto"] = 0;
        $parcn["valorefetivado"] = $valorTaxa;
        $parcn["pagarreceber"] = "P";
        $parcn["baixado"] = false;
        array_push($parcelasnovo, $parcn);

        $financeiroProcessor->setParcelas($parcelasnovo);
        $gravar = $financeiroProcessor->gravar();

        if (!$gravar) {
            foreach ($financeiroProcessor->getErrors() as $error) {
                $this->addError($error);
            }
            return false;
        }

        $parcelas = Financeiroparcela::where('financeiro_id', $financeiroProcessor->getFinanceiro()->id)->get();

        $movtos = [];
        $contamovimentotipo = Contamovimentotipo::where([
                    ['cartao', 1],
                    ['grupo_id', $parcela->grupo_id]
                ])->whereIn('pagarreceber', ["A", "P"])->get()->first();

        if ($contamovimentotipo == null) {
            $this->addError("Não existe um tipo de movimentação de conta do tipo cartão para sua empresa, vá até o cadastro de Tipos de Movimento de Contas e insira um registro.");
            return false;
        }

        $contamovimentotipo_id = $contamovimentotipo->id;
        foreach ($parcelas as $parc) {
            $movto = new Contamovimento();
            $movto->financeiroparcela_id = $parc->id;
            $movto->datahorabaixa = $datahorabaixa;
            $movto->contamovimentotipo_id = $contamovimentotipo_id;
            $movto->pagarreceber = "P";
            $movto->conta_id = $conta_id;
            $movto->valor = $valorTaxa;
            $movto->multa = 0;
            $movto->juros = 0;
            $movto->desconto = 0;
            $movto->valorefetivado = $valorTaxa;
            $movto->contatransferencia_id = null;
            $movto->origem = 'BAI';
            $movto->descricao = 'Baixado, conta nº: ' . $conta_id;
            array_push($movtos, $movto);
        }
        $processor = new caixaProcessor($conta_id);

        $contafechamento = Contafechamento::where('conta_id', $conta_id)
                ->where('datahoraabertura', '<=', $datahorabaixa)
                ->orderBy('datahoraabertura', 'DESC')
                ->first();

        $processor->setContaFechamento($contafechamento);

        $fechado = !is_null($contafechamento) ? $contafechamento->fechado : false;
        $processor->setLancarFechado($fechado);
        $processor->setMovimentos($movtos);
        $processor->setValorTotal($valorTaxa);

        if (!$processor->validarBaixaTitulos())
            throw new \Exception(implode(' ', $processor->getErrors()));

        if (!$processor->baixarTitulos())
            throw new \Exception(implode(' ', $processor->getErrors()));

        return (int) $financeiroProcessor->getFinanceiro()->id;
    }

    public function validarTransferenciaCaixa($transf)
    {
        if (!$this->isUserAllowedTransfer()) {
            $this->addError('usuário não tem permissão para transferir caixa: ' . $this->conta->descricao);
            return false;
        }
        if (!$this->getLancarFechado() && $this->isCaixaFechado()) {
            $this->addError('caixa já está fechado: ' . $this->conta->descricao);
            return false;
        }
        if (!$this->getLancarFechado() && $this->isCaixaFechadoData($transf->datahoraprocesso)) {
            $this->addError('já existe um fechamento anterior a data/hora de transferência: ' . $this->conta->descricao);
            return false;
        }
        if ($this->getLancarFechado()) {
            if (!$this->isUserAllowedFechado()) {
                $this->addError('usuário não tem permissão para operar caixa já fechado: ' . $this->conta->descricao);
                return false;
            }
            if ($transf->datahoraprocesso < $this->getContaFechamento()->datahoraabertura) {
                $this->addError('Data/hora da transferência não está no intervalo desta abertura/fechamento de caixa: ' . $this->conta->descricao);
                return false;
            }
            if ($transf->datahoraprocesso > $this->getContaFechamento()->datahorafechamento) {
                $this->addError('Data/hora da transferência não está no intervalo desta abertura/fechamento de caixa: ' . $this->conta->descricao);
                return false;
            }
        }
        if (!is_null($this->getContaFechamentoDestino()) && !is_null($this->getContaFechamentoDestino())) {
            if (!isset($this->getContaFechamentoDestino()->conta_id)) {
                $this->addError('Não foi possível encontrar a conta de destino');
                return false;
            }
            $conta = \Auth::user()->contas()->where('conta_id', $this->getContaFechamentoDestino()->conta_id)->get();

            if (count($conta) == 0 || $conta->first()->transferir == 0) {
                $this->addError('usuário não tem permissão para transferir caixa: ' . $this->getContaFechamentoDestino()->conta->descricao);
                return false;
            }
            if (!$this->getLancarFechadoDestino() && $this->getContaFechamentoDestino()->fechado == 1) {
                $this->addError('caixa já está fechado: ' . $this->getContaFechamentoDestino()->descricao);
                return false;
            }
            if (!$this->getLancarFechadoDestino() && $this->isCaixaFechadoData($transf->datahoraprocesso,
                            $this->getContaFechamentoDestino()->conta->id)) {
                $this->addError('já existe um fechamento anterior a data/hora de transferência: ' . $this->getLancarFechadoDestino()->conta->descricao);
                return false;
            }

            if ($this->getLancarFechadoDestino()) {
                if (count($conta) == 0 || $conta->first()->lancarfechado == 0) {
                    $this->addError('usuário não tem permissão para operar caixa já fechado: ' . $this->getContaFechamentoDestino()->conta->descricao);
                    return false;
                }

                if ($transf->datahoraprocesso < $this->getContaFechamentoDestino()->datahoraabertura) {
                    $this->addError('Data/hora da transferência não está no intervalo desta abertura/fechamento de caixa: ' . $this->getContaFechamentoDestino()->conta->descricao);
                    return false;
                }
                if ($transf->datahoraprocesso > $this->getContaFechamentoDestino()->datahorafechamento) {
                    $this->addError('Data/hora da transferência não está no intervalo desta abertura/fechamento de caixa: ' . $this->getContaFechamentoDestino()->conta->descricao);
                    return false;
                }
            }
        }
        return true;
    }

    public function transferirCaixa($transf, $numerocheque = null)
    {
        DB::beginTransaction();
        try {
            $contad = Conta::find($transf->destinoconta_id);
            $transf->save();
            $this->setUltimaContaTransferencia($transf->id);
            $movtod = new Contamovimento();
            $movtod->financeiroparcela_id = null;
            $movtod->contatransferencia_id = $transf->id;
            $movtod->datahorabaixa = $transf->datahoraprocesso;
            $movtod->pagarreceber = 'R';
            $movtod->conta_id = $transf->destinoconta_id;
            $movtod->valorefetivado = $transf->valor;
            $movtod->contafechamento_id = $transf->contafechamento_id;
            $movtod->multa = 0;
            $movtod->juros = 0;
            $movtod->desconto = 0;
            $movtod->valor = $transf->valor;
            $movtod->origem = 'TRA';
            $movtod->descricao = 'TRANSFERÊNCIA RECEBIDA DA CONTA ' . $this->conta->descricao;
            if ($numerocheque != null)
                $movtod->descricao .= ', cheque nº' . $numerocheque;
            if (!$this->movimentarCaixa($movtod))
                return false;

            $movto = new Contamovimento();
            $movto->financeiroparcela_id = null;
            $movto->contatransferencia_id = $transf->id;
            $movto->datahorabaixa = $transf->datahoraprocesso;
            $movto->pagarreceber = 'P';
            $movto->conta_id = $transf->origemconta_id;
            $movto->valor = $transf->valor;
            $movto->multa = 0;
            $movto->juros = 0;
            $movto->desconto = 0;
            $movto->contafechamento_id = $transf->contafechamento_id;
            $movto->valorefetivado = $transf->valor;
            $movto->origem = 'TRA';
            $movto->descricao = 'TRANSFERENCIA PARA ' . $contad->descricao;
            if ($numerocheque != null)
                $movto->descricao .= ', cheque nº' . $numerocheque;
            if (!$this->movimentarCaixa($movto))
                return false;
        } catch (ValidationException $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        }
        DB::commit();
        return true;
    }

    public function movimentarCaixa($movto)
    {
        if (is_null($this->getLancarFechadoDestino()))
            $this->setLancarFechadoDestino($this->getLancarFechado());

        $conta = Conta::findOrFail($movto->conta_id);
        $fechamento = Contafechamento::where('conta_id', $movto->conta_id)
                        ->where('fechado', 0)->first();
        if (($movto->pagarreceber == 'P' && $this->getLancarFechado()) || ($movto->pagarreceber == 'R' && $this->getLancarFechadoDestino())) {
            if ($movto->origem == 'TRA') {
                if ($movto->pagarreceber == 'P') {
                    $fechamento = $this->getContaFechamento();
                } else {
                    $fechamento = $this->getContaFechamentoDestino();
                }
            } else {
                $fechamento = $this->getContaFechamento();
            }
        }
        DB::beginTransaction();
        try {
            $movto['user_id'] = \Auth::user()->id;

            $movto['contafechamento_id'] = $fechamento->id;
            if ($movto->pagarreceber == 'P')
                $movto['retroativo'] = $this->getLancarFechado();
            else
                $movto['retroativo'] = $this->getLancarFechadoDestino();

            $movto->save();

            $this->setMovimentoGerado($movto->id);

            $novoSaldo = sprintf('%0.2f',
                    $conta->saldoatual +
                    ($movto->valorefetivado * ($movto->pagarreceber == 'P' ? -1 : 1)));
            $conta->update(array("saldoatual" => $novoSaldo));

            if ($movto->pagarreceber == 'P') {
                if ($this->getLancarFechado())
                    $this->movimentarCaixaFechado($movto);
            } else {
                if ($this->getLancarFechadoDestino())
                    $this->movimentarCaixaFechado($movto);
            }
        } catch (ValidationException $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        }
        DB::commit();
        return true;
    }

    public function movimentarCaixaFechado($movto)
    {
        $conta = Conta::findOrFail($movto->conta_id);

        $fechamento = Contafechamento::find($movto->contafechamento_id);

        $fechamentos = null;
        if ($fechamento->datahorafechamento != null) {
            $fechamentos = Contafechamento::where('conta_id', $movto->conta_id)
                    ->where('datahoraabertura', '>=', $fechamento->datahorafechamento)
                    ->get();
        }

        DB::beginTransaction();
        try {
            if ($fechamentos != null) {
                $fechamento->update(array("saldofinal" => sprintf("%0.2f",
                            $fechamento->saldofinal +
                            ($movto->valorefetivado * ($movto->pagarreceber == 'P' ? -1 : 1)))));
                foreach ($fechamentos as $fech) {
                    $fech->update(array("saldoinicial" => sprintf("%0.2f",
                                $fech->saldoinicial +
                                ($movto->valorefetivado * ($movto->pagarreceber == 'P' ? -1 : 1)))));
                    if ($fech->fechado) {
                        $fech->update(array("saldofinal" => sprintf("%0.2f",
                                    $fech->saldofinal +
                                    ($movto->valorefetivado * ($movto->pagarreceber == 'P' ? -1 : 1)))));
                    }
                }
            }
        } catch (ValidationException $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        }
        DB::commit();
    }

    public function movimentarCaixaEstorno($movto)
    {
        $conta = Conta::findOrFail($movto->conta_id);

        DB::beginTransaction();
        try {

            $novoSaldo = sprintf('%0.2f',
                    $conta->saldoatual +
                    ($movto->valorefetivado * ($movto->pagarreceber == 'R' ? -1 : 1)));
            $conta->update(array("saldoatual" => $novoSaldo));

            $fechamento = Contafechamento::find($movto->contafechamento_id);
            $lancarfechado = !is_null($fechamento) && $fechamento->fechado == 1;
            if ($this->getLancarFechado() || $lancarfechado) {
                $this->movimentarCaixaFechadoEstorno($movto);
            }
        } catch (ValidationException $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        }
        DB::commit();
        return true;
    }

    public function movimentarCaixaFechadoEstorno($movto)
    {
        $conta = Conta::findOrFail($movto->conta_id);
        //$fechamento = Contafechamento::find($this->getContaFechamento()->id);
        $fechamento = Contafechamento::find($movto->contafechamento_id);
        $fechamentos = Contafechamento::where('conta_id', $movto->conta_id)
                ->whereRaw("datahoraabertura >= to_date('{$fechamento->datahorafechamento}', 'yyyy-mm-dd HH24:mi:ss')")
                ->get();
        DB::beginTransaction();
        try {
            $novoSaldo = sprintf('%0.2f',
                    $fechamento->saldofinal +
                    ($movto->valorefetivado * ($movto->pagarreceber == 'R' ? -1 : 1)));
            $fechamento->update(array("saldofinal" => $novoSaldo));
            foreach ($fechamentos as $fech) {
                $novoSaldo = sprintf('%0.2f',
                        $fech->saldoinicial +
                        ($movto->valorefetivado * ($movto->pagarreceber == 'R' ? -1 : 1)));
                $fech->update(array("saldoinicial" => $novoSaldo));
                if ($fech->fechado) {
                    $novoSaldo = sprintf('%0.2f',
                            $fech->saldofinal +
                            ($movto->valorefetivado * ($movto->pagarreceber == 'R' ? -1 : 1)));
                    $fech->update(array("saldofinal" => $novoSaldo));
                }
            }
        } catch (ValidationException $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        }
        DB::commit();
    }

    public function baixarTitulos()
    {

        // SE DESCONTO DAS PARCELAS > DESCONTO DADO NA TELA DO CAIXA,
        // TENTA COLOCAR O DESCONTO EM MENSALIDADES EM ATRASO (QUE NAO TERIA DIREITO)
        $total_desconto = 0;
        foreach ($this->movimentos as $movto) {
            $total_desconto += $movto->desconto;
        }
        if ($this->desconto > $total_desconto) {
            $desconto_arealizar = $this->desconto - $total_desconto;
            $idx = 0;
            foreach ($this->movimentos as $movto) {
                if ($movto->desconto == 0 && $movto->desconto_pontualidade != 0 && $desconto_arealizar > 0) {
                    if (abs($movto->desconto_pontualidade) >= $desconto_arealizar) {
                        $movto->desconto = $desconto_arealizar;
                        $movto->valorefetivado -= $movto->desconto;
                        $desconto_arealizar = 0;
                    } else {
                        $movto->desconto = abs($movto->desconto_pontualidade);
                        $movto->valorefetivado -= $movto->desconto;
                        $desconto_arealizar -= abs($movto->desconto_pontualidade);
                    }
                }
                $idx++;
            }
            //SE COLOCANDO O DESCONTO NAS PARCELAS EM ATRASO AINDA NAO FOI SUFICIENTE,
            //DISTRIBUI PROPORCIONALMENTE NAS PARCELAS
            $idx = 0;
            if ($desconto_arealizar > 0) {
                $desconto_final = $desconto_arealizar;
                foreach ($this->movimentos as $movto) {
                    $idx++;
                    if ($desconto_final > 0) {
                        if ($idx < count($this->movimentos)) {
                            $desc = round($movto->valorefetivado / $this->valor_total * $desconto_arealizar, 2);
                            $movto->desconto += $desc;
                            $desconto_final -= $desc;
                            $movto->valorefetivado -= $desc;
                        } else {
                            //ULTIMA PARCELA COM DESCONTO
                            $movto->desconto += $desconto_final;
                            $movto->valorefetivado -= $desconto_final;
                        }
                    }
                }
            }
        } else if ($total_desconto > $this->desconto) {
            //SE DESCONTO DAS PARCELAS É MAIOR QUE TOTAL DO DESCONTO CONCEDIDO
            //REMOVER DESCONTO DAS PARCELAS
            $desconto_arealizar = $total_desconto - $this->desconto;
            $desconto_final = $desconto_arealizar;
            foreach ($this->movimentos as $movto) {
                if ($desconto_final > 0 && $movto->desconto > 0) {
                    if ($desconto_final <= $movto->desconto) {
                        $movto->desconto -= $desconto_final;
                        $movto->valorefetivado += $desconto_final;
                        $desconto_final = 0;
                    } else {
                        //ULTIMA PARCELA COM DESCONTO
                        $movto->valorefetivado += $movto->desconto;
                        $desconto_final -= $movto->desconto;
                        $movto->desconto -= 0;
                    }
                }
            }
        }
        //CALCULO DOS JUROS/MULTA
        $idx = 0;
        if ($this->juros > 0 || $this->multa > 0) {
            $juros_final = $this->juros;
            $multa_final = $this->multa;
            foreach ($this->movimentos as $movto) {
                $idx++;
                if ($juros_final > 0) {
                    if ($idx < count($this->movimentos)) {
                        $valor = $this->valor_total != 0 ? $this->valor_total : 1;
                        $juros = $this->juros != 0 ? $this->juros : 1;
                        $juros = round($movto->valorefetivado / $valor * $juros, 2);
                        $movto->juros += $juros;
                        $juros_final -= $juros;
                        $movto->valorefetivado += $juros;
                    } else {
                        //ULTIMA PARCELA COM JUROS/MULTA
                        $movto->juros += $juros_final;
                        $movto->valorefetivado += $juros_final;
                    }
                }
                if ($multa_final > 0) {
                    if ($idx < count($this->movimentos)) {
                        $valor = $this->valor_total != 0 ? $this->valor_total : 1;
                        $multa = $this->multa != 0 ? $this->multa : 1;
                        $multa = round($movto->valorefetivado / $valor * $multa, 2);
                        $movto->multa += $multa;
                        $multa_final -= $multa;
                        $movto->valorefetivado += $multa;
                    } else {
                        //ULTIMA PARCELA COM JUROS/MULTA
                        $movto->multa += $multa_final;
                        $movto->valorefetivado += $multa_final;
                    }
                }
            }
        }

        foreach ($this->movimentos as $movto) {
            if (!$this->receberCaixa($movto)) {
                return false;
            }
        }
        return true;
    }

    public function validarEstornoCaixa()
    {
        if (!$this->isUserAllowed()) {
            $this->addError('usuário não tem permissão para operar e/ou transferir caixa: ' . $this->conta->descricao);
            return false;
        }
        if (!$this->isUserAllowedEstornar()) {
            $this->addError('usuário não tem permissão para estornar neste caixa: ' . $this->conta->descricao);
            return false;
        }
        if ($this->getLancarFechado()) {
            if (!$this->isUserAllowedFechado()) {
                $this->addError('usuário não tem permissão para operar caixa já fechado: ' . $this->conta->descricao);
                return false;
            }
        }
        if ($this->isCaixaFechado() && !$this->getLancarFechado()) {
            $this->addError('caixa já está fechado: ' . $this->conta->descricao);
            return false;
        }
        foreach ($this->movimentos_estorno as $movto) {
            if (!$this->getLancarFechado() && $this->isCaixaFechadoData($movto->datahorabaixa)) {
                $this->addError('já existe um fechamento anterior a data/hora da baixa a estornar: ' . $this->conta->descricao . ' - ' . Carbon::parse($movto->datahorabaixa)->format('d/m/Y H:i:s'));
                return false;
            }
            if ($movto->Financeiroparcela != null) {
                if (!$movto->Financeiroparcela->baixado) {
                    $this->addError('a parcela não está baixada - estorno não permitido');
                    return false;
                }
            }
        }

        return true;
    }

    public function estornarCaixa()
    {
        DB::beginTransaction();
        try {
            foreach ($this->movimentos_estorno as $estorno) {
                if ($estorno->financeiroParcela != null) {
                    if (!$this->estornarParcelaCaixa($estorno)) {
                        DB::rollback();
                        return false;
                    }
                } else {
                    if (!$this->estornarTransferenciaCaixa($estorno)) {
                        DB::rollback();
                        return false;
                    }
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError(getErrorsException($e));
            return false;
        }
        DB::commit();
        return true;
    }

    public function estornarParcelaCaixa($estorno)
    {
        $parcela = $estorno->Financeiroparcela;
        $parcela->baixado = false;
        $parcela->multa = 0;
        $parcela->juros = 0;
        $parcela->desconto = 0;
        $parcela->valorefetivado = $parcela->valorefetivado;
        $parcela->datahorabaixa = null;
        $parcela->update();

        $movtoe = new Contamovimentoestorno();
        $movtoe->financeiroparcela_id = $parcela->id;
        $movtoe->contatransferencia_id = null;
        $movtoe->datahorabaixa = $estorno->datahorabaixa;
        $movtoe->contamovimentotipo_id = $estorno->contamovimentotipo_id;
        $movtoe->pagarreceber = $estorno->pagarreceber;
        $movtoe->conta_id = $estorno->conta_id;
        $movtoe->valor = $estorno->valor;
        $movtoe->multa = $estorno->multa;
        $movtoe->juros = $estorno->juros;
        $movtoe->desconto = $estorno->desconto;
        $movtoe->valorefetivado = $estorno->valorefetivado;
        $movtoe->origem = $estorno->origem;
        $movtoe->descricao = $estorno->descricao;
        $movtoe->user_id = \Auth::user()->id;
        $movtoe->motivo = $this->motivo_estorno;
        $movtoe->save();

        $movtoe1 = new Contamovimentoestorno();
        $movtoe1->financeiroparcela_id = $parcela->id;
        $movtoe1->contatransferencia_id = null;
        $movtoe1->datahorabaixa = $estorno->datahorabaixa;
        $movtoe1->contamovimentotipo_id = $estorno->contamovimentotipo_id;
        $movtoe1->pagarreceber = $estorno->pagarreceber == 'P' ? 'R' : 'P';
        $movtoe1->conta_id = $estorno->conta_id;
        $movtoe1->valor = $estorno->valor;
        $movtoe1->multa = $estorno->multa;
        $movtoe1->juros = $estorno->juros;
        $movtoe1->desconto = $estorno->desconto;
        $movtoe1->valorefetivado = $estorno->valorefetivado;
        $movtoe1->origem = 'EST';
        $movtoe1->descricao = 'ESTORNO DA BAIXA DO MOVTO ' . $estorno->id;
        $movtoe1->user_id = \Auth::user()->id;
        $movtoe1->motivo = $this->motivo_estorno;
        $movtoe1->save();

        if (!$this->movimentarCaixaEstorno($estorno)) {
            return false;
        }
        return $estorno->delete();
    }

    public function estornarTransferenciaCaixa($estorno)
    {
        $transferencia = $estorno->contaTransferencia;
        foreach ($transferencia->contaMovimentos as $mov) {
            $movtoe = new Contamovimentoestorno();
            $movtoe->financeiroparcela_id = null;
            $movtoe->contatransferencia_id = null;
            $movtoe->datahorabaixa = $mov->datahorabaixa;
            $movtoe->contamovimentotipo_id = $mov->contamovimentotipo_id;
            $movtoe->pagarreceber = $mov->pagarreceber;
            $movtoe->conta_id = $mov->conta_id;
            $movtoe->valor = $mov->valor;
            $movtoe->multa = $mov->multa;
            $movtoe->juros = $mov->juros;
            $movtoe->desconto = $mov->desconto;
            $movtoe->valorefetivado = $mov->valorefetivado;
            $movtoe->origem = $mov->origem;
            $movtoe->descricao = $mov->descricao;
            $movtoe->user_id = $mov->user_id;
            $movtoe->motivo = $this->motivo_estorno;
            $movtoe->save();

            $movtoe1 = new Contamovimentoestorno();
            $movtoe1->financeiroparcela_id = null;
            $movtoe1->contatransferencia_id = null;
            $movtoe1->datahorabaixa = $estorno->datahorabaixa;
            $movtoe1->contamovimentotipo_id = $mov->contamovimentotipo_id;
            $movtoe1->pagarreceber = $mov->pagarreceber == 'P' ? 'R' : 'P';
            $movtoe1->conta_id = $mov->conta_id;
            $movtoe1->valor = $mov->valor;
            $movtoe1->multa = $mov->multa;
            $movtoe1->juros = $mov->juros;
            $movtoe1->desconto = $mov->desconto;
            $movtoe1->valorefetivado = $mov->valorefetivado;
            $movtoe1->origem = 'EST';
            $movtoe1->descricao = 'ESTORNO DA TRANSFERENCIA DO MOVTO ' . $estorno->contatransferencia_id;
            $movtoe1->user_id = \Auth::user()->id;
            $movtoe1->motivo = $this->motivo_estorno;
            $movtoe1->save();

            if (!$this->movimentarCaixaEstorno($mov)) {
                return false;
            }
            $mov->forceDelete();
        }
        return $transferencia->delete();
    }

}
