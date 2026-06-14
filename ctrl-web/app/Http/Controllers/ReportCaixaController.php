<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Session;
use App\User;
use App\Regiao;
use App\Empresa;
use App\Services\CarbonCustom as Carbon;
use App\Planoconta;
use App\Centrocusto;
use App\EmpresasGrupo;
use App\Contamovimento;
use App\Contafechamento;
use Illuminate\Http\Request;

class ReportCaixaController extends Controller
{

    private $datafim;
    private $datainicio;
    private $dataultimofechamento;
    private $filtros;
    private $despesasGeral;
    private $planos;
    private $planoAux;

    public function fluxoCaixa($pdf = false)
    {
        $contas = [];

        $empresas = $this->getEmpresasUser();

        if (count($_GET) > 0)
            return $this->getFiltersFluxoCaixa($_GET, $pdf, $empresas);

        $empresas = $empresas->pluck('nome_informal', 'id')->prepend('', '');
        return view('reports.caixa.fluxocaixa.fluxocaixa', compact('empresas', 'contas'));
    }

    private function getEmpresasUser()
    {
        return Auth::user()->empresas()->select('id', 'nome_informal');
    }

    public function fluxoCaixaPDF()
    {
        return $this->fluxoCaixa(true);
    }

    public function getFiltersFluxoCaixa($filtros, $pdf, $empresas)
    {
        $empresas_id = empty($filtros['empresa_id']) ? $empresas->pluck('id')->toArray() : explode(',', $filtros['empresa_id']);
        $ativo = $filtros['ativo'] == 'true' ? true : false;
        $contasOld = Auth::user()->contas()
            ->where('visualizar', '=', 1)
            ->where('empresa_id', $empresas_id)
            ->where('conta.ativo', $ativo)
            ->join('contas as conta', 'conta.id', 'contausers.conta_id')->select('conta.descricao', 'conta.id');
        $conta_id = empty($filtros['conta_id']) ? $contasOld->pluck('id')->toArray() : explode(',', $filtros['conta_id']);
        $this->datainicio = insertDataOracle($filtros['datainicio']);
        $this->datafim = insertDataOracle($filtros['datafim']);

        $fechamentos = Contafechamento::whereIn('conta_id', $conta_id)->where('fechado', 1)
            ->whereRaw("TO_CHAR(datahorafechamento, 'yyyy-mm-dd HH24:MI:SS') <= '" . $this->datainicio . " 00:00:00' and datahorafechamento is not null")
            ->orderBy('datahorafechamento', 'DESC')->get()->unique('conta_id');

        $movimentos = $this->getContaMovimentos($empresas_id, $conta_id, $ativo);

        $movSemFechamentos = $this->getMovimentosSemFechamentos($movimentos, $fechamentos, $conta_id);

        $movimentos = $movimentos->filter(function ($f) {
            return strtotime($f->datahorabaixa) >= strtotime($this->datainicio);
        });

        $saldoinicial = $fechamentos->sum('saldofinal') + $movSemFechamentos->where('pagarreceber', 'R')->sum('valorefetivado');
        $saldoinicial -= $movSemFechamentos->where('pagarreceber', 'P')->sum('valorefetivado');

        $contas = DB::table('contas')->whereIn(
            'id',
            $movimentos->whereNotIn('conta_id', $fechamentos->pluck('conta_id')->toArray())->unique('conta_id')->pluck('conta_id')->toArray()
        )->where('contas.ativo', $ativo)->select('saldoinicial', 'created_at', 'descricao')->get();

        $saldoinicial += $contas->sum('saldoinicial');
        $saldofinal = $saldoinicial + $movimentos->where('pagarreceber', 'R')->sum('valorefetivado') - $movimentos->where('pagarreceber', 'P')->sum('valorefetivado');

        $movimentos = $this->montarTableFluxo($movimentos, $fechamentos, $saldofinal, $saldoinicial, $conta_id, $movSemFechamentos, $ativo);

        $contas = $movimentos['contas'];
        $movimentos = $movimentos['movimentos'];

        $this->setFiltersReportFluxo($conta_id, $contasOld, $empresas, $empresas_id, $ativo);
        $filtro = $this->filtros;

        $titulo = 'Relatório de Movimentações de Caixas';

        if ($pdf) {
            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('A4', 'landscape');
            $pdf->loadView('reports.caixa.fluxocaixa.fluxocaixaPDF', compact('titulo', 'contas', 'movimentos', 'contas', 'filtro'));
            return $pdf->stream();
        }

        return view('reports.caixa.fluxocaixa.fluxocaixaPDF', compact('titulo', 'contas', 'movimentos', 'contas', 'filtro'));
    }


    private function getContaMovimentos($empresas_id, $conta_id, $ativo)
    {

        $joinContamovimentos = "SELECT MAX(datahorafechamento) AS datahorafechamento, conta_id AS conta_id_fechamento, fechado "
            . "FROM contafechamentos WHERE datahorafechamento IS NOT NULL AND fechado = 1 AND TO_CHAR(datahorafechamento, 'yyyy-mm-dd') "
            . "< '$this->datainicio' group by conta_id, fechado";

        $onClause = function ($join) {
            $join->on('fechamento.conta_id_fechamento', '=', 'conta.id');
        };
        $withParcelas = [
            // 'financeiroparcela',
            // 'financeiroparcela.financeiro' => function ($query) {
            //     $query->select('id', 'cliente_id');
            // },
            'financeiroparcela.financeiro.cliente' => function ($query) {
                $query->select('clientes.id', 'clientes.nome');
            }
        ];

        $movimentos = Contamovimento:: //with($withParcelas)
            join('contas conta', 'conta.id', 'contamovimentos.conta_id')
            ->leftJoin(DB::raw("($joinContamovimentos) fechamento"), $onClause)
            ->leftJoin("financeiroparcelas as parc", "contamovimentos.financeiroparcela_id", "parc.id")
            ->leftJoin("financeiros as fin", "parc.financeiro_id", "fin.id")
            ->leftJoin("clientes as cli", "fin.cliente_id", "cli.id")
            ->whereIn('conta.empresa_id', $empresas_id)
            ->whereIn('conta.id', $conta_id)
            ->where('conta.ativo', $ativo)
            ->whereRaw("TO_CHAR(contamovimentos.datahorabaixa, 'yyyy-mm-dd') BETWEEN "
                . "CASE WHEN fechamento.datahorafechamento IS NOT NULL "
                . "THEN TO_CHAR(fechamento.datahorafechamento, 'yyyy-mm-dd') "
                . "ELSE TO_CHAR(conta.created_at, 'yyyy-mm-dd') "
                . "END "
                . "AND '$this->datafim'")
            ->select(
                'conta.id as conta_id',
                'conta.created_at',
                'conta.empresa_id as empresa_id',
                'contamovimentos.financeiroparcela_id',
                'contamovimentos.datahorabaixa',
                'fechamento.datahorafechamento',
                'conta.saldoatual as saldoatual_conta',
                'conta.descricao as conta_descricao',
                'contamovimentos.descricao',
                'contamovimentos.valorefetivado',
                'contamovimentos.pagarreceber',
                'conta.id as conta_id',
                'contamovimentos.id as contamovimento_id',
                'cli.nome as cliente'
            )
            ->orderBy('contamovimentos.datahorabaixa', 'asc')->get();

        return $movimentos;
    }

