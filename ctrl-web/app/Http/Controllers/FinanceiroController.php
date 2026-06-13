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
use App\Financeiroparcela;
use App\Contamovimentotipo;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Processors\financeiroProcessor;

class FinanceiroController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function contasreceberindex(Financeiro $fin)
    {
        $this->authorize('viewReceber', $fin);
        $tipo_lancamento = 'R';
        return view('financeiro.contasreceber', compact('tipo_lancamento'));
    }

    public function contaspagarindex()
    {
        $tipo_lancamento = 'P';
        return view('financeiro.contasreceber', compact('tipo_lancamento'));
    }

    public function create_despesa(Financeiro $fin)
    {
        $this->authorize('view', $fin);
        $tipo_lancamento = 'P';
        return $this->create_financeiro($tipo_lancamento);
    }

    public function create_receita(Financeiro $fin)
    {
        $this->authorize('viewReceita', $fin);
        $tipo_lancamento = 'R';
        return $this->create_financeiro($tipo_lancamento);
    }

    public function create_financeiro($tipo_lancamento)
    {
        $conta_id = '';
        $contafechamento_id = '';

        $voltar = 1;
        $planocontas = Planoconta::menuspermissoesAll();
        if ($tipo_lancamento == 'P') {
            $planocontas = Planoconta::despesas();
        } else {
            $planocontas = Planoconta::receitas();
        }
        $centrocustos = Centrocusto::menuspermissoesAll();
        $condicaopagamentos = Condicaopagamento::where('ativo', true)->where('grupo_id',
                        Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $contamovimentotipos = Contamovimentotipo::whereIn('pagarreceber', [$tipo_lancamento, 'A'])->where('ativo', true)->where('grupo_id',
                        Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $planoconta_id = '';
        $planoconta_descricao = '';
        $centrocusto_id = '';
        $centrocusto_descricao = '';
        $tipo_tela = 'COMPLEXA';
        $baixar = false;
        $condicaopagamentos->prepend('', 0);

        return view('financeiro.financeiro_form',
                compact('tipo_lancamento', 'voltar', 'planocontas', 'centrocustos', 'planoconta_id',
                        'planoconta_descricao', 'centrocusto_id', 'centrocusto_descricao', 'tipo_tela', 'baixar',
                        'contamovimentotipos', 'conta_id', 'contafechamento_id', 'condicaopagamentos'));
    }

    public function createbycaixa(Request $request)
    {
        $tipo_lancamento = '';
        $conta_id = '';
        $contafechamento_id = '';
        if ($request != null) {
            $data = $request->only('tipo_lancamento', 'conta_id', 'contafechamento_id');
            $tipo_lancamento = $data['tipo_lancamento'];
            $conta_id = $data['conta_id'];
            if ($tipo_lancamento == 'D') {
                $tipo_lancamento = 'R';
            }
            $contafechamento_id = $data['contafechamento_id'];
        }
        $voltar = 0;
        $planocontas = Planoconta::menuspermissoesAll();
        if ($tipo_lancamento == 'P') {
            $planocontas = Planoconta::despesas();
        } else {
            $planocontas = Planoconta::receitas();
        }
        $centrocustos = Centrocusto::menuspermissoesAll();
        $contamovimentotipos = Contamovimentotipo::whereIn('pagarreceber', [$tipo_lancamento, 'A'])->where('ativo', true)->where('grupo_id',
                        Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $planoconta_id = '';
        $planoconta_descricao = '';
        $centrocusto_id = '';
        $centrocusto_descricao = '';
        $tipo_tela = 'SIMPLES';
        $baixar = 1;
        $condicaopagamentos = Condicaopagamento::where('ativo', true)->where('tipo', 0)->where('grupo_id',
                        Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id')->prepend('',
                'Selecione');

        return view('financeiro.financeiro_form_bycaixa',
                compact('tipo_lancamento', 'voltar', 'planocontas', 'centrocustos', 'planoconta_id',
                        'planoconta_descricao', 'centrocusto_id', 'centrocusto_descricao', 'tipo_tela', 'baixar',
                        'contamovimentotipos', 'conta_id', 'contafechamento_id', 'condicaopagamentos'));
    }

    public function createbyagrupar(Request $request, Financeiro $fin)
    {

        $tipo_lancamento = '';
        $tipo_cliente = '';
        $conta_id = '';
        $origemAgrupar = 'agrupar';
        if ($request != null) {
            $data = $request->only('tipo_lancamento', 'conta_id', 'cliente_id', 'parcelas_financeiro', 'nome');
            $tipo_lancamento = $data['tipo_lancamento'];
            if ($tipo_lancamento == "R")
                $this->authorize('agruparReceber', $fin);
            else
                $this->authorize('agruparPagamento', $fin);
            $conta_id = $data['conta_id'];
            $tipo_cliente = '';
        }
        $cliente_id = $data["cliente_id"];
        $parcelas_financeiro = json_decode($data["parcelas_financeiro"]);
        $total_parcelas = count($parcelas_financeiro);
        $valor = 0;
        $desconto = 0;
        $rateiosFinal = Array();
        $parcelas = Array();
        $rateiostotal = 0;
        $documento = '';
        foreach ($parcelas_financeiro as $parcela) {
            array_push($parcelas, $parcela->id);
            $parcela->valor = strpos($parcela->valor, 'R$') !== false ? insertNumeroDecimalOracle($parcela->valor) : $parcela->valor;

            $valor += $parcela->valor;

            $desconto += strpos($parcela->desconto, 'R$') !== false ? insertNumeroDecimalOracle($parcela->desconto) : $parcela->desconto;

            $parcDb = Financeiroparcela::with('financeiro.rateios')->find($parcela->id);

            $this->checkForBoleto($parcDb);

            $financeiro = $parcDb->financeiro;

            $rateios = $parcDb->financeiro->rateios()->get();

            foreach ($rateios as $rateio) {
                $ratDesc = $rateio->planoconta_id . "|" . $rateio->centrocusto_id;

                if (!array_key_exists($ratDesc, $rateiosFinal)) {
                    $rateiosFinal[$ratDesc] = [
                        $rateio->centrocusto_id,
                        $rateio->centroCusto->descricao,
                        $rateio->planoconta_id,
                        $rateio->planoConta->descricao,
                        round($rateio->valor / $financeiro->valor * $parcela->valor,2)
                    ];

                    $rateiostotal += round($rateio->valor / $financeiro->valor * $parcela->valor, 2);
                } else {
                    $rateiosFinal[$ratDesc][4] += round($rateio->valor / $financeiro->valor * $parcela->valor, 2);
                    $rateiostotal += round($rateio->valor / $financeiro->valor * $parcela->valor, 2);
                }
            }

            if ($total_parcelas == 1) $documento = $parcDb->financeiro->documento;
        }
        if ($rateiostotal != $valor) {
            foreach ($rateiosFinal as $key => $value) {
                $rateiosFinal[$key][4] += ($valor - $rateiostotal);
                break;
            }
        }
        $nome = $data["nome"];
        $voltar = 0;
        $planocontas = Planoconta::menuspermissoesAll();
        if ($tipo_lancamento == 'P') {
            $planocontas = Planoconta::despesas();
        } else {
            $planocontas = Planoconta::receitas();
        }
        $centrocustos = Centrocusto::menuspermissoesAll();
        $contamovimentotipos = Contamovimentotipo::whereIn('pagarreceber', [$tipo_lancamento, 'A'])->where('ativo', true)->where('grupo_id',
                        Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $condicaopagamentos = Condicaopagamento::where('ativo', true)->where('grupo_id',
                        Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $condicaopagamentos->prepend('', 0);
        $planoconta_id = '';
        $planoconta_descricao = '';
        $centrocusto_id = '';
        $centrocusto_descricao = '';
        $contafechamento_id = '';
        $tipo_tela = 'COMPLEXA';
        $baixar = false;
        return view('financeiro.financeiro_form_bycaixa',
                compact('tipo_lancamento', 'voltar', 'planocontas', 'centrocustos', 'planoconta_id',
                        'planoconta_descricao', 'centrocusto_id', 'centrocusto_descricao', 'tipo_tela', 'baixar',
                        'contamovimentotipos', 'conta_id', 'contafechamento_id', 'cliente_id', 'nome', 'rateiosFinal',
                        'origemAgrupar', 'valor', 'parcelas', 'condicaopagamentos', 'desconto', 'documento'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $processor = new financeiroProcessor();
        $processor->setFinanceiroRequest($request);
        $processor->setRateiosRequest($request->only('rateio')["rateio"]);
        $processor->setParcelasRequest($request->only('parcelas')["parcelas"]);
        $processor->setTipoRetorno('redirect');
        $processor->setContaFechamentoId($request->only('contafechamento_id')["contafechamento_id"]);

        if (!$processor->validar($request)) {
            return implode(' ', $processor->getErrors());
        }

        if (!$processor->gravar()) {
            return implode(' ', $processor->getErrors());
        }
        if ($request->only('voltar')["voltar"] == 1) {
            if ($request->only('pagarreceber')["pagarreceber"] == 'R')
                return Redirect::to('financeiro.createReceita')->withMessageSuccess('Lançamento cadastrado com sucesso!');
            else
                return Redirect::to('financeiro.createDespesa')->withMessageSuccess('Lançamento cadastrado com sucesso!');
        } else {
            $empresa_razao_social = Session::get('empresa_padrao')->razao_social;
            $empresa_cnpj = Session::get('empresa_padrao')->cnpj;

            return 'OK|' . $empresa_razao_social . '|' . $empresa_cnpj;
        }
    }

    public function getLancamentosFinanceiros(Request $request)
    {
        //1 = Código do financeiro
        //2 = código Pedido
        //3 = nº NF
        //4 = Fechamento Convenio
        $tipoPesquisa = $request->only('tipoPesquisa')["tipoPesquisa"];
        $pagarreceber = $request->only('pagarreceber')["pagarreceber"];
        $valorPesquisa = (int) trim($request->only('valorPesquisa')["valorPesquisa"]);
        $cliente_id = $request->only('cliente_id')["cliente_id"];
        $colaborador_id = $request->only('colaborador_id')["colaborador_id"];
        $tipodata = $request->only('tipodata')["tipodata"];
        $status_id = $request->only('status_id')["status_id"];
        $sort = $request->only('sort')["sort"];
        $order = $request->only('order')["order"];
        $page = (int) $request->only('page')["page"] - 1;
        $dataini = Carbon::createFromFormat('d/m/Y H:i:s', $request->only('datainicio')["datainicio"] . ' 00:00:00');
        $datafin = Carbon::createFromFormat('d/m/Y H:i:s', $request->only('datafinal')["datafinal"] . ' 23:59:59');

        $dados = DB::table('financeiroparcelas')->where('financeiroparcelas.empresa_id',
                        Session::get('empresa_padrao')->id)
                ->join('financeiros', 'financeiros.id', '=', 'financeiroparcelas.financeiro_id')
                ->join('clientes', 'clientes.id', '=', 'financeiros.cliente_id');
        if ($tipoPesquisa == 0) { //FILTRO
            if ($status_id == 1)
                $rawStatus = "baixado = 0";
            else if ($status_id == 2)
                $rawStatus = "baixado = 1 and agrupamento_status <= 1";
            else if ($status_id == 4)
                $rawStatus = "baixado = 1 and agrupamento_status in (2,3)";
            else
                //$rawStatus = "agrupamento_status <> 2";
                $rawStatus = "agrupamento_status >= 0";

            $dados->where('financeiroparcelas.pagarreceber', $pagarreceber);

            if ($tipodata == 1 || $tipodata == 2 || $tipodata == 3) {
                $dateFormat = "'yyyy-mm-dd HH24:MI:SS'";
                if ($tipodata == 1)
                    $rawDate = "datavencimento";
                elseif($tipodata == 2)
                    $rawDate = "dataemissao";
                else
                    $rawDate = "datahorabaixa";
                $rawDate .= " between TO_DATE('$dataini', $dateFormat) AND TO_DATE('$datafin', $dateFormat)";
            } else {
                $rawDate = '1 = 1';
            }

            $dados->whereRaw("(($rawStatus) AND ($rawDate))");
            if ($cliente_id != '')
                $dados->where('financeiros.cliente_id', $cliente_id);
            else
                $cliente_id = -1;

            if ($colaborador_id != '') {
                $empresa_id = Session::get('empresa_padrao')->id;
                $rawSetor =  /** @lang text */
                "SELECT DISTINCT SETOR_ID FROM SETORCOLABORADORES WHERE COLABORADOR_ID = $colaborador_id";
                $pednf_fin = /** @lang text */
                    "SELECT DISTINCT financeiro_id FROM (SELECT DISTINCT p.financeiro_id FROM pedidos P"
                        . " INNER JOIN FINANCEIROS F ON F.ID = P.FINANCEIRO_ID"
                        . " INNER JOIN FINANCEIROPARCELAS PAR ON F.ID = PAR.FINANCEIRO_ID"
                        . " WHERE P.colaborador_id = $colaborador_id "
                        . " AND (F.CLIENTE_ID = $cliente_id OR $cliente_id = -1)"
                        . " AND $rawStatus AND $rawDate AND par.pagarreceber = '$pagarreceber'"
                        . " AND P.EMPRESA_ID = $empresa_id"
                        . " UNION ALL SELECT DISTINCT N.FINANCEIRO_ID FROM NFEMITIDAS N "
                        . " INNER JOIN NFEMITIDAITEMS I ON I.NFEMITIDA_ID = N.ID"
                        . " INNER JOIN FINANCEIROS F ON F.ID = N.FINANCEIRO_ID"
                        . " INNER JOIN FINANCEIROPARCELAS PAR ON F.ID = PAR.FINANCEIRO_ID"
                        . " WHERE I.SETOR_ID IN ($rawSetor) "
                        . " AND $rawStatus AND $rawDate AND par.pagarreceber = '$pagarreceber'"
                        . " AND (F.CLIENTE_ID = $cliente_id OR $cliente_id = -1)"
                        . " AND N.financeiro_id IS NOT NULL"
                        . " AND N.EMPRESA_ID = $empresa_id)";
                $dados->whereRaw("(FINANCEIROS.ID IN ($pednf_fin))");
            }
        } else {
            switch ((int) $tipoPesquisa) {
                case 1:
                    $dados->where('financeiros.id', $valorPesquisa);
                    break;
                case 2:
                    $dados->whereRaw("(financeiros.id in (SELECT FINANCEIRO_ID FROM PEDIDOS WHERE ID = $valorPesquisa) OR financeiros.documento = '$valorPesquisa')");
                    break;
                case 3:
                    $dados->whereRaw("financeiros.id in (SELECT FINANCEIRO_ID FROM NFEMITIDAS WHERE NFNUMERO = $valorPesquisa)");
                    break;
                case 4;
                    $dados->whereRaw("financeiros.id in (SELECT FINANCEIRO_ID FROM CONVENIOFECHAMENTOS WHERE ID = $valorPesquisa)");
                    break;
                case 5:
                    $dados->whereRaw("financeiros.id in (SELECT FINANCEIRO_ID FROM NFRECEBIDAS WHERE NFNUMERO = $valorPesquisa)");
                    break;
                default:
                    return "Tipo de pesquisa inválido";
            }
        }
        $dados->leftJoin('boletos b', 'b.financeiroparcela_id', 'financeiroparcelas.id');
        if ($pagarreceber == 'R') {
            $dados->leftJoin('chequerecebidofinanceiros cheque', 'cheque.financeiroparcela_id', 'financeiroparcelas.id');
            $dados->leftJoin('chequeemitidoencontrocontas encontrocontas', 'encontrocontas.financeiroparcela_id',
                    'financeiroparcelas.id');
            $dados->leftJoin('chequeemitidofinanceiros encontrocontascheque', 'encontrocontascheque.id',
                    'encontrocontas.chequeemitido_id');
        } else {
            $dados->leftJoin('chequeemitidofinanceiros cheque', 'cheque.financeiroparcela_id', 'financeiroparcelas.id');
            $dados->leftJoin('chequerecebidoencontrocontas encontrocontas', 'encontrocontas.financeiroparcela_id',
                    'financeiroparcelas.id');
            $dados->leftJoin('chequerecebidofinanceiros encontrocontascheque', 'encontrocontascheque.id',
                    'encontrocontas.chequerecebido_id');
        }

        $rawRecebido = "CASE WHEN baixado = 1 THEN valorefetivado ELSE 0 END";
        $rawReceber = "CASE WHEN agrupamento_status <= 1 AND baixado = 0 THEN valorefetivado ELSE 0 END";

        $countResults = $dados->select(DB::raw('count(*) as count'))->get()->first();
        if (!is_null($countResults) && $countResults->count > 20000)
            return "O número de registros é muito grande e pode causar uma sobrecarga. Por favor, utilize os filtros para trazer um número menor resultados.";

        $groupByQuery = "financeiroparcelas.id, financeiros.documento, financeiroparcelas.numero, "
                . "dataemissao, datavencimento, datahorabaixa, financeiroparcelas.valor, "
                . "financeiroparcelas.multa, financeiroparcelas.juros, financeiroparcelas.desconto, "
                . "financeiroparcelas.valorefetivado, clientes.nome, financeiros.descricao, "
                . "financeiroparcelas.baixado, financeiros.cliente_id, financeiroparcelas.agrupamento_status, "
                . "financeiros.cartaoautorizacao, cheque.numerocheque, encontrocontascheque.numerocheque, "
                . "b.nossonumero, $rawRecebido, $rawReceber";

        $results = $dados->selectRaw("financeiroparcelas.id, financeiros.documento, financeiroparcelas.numero, "
                                . "dataemissao, datavencimento, datahorabaixa, financeiroparcelas.valor, financeiroparcelas.multa, "
                                . "financeiroparcelas.juros, financeiroparcelas.desconto, financeiroparcelas.valorefetivado, clientes.nome, "
                                . "financeiros.descricao, financeiroparcelas.baixado, financeiros.cliente_id, financeiroparcelas.agrupamento_status, "
                                . "financeiros.cartaoautorizacao, cheque.numerocheque as numcheque, encontrocontascheque.numerocheque as encontrocontasnumcheque, "
                                . "b.nossonumero as nossonumeroboleto, SUM($rawRecebido) over() as valorrecebido, SUM($rawReceber) over() as valorreceber")
                        ->groupBy(DB::raw($groupByQuery))
                        ->get()->sortBy($sort, SORT_NATURAL, strtoupper($order) !== 'ASC');

        $qdePesquisa = count($results);
        $last = $results->last();

        $resultsPerPage = 1000;

        $totalPages = (int) ceil($qdePesquisa / $resultsPerPage);

        $info = (object) [
                    'info'          => true,
                    'totalPages'    => $totalPages,
                    'atualPage'     => $page + 1,
                    'totalRecebido' => !is_null($last) ? $last->valorrecebido : 0,
                    'totalReceber'  => !is_null($last) ? $last->valorreceber : 0
        ];

        $results = $results->chunk($resultsPerPage);
        if ($page < 0)
            $page = 0;
        $count = $results->count();
        if ($count > 0) {
            for ($i = $page; $i >= 0; $i--) {
                if (isset($results[$i])) {
                    $results = $results[$i];
                    break;
                }
            }
        }

        return $this->formatParcelasToView($results, $pagarreceber, $info);
    }

    public function formatParcelasToView($parcelas, $pagarreceber = 'R', $info = null)
    {
        $rets = Array();
        foreach ($parcelas as $result) {
            $result->valor = requestNumeroDecimalOracle($result->valor);
            $result->multa = requestNumeroDecimalOracle($result->multa);
            $result->juros = requestNumeroDecimalOracle($result->juros);
            $result->desconto = requestNumeroDecimalOracle($result->desconto);
            $result->valorefetivado = requestNumeroDecimalOracle($result->valorefetivado);
            $result->status = '';
            if ($result->agrupamento_status == 2) {
                $result->status = 'BAI';
                $result->classTr = 'agrupado-parcela';
            } else if ($result->baixado) {
                $result->status = 'BAI';
                $result->classTr = 'baixado-parcela';
                if ($result->agrupamento_status == 3) {
                    $result->status = 'CAN';
                    $result->classTr = 'cancelado-parcela';
                }
            } else {
                if (Carbon::createFromFormat('Y-m-d H:i:s', substr($result->datavencimento, 0, 10) . ' 23:59:59') < Carbon::now()) {
                    $result->status = 'ATR';
                    $result->classTr = 'atrasado-parcela';
                } else {
                    $result->status = 'PEN';
                    $result->classTr = 'pendente-parcela';
                }
            }
            if (is_null($result->cartaoautorizacao))
                $result->cartaoautorizacao = '';
            $result->datavencimento = Carbon::parse($result->datavencimento)->format('d/m/Y');
            $result->dataemissao = Carbon::parse($result->dataemissao)->format('d/m/Y');

            if ($result->datahorabaixa != null && $result->datahorabaixa != '')
                $result->datahorabaixa = Carbon::parse($result->datahorabaixa)->format('d/m/Y');
            else
                $result->datahorabaixa = '';

            if ($pagarreceber == 'P')
                unset($result->nossonumeroboleto);

            if (strlen($result->numcheque) == 0)
                $result->numcheque = $result->encontrocontasnumcheque;

            unset($result->chequeEmitidoFinanceiro);
            unset($result->chequeRecebidoFinanceiro);
            unset($result->chequeEmitidoEncontroContas);
            unset($result->chequeRecebidoEncontroContas);
            unset($result->encontrocontasnumcheque);
            array_push($rets, $result);
        }
        if (!is_null($info))
            array_push($rets, $info);
        return $rets;
    }

    public function alterarDescricaoLancamento(Request $request, Financeiro $fin)
    {
        $parcelaspar = explode(',', $request->only('parcelas')["parcelas"]);
        $parcelas = Financeiroparcela::with('financeiro.condicaoPagamento')->whereIn('id', $parcelaspar)->get();
        $this->authorize('igualdadeReceber', $parcelas->first()->financeiro);
        if ($parcelas->first()->pagarreceber == 'R')
            $this->authorize('alterarRecebimento', $fin);
        else
            $this->authorize('alterarPagamento', $fin);
        $motivo = $request->only('motivo')["motivo"];
        $cartaonsu = $request->only('cartaonsu')["cartaonsu"] === "" ? null : $request->only('cartaonsu')["cartaonsu"];
        $cartaoautorizacao = $request->only('cartaoautorizacao')["cartaoautorizacao"] === "" ? null : $request->only('cartaoautorizacao')["cartaoautorizacao"];
        $vencimento_parcela = insertDataOracle($request->only('vencimento')["vencimento"]);
        $documento = $request->only('documento')["documento"];
        $processor = new financeiroProcessor();
        $processor->setParcelas($parcelas);
        $processor->setDescricao($motivo);
        $processor->setCartaoNsu($cartaonsu);
        $processor->setCartaoAutorizacao($cartaoautorizacao);
        $processor->setDataVencimento($vencimento_parcela);
        $processor->setDocumento($documento);
        if (!$processor->alterarDescricao()) {
            return implode(' ', $processor->getErrors());
        }
        return response()->json('OK|');
    }

    public function consultartitulos(Request $request)
    {
        if ($request != null) {
            $data = $request->only('parcelas');
            $parcela = $data['parcelas'];

            $parcelasdet = Financeiroparcela::find($parcela);
            if (is_null($parcelasdet)) {
                return "Parcela não encontrada";
            }
            $Financeiro = Financeiro::find($parcelasdet->financeiro_id);
            $parcelasagrupadas = Financeiroparcela::where('agrupador_financeiro_id', $Financeiro->id)->get();

            $tipo_lancamento = $Financeiro->pagarreceber;

            $voltar = 0;
            $condicaopagamentos = Condicaopagamento::where('ativo', true)->where('grupo_id',
                            Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->get();
            $cartaonsu = "";
            $cartaoautorizacao = "";
            if ($Financeiro->condicaoPagamento->tipo == 2 || $Financeiro->condicaoPagamento->tipo == 3) {
                $cartaoautorizacao = $Financeiro->cartaoautorizacao;
                $cartaonsu = $Financeiro->cartaonsu;
            }
            $condicaopagamentos = $condicaopagamentos->pluck('descricao', 'id')->prepend('', 0);
            return view('financeiro.financeiroconsulta_form_bycaixa',
                    compact('Financeiro', 'tipo_lancamento', 'voltar', 'condicaopagamentos', 'parcelasagrupadas',
                            'cartaonsu', 'cartaoautorizacao'));
        }
    }

    public function cancelar(Request $request)
    {
        $data = $request->only('parcelas', 'descricao');
        $parcelas = json_decode($data["parcelas"])->data;
        $parc_ids = Array();
        foreach ($parcelas as $parc) {
            array_push($parc_ids, $parc[0]);
        }
        $parcelas = Financeiroparcela::whereIn('id', $parc_ids)->get();
        $processor = new financeiroProcessor();
        $processor->setParcelas($parcelas);

        if (!$processor->validarCancelamentoParcelas()) {
            return implode(' ', $processor->getErrors());
        }
        if (!$processor->cancelarParcelas($data["descricao"])) {
            return implode(' ', $processor->getErrors());
        } else {
            return 'OK|';
        }
    }

    public function importReportCartaoIndex($data = null)
    {
        return view('financeiro.cartao.importreportcartao_index');
    }

    public function importReportCartaoGetParcelas(Request $request)
    {
        $file = $request->file('file-upload');

        if (is_null($file))
            return redirect('importReportCartao')->withErrors("Arquivo não encontrado!");

        $fileContents = file_get_contents($file->getRealPath());
        $collectionFile = collect(explode("\n", $fileContents));
        $collectionFile = $collectionFile->splice(1, count($collectionFile));
        $parcelas_id = [];
        foreach ($collectionFile as $key => $value) {

            if (empty($value)) {
                unset($collectionFile[$key]);
                continue;
            }

            $row = collect(explode(';', $value));
            if (isset($row[10]) && isset($row[1])) {
                $autorizacao = $row[10];
                $parcela = $this->getFinanceiroByAutorizacaoCartao($autorizacao, $row[1]);
            } else {
                $parcela = false;
            }

            if (is_bool($parcela)) {
                return redirect('importReportCartao')->withMessageDanger("O arquivo enviado não segue os padrões propostos.");
            }
            $row['financeiro'] = $parcela;
            if (!is_null($parcela))
                array_push($parcelas_id, $parcela->id);
            $collectionFile[$key] = $row;
        }
        $data = $collectionFile;
        $url = 'importReportCartao/' . implode(',', $parcelas_id);
        return view('financeiro.cartao.importreportcartao_index', compact('data', 'url'));
    }

    private function getFinanceiroByAutorizacaoCartao($autorizacao, $date)
    {
        try {
            $date = Carbon::createFromFormat('d/m/Y', $date)->startOfDay()->toDateTimeString();
            if ((int) $autorizacao == 0)
                throw new \InvalidArgumentException("Numero invalido!");
        } catch (\InvalidArgumentException $e) {
            return false;
        }
        //$raw = "cartaoautorizacao = $autorizacao and TRUNC(f.datacompetencia) = TO_DATE('$date', 'YYYY-MM-DD HH24:MI:SS')";
        $raw = "cartaoautorizacao = '$autorizacao' ";
        $raw .= " AND agrupamento_status <= 1 AND baixado = 0 ";
        return Financeiroparcela::with('financeiro.cliente')->join('financeiros f', 'financeiro_id', 'f.id')
                        ->select('financeiroparcelas.id', 'financeiroparcelas.valorefetivado', 'f.cartaoautorizacao',
                                'f.id as financeiro_id')
                        ->whereRaw($raw)->first();
    }

    public function contasReceberImportCartao($parcelas)
    {
        $tipo_lancamento = 'R';
        return view('financeiro.contasreceber', compact('tipo_lancamento', 'parcelas'));
    }

    private function checkForBoleto($parcela)
    {
        if ($parcela->boletogerado == '1') {
            throw new \Exception("Parcela: $parcela->id do Lançamento: $parcela->financeiro_id Possui um boleto gerado então não pode ser agrupada.");
        }
    }

}
