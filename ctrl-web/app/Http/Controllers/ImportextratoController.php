<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use App\Services\CarbonCustom as Carbon;
use App\Financeiro;
use App\Planoconta;
use App\Centrocusto;
use App\Condicaopagamento;
use App\Conta;
use App\Contaextratoconfig;
use App\Contafechamento;
use App\Contamovimento;
use App\Financeiroparcela;
use App\Contamovimentotipo;
use App\Enums\ContaextratoAcao;
use App\Financeirorateio;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Processors\caixaProcessor;
use App\Processors\financeiroProcessor;
use Exception;
use stdClass;

class ImportextratoController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function importExtratoIndex($data = null)
    {
        $contamovimentotipos = Contamovimentotipo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $condicaopagamentos = Condicaopagamento::where([
            ['ativo', 1],
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
        ])->pluck('descricao', 'id')->prepend('Selecione', '');
        $centrocustos = Centrocusto::menuspermissoesAll();
        $planocontas = Planoconta::menuspermissoesAll();
        $extratoacoes = collect(ContaextratoAcao::getCasesForSelect())->prepend("Selecione", "");
        $extratoacaolancar = ContaextratoAcao::from(ContaextratoAcao::Lancar);
        $extratoacaotransferir = ContaextratoAcao::from(ContaextratoAcao::Transferir);
        $contas = Conta::where('empresa_id', Session::get('empresa_padrao')->id)->where('ativo', true)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');

        return view('financeiro.extrato.importextrato_index', compact('contamovimentotipos', 'condicaopagamentos', 'centrocustos', 'planocontas', 'extratoacoes', 'extratoacaolancar', 'extratoacaotransferir', 'contas'));
    }

    public function importExtrato(Request $request)
    {
        try {
            $file = $request->file('file-upload');
            throwIf(is_null($file), 'Arquivo inválido');
            $ofxParser = new \Beccha\OfxParser\Parser();
            $ofx = $ofxParser->loadFromFile($file->getRealPath());
            $bankAccounts = $ofx->getBankAccounts();
            $firstBankAccount = $bankAccounts[0];
            throwIf(!$firstBankAccount, 'Não foram encontradas informações de conta no arquivo OFX');
            $conta = Conta::where('empresa_id', Session::get('empresa_padrao')->id)->where('codigoofx', $firstBankAccount->getAccountNumber())->first();
            throwIf(!$conta, 'Conta não encontrada com código OFX: ' . $firstBankAccount->getAccountNumber());
            throwIf(!$conta->ativo, 'Conta não está ativa: ' . $conta->descricao);
            throwIf($conta->fechado, 'Conta não está aberta: ' . $conta->descricao);
            $startDate = Carbon::parse($firstBankAccount->getStatement()->getStartDate()->format('Y-m-d H:i:s'))->subDays(2)->startOfDay();
            $endDate = Carbon::parse($firstBankAccount->getStatement()->getEndDate()->format('Y-m-d H:i:s'))->addDays(2)->endOfDay();

            $startDateMov = Carbon::parse($firstBankAccount->getStatement()->getStartDate()->format('Y-m-d H:i:s'))->startOfDay();
            $endDateMov = Carbon::parse($firstBankAccount->getStatement()->getEndDate()->format('Y-m-d H:i:s'))->endOfDay();

            $parcelas = Financeiroparcela::join('financeiros as financeiro', 'financeiro.id', 'financeiroparcelas.financeiro_id')
                ->join('clientes as cliente', 'cliente.id', 'financeiro.cliente_id')
                ->where('financeiroparcelas.empresa_id', Session::get('empresa_padrao')->id)
                ->where('financeiroparcelas.baixado', false)
                ->whereBetween('financeiroparcelas.datavencimento', [$startDate, $endDate])
                ->where('financeiroparcelas.boletogerado', false)
                ->select('cliente.nome', 'financeiro.cliente_id', 'financeiroparcelas.id as financeiroparcela_id', 'financeiroparcelas.datavencimento', 'financeiroparcelas.valor', 'financeiro.documento', 'financeiroparcelas.pagarreceber')
                ->get();

            $movimentos = Contamovimento::where('conta_id', $conta->id)
                ->whereBetween('datahorabaixa', [$startDateMov, $endDateMov])
                ->whereNotNull('ofxuniqueid')
                ->get();

            $transactions = $firstBankAccount->getStatement()->getTransactions();
            $result = [];
            $lancamentos = [];
            $configs = Contaextratoconfig::where('conta_id', $conta->id)->get();
            foreach ($transactions as $transaction) {
                $ignorar = false;
                $lancar = false;
                $baixar = false;
                $lancarBaixar = false;
                $dadosbaixa = null;
                $movto = $movimentos->filter(function ($c) use ($transaction) {
                    return $c->ofxuniqueid == $transaction->getUniqueId();
                })->first();
                $baixado = $movto ? true : false;
                $config = $configs->filter(function ($c) use ($transaction) {
                    return str_starts_with(strtoupper($transaction->getMemo()), strtoupper($c->descricao));
                })->first();
                if ($config) {
                    $ignorar = !$baixado && $config->acao == ContaextratoAcao::from(ContaextratoAcao::Ignorar)->getValue();
                    $lancar = !$baixado && $config->acao == ContaextratoAcao::from(ContaextratoAcao::Lancar)->getValue();
                    $isBaixar = !$baixado && $config->acao == ContaextratoAcao::from(ContaextratoAcao::Baixar)->getValue();
                    $lancarBaixar = !$baixado && $config->acao == ContaextratoAcao::from(ContaextratoAcao::LancarBaixar)->getValue();

                    if ($lancar || $lancarBaixar) {
                        $record = new stdClass();
                        $record->descricao = $config->descricao;
                        $record->contamovimentotipo_id = $config->contamovimentotipo_id;
                        $record->conta_id = $config->conta_id;
                        $record->condicaopagamento_id = $config->condicaopagamento_id;
                        $record->planoconta_id = $config->planoconta_id;
                        $record->centrocusto_id = $config->centrocusto_id;
                        $record->cliente_id = $config->cliente_id;
                        $record->acao = $config->acao;

                        $record->nome = @$config->cliente->nome;
                        $record->pcdescricao = @trim($config->planoConta->descricao);
                        $record->ccdescricao = @trim($config->centroCusto->descricao);
                        $record->condpagtodescricao = @trim($config->condicaoPagamento->descricao);
                        $record->movtotipodescricao = @trim($config->contaMovimentoTipo->descricao);

                        if ($lancarBaixar) {
                            $dadosbaixa = [
                                "lancamento" => $record,
                            ];
                        } else {
                            $dadosbaixa = $record;
                        }
                    }

                    if ($isBaixar || $lancarBaixar) {
                        $parc = $parcelas->filter(function ($c) use ($transaction) {
                            return $c->valor == abs($transaction->getAmount() / 100) && $c->pagarreceber == ($transaction->getAmount() < 0 ? 'P' : 'R');
                        });

                        if (count($parc) > 0) {
                            $baixar = true;
                            if ($lancarBaixar) {
                                $dadosbaixa["titulos"] = array_values($parc->toArray());
                            } else {
                                $dadosbaixa = array_values($parc->toArray());
                            }
                        }
                    }
                }

                array_push($result, [
                    'baixado' => $baixado,
                    'lancar' => $lancar,
                    'ignorar' => $ignorar,
                    'lancarbaixar' => $lancarBaixar,
                    'baixar' => false,
                    'sugestaobaixar' => $baixar,
                    'payee' => $transaction->getPayee(),
                    'type' => $transaction->getType(),
                    'date' => $transaction->getDate()->format('Y-m-d H:i:s'),
                    'amount' => $transaction->getAmount() / 100,
                    'uniqueid' => $transaction->getUniqueId(),
                    'name' => $transaction->getName(),
                    'memo' => $transaction->getMemo(),
                    'sic' => $transaction->getSic(),
                    'checknumber' => $transaction->getCheckNumber(),
                    'typedescription' => $transaction->getTypeDescription(),
                    'dadosbaixa' => $dadosbaixa
                ]);

                if ($config && $lancar && !is_null($config->cliente_id)) {
                    array_push($lancamentos, [
                        'acao' => $lancar ? 'lancar' : 'baixar',
                        'date' => $transaction->getDate()->format('Y-m-d H:i:s'),
                        'amount' => $transaction->getAmount() / 100,
                        'uniqueid' => $transaction->getUniqueId(),
                        'memo' => $transaction->getMemo(),
                        'dadosbaixa' => $dadosbaixa
                    ]);
                }
            }

            $contamovimentotipos = Contamovimentotipo::where('ativo', true)
                ->where('grupo_id', Session::get('empresa_padrao')->grupo_id)
                ->orderBy('descricao')
                ->get();

            $contamovimentotiposel = [['text' => 'Selecione', 'value' => '']];

            foreach ($contamovimentotipos as $tipo) {
                array_push($contamovimentotiposel, ['text' => $tipo->descricao, 'value' => $tipo->id]);
            }

            return responseSuccess(['transactions' => $result, 'lancamentos' => $lancamentos, 'conta' => $conta, 'contamovimentotipos' => $contamovimentotiposel]);
        } catch (Exception $e) {
            return responseError(getErrorsException($e));
        }
    }

    public function getParcelas(Request $request)
    {
        try {
            $data = $request->all();
            $ids_ignorar = explode(",", $data['ids_ignorar']);
            $startDate = Carbon::createFromFormat('d/m/Y', $data['dataini'])->startOfDay();
            $endDate = Carbon::createFromFormat('d/m/Y', $data['datafim'])->startOfDay();
            $cliente_id = $data['cliente_id'];
            $pagarreceber = $data['pagarreceber'];

            $parcelas = Financeiroparcela::join('financeiros as financeiro', 'financeiro.id', 'financeiroparcelas.financeiro_id')
                ->join('clientes as cliente', 'cliente.id', 'financeiro.cliente_id')
                ->where('financeiroparcelas.empresa_id', Session::get('empresa_padrao')->id)
                ->where('financeiroparcelas.baixado', false)
                ->whereBetween('financeiroparcelas.datavencimento', [$startDate, $endDate])
                ->where('financeiroparcelas.pagarreceber', $pagarreceber)
                ->where('financeiroparcelas.boletogerado', false);


            if ($cliente_id) {
                $parcelas = $parcelas->where('financeiro.cliente_id', $cliente_id);
            }
            if (count($ids_ignorar) > 0 && $data['ids_ignorar'] != '') {
                $parcelas = $parcelas->whereNotIn('financeiroparcelas.id', $ids_ignorar);
            }
            $parcelas = $parcelas->select('cliente.nome', 'financeiro.cliente_id', 'financeiroparcelas.id as financeiroparcela_id', 'financeiroparcelas.datavencimento', 'financeiroparcelas.valor', 'financeiro.documento', 'financeiroparcelas.pagarreceber')
                ->get();

            return responseSuccess($parcelas);
        } catch (Exception $e) {
            return responseError(getErrorsException($e));
        }
    }

    public function update(Request $request)
    {
        try {
            $data = $request->all();
            $conta_id = $data['conta_id'];
            throwIf(!$conta_id, 'Conta não encontrada ao atualizar');
            $lancamentos = json_decode($data['lancamentos']);
            DB::beginTransaction();
            foreach ($lancamentos as $lancamento) {
                if ($lancamento->acao == 'baixar') {
                    $this->financeiroBaixar($lancamento, $conta_id);
                } elseif ($lancamento->acao == 'lancar') {
                    $this->financeiroLancarBaixar($lancamento);
                }
            }
            DB::commit();
            return responseSuccess();
        } catch (Exception $e) {
            DB::rollback();
            return responseError(getErrorsException($e));
        }
    }

    private function financeiroLancarBaixar($lancamento)
    {

        $processor = new FinanceiroProcessor();
        $raton = new Financeirorateio();
        $dataemissao = $lancamento->date;
        $valortotal = abs($lancamento->amount);
        //financeiro
        $financeiro = new Financeiro();
        $financeiro->empresa_id = Session::get('empresa_padrao')->id;
        $financeiro->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $financeiro->cliente_id = $lancamento->dadosbaixa->cliente_id;
        $financeiro->pagarreceber = $lancamento->amount < 0 ? "P" : "R";
        $financeiro->documento = $lancamento->uniqueid;
        $financeiro->descricao = ("OFX: " . $lancamento->memo);
        $financeiro->cartaonsu = null;
        $financeiro->cartaoautorizacao = null;
        $financeiro->valor = $valortotal;
        $financeiro->condicaopagamento_id = $lancamento->dadosbaixa->condicaopagamento_id;
        $financeiro->datacompetencia = $dataemissao;
        $financeiro->dataemissao = $dataemissao;
        $processor->setFinanceiro($financeiro);
        //rateio
        $rato = [];
        $raton["centrocusto_id"] = $lancamento->dadosbaixa->centrocusto_id;
        $raton["planoconta_id"] = $lancamento->dadosbaixa->planoconta_id;
        $raton["valor"] = $valortotal;
        $raton["financeiro_id"] = $processor->getFinanceiro()->id;
        $raton["percentual"] = 1;
        array_push($rato, $raton);
        $processor->setRateios($rato);
        //parcelas
        $parc = [];
        $financeiroparcela = new Financeiroparcela();
        $financeiroparcela["datavencimento"] = $dataemissao;
        $financeiroparcela["valor"] = $valortotal;
        $financeiroparcela["empresa_id"] = Session::get('empresa_padrao')->id;
        $financeiroparcela["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $financeiroparcela["financeiro_id"] = $processor->getFinanceiro()->id;
        $financeiroparcela["multa"] = 0;
        $financeiroparcela["juros"] = 0;
        $financeiroparcela["desconto"] = 0;
        $financeiroparcela["valorefetivado"] = $valortotal;
        $financeiroparcela["numero"] = 1;
        $financeiroparcela["baixado"] = false;
        $financeiroparcela["datacompetencia"] = $dataemissao;
        $financeiroparcela["pagarreceber"] = $processor->getFinanceiro()->pagarreceber;
        array_push($parc, $financeiroparcela);
        //dados do processor
        $processor->setAgrupar(false);
        $processor->setParcelas($parc);
        $processor->setBaixar(true);
        $processor->setContaId($lancamento->dadosbaixa->conta_id);
        $processor->setDataPagamento($dataemissao);
        $processor->setRecebimentoTipoId($lancamento->dadosbaixa->contamovimentotipo_id);
        $processor->setContaFechamentoId(-1);
        $processor->setOfxuniqueid($lancamento->uniqueid);
        //gravar
        $gravar = $processor->gravar();
        if (!$gravar) {
            $erro = $processor->getErrors();
            throw new exception(implode(" | ", $erro));
        }
    }

    private function financeiroBaixar($lancamento, $conta_id)
    {
        $parcs = [];
        $movtos = [];
        $amount = abs($lancamento->amount);
        $totalTitulos = array_reduce($lancamento->dadosbaixa, function ($accumulator, $item) {
            $accumulator += floatval($item->valor);
            return $accumulator;
        }, 0);
        $totalDesc = $totalTitulos > $amount ? $totalTitulos - $amount : 0;
        $totalJuros = $totalTitulos < $amount ? $amount - $totalTitulos : 0;
        $percDesc =  $totalTitulos == 0 ? 0 : $totalDesc / $totalTitulos;
        $percJuros = $totalTitulos == 0 ? 0 : $totalJuros / $totalTitulos;

        $i = 0;
        $totalJurosAplicados = 0;
        $totalDescontosAplicados = 0;
        foreach ($lancamento->dadosbaixa as $parc) {
            array_push($parcs, $parc->financeiroparcela_id);
            $parcela = Financeiroparcela::find($parc->financeiroparcela_id);
            throwIf(!$parcela, 'Parcela id. ' . $parc->financeiroparcela_id . ' não encontrada');
            throwIf($parcela->baixado, 'Parcela id. ' . $parcela->id . ' já está baixada');
            $movto = new Contamovimento();
            $movto->financeiroparcela_id = $parc->financeiroparcela_id;
            $movto->datahorabaixa = $lancamento->date;
            $movto->contamovimentotipo_id = $lancamento->contamovimentotipo_id;
            $movto->pagarreceber = $parc->pagarreceber;
            $movto->conta_id = $conta_id;
            $movto->valor = $parcela->valor;
            $movto->multa = 0;
            if ($i == count($lancamento->dadosbaixa) - 1) {
                $movto->juros = $totalJuros - $totalJurosAplicados;
                $movto->desconto = $totalDesc - $totalDescontosAplicados;
                $movto->valorefetivado = $parcela->valor + $movto->juros - $movto->desconto;
            } else {
                $movto->juros = round($parcela->valor * $percJuros, 2);
                $movto->desconto = round($parcela->valor * $percDesc, 2);
                $movto->valorefetivado = $parcela->valor + $movto->juros - $movto->desconto;
                $totalJurosAplicados += $movto->juros;
                $totalDescontosAplicados += $movto->desconto;
            }
            $movto->contatransferencia_id = null;
            $movto->origem = 'OFX';
            $movto->descricao = "OFX: " . $lancamento->memo;
            $movto->ofxuniqueid = $lancamento->uniqueid;
            array_push($movtos, $movto);
            $i++;
        }
        $processor = new caixaProcessor($conta_id);
        $contafechamento = Contafechamento::where('conta_id', $conta_id)
            ->where('datahoraabertura', '<=', $lancamento->date)
            ->orderBy('datahoraabertura', 'DESC')
            ->first();

        if (is_null($contafechamento)) {
            return "Conta/Caixa não está aberta ou não possui um fechamento.";
        }

        $processor->setContaFechamento($contafechamento);
        $processor->setLancarFechado($contafechamento->fechado);
        $processor->setMovimentos($movtos);
        $processor->setValorTotal($amount);
        $processor->setValorDesconto($totalDesc);

        if (!$processor->validarBaixaTitulos())
            throw new Exception(implode(' ', $processor->getErrors()));

        if (!$processor->baixarTitulos())
            throw new Exception(implode(' ', $processor->getErrors()));
    }
}