    private function setFiltersReportFluxo($conta_id, $contas, $empresas, $empresas_id, $ativo)
    {
        $this->filtros = 'Período de ' . requestDataOracle($this->datainicio)  . ' a ' . requestDataOracle($this->datafim);
        $this->filtros .= '; Contas: ';
        $this->filtros .= implode(', ', $contas->whereIn("conta.id", $conta_id)->pluck('conta.descricao')->toArray()) . '; Empresa: ';
        $this->filtros .= implode(', ', $empresas->whereIn("id", $empresas_id)->pluck('nome_informal')->toArray());
        $this->filtros .= $ativo ? '; Contas Ativas.' : '; Contas Inativas.';
    }
    private function montarTableFluxo($movimentosOld, $fechamentos, $saldofinal, $saldoinicial, $contas_idOld, $movSemFechamentos, $ativo)
    {
        $movimentos = collect([]);
        $saldoanterior = $saldoinicial;
        foreach ($movimentosOld as $mov) {
            $objFluxo = (object) [];
            $objFluxo->empresa_id = $mov->empresa_id;
            $objFluxo->datahorabaixa = requestDataOracle($mov->datahorabaixa, false);
            $objFluxo->conta_descricao = $mov->conta_descricao;
            $objFluxo->cliente = isset($mov->cliente) ? $mov->cliente : '';
            $objFluxo->descricao = $mov->descricao;
            $objFluxo->entrada = $mov->pagarreceber == 'R' ? $mov->valorefetivado : 0;
            $objFluxo->saida = $mov->pagarreceber == 'P' ? $mov->valorefetivado : 0;
            $objFluxo->saldo = $mov->pagarreceber == "R" ? $saldoanterior + $mov->valorefetivado : $saldoanterior - $mov->valorefetivado;

            $saldoanterior = $objFluxo->saldo;
            $movimentos->push($objFluxo);
        }

        if (count($movimentosOld) > 0) {
            $objTotal = (object) [];
            $objTotal->totalEntrada = $movimentos->sum('entrada');
            $objTotal->totalSaida = $movimentos->sum('saida');
            $objTotal->totalGeral = $saldofinal;
            $movimentos->push($objTotal);
        }

        $contas = collect([]);
        $contaAnterior = null;
        $contaAnteriorDesc = null;
        $movs = $movimentosOld->sortBy('conta_id');
        $count = 0;

        $contasSaldos = DB::table('contas')->where('contas.ativo', $ativo)->whereIn('id', $contas_idOld)->select('saldoinicial', 'id')->get();
        foreach ($movs as $mov) {
            $count++;
            if ((!is_null($contaAnterior) && $contaAnterior !== $mov->conta_id))
                $contas = $this->putObjectContasFluxo($contas, $contaAnterior, $fechamentos, $contasSaldos, $movimentosOld, $contaAnteriorDesc, $movSemFechamentos);

            if (count($movs) == $count)
                $contas = $this->putObjectContasFluxo($contas, $mov->conta_id, $fechamentos, $contasSaldos, $movimentosOld, $mov->conta_descricao, $movSemFechamentos);

            $contaAnteriorDesc = $mov->conta_descricao;
            $contaAnterior = $mov->conta_id;
        }
        $contasSemMov = DB::table('contas')->where('contas.ativo', $ativo)->whereIn('id', $contas_idOld)->whereNotIn('id', $movs->pluck('conta_id')->toArray())->get();
        foreach ($contasSemMov as $conta) {
            $contas = $this->putObjectContasFluxo($contas, $conta->id, $fechamentos, $contasSaldos, $movimentosOld, $conta->descricao, $movSemFechamentos);
        }
        $contas = $contas->sortBy('conta_descricao');

        if (count($contas) > 0) {
            $objTotal = (object) [];
            $objTotal->saldogeralinicial = $contas->sum('saldoinicial');
            $objTotal->saldogeralfinal = $contas->sum('saldofinal');
            $contas->push($objTotal);
        }
        return ['movimentos' => $movimentos, 'contas' => $contas];
    }

    private function putObjectContasFluxo($contas, $conta_id, $fechamentos, $contasSaldos, $movimentosOld, $desc_conta, $movSemFechamentos)
    {
        $fechamentoAnterior = $fechamentos->where('conta_id', $conta_id)->first();
        if (is_null($fechamentoAnterior)) {
            $saldoinicialConta = $contasSaldos->where('id', $conta_id)->sum('saldoinicial');
        } else {
            $saldoinicialConta = $fechamentoAnterior->saldofinal;
        }
        $contaMovSemFechamento = $movSemFechamentos->where('conta_id', $conta_id);

        $saldoinicialConta += $contaMovSemFechamento->where('pagarreceber', 'R')->sum('valorefetivado') - $contaMovSemFechamento->where('pagarreceber', 'P')->sum('valorefetivado');

        $contamovimento = $movimentosOld->where('conta_id', $conta_id);
        $saldofinalConta = $saldoinicialConta + $contamovimento->where('pagarreceber', 'R')->sum('valorefetivado');
        $saldofinalConta -= $contamovimento->where('pagarreceber', 'P')->sum('valorefetivado');

        $objConta = (object) [];
        $objConta->conta_id = $conta_id;
        $objConta->conta_descricao = $desc_conta;
        $objConta->datainicio = $this->datainicio;
        $objConta->saldoinicial = $saldoinicialConta;
        $objConta->datafim = $this->datafim;
        $objConta->saldofinal = $saldofinalConta;

        $contas->push($objConta);
        return $contas;
    }

    private function getMovimentosSemFechamentos($movimentos, $fechamentos, $conta_id)
    {
        $movSemFechamentos = collect([]);
        foreach ($conta_id as $conta) {
            $contafechamento = $fechamentos->where('conta_id', $conta)->first();
            $movs = $movimentos->where('conta_id', $conta);
            if (is_null($contafechamento)) {
                $filterFunction = function ($f) {
                    return strtotime($f->datahorabaixa) <= strtotime($this->datainicio);
                };
            } else {
                $this->dataultimofechamento = $contafechamento->datahorafechamento;
                $filterFunction = function ($f) {
                    return strtotime($f->datahorabaixa) >= strtotime($this->dataultimofechamento) and strtotime($f->datahorabaixa) <= strtotime($this->datainicio);
                };
            }
            $movs = $movs->filter($filterFunction);
            $movSemFechamentos->push($movs);
        }
        return $movSemFechamentos->flatten();
    }

