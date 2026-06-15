<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use App\Conta;
use App\Empresa;
use App\Services\CarbonCustom as Carbon;
use App\Financeiro;
use App\Contamovimento;
use App\Contafechamento;
use App\Financeiroparcela;
use App\Contatransferencia;
use App\Contamovimentotipo;
use Illuminate\Http\Request;
use App\Processors\caixaProcessor;
use App\Http\Controllers\Controller;
use App\Processors\financeiroProcessor;

class CaixaController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Conta $co)
    {
        $this->authorize('viewCaixa', $co);
        $user_id = \Auth::user()->id;
        $contas = Conta::rightJoin("contausers as conu", "conu.conta_id", "contas.id")
            ->whereRaw("conu.user_id = $user_id and (conu.operar = 1 or conu.visualizar = 1)")
            ->select("contas.id", "contas.fechado", "contas.conta", "contas.descricao", "conu.lancarfechado", "contas.ativo")
            ->orderBy("contas.descricao")
            ->get();

        return view('financeiro.caixas', compact('contas'));
    }

    public function abrirCaixa(Request $request)
    {
        $processor = new caixaProcessor($request->only('conta_id')["conta_id"]);
        $processor->setDataAbertura(Carbon::createFromFormat('d/m/Y H:i:s', $request->only('data_abertura')["data_abertura"]));

        if ($processor->abrirCaixa())
            return response()->json('OK|');
        else
            return implode(' ', $processor->getErrors());
    }

    public function abrirTelaCaixa($par, Conta $con)
    {
        $titulo = 'CAIXA';
        $pars = explode('|', $par);
        $Conta = Conta::find($pars[0]);
        $this->authorize('lanRetroativo', $Conta);
        $fechado = $pars[1] == 1;

        $permissao = \Auth::user()->contas()->with('conta')
            ->where('conta_id', '=', $pars[0])->get();
        $operar = $permissao->first()->operar == 1;
        $estornar = $permissao->first()->estornar == 1;

        $contamovimentotipos = Contamovimentotipo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $contasuser = \Auth::user()->contas()->with('conta')
            ->where('conta_id', '<>', $pars[0])
            ->where('transferir', '=', 1)->get();
        if ($fechado) {
            $contauser = \Auth::user()->contas()
                ->with('conta')
                ->where('conta_id', '=', $pars[0])
                ->first();
            if ($contauser == null || !$contauser->lancarfechado) {
                return Redirect::back()
                    ->withErrors('Usuário não permitido para operar caixa já fechado.')
                    ->withInput();
            }
        }
        $contastransf = array();
        foreach ($contasuser as $conta) {
            if ($conta->conta->ativo) {
                $contastransf[$conta->conta->id] = $conta->conta->descricao;
            }
        }

        if (!$fechado) {
            return view('financeiro.caixa', compact('Conta', 'contamovimentotipos', 'contastransf', 'operar', 'estornar'));
        } else {
            $dados = Contafechamento::where('contas.empresa_id', Session::get('empresa_padrao')->id)
                ->where('contas.id', $pars[0])
                ->whereBetween('datahoraabertura', [date("Y-m-d", strtotime('-2 days')), Carbon::now()])
                ->join('contas', 'contas.id', '=', 'contafechamentos.conta_id')
                ->select('contafechamentos.id', 'datahoraabertura', 'datahorafechamento', 'contafechamentos.saldoinicial', 'contafechamentos.saldofinal')
                ->get();

            $dataInicial = date("d/m/Y", strtotime('-2 days'));
            $dataFinal = requestDataOracle(Carbon::now());
            return view('financeiro.caixafechado', compact('Conta', "dados", 'dataInicial', 'dataFinal'));
        }
    }

    public function abrirTelaCaixaFechado($par)
    {
        $titulo = 'CAIXA';
        $contafechamento = Contafechamento::find($par);
        $Conta = Conta::find($contafechamento->conta_id);

        $permissao = \Auth::user()->contas()->with('conta')
            ->where('conta_id', '=', $Conta->id)->get();
        $operar = $permissao->first()->operar == 1;
        $estornar = $permissao->first()->estornar == 1;
        $contamovimentotipos = Contamovimentotipo::where('ativo', true)
            ->where('grupo_id', Session::get('empresa_padrao')->grupo_id)
            ->orderBy('descricao')
            ->pluck('descricao', 'id');

        $contasuser = \Auth::user()->contas()->with('conta')
            ->where('conta_id', '<>', $Conta->id)
            ->where('transferir', '=', 1)->get();

        $contastransf = array();
        foreach ($contasuser as $conta) {
            if ($conta->conta->ativo) {
                $contastransf[$conta->conta->id] = $conta->conta->descricao;
            }
        }
        return view('financeiro.caixa', compact('Conta', 'contamovimentotipos', 'contastransf', 'operar', 'estornar', 'contafechamento'));
    }

    function filterContaFechamento($contaId, $dataInicial, $dataFinal)
    {
        $Conta = Conta::find($contaId);
        $dataInicial .= " 00:00:00";
        $dataFinal .= " 23:59:59";

        $dados = Contafechamento::where('contas.empresa_id', Session::get('empresa_padrao')->id)
            ->where('contas.id', $contaId)
            ->where('contafechamentos.fechado', true)
            ->whereBetween('datahoraabertura', [$dataInicial, $dataFinal])
            ->join('contas', 'contas.id', '=', 'contafechamentos.conta_id')
            ->select('contafechamentos.id', 'datahoraabertura', 'datahorafechamento', 'contafechamentos.saldoinicial', 'contafechamentos.saldofinal')
            ->get();

        $dataInicial = Carbon::createFromFormat('Y-m-d H:i:s', $dataInicial)->format('d/m/Y');
        $dataFinal = Carbon::createFromFormat('Y-m-d H:i:s', $dataFinal)->format('d/m/Y');
        return view('financeiro.caixafechado', compact('Conta', "dados", 'dataInicial', 'dataFinal'));
    }

    public function fecharCaixa(Request $request)
    {
        $date = Carbon::createFromFormat("d/m/Y H:i", $request->only('data_fechamento')["data_fechamento"])->format("Y-m-d H:i") . ':59';
        $conta_id = $request->only('conta_id')["conta_id"];
        $fechamento = Contafechamento::where('conta_id', $conta_id)->where('fechado', 0)->first();

        if (is_null($fechamento)) {
            return response()->json("Data de Abertura da conta não encontrada, favor contatar o suporte!");
        }

        if (strtotime($fechamento->datahoraabertura) > strtotime($date))
            return response()->json("A data de fechamento do caixa não pode ser menor que a de abertura.");

        $processor = new caixaProcessor($request->only('conta_id')["conta_id"]);

        $processor->setDataFechamento($date);

        if ($processor->fecharCaixa()) {
            if ($processor->getIsReopenCaixa())
                return response()->json('OPEN' . $conta_id);

            return response()->json('OK|' . $processor->getUltimoFechamentoId());
        } else
            return implode(' ', $processor->getErrors());
    }

    public function transferirCaixa(Request $request)
    {
        $transf = new Contatransferencia();
        //$transf->contamovimentotipo_id = $request->only('contamovimentotipo_id')["contamovimentotipo_id"];
        $contafechamento_id = $request->only('contafechamento_id')["contafechamento_id"];
        $transf->origemconta_id = $request->only('conta_id')["conta_id"];
        $transf->destinoconta_id = $request->only('conta_destino_id')["conta_destino_id"];
        $transf->datahoraprocesso = Carbon::createFromFormat('d/m/Y H:i:s', $request->only('data_transferencia')["data_transferencia"]);
        $transf->origemconta_id = $request->only('conta_id')["conta_id"];
        $transf->valor = insertNumeroDecimalOracle($request->only('valor')["valor"]);
        $transf->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $transf->empresa_id = Session::get('empresa_padrao')->id;
        $contafechamentoOrigem = null;
        if ($contafechamento_id != -1) {
            $contafechamentoOrigem = Contafechamento::find($contafechamento_id);
        }
        $fechadoDestino = true;
        $contafechamentoDestino = Contafechamento::where('conta_id', $transf->destinoconta_id)
            ->where('datahoraabertura', '<=', $transf->datahoraprocesso)->orderBy('datahoraabertura', 'DESC')
            ->first();
        if ($contafechamentoDestino != null) {
            $fechadoDestino = $contafechamentoDestino->fechado;
        }
        if ($contafechamentoDestino == null) {
            return response()->json(implode(' ', 'Não foi possível encontrar abertura/fechamento de caixa para a conta destino.'));
        }
        // dd($contafechamentoOrigem, $contafechamentoDestino);
        $processorOrigem = new caixaProcessor($request->only('conta_id')["conta_id"]);
        $processorOrigem->setLancarFechado($contafechamento_id != -1 && $contafechamentoOrigem->fechado);
        $processorOrigem->setContaFechamento($contafechamentoOrigem);

        $processorOrigem->setLancarFechadoDestino($fechadoDestino);
        $processorOrigem->setContaFechamentoDestino($contafechamentoDestino);
        // $processorDestino = new caixaProcessor($request->only('conta_destino_id')["conta_destino_id"]);
        // $processorDestino->setLancarFechado($fechadoDestino);
        // $processorDestino->setContaFechamento($contafechamentoDestino);

        if (!$processorOrigem->validarTransferenciaCaixa($transf))
            return response()->json(implode(' ', $processorOrigem->getErrors()));
        // if (!$processorDestino->validarTransferenciaCaixa($transf))
        // return response()->json(implode(' ', $processorDestino->getErrors()));
        // dd(!$processorOrigem->transferirCaixa($transf), 'a');
        // dd($processorOrigem->transferirCaixa($transf));
        if ($processorOrigem->transferirCaixa($transf))
            return response()->json('OK|' . $processorOrigem->getMovimentoGerado());
        else
            return response()->json(implode(' ', $processorOrigem->getErrors()));
    }

    public function baixarduplicatasbycaixa(Request $request)
    {
        $tipo_lancamento = '';
        $conta_id = '';
        $pagarreceber = "";
        if ($request != null) {
            $data = $request->only('conta_id_receber', 'parcelas', 'empresa_cnpj', 'empresa_razao_social');
            $conta_id = $data['conta_id_receber'];
            $parcela = json_decode($data['parcelas']);
            $empresa_cnpj = $data['empresa_cnpj'];
            $empresa_razao_social = $data['empresa_razao_social'];

            $contasuser = \Auth::user()->contasAtivas()->with('conta')
                ->where('operar', '=', 1)->get();

            $contas = array();
            $contas[-1] = '';
            foreach ($contasuser as $conta) {
                if ($conta->conta->ativo) {
                    $contas[$conta->conta->id] = $conta->conta->descricao;
                }
            }


            $parcelas = [];
            $valor_total = 0;
            $valor_liquido = 0;
            $valor_desconto = 0;
            $valor_multa = 0;
            $valor_juros = 0;

            $parcelasdet = Financeiroparcela::whereIn('id', $parcela)
                ->where('baixado', false)->get();

            $descricao = '';
            foreach ($parcelasdet as $fin) {
                $fin->cliente_id = $fin->cliente->id;
                $fin->cliente_nome = $fin->cliente->nome;
                $valor_juros += abs($fin->valor_juros);
                $valor_multa += abs($fin->valor_multa);
                $valor_desconto += abs($fin->valor_desconto);

                $valor_total += $fin->valor_original;
                $valor_liquido += $fin->valor_liquido;
                array_push($parcelas, $fin);
                $descricao = $fin->financeiro->descricao;
                $pagarreceber = $fin->pagarreceber;
            }
        }
        $voltar = 0;
        $recebimentotipos = Contamovimentotipo::whereIn('pagarreceber', [$pagarreceber, 'A'])->where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $baixarfechado = 0;
        return view('financeiro.baixar_form_bycaixa', compact('voltar', 'recebimentotipos', 'conta_id', 'parcelas', 'valor_total', 'valor_desconto', 'valor_liquido', 'valor_multa', 'valor_juros', 'contas', 'empresa_cnpj', 'empresa_razao_social', 'descricao', 'baixarfechado'));
    }

    public function baixartitulosbycaixa(Request $request, Financeiro $fin)
    {
        $tipo_lancamento = '';
        $conta_id = '';
        $descricao = '';
        $pagarreceber = '';
        if ($request != null) {
            $data = $request->only('conta_id_receber', 'parcelas', 'baixarfechado', 'encontrocontas');

            if ($data['encontrocontas'] != '') {
                $arrayParcelasEncontroContas_id = json_decode($data['encontrocontas']);
                $descricao .= 'ENC. CONTAS PARC.: ' . implode(', ', $arrayParcelasEncontroContas_id) . ', ';
                $parcelasEncontroContas = Financeiroparcela::whereIn('id', $arrayParcelasEncontroContas_id)->get();
                $valor_credito = $parcelasEncontroContas->sum('valor');
                $parcelasEncontroContas = json_encode($arrayParcelasEncontroContas_id);
                $encontrocontas = true;
            } else {
                $parcelasEncontroContas = [];
                $valor_credito = 0;
                $parcelasEncontroContas = '[]';
                $encontrocontas = false;
                $descricao .= 'QUANTIDADE DE PARCELAS: ';
            }

            $conta_id = $data['conta_id_receber'];
            $parcela = json_decode($data['parcelas']);
            $baixarfechado = $data['baixarfechado'];
            $contasuser = \Auth::user()->contas()->with('conta')
                ->where('operar', '=', 1)
                ->get();

            $contas = array();
            $contas[-1] = '';
            foreach ($contasuser as $conta) {
                if ($conta->conta->ativo && $conta->conta->empresa_id == Session::get('empresa_padrao')->id) {
                    $contas[$conta->conta->id] = $conta->conta->descricao;
                }
            }

            $parcelasdet = Financeiroparcela::whereIn('id', $parcela)->where('baixado', false)->get();
            if (count($parcelasdet) !== count($parcela)) {
                return "Uma ou mais parcelas selecionadas não existem, favor refazer a busca e tentar novamente";
            }
            if (is_null($parcelasdet->first())) {
                return "Não foi possível encontrar a(s) parcela(s) selecionada(s)";
            }
            $this->authorize('igualdadeReceber', $parcelasdet->first()->financeiro);
            if ($parcelasdet->first()->pagarreceber == 'R')
                $this->authorize('receber', $fin);
            else
                $this->authorize('pagar', $fin);
            $parcelas = [];
            $valor_total = 0;
            $valor_liquido = $encontrocontas ? 0 - $valor_credito : 0;
            $valor_desconto = 0;
            $valor_multa = 0;
            $valor_juros = 0;
            $empresa_cnpj = Session::get('empresa_padrao')->cnpj;
            $empresa_razao_social = Session::get('empresa_padrao')->razao_social;
            $count = 0;
            foreach ($parcelasdet as $fin) {
                $empresa_cnpj = $fin->empresa->cnpj;
                $empresa_razao_social = $fin->empresa->razao_social;

                if ($fin->financeiro->cliente != null) {
                    $fin->cliente_id = $fin->financeiro->cliente_id;
                    $fin->cliente_nome = $fin->financeiro->cliente->nome;
                }
                $valor_juros += abs($fin->juros);
                $valor_multa += abs($fin->multa);
                $valor_desconto += abs($fin->desconto);
                $valor_total += $fin->valor;
                $valor_liquido += $fin->valorefetivado;

                array_push($parcelas, $fin);
                $count++;
                if ($encontrocontas) {
                    $descricao .= $fin->id;
                    $descricao .= $count == count($parcelasdet) ? '' : ', ';
                }
                $pagarreceber = $fin->pagarreceber;
            }
            if (!$encontrocontas) $descricao .= $count;
        }
        $valor_juros = requestNumeroDecimalOracle($valor_juros);
        $valor_multa = requestNumeroDecimalOracle($valor_multa);
        $valor_desconto = requestNumeroDecimalOracle($valor_desconto);
        $voltar = 0;
        $recebimentotipos = Contamovimentotipo::whereIn('pagarreceber', [$pagarreceber, 'A'])
            ->where('ativo', true)
            ->where('grupo_id', Session::get('empresa_padrao')->grupo_id)
            ->orderBy('descricao')->pluck('descricao', 'id');

        return view('financeiro.baixar_form_bycaixa', compact('voltar', 'recebimentotipos', 'conta_id', 'parcelas', 'valor_total', 'valor_desconto', 'valor_liquido', 'contas', 'valor_multa', 'valor_juros', 'empresa_cnpj', 'empresa_razao_social', 'descricao', 'baixarfechado', 'valor_credito', 'encontrocontas', 'parcelasEncontroContas'));
    }

    public function cancelartitulosbycaixa(Request $request, Financeiro $fin)
    {
        $tipo_lancamento = '';
        $conta_id = '';
        if ($request != null) {
            $data = $request->only('conta_id_receber', 'parcelas');
            $conta_id = $data['conta_id_receber'];
            $parcela = json_decode($data['parcelas']);

            $contasuser = \Auth::user()->contas()->with('conta')
                ->where('operar', '=', 1)->get();

            $contas = array();
            $contas[-1] = '';
            foreach ($contasuser as $conta) {
                if ($conta->conta->ativo) {
                    $contas[$conta->conta->id] = $conta->conta->descricao;
                }
            }

            $parcelasdet = Financeiroparcela::whereIn('id', $parcela)->where('baixado', false)->get();
            $recpag = $parcelasdet->first()->pagarreceber;

            if ($recpag === "R")
                $this->authorize('cancelarRecebimento', $fin);
            else
                $this->authorize('cancelarPagamento', $fin);

            $this->authorize('igualdadeReceber', $parcelasdet->first()->financeiro);
            $parcelas = [];
            $valor_total = 0;
            $valor_liquido = 0;
            $valor_desconto = 0;
            $valor_multa = 0;
            $valor_juros = 0;
            foreach ($parcelasdet as $fin) {
                $valor_juros += abs($fin->juros);
                $valor_multa += abs($fin->multa);
                $valor_desconto += abs($fin->desconto);
                $fin->valorefetivado = $fin->valor;
                $valor_total += $fin->valor;
                $valor_liquido += $fin->valorefetivado;
                $fin->cliente_nome = $fin->financeiro->cliente->nome;
                $fin->descricao = $fin->financeiro->descricao;
                array_push($parcelas, $fin);
            }
        }
        $voltar = 0;
        return view('financeiro.cancelar_form_bycaixa', compact('voltar', 'conta_id', 'parcelas', 'valor_total', 'valor_desconto', 'valor_liquido', 'contas', 'valor_multa', 'valor_juros'));
    }

    public function baixar(Request $request, $withTransaction = true)
    {
        $data = $request->only('conta_id', 'contamovimentotipo_idM', 'data_pagamentoM', 'valor_desconto', 'valor_multa', 'valor_juros', 'parcelas', 'valor_total', 'valor_liquido', 'parcial', 'valor_parcial', 'descricao', 'baixarfechado');

        if ($withTransaction) DB::beginTransaction();
        try {
            $descricao = $data['descricao'];

            $datapagamento = Carbon::createFromFormat('d/m/Y H:i:s', $data["data_pagamentoM"]);

            $this->validateDate($datapagamento);

            if (strlen($descricao) > 255)
                throw new \Exception("O campo Observação tem " . strlen($descricao) . " caracteres, o máximo permitido é 255.");

            if (floatval($data["valor_liquido"]) < 0)
                throw new \Exception('O valor líquido não pode ser menor que zero.');

            if (isset($request->only('parcelasEncontroContas')['parcelasEncontroContas'])) {
                $parcelasEC = json_decode($request->only('parcelasEncontroContas')['parcelasEncontroContas']);
                $baixarParcEncontoContas = $this->baixarParcelasEncontroContas($parcelasEC, $data['contamovimentotipo_idM'], $data['conta_id'], $datapagamento);
                if (!is_bool($baixarParcEncontoContas))
                    throw new \Exception($baixarParcEncontoContas);
            }

            $baixarfechado = $data['baixarfechado'];
            if ($baixarfechado) {
                $fechamentos = Contafechamento::where('conta_id', $data["conta_id"])
                    ->where('contafechamentos.fechado', true)
                    ->where('datahoraabertura', '<=', $datapagamento)
                    ->where('datahorafechamento', '>=', $datapagamento)
                    ->get();
                if (count($fechamentos) == 0) {
                    throw new \Exception('Não foi encontrado movimento de caixa no período informado para a baixa em caixa fechado.');
                }
            }

            $parcial = $data['parcial'];
            if ($parcial) {
                $valor_parcial = floatval(insertNumeroDecimalOracle($data['valor_parcial']));
                $valor_total = floatval(insertNumeroDecimalOracle($data["valor_total"]));
                $valor_liquido = floatval(insertNumeroDecimalOracle($data["valor_liquido"]));
                $valor_desconto = floatval(insertNumeroDecimalOracle($data["valor_desconto"]));
                $valor_multa = floatval(insertNumeroDecimalOracle($data["valor_multa"]));
                $valor_juros = floatval(insertNumeroDecimalOracle($data["valor_juros"]));
                if ($valor_parcial == 0) {
                    throw new \Exception("Para pagamento parcial, favor informar o valor do pagamento parcial.");
                } else {
                    if ($valor_parcial >= $valor_total) {
                        throw new \Exception("Para pagamento parcial, o valor parcial não pode ser maior ou igual ao valor total a baixar.");
                    }
                }
                $parcelas = json_decode($data["parcelas"])->data;
                $total = $valor_parcial;
                $total_desconto = $valor_desconto;
                $total_multa = $valor_multa;
                $total_juros = $valor_juros;
                $total_liquido = $valor_liquido;
                $i = 0;
                $parcelas_baixadas = array();

                foreach ($parcelas as $parc) {
                    $i++;
                    $financeiroparcela = Financeiroparcela::with('financeiro.condicaoPagamento')->find($parc[0]);
                    $pgto = $financeiroparcela->financeiro->condicaoPagamento;

                    //if (!is_null($pgto) && ($pgto->tipo == 2 || $pgto->tipo == 3))
                    //    throw new \Exception("Não é possível pagar parcialmente parcelas com a condição de pagamento do tipo Cartão.");

                    $financeiro = $financeiroparcela->financeiro;
                    $processor = new financeiroProcessor();
                    $processor->setTipoRetorno('');
                    $processor->setDescricao($descricao);
                    $processor->setDataVencimento($financeiroparcela->data_vencimento);
                    $processor->setDataPagamento($datapagamento);
                    $processor->setFinanceiro($financeiro);
                    $processor->setAgrupar(true);
                    $processor->setRecebimentoTipoId($data["contamovimentotipo_idM"]);
                    $processor->setContaId($data["conta_id"]);
                    $processor->setParcelasOrigem([$financeiroparcela->id]);
                    // RATEIOS
                    $rateios = array();
                    foreach ($financeiro->rateios as $rateio) {
                        $rateio['id'] = null;
                        array_push($rateios, $rateio);
                    }
                    $processor->setRateios($rateios);

                    $percentual = ($financeiroparcela->valor) / $valor_total;
                    $percentual1 = ($valor_parcial / $valor_liquido);
                    $percentual2 = (1 - $percentual1);

                    $valor_parcela_multa1 = round($percentual * $valor_multa * $percentual1, 2);
                    $valor_parcela_multa2 = round($percentual * $valor_multa * $percentual2, 2);
                    $valor_parcela_juros1 = round($percentual * $valor_juros * $percentual1, 2);
                    $valor_parcela_juros2 = round($percentual * $valor_juros * $percentual2, 2);
                    $valor_parcela_desconto1 = round($percentual * $valor_desconto * $percentual1, 2);
                    $valor_parcela_desconto2 = round($percentual * $valor_desconto * $percentual2, 2);
                    $valor_parcela_liquido1 = round($valor_liquido * $percentual * $percentual1, 2);
                    $valor_parcela_liquido2 = round($valor_liquido * $percentual * $percentual2, 2);
                    $valor_parcela1 = $valor_parcela_liquido1 - $valor_parcela_juros1 - $valor_parcela_multa1 + $valor_parcela_desconto1;
                    $valor_parcela2 = $valor_parcela_liquido2 - $valor_parcela_juros2 - $valor_parcela_multa2 + $valor_parcela_desconto2;

                    if ($i == count($parcelas)) {
                        $total_desconto -= ($valor_parcela_desconto1);
                        $total_juros -= ($valor_parcela_juros1);
                        $total_multa -= ($valor_parcela_multa1);
                        $total_liquido -= ($valor_parcela_liquido1);

                        $valor_parcela_desconto2 = $total_desconto;
                        $valor_parcela_juros2 = $total_juros;
                        $valor_parcela_multa2 = $total_multa;
                        $valor_parcela_liquido2 = $total_liquido;
                        $valor_parcela2 = $valor_parcela_liquido2 - $valor_parcela_juros2 - $valor_parcela_multa2 + $valor_parcela_desconto2;
                    } else {
                        $total_desconto -= ($valor_parcela_desconto1 + $valor_parcela_desconto2);
                        $total_juros -= ($valor_parcela_juros1 + $valor_parcela_juros2);
                        $total_multa -= ($valor_parcela_multa1 + $valor_parcela_multa2);
                        $total_liquido -= ($valor_parcela_liquido1 + $valor_parcela_liquido2);
                    }

                    $parcelasnovo = array();
                    $parcn = new Financeiroparcela();
                    $parcn["grupo_id"] = $financeiroparcela->grupo_id;
                    $parcn["empresa_id"] = $financeiroparcela->empresa_id;
                    $parcn["numero"] = $financeiroparcela->numero;
                    $parcn["datacompetencia"] = $financeiro->datacompetencia;
                    $parcn["datavencimento"] = $financeiroparcela->datavencimento;
                    $parcn["datahorabaixa"] = null; //$data_pagamento;
                    $parcn["valor"] = $valor_parcela1;
                    $parcn["multa"] = $valor_parcela_multa1;
                    $parcn["juros"] = $valor_parcela_juros1;
                    $parcn["desconto"] = $valor_parcela_desconto1;
                    $parcn["valorefetivado"] = $valor_parcela_liquido1;
                    $parcn["pagarreceber"] = $financeiroparcela->pagarreceber;
                    $parcn["baixado"] = true;
                    array_push($parcelasnovo, $parcn);

                    $parcn = new Financeiroparcela();
                    $parcn["grupo_id"] = $financeiroparcela->grupo_id;
                    $parcn["empresa_id"] = $financeiroparcela->empresa_id;
                    $parcn["numero"] = $financeiroparcela->numero;
                    $parcn["datacompetencia"] = $financeiro->datacompetencia;
                    $parcn["datavencimento"] = $financeiroparcela->datavencimento;
                    $parcn["datahorabaixa"] = null; //$data_pagamento;
                    $parcn["valor"] = $valor_parcela2;
                    $parcn["multa"] = $valor_parcela_multa2;
                    $parcn["juros"] = $valor_parcela_juros2;
                    $parcn["desconto"] = $valor_parcela_desconto2;
                    $parcn["valorefetivado"] = $valor_parcela_liquido2;
                    $parcn["pagarreceber"] = $financeiroparcela->pagarreceber;
                    $parcn["baixado"] = false;
                    array_push($parcelasnovo, $parcn);
                    //if($i==2)

                    $processor->setParcelas($parcelasnovo);
                    if (!$processor->reparcelar()) {
                        throw new \Exception(implode(' ', $processor->getErrors()));
                    }
                    foreach ($processor->getParcelasBaixadas() as $parcid) {
                        array_push($parcelas_baixadas, $parcid);
                    }
                    //echo(' | ');
                }

                if ($withTransaction) DB::commit();

                return 'OK|' . implode('|', $parcelas_baixadas);
            } else {
                // NÃO É PARCIAL
                // =============
                $parcelas = json_decode($data["parcelas"])->data;
                $parcs = array();
                $movtos = array();

                $valor_desconto = floatval(insertNumeroDecimalOracle($data["valor_desconto"]));
                $valor_multa = floatval(insertNumeroDecimalOracle($data["valor_multa"]));
                $valor_juros = floatval(insertNumeroDecimalOracle($data["valor_juros"]));
                $valor_total = floatval(insertNumeroDecimalOracle($data["valor_total"]));
                $valor_liquido = floatval(insertNumeroDecimalOracle($data["valor_liquido"]));
                $total_desconto = 0;
                $requestRateio = $request->only('rateio')["rateio"];
                if ($requestRateio != null)
                    $rateio = json_decode($requestRateio);
                else
                    $rateio = [];
                if (count($rateio) > 0) {
                    foreach ($parcelas as $parc) {
                        $detalhe = Financeiroparcela::findOrFail($parc[0]);
                        //$descricao = $parc[4] . ' DE ' . $parc[3];
                        array_push($parcs, $parc[0]);
                        $idx = 0;
                        $valor_originalT = $parc[5];
                        $valor_descontoT = $parc[5] - abs($parc[7]);
                        $valor_liquidoT = $parc[7];

                        foreach ($rateio as $rat) {
                            $movto = new Contamovimento();
                            $movto->financeiroparcela_id = $parc[0];
                            $movto->datahorabaixa = $datapagamento;
                            $movto->contamovimentotipo_id = $rat[0];
                            $movto->conta_id = $data["conta_id"];
                            $movto->contatransferencia_id = null;
                            $movto->origem = 'FIN';
                            $movto->descricao = $descricao; //'REF '.$parc[4].' EM '.Carbon::now()->format('d/m/Y H:i:s');
                            $movto->multa = 0;
                            $movto->juros = 0;
                            $movto->pagarreceber = $parc[1];
                            $idx++;
                            if ($idx < count($rateio)) {
                                $movto->valor = round(floatval(insertNumeroDecimalOracle($rat[2])) / $valor_liquido * $parc[5], 2);

                                $movto->desconto = round(floatval(insertNumeroDecimalOracle($rat[2])) / $valor_liquido * (floatval(insertNumeroDecimalOracle($parc[5])) - abs(floatval(insertNumeroDecimalOracle($parc[7])))), 2);
                                $movto->valorefetivado = $movto->valor - $movto->desconto;
                                $valor_originalT -= $movto->valor;
                                $valor_descontoT -= $movto->desconto;
                                $valor_liquidoT -= $movto->valorefetivado;
                            } else {
                                //ULTIMA PARCELA
                                $movto->valor = $valor_originalT; //$parc[5];
                                $movto->desconto = $valor_descontoT; //$parc[5] - abs($parc[7]);
                                $movto->valorefetivado = $valor_liquidoT; //$parc[7];
                            }
                            array_push($movtos, $movto);
                            $total_desconto += $movto->desconto;
                        }
                    }
                } else {
                    foreach ($parcelas as $parc) {
                        $detalhe = Financeiroparcela::findOrFail($parc[0]);
                        //$descricao = $parc[4] . ' DE ' . $parc[3];
                        array_push($parcs, $parc[0]);
                        $movto = new Contamovimento();
                        $movto->financeiroparcela_id = $parc[0];
                        $movto->datahorabaixa = $datapagamento;
                        $movto->contamovimentotipo_id = $data["contamovimentotipo_idM"];
                        $movto->pagarreceber = $parc[1];
                        $movto->conta_id = $data["conta_id"];
                        $movto->valor = $parc[5];
                        $movto->multa = 0;
                        $movto->juros = 0;
                        $movto->desconto = $parc[5] - abs($parc[7]);
                        $movto->valorefetivado = $parc[7];
                        $movto->contatransferencia_id = null;
                        $movto->origem = 'FIN';
                        $movto->descricao = $descricao; //'REF '.$parc[4].' EM '.Carbon::now()->format('d/m/Y H:i:s');
                        array_push($movtos, $movto);
                        $total_desconto += $movto->desconto;
                    }
                }

                $processor = new caixaProcessor($data['conta_id']);
                $processor->setMovimentos($movtos);
                $processor->setValorDesconto($valor_desconto);
                $processor->setValorMulta($valor_multa);
                $processor->setValorJuros($valor_juros);
                $processor->setValorTotal($valor_total);
                if ($baixarfechado == 1) {
                    $processor->setLancarFechado(true);
                    $processor->setContaFechamento($fechamentos->first());
                }

                if (!$processor->validarBaixaTitulos()) {
                    throw new \Exception(implode(' ', $processor->getErrors()));
                }
                if (!$processor->baixarTitulos()) {
                    throw new \Exception(implode(' ', $processor->getErrors()));
                } else {
                    if ($withTransaction) DB::commit();
                    return 'OK|';
                }
            }
        } catch (\Exception $e) {
            if (!$withTransaction) {
                throw $e;
            }

            if ($withTransaction) DB::rollback();

            return getErrorsException($e);
        }
    }

    private function baixarParcelasEncontroContas($parcelas, $contamovimentotipo_id, $conta_id, $datapagamento)
    {
        $movtos = [];
        $valorTotal = 0;
        foreach ($parcelas as $parc) {
            $detalhe = Financeiroparcela::findOrFail($parc);
            $movto = new Contamovimento();
            $movto->financeiroparcela_id = $parc;
            $movto->datahorabaixa = $datapagamento;
            $movto->contamovimentotipo_id = $contamovimentotipo_id;
            $movto->pagarreceber = $detalhe->pagarreceber;
            $movto->conta_id = $conta_id;
            $movto->valor = $detalhe->valor;
            $valorTotal += $detalhe->valor;
            $movto->multa = 0;
            $movto->juros = 0;
            $movto->desconto = 0;
            $movto->valorefetivado = $detalhe->valorefetivado;
            $movto->contatransferencia_id = null;
            $movto->origem = 'FIN';
            $movto->descricao = $detalhe->financeiro->descricao . ' - ENCONTRO DE CONTAS';
            array_push($movtos, $movto);
        }

        $processor = new caixaProcessor($conta_id);
        $processor->setMovimentos($movtos);
        $processor->setValorTotal($valorTotal);

        if (!$processor->validarBaixaTitulos())
            return implode(' ', $processor->getErrors());

        if (!$processor->baixarTitulos())
            return implode(' ', $processor->getErrors());
        return true;
    }

    public function cancelar(Request $request)
    {
        $data = $request->only('conta_id', 'data_pagamentoM', 'parcelas', 'descricao');

        $parcelas = json_decode($data["parcelas"])->data;
        $parcs = array();
        $movtos = array();
        foreach ($parcelas as $parc) {
            $detalhe = Financeiroparcela::findOrFail($parc[0]);
            $descricao = 'CANCELAMENTO DE PARCELA COD.' . $parc[0] . ' DE ' . $parc[3] . '. MOTIVO: "' . $data['descricao'] . '"';
            array_push($parcs, $parc[0]);
            $movto = new Contamovimento();
            $movto->financeiroparcela_id = $parc[0];
            //$movto->grupo_id = Session::get('empresa_padrao')->grupo_id;
            //$movto->empresa_id = Session::get('empresa_padrao')->id;
            $movto->datahorabaixa = Carbon::createFromFormat('d/m/Y H:i:s', $data["data_pagamentoM"]);
            $movto->contamovimentotipo_id = null;
            $movto->pagarreceber = $parc[1];
            $movto->conta_id = $data["conta_id"];
            $movto->valor = $parc[5];
            $movto->multa = 0;
            $movto->juros = 0;
            $movto->desconto = 0;
            $movto->valorefetivado = 0;
            $movto->contatransferencia_id = null;
            $movto->origem = 'CAN';
            $movto->descricao = $descricao;
            array_push($movtos, $movto);
        }
        $processor = new caixaProcessor($data['conta_id']);
        $processor->setMovimentos($movtos);
        $processor->setValorDesconto(0);
        $processor->setValorMulta(0);
        $processor->setValorJuros(0);
        $processor->setValorTotal(0);
        $processor->setCancelar(true);
        if (!$processor->validarBaixaTitulos()) {
            return implode(' ', $processor->getErrors());
        }
        if (!$processor->baixarTitulos()) {
            return implode(' ', $processor->getErrors());
        } else {
            return 'OK|';
        }
    }

    public function gerarRecibo(Request $request)
    {
        $contamovimento_id = $request->only('id')['id'];
        $ok = true;
        $msg = '';
        $movtos = Contamovimento::find($contamovimento_id);
        if ($movtos == null) {
            $ok = false;
            $msg = 'Movimento de conta não encontrado';
        }
        $empresa_razao_social = Session::get('empresa_padrao')->razao_social;
        $empresa_cnpj = Session::get('empresa_padrao')->cnpj;
        if ($movtos->financeiroparcela != null) {
            if (!$movtos->financeiroparcela->baixado) {
                $ok = false;
                $msg = 'Esta parcela não está paga. Provavelmente, houve estorno posterior ao pagamento.';
            }
            $empresa = Empresa::find($movtos->financeiroparcela->empresa_id);
            $empresa_razao_social = $empresa->razao_social;
            $empresa_cnpj = $empresa->cnpj;
        }

        $movtos['empresa_razao_social'] = $empresa_razao_social;
        $movtos['empresa_cnpj'] = $empresa_cnpj;

        return ['ok' => $ok, 'msg' => $msg, 'movto' => $movtos];
    }

    public function gerarReciboCR(Request $request)
    {
        $parcelaspar = explode(',', $request->only('parcelas')["parcelas"]);
        $parcelas = Financeiroparcela::whereIn('id', $parcelaspar)->get();
        $empresa_razao_social = Session::get('empresa_padrao')->razao_social;
        $empresa_cnpj = Session::get('empresa_padrao')->cnpj;

        $movtos = array();
        foreach ($parcelas as $parc) {
            $empresa = Empresa::find($parc->empresa_id);
            $empresa_razao_social = $empresa->razao_social;
            $empresa_cnpj = $empresa->cnpj;

            // colunas reais (Postgres): pagarreceber e datahorabaixa
            // (antes pagar_receber/data_pagamento, inexistentes → recibo CR quebrava).
            $mov = Contamovimento::with('financeiroparcela')
                ->where('financeiroparcela_id', $parc->id)
                ->where('pagarreceber', $parc->pagarreceber)
                ->orderBy('datahorabaixa', 'desc')
                ->get()
                ->first();
            if (is_null($mov))
                continue;
            $mov['empresa_razao_social'] = $empresa_razao_social;
            $mov['empresa_cnpj'] = $empresa_cnpj;
            //$mov->empresa = null;
            array_push($movtos, $mov);
        }
        $ok = true;
        $msg = '';
        if (count($movtos) == 0) {
            $ok = false;
            $msg = 'Movimento de conta não encontrado';
        }

        return ['ok' => $ok, 'msg' => $msg, 'movto' => $movtos];
    }

    public function fecharModalIframe()
    {
        return view('general.close_modal');
    }

    public function estornarLancamentoCaixa(Request $request)
    {
        $contamovimento_id = $request->only('contamovimento_id')["contamovimento_id"];
        $contafechamento_id = $request->only('contafechamento_id')["contafechamento_id"];
        $motivo = $request->only('motivo')["motivo"];
        $mov = Contamovimento::find($contamovimento_id);
        $processor = new caixaProcessor($mov->conta_id);
        $parc = $mov->financeiroparcela;
        if ($mov->origem == "CHQ" || strpos($mov->descricao, 'cheque n') != false)
            return response()->json('Esta movimentação foi gerada a partir de um cheque. Para realizar o estorno você precisa ir até os cheques emitidos/recebidos!');
        if ($parc != null) {

            if ($parc->pagarreceber == "R")
                $numcheque = !is_null($parc->chequeEmitidoEncontroContas->first()) ? $parc->chequeEmitidoEncontroContas->first()->chequeemitido->numerocheque : '';
            else
                $numcheque = !is_null($parc->chequeRecebidoEncontroContas->first()) ? $parc->chequeRecebidoEncontroContas->first()->chequerecebido->numerocheque : '';

            if ($numcheque == '') {
                if ($parc->pagarreceber == "R")
                    $numcheque = !is_null($parc->chequeRecebidoFinanceiro->first()) ? $parc->chequeRecebidoFinanceiro->first()->numerocheque : '';
                else
                    $numcheque = !is_null($parc->chequeEmitidoFinanceiro->first()) ? $parc->chequeEmitidoFinanceiro->first()->numerocheque : '';
            }

            if ($numcheque !== '')
                return response()->json('Esta movimentação foi gerada a partir de um cheque. Para realizar o estorno você precisa ir até os cheques emitidos/recebidos!');

            $fin = new Financeiro();
            $estorno = $this->validaEstornoTaxa($parc, $fin);
            if ((is_bool($estorno) && !$estorno) || !is_bool($estorno))
                return $estorno;

            $movs = Contamovimento::where('financeiroparcela_id', $parc->id)
                ->where('contafechamento_id', $mov->contafechamento_id)
                ->get();
            $processor->setMovimentosEstorno($movs);
        } else {
            $processor->setMovimentosEstorno([$mov]);
        }
        $contafechamento = null;
        if ($contafechamento_id != -1) {
            $contafechamento = Contafechamento::find($contafechamento_id);
        }
        $processor->setMotivoEstorno($motivo);
        $processor->setLancarFechado($contafechamento_id != -1 && $contafechamento->fechado);
        $processor->setContaFechamento($contafechamento);
        if (!$processor->validarEstornoCaixa()) {
            return response()->json(implode(' ', $processor->getErrors()));
        }
        if ($mov->contaTransferencia != null) {
            $movtos = Contamovimento::where('contatransferencia_id', $mov->contatransferencia_id)->get();
            foreach ($movtos as $mv) {
                $contafechamentoDestino = Contafechamento::find($mv->contafechamento_id);
                $processorOrigem = new caixaProcessor($mv->conta_id);
                $processorOrigem->setLancarFechado($contafechamentoDestino->fechado);
                $processorOrigem->setContaFechamento($contafechamentoDestino);
                if (!$processorOrigem->validarTransferenciaCaixa($mv->contaTransferencia))
                    return response()->json(implode(' ', $processorOrigem->getErrors()));
            }
        }
        if (!$processor->EstornarCaixa()) {
            return response()->json(implode(' ', $processor->getErrors()));
        }
        return response()->json('OK|');
    }

    private function estornarLancamentoFromCartao($parcelasCPCartao, $fin, $estornoTaxa = false)
    {
        $request = new Request();
        $request->replace(['parcelas' => implode(",", $parcelasCPCartao->pluck('id')->toArray()), 'motivo' => "Parcelas CR Pgto tipo cartão estornadas"]);
        $estorno = $this->estornarLancamentoCR($request, $fin, $estornoTaxa);
        if ($estorno !== "OK|") {
            return $estorno;
        }
        $request = new Request();
        $data = [];
        foreach ($parcelasCPCartao as $parc) {
            $d = [
                $parc->id
            ];
            array_push($data, $d);
        }
        $parcelas = json_encode((object) [
            'data' => $data
        ]);
        $request->replace(['parcelas' => $parcelas, 'descricao' => "Estorno parcela da taxa de cartão"]);
        $controller = new FinanceiroController();
        $canc = $controller->cancelar($request);
        if ($canc !== "OK|")
            return $canc;
        return "OK|";
    }

    public function estornarLancamentoCR(Request $request, Financeiro $fin, $estornoTaxa = false)
    {
        $parcelaspar = explode(',', $request->only('parcelas')["parcelas"]);
        $parcelas = Financeiroparcela::with('financeiroTaxa.parcelas')->whereIn('id', $parcelaspar)->get();
        $financeiro = $parcelas->first()->financeiro;

        if ($financeiro->pagarreceber === "R")
            $this->authorize('estornarRecebimento', $financeiro);
        else
            $this->authorize('estornarPagamento', $financeiro);

        $this->authorize('igualdadeReceber', $financeiro);
        try {
            $motivo = $request->only('motivo')["motivo"];
            $movtos = array();
            foreach ($parcelas as $parc) {
                $mov = Contamovimento::where('financeiroparcela_id', $parc->id)
                    ->where('pagarreceber', $parc->pagarreceber)
                    ->orderBy('datahorabaixa', 'desc')
                    ->get();

                foreach ($mov as $m)
                    array_push($movtos, $m);
                $estorno = $this->validaEstornoTaxa($parc, $fin);

                if ((is_bool($estorno) && !$estorno) || !is_bool($estorno))
                    return $estorno;
            }

            $contas = array();
            foreach ($movtos as $movto) {
                if (!array_key_exists($movto->conta_id, $contas)) {
                    $contas[$movto->conta_id] = [$movto];
                } else {
                    array_push($contas[$movto->conta_id], $movto);
                }
            }
            foreach ($contas as $key => $value) {
                $processor = new caixaProcessor($key);
                $processor->setMovimentosEstorno($value);
                $processor->setMotivoEstorno($motivo);
                $processor->setLancarFechado($estornoTaxa);
                if (!$processor->validarEstornoCaixa()) {
                    if ($estornoTaxa) {
                        return implode(' ', $processor->getErrors());
                    }
                    return response()->json(implode(' ', $processor->getErrors()));
                }
            }
            foreach ($contas as $key => $value) {
                $processor = new caixaProcessor($key);
                $processor->setMovimentosEstorno($value);
                $processor->setMotivoEstorno($motivo);
                $processor->setLancarFechado($estornoTaxa);
                if (!$processor->EstornarCaixa()) {
                    if ($estornoTaxa) {
                        return implode(' ', $processor->getErrors());
                    }
                    return response()->json(implode(' ', $processor->getErrors()));
                }
            }
            if ($estornoTaxa) {
                return "OK|";
            }
            return response()->json('OK|');
        } catch (\Exception $e) {
            if ($estornoTaxa) {
                return $e->getMessage();
            }
            return getErrorsException($e);
        }
    }

    private function validaEstornoTaxa($parc, $fin)
    {
        $parcelasCPCartao = $parc->financeiroTaxa;
        if (!is_null($parcelasCPCartao)) {
            $parcelasCPCartao = $parcelasCPCartao->parcelas;
            $estorno = $this->estornarLancamentoFromCartao($parcelasCPCartao, $fin, true);

            if ($estorno !== "OK|")
                return $estorno;
        }
        $parc->update(['financeirotaxa_id' => null]);
        return true;
    }

    private function validateDate($data)
    {
        $now = Carbon::now()->addMinute(2);
        $greaterOrEqual = $now->greaterThanOrEqualTo($data);

        if (!$greaterOrEqual) {
            throw new \Exception("Lançamentos com datas futuras tem um limite de 2 minutos.");
        }

        return;
    }
}
