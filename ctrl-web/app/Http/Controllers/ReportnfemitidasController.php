<?php

namespace App\Http\Controllers;

use App\Nfemitida;
use App\NfInutilizacaoEntrada;
use App\Nfrecebida;
use DB;
use Illuminate\Support\Collection;
use Session;
use App\Cliente;
use App\Produto;
use App\Services\CarbonCustom as Carbon;
use App\Nfoperacao;
use App\Nfsituacao;

# Valor Liquido da Nota = VNF - VDESC sem adição, pode vir a mudar
class ReportnfemitidasController extends Controller
{
    private $filtros;

    public function index()
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $cfop = Nfoperacao::where([['empresa_id', Session::get('empresa_padrao')->id]])
            ->whereIn('aparecetela', [1,2])->orderBy('cfop', 'asc')->pluck('descricao', 'id')->prepend('Selecione', '');
        $situacao = Nfsituacao::whereIn('id',['100','101','102','205'])->pluck('msgerroreceita','id')->prepend('Selecione','');
        $produto = Produto::where(['empresa_id'=>$empresa_id,'ativo'=>1,'nfepermite'=>1])
            ->orderBy('descricao')->pluck('descricao','id')->prepend('Selecione','');
        $situacao['2002'] = 'Gravado/Rejeição';
        $modelosDb = Nfrecebida::where("empresa_id", $empresa_id)
            ->where("tipolancamento", "0")
            ->selectRaw("nfmodelo")
            ->groupBy("nfmodelo")->get();
        $inut = NfInutilizacaoEntrada::where("empresa_id", $empresa_id)
            ->selectRaw("nfmodelo")
            ->groupBy("nfmodelo")->get();
        $modelos = collect(["55" => "55", "65" => "65"]);
        foreach ($modelosDb as $i) {
            $modelos->put((string) $i->nfmodelo, (string) $i->nfmodelo);
        }
        foreach ($inut as $i) {
            $modelos->put((string) $i->nfmodelo, (string) $i->nfmodelo);
        }
        $modelos = $modelos->sort();
        return view('reports.nf.emitidas.index')->with(compact('cfop','situacao','produto', 'modelos'));
    }

    public function filtroEmitidas()
    {
        $get = $_GET;
        $hub = $get["hub"] == 1;
        if($hub){
            $nfs = $this->getNfe($get);
            $titulo = "Relatório de Nota Fiscal Emitida";
            $filtro = $this->filtros;
            $fonte = "fontSize_10";
            return view('reports.nf.emitidas.emitidas_pdf')->with(compact('titulo','filtro','nfs','fonte'));
        }else{
            $nfs = $this->getProds($get);
            $titulo = "Relatório de Nota Fiscal Emitida por Produto";
            $fonte = "fontSize_11";
            $filtro = $this->filtros;
            return view('reports.nf.emitidas.nfprod_pdf')->with(compact('titulo','filtro','nfs','fonte'));
        }
    }

    private function getNfe($filtro)
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $operacao = $filtro["operacao"] == '0' ? null : $filtro["operacao"];
        $cliente_id = $filtro["cliente"] == '0' ? null : $filtro["cliente"];
        $situacao = $filtro["situacao"];
        $ordem = $filtro["ordem"];
        $modelo = $filtro["modelos"] ? $filtro["modelos"] : null;
        $notin = ['100', '101', '205', '102', '135'];

        $docs = DB::table('nfrecebidas as nf')->join('nfoperacaos as ope','nf.nfoperacao_id','ope.id')
                    ->join('clientes','nf.cliente_id','clientes.id')
                    ->where('nf.empresa_id', $empresa_id)
                    ->where('nf.tipolancamento', 0) //tipolancamento 0 = emissão própria
                    ->whereBetween('nf.datahoraemissao', [$datainicio, $datafim]);

        $dbnf = DB::table('nfemitidas as nf')->join('nfoperacaos as ope','nf.nfoperacao_id','ope.id')
                    ->join('clientes','nf.cliente_id','clientes.id')
                    ->where([
                        ['nf.empresa_id',$empresa_id]
                    ])
                    ->whereBetween('nf.datahoraemissao',[$datainicio,$datafim]);

        if ($situacao != 2002) {
            if ($situacao == 101) {
                $dbnf = $dbnf->whereRaw('nf.nfsituacao_id in (101, 135) and '.
                    'cancelamentomotivo is not null');
            } else {
                $dbnf = $dbnf->where('nf.nfsituacao_id',$situacao);
            }
            $docs->where('nf.nfsituacao_id', $situacao);
        } else {
            $dbnf = $dbnf->whereNotIn('nf.nfsituacao_id',$notin);
            $docs->whereNotIn('nf.nfsituacao_id', $notin);
        }
        if (!is_null($operacao)) {
            $dbnf = $dbnf->where('nf.nfoperacao_id',$operacao);
            $docs->where('nf.nfoperacao_id', $operacao);
        }
        if (!is_null($cliente_id)) {
            $dbnf = $dbnf->where('nf.cliente_id',$cliente_id);
            $docs->where('nf.cliente_id', $cliente_id);
        }
        if (! is_null($modelo)) {
            $dbnf = $dbnf->whereRaw('(nf.nfmodelo in  (' . $modelo . ') ) ');
            $docs->whereRaw('(nf.nfmodelo in  (' . $modelo . ') ) ');
        }

        $dbnf = $dbnf->select('nf.id as nf_id',DB::raw("to_char(nf.datahoraemissao,'dd/mm/yyyy') as data"),
            'clientes.nome as destinatario','nf.nfmodelo','nf.nfnumero','nf.nfserie',
            'ope.descricao as operacao','nf.vnf','nf.vdesc','ope.id as operacao_id','ope.cfop');
        $docs->select('nf.id as nf_id',DB::raw("to_char(nf.datahoraemissao,'dd/mm/yyyy') as data"),
            'clientes.nome as destinatario','nf.nfmodelo','nf.nfnumero','nf.nfserie',
            'ope.descricao as operacao','nf.vnf','nf.vdesc','ope.id as operacao_id','ope.cfop');
        if ($ordem == 'D') {
            $dbnf = $dbnf->orderBy(DB::raw("to_char(nf.datahoraemissao,'yyyy-mm-dd')"))->orderBy('cfop');
            $docs->orderBy(DB::raw("to_char(nf.datahoraemissao,'yyyy-mm-dd')"))->orderBy('cfop');
        } else {
            $dbnf = $dbnf->orderBy('operacao')->orderBy('nf.datahoraemissao');
            $docs->orderBy('operacao')->orderBy('nf.datahoraemissao');
        }
        $dbnf = $dbnf->orderBy('destinatario')->get();
        $docs = $docs->orderBy('destinatario')->get();
        $dbnf = $this->customSort($dbnf->merge($docs));
        if ($situacao == 102 && ! $cliente_id) {
            $this->getInut($empresa_id, [$datainicio, $datafim], $modelo, $dbnf);
            $dbnf = $this->customSort($dbnf);
        }
        $nf = collect([]);
        $principal = $ordem == 'D' ? 'data' : 'operacao';
        $anterior = null;
        $total = 0;
        $count = 0;
        $totalgeral = 0;
        foreach ($dbnf as $db) {
            if(!is_null($anterior) && $anterior != $db->{$principal}){
                $objtotal = (object) array();
                $objtotal->quant = $count;
                $objtotal->total = $total;
                $nf->push($objtotal);
                $total = 0;
                $count = 0;
            }
            if(is_null($anterior) || $anterior != $db->{$principal}){
                $objmain = (object) array();
                $objmain->tipo = $ordem == 'D' ? "Data Emissão" : "Operação";
                $objmain->sec  = $ordem == 'D' ? "Operação" : "Emissão";
                $objmain->principal = $db->{$principal};
                $nf->push($objmain);
            }
            $objnormal = (object) array();
            $objnormal->secundario = $ordem == 'D' ? $db->operacao : $db->data;
            $objnormal->modelo = $db->nfmodelo;
            $objnormal->numero = $db->nfnumero;
            $objnormal->serie = $db->nfserie;
            $objnormal->operacao = $db->operacao;
            $objnormal->destinatario = $db->destinatario;
            $objnormal->valor = $db->vnf;
            $nf->push($objnormal);
            $anterior = $db->{$principal};
            $count++;
            $total      += $objnormal->valor;
            $totalgeral += $objnormal->valor;
        }
        
        if(count($nf) > 0){
            $objtotal = (object) array();
            $objtotal->quant = $count;
            $objtotal->total = $total;
            $nf->push($objtotal);
            
            $objgeral = (object) array();
            $objgeral->quantidade = $dbnf->count();
            $objgeral->totalgeral = $totalgeral;
            $nf->push($objgeral);
        }
        $this->fazerFiltrosEmitidas($filtro);
        return $nf;
    }

    /**
     * @param $empresa_id
     * @param $dates
     * @param $modelo
     * @param Collection|Nfemitida|Nfrecebida $dbnf
     */
    function getInut($empresa_id, $dates, $modelo, &$dbnf)
    {
        $inut = DB::table("empresainutilizacaos")
            ->where("empresa_id", $empresa_id)
            ->whereBetween('datahora', $dates)->orderBy('datahora');
        $inutE = NfInutilizacaoEntrada::where("empresa_id", $empresa_id)
            ->whereBetween('datahora', $dates)->orderBy('datahora');

        if (! is_null($modelo)) {
            $inut->whereRaw("nfmodelo in (" . $modelo . ")");
            $inutE->whereRaw("nfmodelo in (" . $modelo . ")");
        }

        $inut = $inut->selectRaw('-1 as nf_id, to_char(datahora,\'dd/mm/yyyy\') as data, ' .
            '\'NF inexistente no banco\' as destinatario, nfmodelo, nini as nfnumero, nfeserie as nfserie, ' .
            '\'NF inexistente no banco\' as operacao, 0 as vnf, 0 as vdesc, -1 as operacao_id, ' .
            '\' \' as cfop')->get();
        $inutE = $inutE->selectRaw('-1 as nf_id, to_char(datahora,\'dd/mm/yyyy\') as data, ' .
            '\'NF inexistente no banco\' as destinatario, nfmodelo, nfnumero, nfserie, ' .
            '\'NF inexistente no banco\' as operacao, 0 as vnf, 0 as vdesc, -1 as operacao_id, ' .
            '\' \' as cfop')->get();

        foreach ($inut as $i) {
            $has = $dbnf->where("nfmodelo", $i->nfmodelo)->where("nfnumero", $i->nfnumero)->where("nfserie", $i->nfserie)->first() !== null;
            if (! $has) {
                $dbnf->push($i);
            }
        }
        foreach ($inutE as $i) {
            $has = $dbnf->where("nfmodelo", $i->nfmodelo)->where("nfnumero", $i->nfnumero)->where("nfserie", $i->nfserie)->first() !== null;
            if (! $has) {
                $dbnf->push($i);
            }
        }
    }

    function customSort(Collection $nfOriginal) {
        $nfOriginal = $nfOriginal->sortBy(function ($nf) {
            $date = Carbon::createFromFormat("d/m/Y", $nf->data);
            return $date->year . "-" . $date->month . "-" . $date->day;
        });
        $nfs = $nfOriginal->unique("data");

        $finalCollection = collect([]);
        foreach ($nfs as $nf) {
            $nfOriginal->where("data", $nf->data)->sortBy("destinatario")->each(function ($nf) use ($finalCollection) {
                $finalCollection->push($nf);
            });
        }
        
        return $finalCollection;
    }

    private function getProds($filtro)
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $produto_id = $filtro["produto"] == '0' ? -1 : $filtro["produto"];
        $cfop = $filtro["operacao"] == '0' ? -1 : $filtro["operacao"];
        $query = $this->getQuery($datainicio,$datafim,$empresa_id,$cfop,$produto_id);
        $dbitems = collect(DB::select($query));
        $items = collect([]);
        $anterior = null;
        $quantsaidas = 0;
        $quantentradas = 0;
        $saida = 0;
        $entrada = 0;
        $quantgeral = 0;
        $saidageral = 0;
        $entradageral = 0;
        $quantsaidageral = 0;
        $quantentradageral = 0;
        foreach ($dbitems as $item) {
            if(!is_null($anterior) && $anterior != $item->produto_id){
                $objtotal = (object) array();
                $objtotal->qantsaidas =  $quantsaidas ;
                $objtotal->qantentradas =  $quantentradas ;
                $objtotal->saidas = $saida;
                $objtotal->entradas = $entrada;
                $items->push($objtotal);
                $quantsaidas = 0;
                $quantentradas = 0;
                $entrada = 0;
                $saida = 0;
            }
            if(is_null($anterior) || $anterior != $item->produto_id){
                $objprincipal = (object) array();
                $objprincipal->produto_id = $item->produto_id;
                $objprincipal->produto = $item->produto;
                $items->push($objprincipal);
            }
            $objnormal = (object) array();
            $objnormal->data = requestDataOracle($item->data,false);
            $objnormal->modelo = $item->nfmodelo;
            $objnormal->serie = $item->nfserie;
            $objnormal->numero = $item->nfnumero;
            $objnormal->cfop = $item->cfop;
            $objnormal->tipo = $item->tipo == '0' ? 'E' : 'S';
            $objnormal->quantidade =  $item->quantidade;
            $objnormal->valor = $item->valor;
            $items->push($objnormal);
            $anterior = $item->produto_id;
            $quantentradas += $item->tipo == '0' ?  $item->quantidade : 0;
            $quantsaidas += $item->tipo == '0' ? 0 :  $item->quantidade;
            $entrada += $item->tipo == '0' ? $item->valor : 0;
            $saida += $item->tipo == '0' ? 0 : $item->valor;
            $quantgeral +=  $item->quantidade;
            $saidageral += $item->tipo   == '0' ? 0 : $item->valor;
            $entradageral += $item->tipo == '0' ? $item->valor : 0;
            $quantsaidageral += $item->tipo == '0'? 0 : $item->quantidade;
            $quantentradageral += $item->tipo == '0'? $item->quantidade : 0;
        }
        if(count($items) > 0){
            $objtotal = (object) array();
            $objtotal->qantsaidas =  $quantsaidas ;
            $objtotal->qantentradas =  $quantentradas ;
            $objtotal->saidas = $saida;
            $objtotal->entradas = $entrada;
            $items->push($objtotal);
            if($produto_id == -1){
                $objgeral = (object) array();
                $objgeral->quantsaidas =  $quantsaidageral ;
                $objgeral->quantentradas =  $quantentradageral ;
                $objgeral->saidageral = $saidageral;
                $objgeral->entradageral = $entradageral;
                $items->push($objgeral);
            }
        }
        $this->fazerFiltrosProdutos($filtro);
        return $items;
    }

    private function fazerFiltrosEmitidas($filtro)
    {
        $datainicio = requestDataOracle($filtro["datainicio"]);
        $datafim = requestDataOracle($filtro["datafim"]);
        $situacao = $filtro["situacao"] != 2002 ?Nfsituacao::where('id',$filtro["situacao"])->pluck('msgerroreceita')->first() : "Gravado/Rejeiçãos";
        $operacao = $filtro["operacao"] == "0" ? "todos" : Nfoperacao::where('id',$filtro["operacao"])->pluck('descricao')->first();
        $cliente = $filtro["cliente"] == "0" ? "todos" : Cliente::where('id',$filtro["cliente"])->pluck('nome')->first();
        if($filtro["modelos"])
            $modelo = $filtro["modelos"];
        else
            $modelo = "todos";
        $this->filtros = "Filtro: Período: $datainicio a $datafim, Situação: $situacao, ".
            "Operação: $operacao, Emitente/Destinatário: $cliente,  Modelo: $modelo";
    }

    private function fazerFiltrosProdutos($filtro)
    {
        $inicio = requestDataOracle($filtro["datainicio"]);
        $fim = requestDataOracle($filtro["datafim"]);
        $operacao = $filtro["operacao"] == "0" ? "todos" : Nfoperacao::where('id',$filtro["operacao"])->pluck('descricao')->first();
        $produto = $filtro["produto"] == "0" ? "todos" : Produto::where('id',$filtro["produto"])->pluck('descricao')->first();
        $this->filtros = "Filtro: Período $inicio a $fim, Operação: $operacao, Produto: $produto";
    }

    private function getQuery($datainicio, $datafim, $empresa_id, $operacao, $produto)
    {
        $subSel = "select produtos.descricao as produto, nfitems.cprod as produto_id, nf.nfmodelo, " .
            "nf.nfnumero, nf.nfserie, nf.tipo, nf.datahoraemissao as data, nfitems.qcom as quantidade, " .
            "cfop.cfop, nf.id, nf.vprod as nf_bruto, nfitems.vprod, coalesce(nf.vdesc,0) as vdesc ";
        $baseJoin = "inner join nfoperacaos cfop on nfitems.nfoperacao_id = cfop.id inner join produtos on nfitems.cprod = produtos.id ";
        $select = "produto, produto_id, nfmodelo, nfnumero, nfserie, tipo, data, cfop, id as nf_id, quantidade, " .
            "sum((vprod - (vdesc / (CASE WHEN nf_bruto = 0 THEN 1 ELSE nf_bruto END)) * vprod)) as valor from ( " . $subSel . "";
        $where = "where nf.datahoraemissao between to_date('$datainicio','yyyy-mm-dd hh24:mi:ss')" .
            " and to_date('$datafim','yyyy-mm-dd hh24:mi:ss')" .
            " and nf.nfsituacao_id = 100 ".
            " and (nfitems.cprod = $produto or $produto = -1) ".
            " and (nfitems.nfoperacao_id = $operacao or $operacao = -1) ".
            " and nf.empresa_id = $empresa_id ";
        $group = "group by produto, produto_id, nfmodelo, nfnumero, nfserie, tipo, data, cfop, id, quantidade";
        $query1 = "select " . $select . " from nfemitidas nf inner join nfemitidaitems nfitems on nfitems.nfemitida_id = nf.id " . $baseJoin . $where .") nfe " . $group;
        $query2 = "select " . $select . " from nfrecebidas nf inner join nfrecebidaitems nfitems on nfitems.nfrecebida_id = nf.id " . $baseJoin . $where .") docs " . $group;
        $query = /** @lang text */
            "select * from (" . $query1 . " UNION ALL " . $query2 . ") query order by produto, data";
        return $query;
    }
}