    //// Report Despesas por Tipo
    public function despesasIndex()
    {
        $empresas_id = \Auth::user()->empresas()->pluck('id')->toArray();
        $regionais = \Auth::user()->empresas()->where('regiao_id', '!=', null)->pluck('regiao_id')->toArray();
        $consolidado = Regiao::whereIn('id', $regionais)->pluck('descricao', 'id')->prepend('Selecione', '');
        $empresas = Empresa::whereIn('id', $empresas_id)->pluck('nome_informal', 'id')->prepend('Selecione', '');
        return view('reports.caixa.despesas.despesas_tipo')->with(compact('empresas', 'consolidado', 'empresas'));
    }

    public function despesasFiltro()
    {
        $get = $_GET;
        $datainicio = $get["datainicio"];
        $datafim = $get["datafim"];
        $retorno = $this->buscaPlanos($get, 'P');
        $despesas = $this->planos;
        $titulo = "Relatório de Despesas por Plano de Contas";
        $filtro = $this->filtros;
        return view('reports.caixa.despesas.despesas_table')->with(compact('titulo', 'filtro', 'despesas', 'datainicio', 'datafim'));
    }

    public function despesaSub()
    {
        $get = $_GET;
        $lancamentos = $this->getLancamentos($get, 'P');
        $titulo = "Relatório de Despesas por Plano de Contas";
        $filtro = $this->filtros;
        return view('reports.caixa.despesas.sub_report')->with(compact('titulo', 'filtro', 'lancamentos'));
    }

    //// Report Receitas por Tipo
    public function receitasIndex()
    {
        $empresas_id = \Auth::user()->empresas()->pluck('id')->toArray();
        $regionais = \Auth::user()->empresas()->where('regiao_id', '!=', null)->pluck('regiao_id')->toArray();
        $consolidado = Regiao::whereIn('id', $regionais)->pluck('descricao', 'id')->prepend('Selecione', '');
        $empresas = Empresa::whereIn('id', $empresas_id)->pluck('nome_informal', 'id')->prepend('Selecione', '');
        return view('reports.caixa.receita.receita_tipo')->with(compact('empresas', 'consolidado', 'empresas'));
    }

    public function receitasFiltro()
    {
        $get = $_GET;
        $datainicio = $get["datainicio"];
        $datafim = $get["datafim"];
        $retorno = $this->buscaPlanos($get, 'R');
        $hub = $retorno["hub"];
        $id = $retorno["id"];
        $despesas = $this->planos;
        $titulo = "Relatório de Receitas por Plano de Contas";
        $filtro = $this->filtros;
        return view('reports.caixa.receita.receita_table')->with(compact('titulo', 'filtro', 'despesas', 'datainicio', 'datafim', 'hub', 'id'));
    }

    public function receitasSub()
    {
        $get = $_GET;
        $lancamentos = $this->getLancamentos($get, 'R');
        $titulo = "Relatório de Receitas por Plano de Contas";
        $filtro = $this->filtros;
        return view('reports.caixa.despesas.sub_report')->with(compact('titulo', 'filtro', 'lancamentos'));
    }

    ///// Privadas
    private function buscaPlanos($filtro, $tipo)
    {
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $regional = $filtro["consolidado"] == "0" ? null : $filtro["consolidado"];
        $empresa = $filtro["empresa"] == "0" ? null : $filtro["empresa"];

        $this->filtros = "Filtro: Período " . requestDataOracle($datainicio, false) . " a " . requestDataOracle($datafim, false);
        $empresas_id = implode(',', \Auth::user()->empresas()->pluck('id')->toArray());
        if (!is_null($empresa)) {
            $this->filtros .= ", Empresa: " . Empresa::where('id', $empresa)->pluck('nome_informal')->first();
            $id = $empresa;
            $hub = 1;
            $queryJuros = $this->getQueryJurosMulta($datainicio, $datafim, $id, 'empresa', $tipo, $empresas_id);
            $query = $this->getQueryPlanoContas($datainicio, $datafim, $id, 'empresa', $tipo, $empresas_id);
        } else if (!is_null($regional)) {
            $this->filtros .= ", Regional: " . Regiao::where('id', $regional)->pluck('descricao')->first();
            $id = $regional;
            $hub = 2;
            $queryJuros = $this->getQueryJurosMulta($datainicio, $datafim, $id, 'regional', $tipo, $empresas_id);
            $query = $this->getQueryPlanoContas($datainicio, $datafim, $id, 'regional', $tipo, $empresas_id);
        } else {
            $id = Session::get('empresa_padrao')->grupo_id;
            $hub = 3;
            $this->filtros .= ", Grupo: " . EmpresasGrupo::where('id', $id)->pluck('descricao')->first();
            $queryJuros = $this->getQueryJurosMulta($datainicio, $datafim, $id, 'grupo', $tipo, $empresas_id);
            $query = $this->getQueryPlanoContas($datainicio, $datafim, $id, 'grupo', $tipo, $empresas_id);
        }

        $despesas = collect(DB::select($query));
        $juros = collect(DB::select($queryJuros));
        $juros = $this->getJurosMultasDesc($juros);
        $this->montarTablePlanoContas($despesas, $juros, $hub, $id);
        $retorno["hub"] = $hub;
        $retorno["id"] = $id;
        return $retorno;
    }

    private function montarTablePlanoContas($despesas, $juros, $tipo, $id)
    {
        $total = 0;
        $totalgeral = 0;
        $nivel = null;
        $this->planos = collect([]);
        foreach ($despesas as $plano) {
            if (!is_null($nivel) && $nivel > $plano->nivel && $plano->nivel == 1) {
                $objtotal = (object) array();
                $objtotal->totalplan = $total;
                $this->planos->push($objtotal);
                $total = 0;
            }
            if ($plano->valor == "0") {
                $objplano = (object) array();
                $objplano->plano_id = $plano->planoconta_id;
                $objplano->plano = $plano->planoconta;
                $objplano->nivel = $plano->nivel;
                $objplano->codigo = $plano->codigo;
                $this->planos->push($objplano);
                if (!is_null($juros)) {
                    $cjuro = $juros->where('codigo', $plano->codigo);
                    if ($cjuro->isNotEmpty()) {
                        $juros->shift();
                        $retorno = $this->insertjuros($juros);
                        $total += $retorno["total"];
                        $totalgeral += $retorno["total"];
                        $nivel = $retorno["nivel"];
                    }
                }
            }
            if ($plano->valor != "0") {
                $objnormal = (object) array();
                $objnormal->planoconta_id = $plano->planoconta_id;
                $objnormal->planoconta = $plano->planoconta;
                $objnormal->nivel = $plano->nivel;
                $objnormal->codigo = $plano->codigo;
                $objnormal->valor = $plano->valor;
                $objnormal->link = true;
                $objnormal->tipo = $tipo;
                $objnormal->tipo_id = $id;
                $total += $plano->valor;
                $totalgeral += $plano->valor;
                $nivel = $plano->nivel;
                $this->planos->push($objnormal);
            }
        }

        if (count($this->planos) > 0) {
            $objtotal = (object) array();
            $objtotal->totalplan = $total;
            $this->planos->push($objtotal);
            $total = 0;
            if (!is_null($juros)) {
                if (!$this->planos->contains('planoconta_id', $juros->first()->planoconta_id)) {
                    $retorno = $this->insertjuros($juros);
                    $total += $retorno["total"];
                    $totalgeral += $retorno["total"];
                    $objretorno = (object) array();
                    $objretorno->totalplan = $total;
                    $this->planos->push($objretorno);
                }
            }
            $objtotalgeral = (object) array();
            $objtotalgeral->totalgeral = $totalgeral;
            $this->planos->push($objtotalgeral);
        }
    }

