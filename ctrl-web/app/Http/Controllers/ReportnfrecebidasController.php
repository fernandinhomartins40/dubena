<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Empresa;
use App\Produto;
use App\Cliente;
use App\Services\CarbonCustom as Carbon;
use App\Nfoperacao;

#Entrada de Documentos Fiscais
class ReportnfrecebidasController extends Controller
{

    private $filtros;

    public function index()
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $cfop = Nfoperacao::where([['empresa_id', Session::get('empresa_padrao')->id]])
            ->whereIn('aparecetela', [0,2])->orderBy('cfop', 'asc')->pluck('descricao', 'id')->prepend('Selecione', '');
        $produto = Produto::where(['empresa_id'=>$empresa_id,'ativo'=>1,'nfepermite'=>1])->orderBy('descricao')->pluck('descricao','id')->prepend('Selecione','');		
        return view('reports.nf.recebidas.index')->with(compact('cfop','produto'));
    }

    public function filtrarRecebidas()
    {
        $get = $_GET;
        $hub = $get["hub"] == 1;
        if($hub){
            $documentos = $this->getDocumentos($get);
            $titulo     = "Entrada de Documentos Fiscais";
            $filtro     = $this->filtros;
            return view('reports.nf.recebidas.recebidas_pdf')->with(compact('titulo','filtro','documentos'));
        }else{
            $documentos = $this->getProdutos($get);
            $titulo     = "Entrada de Documentos Fiscais por Produto";
            $filtro     = $this->filtros;
            return view('reports.nf.recebidas.recprods_pdf')->with(compact('titulo','filtro','documentos'));
        }
    }

    private function getDocumentos($filtro)
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $operacao = $filtro["operacao"] == '0' ? null : $filtro["operacao"];
        $emitente = $filtro["emitente"] == '0' ? null : $filtro["emitente"];
        switch($filtro["ordem"]){
            case 'D':
                $principal  = "data";
                $tipo = "Data Emissão";
                $tipo_sec = "Operação";
                $tipo_ter = "Emitente";
                $secundario = "operacao";
                $terciario  = "emitente";
                break;
            case "O":
                $principal  = "operacao";
                $tipo = "Operação";
                $tipo_sec = "Data Emissão";
                $tipo_ter = "Emitente";
                $secundario = "data";
                $terciario  = "emitente";
                break;
            default:
                $principal  = "emitente";
                $tipo = "Emitente";
                $tipo_sec = "Data Emissão";
                $tipo_ter = "Operação";
                $secundario = "data";
                $terciario  = "operacao";
                break;
        }

        $dbdoc = DB::table('nfrecebidas nf')->join('nfoperacaos op','nf.nfoperacao_id','op.id')
                    ->join('clientes','nf.cliente_id','clientes.id')
                    ->whereBetween('nf.datahoraemissao',[$datainicio,$datafim])
                    ->where('nf.tipolancamento', "1")
                    ->where('nf.empresa_id', $empresa_id);

        if(!is_null($operacao))
            $dbdoc = $dbdoc->where('nf.nfoperacao_id',$operacao);
        if(!is_null($emitente))
            $dbdoc = $dbdoc->where('nf.cliente_id',$emitente);
        
        $dbdoc = $dbdoc->select('nf.nfmodelo','nf.nfserie','nf.nfnumero','nf.datahoraemissao as data',
                        'op.descricao as operacao','clientes.nome as emitente','nf.vnf','nf.vdesc')
                    ->orderBy($principal)->orderBy($secundario)->orderBy($terciario)->get();
        
        $documentos = collect([]);
        $anterior = null;
        $total = 0;
        $count = 0;
        $totalgeral = 0;
        $countgeral = 0;
        foreach ($dbdoc as $doc) {
            $doc->data = requestDataOracle($doc->data,false);
            if(!is_null($anterior) && $anterior != $doc->{$principal}){
                $objtotal = (object) array();
                $objtotal->quantidade = $count;
                $objtotal->valor = $total;
                $documentos->push($objtotal);
                $total = 0;
                $count = 0;
            }
            if(is_null($anterior) || $anterior != $doc->{$principal}){
                $objprincipal = (object) array();
                $objprincipal->tipo = $tipo;
                $objprincipal->principal = $doc->{$principal};
                $objprincipal->sec = $tipo_sec;
                $objprincipal->ter = $tipo_ter;
                $documentos->push($objprincipal);
            }
            $objnormal = (object) array();
            $objnormal->secundario = $doc->{$secundario};
            $objnormal->terciario  = $doc->{$terciario};
            $objnormal->modelo = $doc->nfmodelo;
            $objnormal->numero = $doc->nfnumero;
            $objnormal->serie  = $doc->nfserie;
            $objnormal->valor  = $doc->vnf;
            $documentos->push($objnormal);
            $anterior = $doc->{$principal};
            $count++;
            $countgeral++;
            $totalgeral += $objnormal->valor;
            $total      += $objnormal->valor;
        }
        if(count($documentos) > 0){
            $objtotal = (object) array();
            $objtotal->quantidade = $count;
            $objtotal->valor = $total;
            $documentos->push($objtotal);
            if(is_null($emitente) || $principal != 'emitente'){
                $objgeral = (object) array();
                $objgeral->totalquant = $countgeral;
                $objgeral->totalgeral = $totalgeral;
                $documentos->push($objgeral);
            }
        }
        $this->makeFilters('recebidas',$filtro);
        return $documentos;
    }

    private function getProdutos($filtro)
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $produto = $filtro["produto"] == '0' ? null : $filtro["produto"];
        $operacao = $filtro["operacao"] == '0' ? null : $filtro["operacao"];
        
        $dbdoc = DB::table('nfrecebidas nf')->join('nfrecebidaitems nfitems','nfitems.nfrecebida_id','nf.id')
                        ->join('produtos','nfitems.cprod','produtos.id')
                        ->join('nfoperacaos op','nfitems.nfoperacao_id','op.id')
                        ->join('clientes','nf.cliente_id','clientes.id')
                        ->whereBetween('nf.datahoraemissao',[$datainicio,$datafim])
                        ->where('nf.tipolancamento', "1")
                        ->where('nf.empresa_id', $empresa_id);
        if(!is_null($produto))
            $dbdoc = $dbdoc->where('nfitems.cprod',$produto);
        if(!is_null($operacao))
            $dbdoc = $dbdoc->where('nfitems.nfoperacao_id',$operacao);
        
        $dbdoc =  $dbdoc->select('produtos.id as produto_id','produtos.descricao as produto','nf.nfnumero','nf.nfserie','clientes.nome',
                            'op.cfop','nfitems.qcom as quantidade','nf.datahoraemissao as data','nfitems.vuncom as unitario',
                            DB::raw("sum(nfitems.vprod - (coalesce(nf.vdesc,0) / nf.vprod) * nfitems.vprod) as valor"))
                        ->orderBy('produto')->orderBy('data')->orderBy('cfop')
                        ->groupBy('produtos.id','produtos.descricao','nf.nfnumero','nf.nfserie','clientes.nome',
                            'op.cfop','nfitems.qcom','datahoraemissao','nfitems.vuncom')
                        ->get();
        
        $documentos = collect([]);
        $anterior = null;
        $total = 0;
        $quant = 0;
        $totalgeral = 0;
        $quantgeral = 0;
        foreach ($dbdoc as $doc) {
            if(!is_null($anterior) && $anterior != $doc->produto_id){
                $objtotal = (object) array();
                $objtotal->quant = $quant;
                $objtotal->total = $total;
                $documentos->push($objtotal);
                $total = 0;
                $quant = 0;
            }
            if(is_null($anterior) || $anterior != $doc->produto_id){
                $objprincipal = (object) array();
                $objprincipal->produto_id = $doc->produto_id;
                $objprincipal->produto    = $doc->produto;
                $documentos->push($objprincipal);
            }
            $objnormal = (object) array();
            $objnormal->data     = requestDataOracle($doc->data,false);
            $objnormal->emitente = $doc->nome;
            $objnormal->numero   = $doc->nfnumero;
            $objnormal->serie    = $doc->nfserie;
            $objnormal->cfop     = $doc->cfop;
            $objnormal->quant    = $doc->quantidade;
            $objnormal->unitario = $doc->unitario;
            $objnormal->valor    = $doc->valor;
            $documentos->push($objnormal);
            $anterior = $doc->produto_id;
            $total      += $objnormal->valor;
            $quant      += $objnormal->quant;
            $totalgeral += $objnormal->valor;
            $quantgeral += $objnormal->quant;
        }
        if(count($documentos) > 0){
            $objtotal = (object) array();
            $objtotal->quant = $quant;
            $objtotal->total = $total;
            $documentos->push($objtotal);
            if(is_null($produto)){
                $objtotalgeral = (object) array();
                $objtotalgeral->quantgeral = $quantgeral;
                $objtotalgeral->totalgeral = $totalgeral;
                $documentos->push($objtotalgeral);
            }
        }
        $this->makeFilters('prods',$filtro);
        return $documentos;
    }

    private function makeFilters($which, $filtro)
    {
        $inicio = requestDataOracle($filtro["datainicio"]);
        $fim = requestDataOracle($filtro["datafim"]);
        $operacao = $filtro["operacao"] == '0' ? "todos" : Nfoperacao::where('id',$filtro["operacao"])->pluck('descricao')->first();
        $this->filtros = "Filtro: Período $inicio a $fim, Operação: $operacao";
        if($which == 'recebidas'){
            $emitente = $filtro["emitente"] == '0' ? "todos" : Cliente::where('id',$filtro["emitente"])->pluck('nome')->first();
            $this->filtros .= ", Emitente: $emitente";
            return true;
        }else{
            $produto = $filtro["produto"] == '0' ? "todos" : Produto::where('id',$filtro["produto"])->pluck('descricao')->first();
            $this->filtros .= ", Produto: $produto";
            return true;
        }
    }
}