    private function getJurosMultasDesc($juros)
    {
        $despesas = collect([]);
        $x = 0;
        if (count($juros) > 0) {
            foreach ($juros as $juro) {
                $objjuro = (object) array();
                $objjuro->planoconta_id = $juro->plano_id;
                $objjuro->codigo = $juro->codigo;
                $objjuro->planoconta = $juro->descricao;
                $objjuro->nivel = $juro->nivel;
                $objjuro->finalizador = $juro->finalizador;
                if ($juro->descontos == '0' && $juro->juros_multas == '0') {
                    $objjuro->valor = '0';
                    $x++;
                } else {
                    $objjuro->valor = ($juro->juros_multas + $juro->descontos);
                }
                $despesas->push($objjuro);
            }
        }
        return $x == count($juros) ? null : $despesas;
    }

    private function insertjuros($juros)
    {
        $total = 0;
        $nivel = null;
        $wj = $juros->where('finalizador', 1);
        $multas = $wj->sum('valor');
        if ($multas > 0) {
            foreach ($juros as $juro) {
                if ($juro->valor == '0' && $juro->finalizador == '0') {
                    $objplano = (object) array();
                    $objplano->plano_id = $juro->planoconta_id;
                    $objplano->plano = $juro->planoconta;
                    $objplano->nivel = $juro->nivel;
                    $objplano->codigo = $juro->codigo;
                    $this->planos->push($objplano);
                } else {
                    if ($juro->valor > 0 && $juro->finalizador == '1') {
                        $objnormal = (object) array();
                        $objnormal->planoconta_id = $juro->planoconta_id;
                        $objnormal->planoconta = $juro->planoconta;
                        $objnormal->nivel = $juro->nivel;
                        $objnormal->codigo = $juro->codigo;
                        $objnormal->valor = $juro->valor;
                        $objnormal->link = false;
                        $total += $juro->valor;
                        $nivel = $juro->nivel;
                        $this->planos->push($objnormal);
                    }
                }
            }
        }
        $retorno["total"] = $total;
        $retorno["nivel"] = $nivel;
        return $retorno;
    }

    ////Relatório Despesas por Tipo Centro Custo
    public function despesasCentro()
    {
        $centropai = Centrocusto::where(['empresa_id' => Session::get('empresa_padrao')->id, 'finalizador' => 0])
            ->pluck('descricao', 'id')->prepend('Selecione', '');

        return view('reports.caixa.despesas_cc.cc_index')->with(compact('centropai'));
    }

    public function centroFiltro()
    {
        $get = $_GET;
        $datainicio = $get["datainicio"];
        $datafim = $get["datafim"];
        $titulo = "Relatório de Despesas por Centro de Custo";
        $busca = $this->buscaCentros($get, 'P');
        $centros = $busca["despesas"];
        $incluido = $busca["juros"];
        $filtro = $this->filtros;
        $empresa = Session::get('empresa_padrao')->id;
        return view('reports.caixa.despesas_cc.cc_pdf')->with(compact('titulo', 'filtro', 'centros', 'datainicio', 'datafim', 'incluido', 'empresa'));
    }

    public function subCentroCusto()
    {
        $get = $_GET;
        $lancamentos = $this->getLancamentos($get, 'P', "centro");
        $titulo = "Relatório de Despesas por Centro de Custo";
        $filtro = $this->filtros;
        return view('reports.caixa.despesas_cc.sub_report_cc')->with(compact('titulo', 'filtro', 'lancamentos'));
    }

    //CC Receitas
    public function receitasCentro()
    {
        $centropai = Centrocusto::where(['empresa_id' => Session::get('empresa_padrao')->id, 'finalizador' => 0])
            ->pluck('descricao', 'id')->prepend('Selecione', '');

        return view('reports.caixa.receitas_cc.cc_index')->with(compact('centropai'));
    }

    public function centroFiltroReceitas()
    {
        $get = $_GET;
        $datainicio = $get["datainicio"];
        $datafim = $get["datafim"];
        $titulo = "Relatório de Receitas por Centro de Custo";
        $busca = $this->buscaCentros($get, 'R');
        $centros = $busca["despesas"];
        $incluido = $busca["juros"];
        $filtro = $this->filtros;
        $empresa = Session::get('empresa_padrao')->id;
        return view('reports.caixa.receitas_cc.ccreceitas_table')->with(compact('titulo', 'filtro', 'centros', 'datainicio', 'datafim', 'incluido', 'empresa'));
    }

    public function subCentroCustoReceitas()
    {
        $get = $_GET;
        $lancamentos = $this->getLancamentos($get, 'R', "centro");
        $titulo = "Relatório de Receitas por Centro de Custo";
        $filtro = $this->filtros;
        return view('reports.caixa.despesas_cc.sub_report_cc')->with(compact('titulo', 'filtro', 'lancamentos'));
    }

    private function buscaCentros($filtro, $tipo)
    {
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $pai = $filtro["centropai_id"] == '0' ? null : $filtro["centropai_id"];
        $filho = $filtro["centrofilho_id"] == '0' ? null : $filtro["centrofilho_id"];
        $empresa = Session::get('empresa_padrao')->id;
        $config = Session::get('empresa_config');
        $descEquals = false;
        $juroEquals = false;
        if (!is_null($config)) {
            $desconto_id = $config->ccdespesasdescontos_id;
            $juros_id = $config->ccdespesasjuros_id;
            $null = true;
            if (!is_null($filho)) {
                $descEquals = $desconto_id == $filho;
                $juroEquals = $juros_id == $filho;
            } else if (!is_null($pai)) {
                $queryCentro = "select id from centrocustos centro where empresa_id = $empresa start with id = $pai connect by prior id = paicentrocusto_id";
                $centro = collect(DB::select($queryCentro));
                $descEquals = $centro->contains('id', $desconto_id);
                $juroEquals = $centro->contains('id', $juros_id);
            } else {
                $descEquals = true;
                $juroEquals = true;
            }
        }

        $query = $this->getCentroCustoQuery($datainicio, $datafim, $pai, $filho, $empresa, $tipo);
        if (isset($null) && !is_null($juros_id) && !is_null($desconto_id)) {
            if ($descEquals || $juroEquals) {
                $desc = $tipo == 'R' ? 'P' : 'R';
                $queryJuros = $this->getQueryJurosDescontosCC($datainicio, $datafim, $empresa, $tipo, $desconto_id, $juros_id, $desc, $descEquals, $juroEquals);
                $juros = collect(DB::select($queryJuros));
                $juros = $this->getJurosMultasDescCC($juros);
                $null = is_null($juros);
            }
        }

        $dbdespesas = collect(DB::select($query));
        $despesas = collect([]);
        $total = 0;
        $totalgeral = 0;
        $nivel = null;
        $inserted = false;
        foreach ($dbdespesas as $despesa) {
            if (!is_null($nivel) && $despesa->nivel == 1) {
                $objtotal = (object) array();
                $objtotal->total = $total;
                $despesas->push($objtotal);
                $total = 0;
            }
            if ($despesa->valor == '0' && $despesa->finalizador == "0") {
                $objcentro = (object) array();
                $objcentro->centro_id = $despesa->centro_id;
                $objcentro->centro = $despesa->centro;
                $objcentro->nivel = $despesa->nivel;
                $objcentro->codigo = $despesa->codigo;
                $despesas->push($objcentro);
                if (isset($null) && !$null) {
                    $cjuro = $juros->where('codigo', $despesa->codigo)->count();
                    if ($cjuro > 0) {
                        $juros->shift();
                        $retorno = $this->insertJurosCC($juros, $despesas);
                        $depesas = $retorno["despesas"];
                        $total += $retorno["total"];
                        $totalgeral += $retorno["total"];
                        $inserted = true;
                    }
                }
            }
            if ($despesa->valor != "0" && $despesa->finalizador == "1") {
                $objnormal = (object) array();
                $objnormal->centrocusto_id = $despesa->centro_id;
                $objnormal->centro = $despesa->centro;
                $objnormal->codigo = $despesa->codigo;
                $objnormal->nivel = $despesa->nivel;
                $objnormal->valor = $despesa->valor;
                $objnormal->link = true;
                $despesas->push($objnormal);
                $nivel = $despesa->nivel;
                $total += $despesa->valor;
                $totalgeral += $despesa->valor;
            }
        }

        if (count($despesas) > 0 || (isset($juros) && count($juros) > 0)) {
            if (count($despesas) > 0) {
                $objtotal = (object) array();
                $objtotal->total = $total;
                $despesas->push($objtotal);
            }

            if (isset($null) && !$null && !$inserted) {
                $retorno = $this->insertJurosCC($juros, $despesas);
                $depesas = $retorno["despesas"];
                $total += $retorno["total"];
                $totalgeral += $retorno["total"];
            }

            $objtotalgeral = (object) array();
            $objtotalgeral->totalgeral = $totalgeral;
            $despesas->push($objtotalgeral);
        }

        $this->filtros = "Filtro: período " . requestDataOracle($datainicio, false) . " a " . requestDataOracle($datafim, false);
        $this->filtros .= is_null($pai) ? ", Centro Custo Tipo: todos" : ", Centro Custo Tipo: " . Centrocusto::where('id', $pai)->pluck('descricao')->first();
        $this->filtros .= is_null($filho) ? ", Centro Custo Sub-Tipo: todos." : ", Centro Custo Sub-Tipo: " . Centrocusto::where('id', $filho)->pluck('descricao')->first();

        $retorno["despesas"] = $despesas;
        $retorno["juros"] = !is_null($juros_id) && !is_null($desconto_id);
        return $retorno;
    }

    private function getJurosMultasDescCC($juros)
    {
        $despesas = collect([]);
        $x = 0;
        if (count($juros) > 0) {
            foreach ($juros as $juro) {
                $objjuro = (object) array();
                $objjuro->centro_id = $juro->centro_id;
                $objjuro->codigo = $juro->codigo;
                $objjuro->centro = $juro->centro;
                $objjuro->nivel = $juro->nivel;
                $objjuro->finalizador = $juro->finalizador;
                if ($juro->descontos == '0' && $juro->juros_multa == '0') {
                    $objjuro->valor = '0';
                    $x++;
                } else {
                    $objjuro->valor = ($juro->juros_multa + $juro->descontos);
                }
                $despesas->push($objjuro);
            }
        }
        return count($juros) == $x ? null : $despesas;
    }

    private function insertJurosCC($juros, $despesas)
    {
        $total = 0;
        $val = $juros->sum('valor');
        if ($val > 0) {
            foreach ($juros as $juro) {
                if ($juro->valor == '0' && $juro->finalizador == '0') {
                    $objcentro = (object) array();
                    $objcentro->centro_id = $juro->centro_id;
                    $objcentro->centro = $juro->centro;
                    $objcentro->nivel = $juro->nivel;
                    $objcentro->codigo = $juro->codigo;
                    $despesas->push($objcentro);
                } else if ($juro->valor != '0' && $juro->finalizador == '1') {
                    $objjuro = (object) array();
                    $objjuro->centrocusto_id = $juro->centro_id;
                    $objjuro->centro = $juro->centro;
                    $objjuro->nivel = $juro->nivel;
                    $objjuro->codigo = $juro->codigo;
                    $objjuro->valor = $juro->valor;
                    $objjuro->link = false;
                    $despesas->push($objjuro);
                    $total += $juro->valor;
                }
            }
        }
        $retorno["total"] = $total;
        $retorno["despesas"] = $despesas;
        return $retorno;
    }

    //SUB Colocar OBS sobre a mensagem de aviso


    private function getLancamentos($filtro, $pagarreceber, $tipo = "plano")
    {
        $tipo = $tipo == "plano";
        $empresas_id = $empresas_id = implode(',', \Auth::user()->empresas()->pluck('id')->toArray());;
        if ($tipo)
            $planoconta_id = $filtro["planoconta"];
        else
            $centro_id = $filtro["centrocusto"];

        $hub = $filtro["hub"];
        $id = $filtro["id"];
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $financeiro = "select financeiro_id " .
            "from financeiroparcelas parc " .
            "inner join empresas on parc.empresa_id = empresas.id " .
            "where parc.agrupamento_status < 2 and parc.datacompetencia between " .
            "to_date('$datainicio', 'yyyy-mm-dd hh24:mi:ss') and " .
            "to_date('$datafim', 'yyyy-mm-dd hh24:mi:ss') and ";

        $dblancamentos = DB::table('financeirorateios as rato')
            ->join('financeiros', 'rato.financeiro_id', 'financeiros.id')
            ->join('clientes', 'financeiros.cliente_id', 'clientes.id')
            ->join('centrocustos as centro', 'rato.centrocusto_id', 'centro.id')
            ->join('planocontas as plano', 'rato.planoconta_id', 'plano.id');
        if ($hub == '1') {
            $financeiro .= "parc.empresa_id = $id and parc.pagarreceber = '$pagarreceber' and parc.empresa_id in ($empresas_id) group by financeiro_id";
        } else if ($hub == '2') {
            $financeiro .= "empresas.regiao_id = $id and parc.pagarreceber = '$pagarreceber' and parc.empresa_id in ($empresas_id) group by financeiro_id";
        } else {
            $financeiro .= "parc.grupo_id = $id and parc.pagarreceber = '$pagarreceber' and parc.empresa_id in ($empresas_id) group by financeiro_id";
        }
        $dblancamentos = $dblancamentos->whereRaw("rato.financeiro_id in ($financeiro)");


        if ($tipo) {
            $dblancamentos = $dblancamentos->where('rato.planoconta_id', $planoconta_id);
            $dbselect = "plano.descricao as planoconta";
            $dbgroup = 'plano.descricao';
        } else {
            $dblancamentos = $dblancamentos->where('rato.centrocusto_id', $centro_id);
            $dbselect = "centro.descricao as centrocusto";
            $dbgroup = 'centro.descricao';
        }

        $dblancamentos = $dblancamentos->select(
            'financeiros.id as financeiro_id',
            'clientes.id as cliente_id',
            'clientes.nome',
            'financeiros.datacompetencia',
            'financeiros.descricao',
            'financeiros.pagarreceber',
            DB::raw('sum(rato.valor) as valor'),
            $dbselect
        )
            ->groupBy(
                'financeiros.id',
                'clientes.id',
                'clientes.nome',
                'financeiros.datacompetencia',
                'financeiros.descricao',
                'financeiros.pagarreceber',
                $dbgroup
            )
            ->orderBy('clientes.nome')->get();

        $lancamentos = collect([]);
        $anterior = null;
        $finanterior = null;
        foreach ($dblancamentos as $despesa) {
            if (!is_null($anterior) && $despesa->cliente_id != $anterior) {
                $wdesp = $dblancamentos->where('cliente_id', $anterior);
                $objtotal = (object) array();
                $objtotal->total = $wdesp->sum('valor');
                $lancamentos->push($objtotal);
            }
            if (is_null($anterior) || $anterior != $despesa->cliente_id) {
                $objfornecedor = (object) array();
                $objfornecedor->fornecedor_id = $despesa->cliente_id;
                $objfornecedor->fornecedor = $despesa->nome;
                $objfornecedor->pagar = $despesa->pagarreceber;
                $lancamentos->push($objfornecedor);
            }
            $objnormal = (object) array();
            $objnormal->data = requestDataOracle($despesa->datacompetencia, false);
            $objnormal->codigo = $despesa->financeiro_id;
            $objnormal->descricao = $despesa->descricao;
            $objnormal->valor = $despesa->valor;
            $lancamentos->push($objnormal);
            $anterior = $despesa->cliente_id;
        }

        if (count($lancamentos) > 0) {
            $wdesp = $dblancamentos->where('cliente_id', $anterior);
            $objtotal = (object) array();
            $objtotal->total = $wdesp->sum('valor');
            $lancamentos->push($objtotal);

            $objtotalgeral = (object) array();
            $objtotalgeral->totalgeral = $dblancamentos->sum('valor');
            $lancamentos->push($objtotalgeral);
        }

        $this->filtros = "FIltro: Período " . requestDataOracle($datainicio, false) . " a " . requestDataOracle($datafim, false);
        if ($tipo) {
            $this->filtros .= ", Plano de Conta: " . Planoconta::where('id', $planoconta_id)->pluck('descricao')->first();
        } else {
            $this->filtros .= ", Centro de Custos: " . Centrocusto::where('id', $centro_id)->pluck('descricao')->first();
        }
        return $lancamentos;
    }

    private function getQueryPlanoContas($datainicio, $datafim, $parametro, $padrao, $tipo, $empresas)
    {
        $query =
            "select planoconta_id, codigo, planoconta, nivel, sum(valor) as valor " .
            "from( " .
            "select planoconta_id, codigo, planoconta, nivel, sum(valor) as valor " .
            "from( " .
            "select id as planoconta_id, codigo, descricao as planoconta, nivel, 0 as valor " .
            "from planocontas " .
            "start with id in ( " .
            "select rato.PLANOCONTA_ID " .
            "from financeiroparcelas parc   " .
            "inner join financeirorateios rato on rato.financeiro_id = parc.financeiro_id " .
            "inner join empresas on parc.empresa_id = empresas.id " .
            "where parc.datacompetencia  " .
            "between to_date('$datainicio','yyyy-mm-dd hh24:mi:ss') and to_date('$datafim','yyyy-mm-dd hh24:mi:ss') and " .
            "parc.agrupamento_status < 2 and parc.pagarreceber = '$tipo' and ";

        if ($padrao == 'empresa') {
            $query = $query . "parc.empresa_id = $parametro ";
        } else if ($padrao == 'regional') {
            $query = $query . "empresas.regiao_id = $parametro and parc.empresa_id in ($empresas) ";
        } else {
            $query = $query . "parc.grupo_id = $parametro and parc.empresa_id in ($empresas) ";
        }
        $query = $query .
            ") " .
            "connect by prior paiplanoconta_id = id " .
            "order by codigo " .
            ") qry  " .
            "group by planoconta_id, codigo, planoconta, nivel " .

            "union all " .

            "select plano.id as planoconta_id, plano.codigo, plano.descricao as plano, plano.nivel, sum(rato.valor) as valor " .
            "from financeirorateios rato " .
            "inner join planocontas plano on rato.planoconta_id = plano.id " .
            "where financeiro_id in (select parc.financeiro_id " .
            "from financeiroparcelas parc  " .
            "inner join empresas on parc.empresa_id = empresas.id " .
            "where parc.datacompetencia " .
            "between to_date('$datainicio','yyyy-mm-dd hh24:mi:ss') and to_date('$datafim','yyyy-mm-dd hh24:mi:ss') and " .
            "parc.pagarreceber = '$tipo' and " .
            "parc.agrupamento_status < 2 and ";

        if ($padrao == 'empresa') {
            $query = $query . "parc.empresa_id = $parametro ";
        } else if ($padrao == 'regional') {
            $query = $query . "empresas.regiao_id = $parametro and parc.empresa_id in ($empresas) ";
        } else {
            $query = $query . "parc.grupo_id = $parametro and parc.empresa_id in ($empresas) ";
        }

        $query = $query .
            "group by parc.financeiro_id) " .
            "group by plano.descricao, plano.codigo, plano.nivel, plano.id" .
            ") " .
            "group by planoconta_id, codigo, planoconta, nivel " .
            "order by codigo, planoconta";

        return $query;
    }

    private function getQueryJurosMulta($datainicio, $datafim, $parametro, $padrao, $tipo, $empresas)
    {
        if ($tipo == 'R') {
            $juros_id = 'pcrecetajuro_id';
            $desconto_id = 'pcreceitadesconto_id';
            $desc = 'P';
        } else {
            $juros_id = 'pcdespesasjuro_id';
            $desconto_id = 'pcdespesasdesconto_id';
            $desc = 'R';
        }

        $query =
            "select plano_id, codigo, descricao, nivel, finalizador, sum(juros_multa) as juros_multas, sum(desconto) as descontos " .
            "from( " .
            "select plano_id, string_agg(codigo, '' order by codigo) as codigo, " .
            "string_agg(descricao, '' order by descricao) as descricao, " .
            "sum(nivel) as nivel, " .
            "string_agg(finalizador, '' order by finalizador) as finalizador, " .
            "sum(juros + multa) as juros_multa, 0 as desconto " .
            "from( " .
            "select id as plano_id, codigo, descricao, nivel, 0 as juros, 0 as multa, finalizador " .
            "from planocontas " .
            "start with id in ( " .
            "select $juros_id " .
            "from empresaconfigs config " .
            "inner join empresas on config.empresa_id = empresas.id " .
            "where ";

        if ($padrao == 'regional')
            $query .= " empresas.regiao_id = $parametro and empresas.id in ($empresas) ";
        else
            $query .= " config.grupo_id = " . Session::get('empresa_padrao')->grupo_id . " and empresas.id in ($empresas) ";

        $query .= " and rownum <= 1 " .
            ") " .
            "connect by prior paiplanoconta_id = id " .

            "union all " .

            "select sum(plano_id) as plano_id, '' as codigo, '' as descricao, 0 as nivel, sum(juros) as juros, sum(multa) as multa, '' as finalizador " .
            "from( " .
            "select $juros_id as plano_id, $desconto_id as plano_desconto, 0 as juros, 0 as multa, 0 as desconto " .
            "from empresaconfigs config " .
            "inner join empresas on config.empresa_id = empresas.id " .
            "where";

        if ($padrao == 'regional')
            $query .= " empresas.regiao_id = $parametro and empresas.id in ($empresas) ";
        else
            $query .= " config.grupo_id = " . Session::get('empresa_padrao')->grupo_id . " and empresas.id in ($empresas) ";

        $query .=  " and rownum <= 1 " .

            "union all " .

            "select 0 as plano_id, 0 as plano_desconto, sum(juros) as juros, sum(multa) as multa, 0 as desconto " .
            "from financeiroparcelas parc " .
            "inner join empresas on parc.empresa_id = empresas.id " .
            "where parc.datacompetencia between " .
            "to_date('$datainicio','yyyy-mm-dd hh24:mi:ss') and " .
            "to_date('$datafim','yyyy-mm-dd hh24:mi:ss') and " .
            "pagarreceber = '$tipo' and agrupamento_status < 2 and " .
            "(juros <> 0 or multa <> 0) and ";

        if ($padrao == 'empresa')
            $query .= "parc.empresa_id = $parametro and parc.empresa_id in ($empresas) ";
        else if ($padrao == 'regional')
            $query .= "empresas.regiao_id = $parametro and parc.empresa_id in ($empresas) ";
        else
            $query .= "parc.grupo_id = $parametro and parc.empresa_id in ($empresas) ";

        $query .=
            ") juros_multas " .
            ") recurssive " .
            "group by plano_id " .

            "union all " .

            "select plano_desconto, string_agg(codigo, '' order by codigo) as codigo, " .
            "string_agg(descricao, '' order by descricao) as descricao, " .
            "sum(nivel) as nivel, " .
            "string_agg(finalizador, '' order by finalizador) as finalizador, " .
            "0 as juros_multa, sum(desconto) as desconto " .
            "from( " .
            "select id as plano_desconto, codigo, descricao, nivel, 0 as desconto, finalizador " .
            "from planocontas plano " .
            "start with id in ( " .
            "select $desconto_id " .
            "from empresaconfigs config " .
            "inner join empresas on config.empresa_id = empresas.id " .
            "where";

        if ($padrao == 'regional')
            $query .= " empresas.regiao_id = $parametro and empresas.id in ($empresas) ";
        else
            $query .= " config.grupo_id = " . Session::get('empresa_padrao')->grupo_id . " and empresas.id in ($empresas)";

        $query .=    " and rownum <= 1 " .
            ") " .
            "connect by prior paiplanoconta_id = id " .

            "union all " .

            "select sum(plano_desconto) as plano_desconto, '' as codigo, '' as descricao, 0 as nivel, sum(desconto) as desconto, '' as finalizador " .
            "from( " .
            "select $desconto_id as plano_desconto, 0 as desconto " .
            "from empresaconfigs config " .
            "inner join empresas on config.empresa_id = empresas.id " .
            "where ";

        if ($padrao == 'regional')
            $query .= " empresas.regiao_id = $parametro and empresas.id in ($empresas)";
        else
            $query .= " config.grupo_id = " . Session::get('empresa_padrao')->grupo_id . "and empresas.id in ($empresas)";

        $query .=    " and rownum <= 1 " .

            "union all " .

            "select 0 as plano_desconto, sum(desconto) as desconto " .
            "from financeiroparcelas parc " .
            "inner join empresas on parc.empresa_id = empresas.id " .
            "where parc.datacompetencia between " .
            "to_date('$datainicio','yyyy-mm-dd hh24:mi:ss') and " .
            "to_date('$datafim','yyyy-mm-dd hh24:mi:ss') and " .
            "pagarreceber = '$desc' and agrupamento_status < 2 and parc.desconto <> 0 and ";
        if ($padrao == 'empresa')
            $query .= "parc.empresa_id = $parametro and parc.empresa_id in ($empresas)";
        else if ($padrao == 'regional')
            $query .= "empresas.regiao_id = $parametro and parc.empresa_id in ($empresas)";
        else
            $query .= "parc.grupo_id = $parametro and parc.empresa_id in ($empresas)";

        $query .=
            ") descontos " .
            ") query_2 " .
            "group by plano_desconto " .
            ") principal " .
            "group by plano_id, codigo, descricao, nivel, juros_multa, desconto, finalizador " .
            "order by codigo";

        return $query;
    }

    private function getCentroCustoQuery($datainicio, $datafim, $pai, $filho, $empresa, $tipo)
    {
        $queryStart = "select rato.centrocusto_id " .
            "from financeiroparcelas parc " .
            "inner join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
            "inner join centrocustos centro on rato.centrocusto_id = centro.id " .
            "where parc.datacompetencia between " .
            "to_date('$datainicio','yyyy-mm-dd hh24:mi:ss') and " .
            "to_date('$datafim','yyyy-mm-dd hh24:mi:ss') and " .
            "parc.empresa_id = $empresa and parc.agrupamento_status < 2 and " .
            "parc.pagarreceber = '$tipo'";

        if (is_null($pai)) {
            $pai = $queryStart;
        } else if (is_null($filho)) {
            $pai_id = $pai;
            $pai = $queryStart . " and centrocusto_id in ( " .
                "select id from centrocustos " .
                "where empresa_id = $empresa " .
                "start with id = $pai " .
                "connect by prior id = paicentrocusto_id " .
                ") group by centrocusto_id";

            $filho = " and centrocusto_id in ( " .
                "select id " .
                "from centrocustos " .
                "where empresa_id = $empresa " .
                "start with id = $pai_id " .
                "connect by prior id = paicentrocusto_id " .
                ")";
        } else if (!is_null($filho)) {
            $pai = $filho;
            $filho = " and centrocusto_id in ($filho)";
        }

        $query =
            "select centro_id, codigo, centro, nivel, finalizador, sum(valor) as valor " .
            "from( " .
            "select id as centro_id, codigo, descricao as centro, nivel, finalizador, 0 as valor " .
            "from centrocustos " .
            "where empresa_id = $empresa " .
            "start with id in ( " .
            "$pai " .
            ") " .
            "connect by id = prior paicentrocusto_id " .

            "union all  " .

            "select rato.centrocusto_id as centro_id, centro.codigo, centro.descricao as centro, centro.nivel, centro.finalizador, sum(rato.valor) as valor " .
            "from financeirorateios rato " .
            "inner join centrocustos centro on rato.centrocusto_id = centro.id " .
            "where rato.financeiro_id in(select parc.financeiro_id " .
            "from financeiroparcelas parc  " .
            "inner join empresas on parc.empresa_id = empresas.id " .
            "where parc.datacompetencia " .
            "between to_date('$datainicio','yyyy-mm-dd hh24:mi:ss') and to_date('$datafim','yyyy-mm-dd hh24:mi:ss') and " .
            "parc.pagarreceber = '$tipo' and " .
            "parc.agrupamento_status < 2 and parc.empresa_id = $empresa " .
            "group by parc.financeiro_id)" .
            " $filho " .
            "group by rato.centrocusto_id, centro.codigo, centro.descricao, centro.nivel, centro.finalizador, rato.id" .
            ") recursao " .
            "group by centro_id, codigo, centro, nivel, finalizador " .
            "order by codigo";

        return $query;
    }

    private function getQueryJurosDescontosCC($datainicio, $datafim, $empresa, $tipo, $desconto_id, $juros_id, $desc, $jeq, $deq)
    {
        $query = "select centro_id, codigo, centro, nivel, finalizador, sum(juros_multa) as juros_multa, sum(descontos) as descontos " .
            "from( ";
        if ($jeq) {
            $query .= "select centro_id, string_agg(codigo, '' order by codigo) as codigo, " .
                "string_agg(centro, '' order by centro) as centro, " .
                "sum(nivel) as nivel, sum(juros_multa) as juros_multa, sum(descontos) as descontos, " .
                "string_agg(finalizador, '' order by finalizador) as finalizador " .
                "from( " .
                "select id as centro_id, codigo, descricao as centro, nivel, 0 as juros_multa, 0 as descontos, finalizador " .
                "from centrocustos " .
                "where empresa_id = $empresa " .
                "start with id in ( " .
                "select $juros_id " .
                "from empresaconfigs " .
                "where empresa_id = $empresa " .
                ") " .
                "connect by prior paicentrocusto_id = id " .

                "union all " .

                "select sum(centro_id) as centro_id, '' as codigo, '' as centro, 0 as nivel, " .
                "sum(juros + multa) as juros_multa, 0 as descontos, '' as finalizador " .
                "from( " .
                "select $juros_id as centro_id, 0 as juros, 0 as multa " .
                "from empresaconfigs " .
                "where empresa_id = $empresa " .

                "union all " .

                "select 0 as centro_id, parc.juros as juros, parc.multa as multa " .
                "from financeiroparcelas parc " .
                "where parc.datacompetencia between " .
                "to_date('$datainicio','yyyy-mm-dd hh24:mi:ss') and " .
                "to_date('$datafim','yyyy-mm-dd hh24:mi:ss') and " .
                "parc.agrupamento_status < 2 and parc.empresa_id = $empresa and " .
                "parc.pagarreceber = '$tipo' and (parc.juros <> 0 or parc.multa <> 0) " .
                ") " .
                ") juros " .
                "group by centro_id ";
        }
        if ($jeq && $deq)
            $query .= "union all ";

        if ($deq) {
            $query .= "select centro_id, string_agg(codigo, '' order by codigo) as codigo, " .
                "string_agg(centro, '' order by centro) as centro, " .
                "sum(nivel) as nivel, 0 as juros_multa, sum(descontos) as descontos, " .
                "string_agg(finalizador, '' order by finalizador) as finalizador " .
                "from( " .
                "select id as centro_id, codigo, descricao as centro, nivel, 0 as juros_multa, 0 as descontos, finalizador " .
                "from centrocustos " .
                "where empresa_id = $empresa " .
                "start with id in ( " .
                "select $desconto_id " .
                "from empresaconfigs " .
                "where empresa_id = $empresa " .
                ") " .
                "connect by prior paicentrocusto_id = id " .

                "union all " .

                "select sum(centro_id) as centro_id, '' as codigo, '' as centro, 0 as nivel, " .
                "0 as juros_multa, sum(desconto) as desconto, '' as finalizador " .
                "from( " .
                "select $desconto_id as centro_id, 0 as desconto " .
                "from empresaconfigs " .
                "where empresa_id = $empresa " .

                "union all " .

                "select 0 as centro_id, parc.desconto " .
                "from financeiroparcelas parc " .
                "where parc.datacompetencia between " .
                "to_date('$datainicio','yyyy-mm-dd hh24:mi:ss') and " .
                "to_date('$datafim','yyyy-mm-dd hh24:mi:ss') and " .
                "empresa_id = $empresa and parc.agrupamento_status < 2 and " .
                "parc.pagarreceber = '$desc' and parc.desconto <> 0 " .
                ") " .
                ") descontos " .
                "group by centro_id ";
        }
        $query .= ") principal " .
            "group by centro_id,codigo,centro,nivel,finalizador " .
            "order by codigo, centro";

        return $query;
    }
}
