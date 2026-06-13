<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Setor;
use App\Pedido;
use App\Cliente;
use App\Produto;
use App\Segmento;
use App\Services\CarbonCustom as Carbon;
use App\Tipopessoa;
use App\Colaborador;
use App\Pedidosituacao;
use App\Condicaopagamento;
use Illuminate\Http\Request;
use App\Repository\SelectRepository;

class ReportvendapdvController extends Controller
{
    private $empresa_id;
    private $grupo_id;
    private $repository;

    public function __construct(SelectRepository $repository){
        $this->repository = $repository;
    }

    public function pdvIndex() {
        $this->definition();
        $setores = $this->repository->getSetores()->prepend("Selecione","");
        $segmentos = $this->repository->getSegmentos()->prepend("Selecione","");
        return view('reports.vendas.segmento.segmento_setor')->with(compact('setores','segmentos'));
    }

    public function pdvPdf() {
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"];
        $compram = $get["compram"];
        if($compram == 1){
            $pdvfiltro = $this->buscaCompram($get);
            $pedidos = $pdvfiltro["pedidos"];
            $filtro = $pdvfiltro["filtro"];
            $titulo = "Relatório de Vendas por Segmento";
            if($tipo == 1){
                return view('reports.vendas.segmento.segmento_compraram_pdf')->with(compact('titulo','filtro','pedidos'));
            } else {
                $pdf = \App::make('dompdf.wrapper');
                $pdf->loadView('reports.vendas.segmento.segmento_compraram_pdf', compact('titulo','filtro','pedidos'));
                return $pdf->stream();
            }
        } else {
            $naocompram = $this->buscaNaoCompram($get);
            $clientes = $naocompram["clientes"];
            $filtro = $naocompram["filtro"];
            $titulo = "Relatório de Clientes sem Compras por Segmento";
            if($tipo == 1){
                return view('reports.vendas.segmento.setor_naocompram_pdf')->with(compact('titulo','filtro','clientes'));
            }
        }
    }

    private function buscaCompram($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $segmento = $filtro["segmento"] == 0 ? null : $filtro["segmento"];
        $setor = $filtro["setor"] == 0 ? null : $filtro["setor"];

        $operadorset = $setor == null ? '!=' : '=';
        $operadorseg = $segmento == null ? '!=' : '=';
        $situacao = DB::table('pedidosituacaos sit')
            ->whereRaw("grupo_id = " . $this->grupo_id . " and (fechadoconcluido = 1 or entregafinalizada = 1)")
            ->select('id')->get()->pluck('id')->toArray();

        $clientes = DB::table('clientes')->join('pedidos','pedidos.cliente_id','clientes.id')
                    ->join('pedidoitems as items','pedidos.id','items.pedido_id')
                    ->join('setors as setor','pedidos.entregasetor_id','setor.id')
                    ->join('produtos','items.produto_id','produtos.id')
                    ->join('segmentos','clientes.segmento_id','segmentos.id')
                    ->join('bairros','clientes.bairro_id','bairros.id')
                    ->join('cidades','clientes.cidade_id','cidades.id')
                    ->join('ruas','clientes.rua_id','ruas.id')
                    ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
                    ->whereIn('pedidos.pedidosituacao_id',$situacao)
                    ->where([
                        ['pedidos.empresa_id',$this->empresa_id],
                        ['pedidos.entregasetor_id',$operadorset,$setor],
                        ['clientes.segmento_id',$operadorseg,$segmento],
                        ['clientes.ativo',1],
                        ['items.precovendaunitario','>','0']
                    ])
                    ->select('clientes.id as cliente_id','pedidos.id as pedido_id','clientes.nome',
                        'clientes.numero','pedidos.valorvenda','items.precovendaunitario','items.produto_id',
                        'pedidos.datahoraprevisaoentrega as data','produtos.descricao as produto',
                        'ruas.descricao as rua', 'bairros.descricao as bairro','cidades.descricao as cidade',
                        'pedidos.valordesconto','setor.descricao as setor','pedidos.entregasetor_id as setor_id',
                        'clientes.segmento_id','segmentos.descricao as segmento',
                        DB::raw('sum(items.quantidade) as quantidade, sum(items.precovendatotal) as precovendatotal'))
                    ->groupBy('clientes.id', 'pedidos.id', 'clientes.nome', 'clientes.numero', 'pedidos.valorvenda', 
                        'items.precovendaunitario', 'items.produto_id', 'pedidos.datahoraprevisaoentrega', 
                        'produtos.descricao', 'ruas.descricao', 'bairros.descricao', 'cidades.descricao', 
                        'pedidos.valordesconto', 'setor.descricao', 'pedidos.entregasetor_id', 'clientes.segmento_id', 
                        'segmentos.descricao')
                    ->orderBy('segmento')->orderBy('clientes.nome')->orderBy('produto')->orderBy('data')
                    ->get();

        $pedidos = collect([]);
        $clianterior = null;
        $seganterior = null;
        $segdescanterior = null;
        $prodanterior = null;
        $setoranterior = null;
        $pedanterior = null;
        $count = 0;
        $countprod = 0;
        $totalsegmento = [];
        foreach($clientes as $cliente){
            if(! is_null($prodanterior) && $prodanterior != $cliente->produto_id 
                    || !is_null($prodanterior) && $clianterior != $cliente->cliente_id) {
                // $wprod = $clientes->where('cliente_id',$clianterior)
                //          ->where('produto_id',$prodanterior);
                $wprod = $clientes->filter(function ($cli) use($clianterior, $prodanterior) {
                    $clis = $cli->cliente_id == $clianterior;
                    $prod = $cli->produto_id == $prodanterior;
                    return $clis && $prod;
                });

                $objtotalprod = (object) array();
                $objtotalprod->qtdprod = $wprod->sum('quantidade');
                $total = $wprod->unique('pedido_id')->sum('precovendatotal');
                $objtotalprod->totaldesc = $pedidos->where('cli_id',$clianterior)->where('produto_id',$prodanterior)->sum('desconto');
                $objtotalprod->total = $total - $objtotalprod->totaldesc;
                $media = ($objtotalprod->total / $objtotalprod->qtdprod);
                $objtotalprod->media = requestNumeroDecimalOracle($media);
                $pedidos->push($objtotalprod);
            }
            if(!is_null($clianterior) && $clianterior != $cliente->cliente_id 
                    || !is_null($clianterior) && $seganterior != $cliente->segmento_id) {
                // $wcli = $clientes->where('cliente_id',$clianterior)->where('segmento_id',$seganterior);
                $wcli = $clientes->filter(function ($cli) use($clianterior, $seganterior) {
                    $seg = $cli->segmento_id == $seganterior;
                    $cliente = $cli->cliente_id == $clianterior;
                    return $seg && $cliente;
                });
                $objtotalcli = (object) array();
                $objtotalcli->quanttotal = $wcli->sum('quantidade');
                $objtotalcli->totaldesc = $wcli->unique('pedido_id')->sum('valordesconto');
                $objtotalcli->vendatotal = $wcli->unique('pedido_id')->sum('valorvenda');
                $pedidos->push($objtotalcli);
            }
            if(is_null($seganterior) || $seganterior != $cliente->segmento_id){
                if(count($totalsegmento)>0){
                    $objtot = (object) array();
                    $objtot->segmentototal = "Total do Segmento: " . $segdescanterior;
                    $pedidos->push($objtot);        
                    foreach($totalsegmento as $key=>$tot){
                        $objtot = (object) array();
                        $objtot->segmentoproduto_id = $key;
                        $objtot->segmentoproduto = $tot[0];
                        $objtot->segmentoqtde = $tot[1];
                        $objtot->segmentovalor = $tot[2];
                        $objtot->segmentodesconto = $tot[3];
                        $pedidos->push($objtot);        
                    }
                }
                $objsegmento = (object) array();
                $objsegmento->segmento_id = $cliente->segmento_id;
                $objsegmento->segmento = $cliente->segmento;
                $pedidos->push($objsegmento);
                $totalsegmento = [];
            }
            if(is_null($clianterior) || $clianterior != $cliente->cliente_id ||
                     $seganterior != $cliente->segmento_id){
                $objcliente = (object) array();
                $objcliente->cliente_id = $cliente->cliente_id;
                $objcliente->cliente = $cliente->nome;
                $objcliente->endereco = $cliente->bairro . " - " . $cliente->rua . ", " . $cliente->numero;
                $pedidos->push($objcliente);
                $countprod = 0;
            }

            $valortotal = $clientes->unique('pedido_id')->where('pedido_id',$cliente->pedido_id)->sum('valorvenda') + $cliente->valordesconto;
            $objnormal = (object) array();
            $objnormal->produto_id = $cliente->produto_id;
            $objnormal->cli_id = $cliente->cliente_id;
            $objnormal->data = requestDataOracle($cliente->data,false);
            $objnormal->produto = $cliente->produto;
            $objnormal->quantidade = $cliente->quantidade;
            $objnormal->valor = $cliente->precovendaunitario * $objnormal->quantidade;
            $desconto = ($objnormal->valor / $valortotal) * $cliente->valordesconto;
            $objnormal->desconto = $desconto;
            $objnormal->pedido_id = $cliente->pedido_id;
            $objnormal->setor = $cliente->setor;
            $pedidos->push($objnormal);
            $clianterior = $cliente->cliente_id;
            $seganterior = $cliente->segmento_id;
            $segdescanterior = $cliente->segmento;
            $prodanterior = $cliente->produto_id;
            $setoranterior = $cliente->setor_id;
            $pedanterior = $cliente->pedido_id;
            //total por segmentos
            if(!array_key_exists($cliente->produto_id, $totalsegmento)){
                $totalsegmento[$cliente->produto_id] = [$cliente->produto, $objnormal->quantidade, $objnormal->valor, $objnormal->desconto];
            } else {
                $totalsegmento[$cliente->produto_id][1] += $objnormal->quantidade;
                $totalsegmento[$cliente->produto_id][2] += $objnormal->valor;
                $totalsegmento[$cliente->produto_id][3] += $objnormal->desconto;
            }
            $count++;
            if ($count == count($clientes)) {
                // $wprod = $clientes->where('cliente_id',$clianterior)
                //                 ->where('produto_id',$prodanterior);
                $wprod = $clientes->filter(function ($cli) use($clianterior, $prodanterior) {
                    $prod = $cli->produto_id == $prodanterior;
                    $clis = $cli->cliente_id == $clianterior;
                    return $prod && $clis;
                });
                $objtotalprod = (object) array();
                $objtotalprod->qtdprod = $wprod->sum('quantidade');
                $total = $wprod->unique('pedido_id')->sum('precovendatotal');
                $objtotalprod->totaldesc = $pedidos->where('cli_id',$clianterior)->where('produto_id',$prodanterior)->sum('desconto');
                $objtotalprod->total = $total - $objtotalprod->totaldesc;
                $media = ($objtotalprod->total / $objtotalprod->qtdprod);
                $objtotalprod->media = requestNumeroDecimalOracle($media);
                $pedidos->push($objtotalprod);
                
                $wcli = $clientes->where('cliente_id',$clianterior)->where('segmento_id',$seganterior);
                $objtotalcli = (object) array();
                $objtotalcli->quanttotal = $wcli->sum('quantidade');
                $objtotalcli->totaldesc = $wcli->unique('pedido_id')->sum('valordesconto');
                $objtotalcli->vendatotal = $wcli->unique('pedido_id')->sum('valorvenda');
                $pedidos->push($objtotalcli);
                if(count($totalsegmento)>0){
                    $objtot = (object) array();
                    $objtot->segmentototal = "Total do Segmento: " . $segdescanterior;
                    $pedidos->push($objtot);        
                    foreach($totalsegmento as $key=>$tot){
                        $objtot = (object) array();
                        $objtot->segmentoproduto_id = $key;
                        $objtot->segmentoproduto = $tot[0];
                        $objtot->segmentoqtde = $tot[1];
                        $objtot->segmentovalor = $tot[2];
                        $objtot->segmentodesconto = $tot[3];
                        $pedidos->push($objtot);        
                    }
                    $objtot = (object) array();
                    $objtot->segmentototalfim = "Total do Segmento: " . $segdescanterior;
                    $pedidos->push($objtot);        
                }

            }
        }

        $filtro = "Filtros: Período: " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $segmento == null ? $filtro . ", Segmento: todos" : $filtro . ", Segmento: " . Segmento::where('id',$segmento)->pluck('descricao')->first();
        $filtro = $setor == null ? $filtro . ", Setor: todos, com compras." : $filtro . ", Setor: " . Setor::where('id',$setor)->pluck('descricao')->first().", com compras.";
        $retorno["pedidos"] = $pedidos;
        $retorno["filtro"] = $filtro;
        return $retorno;
    }

    private function buscaNaoCompram($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $segmento_id = $filtro["segmento"] == 0 ? null : $filtro["segmento"];
        $setor_id = $filtro["setor"] == 0 ? null : $filtro["setor"];

        $cli = DB::table('clientes')
                    ->leftJoin('setors as setor','clientes.setor_id','setor.id')
                    ->join('segmentos','clientes.segmento_id','segmentos.id')
                    ->join('bairros','clientes.bairro_id','bairros.id')
                    ->join('ruas','clientes.rua_id','ruas.id')
                    ->where([
                        ['clientes.ativo',1],
                        ['clientes.cliente',1],
                        ['clientes.empresa_id',$this->empresa_id]
                    ])
                    ->whereRaw("clientes.id not in (select cliente_id from pedidos where pedidosituacao_id in (select id from pedidosituacaos
                             where ativo = 1 and grupo_id = $this->grupo_id and fechadoconcluido = 1 or entregafinalizada = 1) and
                             datahoraprevisaoentrega between to_date('$datainicio','yyyy-mm-dd hh24:mi:ss') and 
                             to_date('$datafim','yyyy-mm-dd hh24:mi:ss'))");
        $cli = is_null($segmento_id) ? $cli : $cli->where('clientes.segmento_id',$segmento_id);
        $cli = is_null($setor_id) ? $cli : $cli->where('clientes.setor_id',$setor_id);
        $cli = $cli->select('clientes.id','clientes.nome','setor.descricao as setor','segmentos.descricao as segmento',
                            'clientes.segmento_id','clientes.setor_id',
                            DB::raw("(bairros.descricao || ' - ' || ruas.descricao || ', ' || clientes.numero) as endereco"),
                            DB::raw('(select listagg(telefone) within group(order by telefone) as telefone
                            from clientetelefones tel where tel.cliente_id = clientes.id) as telefone'))
                    ->orderBy('segmento')->orderBy('clientes.nome')->get();
        $clientes = collect([]);
        $seganterior = null;
        $count = 0;
        $clisegcount = 0;
        foreach($cli as $cliente){
            if(!is_null($seganterior) && $seganterior != $cliente->segmento_id){
                $objtotal = (object) array();
                $objtotal->totalcliente = $cli->where('segmento_id',$seganterior)->count();
                $clientes->push($objtotal);
            }
            if(is_null($seganterior) || $seganterior != $cliente->segmento_id){
                $objseg = (object) array();
                $objseg->segmento_id = $cliente->segmento_id;
                $objseg->segmento = $cliente->segmento;
                $clientes->push($objseg);
                $clisegcount = 0;
            }
            $objnormal = (object) array();
            $objnormal->nome = $cliente->nome;
            $objnormal->endereco = $cliente->endereco;
            $objnormal->telefone = $cliente->telefone;
            $objnormal->setor = $cliente->setor;
            $clientes->push($objnormal);
            $seganterior = $cliente->segmento_id;
            $count++;
            $clisegcount++;
            if($count == count($cli)){
                if($clisegcount > 1){
                    $objtotal = (object) array();
                    $objtotal->totalcliente = $cli->where('segmento_id',$seganterior)->count();
                    $clientes->push($objtotal);
                }
                $objtotalgeral = (object) array();
                $objtotalgeral->total = $count;
                $clientes->push($objtotalgeral);
            }
        }
        $filtro = "Filtros: Período: " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $segmento_id == null ? $filtro . ", Segmento: todos" : $filtro . ", Segmento: " . Segmento::where('id',$segmento_id)->pluck('descricao')->first();
        $filtro = $setor_id == null ? $filtro . ", Setor: todos, sem compras." : $filtro . ", Setor: " . Setor::where('id',$setor_id)->pluck('descricao')->first() . ", sem compras.";
        $retorno["clientes"] = $clientes;
        $retorno["filtro"] = $filtro;
        return $retorno;
    }

    //////////////Vendas por Entregador
    public function entregadorIndex(){
        $this->definition();
        $colaboradores = $this->repository->getSetorColaborador()->prepend("Selecione","");
        return view('reports.vendas.entregador.entregador')->with(compact('colaboradores'));
    }

    public function filtroEntregador(){
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"];
        $entregar = $this->buscaEntregador($get);
        $filtro = $entregar["filtro"];
        $entregadores = $entregar["entregadores"];
        $titulo = "Relatório de Vendas por Entregador";
        if($tipo == 1){
            return view('reports.vendas.entregador.entregador_pdf')->with(compact('titulo','filtro','entregadores'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.vendas.entregador.entregador_pdf', compact('titulo','filtro','entregadores'));
            return $pdf->stream();
        }
    }

    private function buscaEntregador($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $colaborador_id = $filtro["colaborador"] == 0 ? null : $filtro["colaborador"];

        $concluido = Pedidosituacao::where(['fechadoconcluido'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $realizado = Pedidosituacao::where(['entregafinalizada'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $situacao = array_merge($concluido,$realizado);
        $colaboradores = DB::table('pedidos')
                        ->join('colaboradors as colaborador','pedidos.colaborador_id','colaborador.id')
                        ->join('pedidooperacaos as operacoes','pedidos.pedidooperacao_id','operacoes.id')
                        ->join('pedidoitems as items','items.pedido_id','pedidos.id')
                        ->join('produtos','items.produto_id','produtos.id')
                        ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
                        ->whereIn('pedidos.pedidosituacao_id',$situacao)
                        ->where([
                            ['pedidos.empresa_id',$this->empresa_id],
                            ['items.precovendaunitario','>','0']
                        ]);

        if(!is_null($colaborador_id))
            $colaboradores = $colaboradores->where('pedidos.colaborador_id',$colaborador_id);

        $colaboradores = $colaboradores->select('pedidos.id','pedidos.colaborador_id','colaborador.nome','pedidos.pedidooperacao_id',
                                    'operacoes.descricao as operacao','items.produto_id','produtos.descricao as produto','pedidos.valorvenda',
                                    'items.quantidade','items.precovendatotal','items.precovendaunitario','pedidos.valordesconto')
                                 ->orderBy('colaborador.nome')->orderBy('operacao')->orderBy('produto')->orderBy('pedidos.id')
                                 ->get();
        $entregadores = collect([]);
        $anterior = null;
        $opanterior = null;
        $count = 0;
        $countcol = 0;
        $countop = 0;
        foreach($colaboradores as $colaborador){
            if(!is_null($opanterior) && $opanterior != $colaborador->pedidooperacao_id 
                    || !is_null($opanterior) && $anterior != $colaborador->colaborador_id){
                $wop = $colaboradores->where('colaborador_id',$anterior)
                                     ->where('pedidooperacao_id',$opanterior);
                $objtotop = (object) array();
                $objtotop->quantidadetotal = $wop->sum('quantidade');
                $objtotop->totalop = $wop->unique('id')->sum('valorvenda');
                $objtotop->totaldesconto = $wop->unique('id')->sum('valordesconto');
                $entregadores->push($objtotop);
                $countop = 0;
            }
            if(!is_null($anterior) && $colaborador->colaborador_id != $anterior){
                $wcol = $colaboradores->where('colaborador_id',$anterior);
                $objtotcol = (object) array();
                $objtotcol->quantotal = $wcol->sum('quantidade');
                $objtotcol->totalcol = $wcol->unique('id')->sum('valorvenda');
                $objtotcol->descontototal = $wcol->unique('id')->sum('valordesconto');
                $entregadores->push($objtotcol);
            }
            if(is_null($anterior) || $colaborador->colaborador_id != $anterior){
                $objcol = (object) array();
                $objcol->colaborador_id = $colaborador->colaborador_id;
                $objcol->colaborador = $colaborador->nome;
                $entregadores->push($objcol);
                $countcol++;
            }
            if(is_null($opanterior) || $anterior != $colaborador->colaborador_id
                    || $opanterior != $colaborador->pedidooperacao_id){
                $objop = (object) array();
                $objop->operacao_id = $colaborador->pedidooperacao_id;
                $objop->operacao = $colaborador->operacao;
                $entregadores->push($objop);
            }
            $valtotal = $colaboradores->unique('id')->where('id',$colaborador->id)->sum('valorvenda') + $colaborador->valordesconto;
            $objnormal = (object) array();
            $objnormal->pedido_id = $colaborador->id;
            $objnormal->produto = $colaborador->produto;
            $objnormal->quantidade = $colaborador->quantidade;
            $objnormal->valor = $colaborador->precovendaunitario * $objnormal->quantidade;
            $objnormal->desconto = ($objnormal->valor / $valtotal) * $colaborador->valordesconto;
            $entregadores->push($objnormal);
            $anterior = $colaborador->colaborador_id;
            $opanterior = $colaborador->pedidooperacao_id;
            $count++;
            $countop++;
            if($count == count($colaboradores)){
                if($countcol > 1){
                    $wcol = $colaboradores->where('colaborador_id',$anterior);
                    $objtotcol = (object) array();
                    $objtotcol->quantotal = $wcol->sum('quantidade');
                    $objtotcol->totalcol = $wcol->unique('id')->sum('valorvenda');
                    $objtotcol->descontototal = $wcol->unique('id')->sum('valordesconto');
                    $entregadores->push($objtotcol);
                }
                if($countop > 1){
                    $wop = $colaboradores->where('colaborador_id',$anterior)
                                     ->where('pedidooperacao_id',$opanterior);
                    $objtotop = (object) array();
                    $objtotop->quantidadetotal = $wop->sum('quantidade');
                    $objtotop->totalop = $wop->unique('id')->sum('valorvenda');
                    $objtotop->totaldesconto = $wop->unique('id')->sum('valordesconto');
                    $entregadores->push($objtotop);
                }
                $objtotalgeral = (object) array();
                $objtotalgeral->qtdtotal = $entregadores->sum('quantidade');
                $objtotalgeral->total = $colaboradores->unique('id')->sum('valorvenda');
                $objtotalgeral->descontototal = $entregadores->sum('desconto');
                $entregadores->push($objtotalgeral);
            }
        }
        // dd($entregadores);
        $filtro = "Filtro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $colaborador_id == null ? $filtro . ", Entregador: todos." : $filtro . ", Entregador: " . Colaborador::where('id',$colaborador_id)->pluck('nome')->first();
        $retorno["entregadores"] = $entregadores;
        $retorno["filtro"] = $filtro;
        return $retorno;
    }

    //Vendas Convenio
    public function indexConvenio(){
        $this->definition();
        $conveniados = $this->repository->getConvenios()->prepend('Selecione','');
        return view('reports.vendas.convenio.convenios')->with(compact('conveniados'));
    }

    public function convenioFiltro(){
        $this->definition();
        $get = $_GET;
        $tipoconv = $get["compram"];
        if($tipoconv == 1){
            $conv = $this->buscaCovenio($get);
            $titulo = "Relatório de Vendas por Convênio";
            $filtro = $conv["filtro"];
            $convenios = $conv["conveniados"];
            return view('reports.vendas.convenio.convenios_pdf')->with(compact('titulo','filtro','convenios'));
        }elseif($tipoconv == 2){
            $titulo = "Relatório de Clientes sem Compras por Convênio";
            $nconv = $this->buscaNaoConvenio($get);
            $filtro = $nconv["filtro"];
            $clientes = $nconv["clientes"];
            return view('reports.vendas.convenio.convenios_naocompram_pdf')->with(compact('titulo','filtro','clientes'));
        }elseif($tipoconv == 3){
            $titulo = "Relatório de Vendas por Convênio (outra forma de pagamento)";
            $conv = $this->buscaConvenioOutros($get);
            $filtro = $conv["filtro"];
            $convenios = $conv["conveniados"];
            return view('reports.vendas.convenio.convenios_outros_pdf')->with(compact('titulo','filtro','convenios'));
        }
    }

    private function buscaCovenio($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $convenio_id = $filtro["conveniado"] == 0 ? null : $filtro["conveniado"];

        $concluido = Pedidosituacao::where(['fechadoconcluido'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $realizado = Pedidosituacao::where(['entregafinalizada'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $situacao = array_merge($concluido,$realizado);
        $forma = Condicaopagamento::where(['grupo_id'=>$this->grupo_id,'ativo'=>1,'tipo'=>4])->pluck('id')->toArray();
        $convenios = DB::table('clienteconvenios as convenio')
                            ->join('clientes','convenio.cliente_id','clientes.convenio_id')
                            ->join('clientes as conveniado','convenio.cliente_id','conveniado.id')
                            ->join('pedidos','pedidos.cliente_id','clientes.id')
                            ->join('setors as setor','pedidos.entregasetor_id','setor.id')
                            ->join('pedidoitems as items','items.pedido_id','pedidos.id')
                            ->join('produtos','items.produto_id','produtos.id')
                            ->leftJoin('clienteprodutosconvenios prodcon', function ($query) {
                                $query->on('prodcon.cliente_id', 'clientes.convenio_id');
                                $query->on('prodcon.cliente_id', 'convenio.cliente_id');
                                $query->on('prodcon.produto_id', 'items.produto_id');
                                $query->on('prodcon.produto_id', 'produtos.id');
                            })
                            ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
                            ->whereIn('pedidos.pedidosituacao_id',$situacao)
                            ->whereIn('pedidos.condicaopagamento_id',$forma)
                            ->where([
                                ['clientes.empresa_id',$this->empresa_id],
                                ['items.precovendaunitario','>','0']
                            ]);
        if(!is_null($convenio_id))
            $convenios = $convenios->where('clientes.convenio_id',$convenio_id);
        
        $convenios = $convenios->select('clientes.id as cliente_id','clientes.nome','conveniado.nome as convenio','conveniado.id as conveniado_id','pedidos.valorvenda',
                                'clientes.id','pedidos.id as pedido_id','pedidos.entregasetor_id as setor_id','setor.descricao as setor','produtos.descricao as produto',
                                'items.quantidade','items.precovendaunitario','convenio.comissao',
                                DB::raw('coalesce(prodcon.preco, 0) as precovenda'))
                            ->orderBY('convenio')->orderBy('setor')->orderBy('clientes.nome')->get();

        $conveniados = collect([]);
        $anterior = null;
        $setanterior = null;
        $count = 0;
        $countcon = 0;
        $countset = 0;
        foreach ($convenios as $convenio) {
            if(!is_null($setanterior) && $setanterior != $convenio->setor_id 
            || !is_null($setanterior) && $anterior != $convenio->conveniado_id){
                $wset = $conveniados->where('convenio_id',$anterior)->where('set_id',$setanterior);
                $objtotalset = (object) array();
                $objtotalset->quantidadetot = $wset->sum('quantidade');
                $objtotalset->totalsetbruto = $wset->sum('valorbruto');
                $objtotalset->totalliquido = $wset->sum('valorliquido');
                $conveniados->push($objtotalset);
            }
            if(!is_null($anterior) && $anterior != $convenio->conveniado_id){
                $wconv = $conveniados->where('convenio_id',$anterior);
                $objtotalconv = (object) array();
                $objtotalconv->totalquantidade = $wconv->sum('quantidade');
                $objtotalconv->totalbruto = $wconv->sum('valorbruto');
                $objtotalconv->totalliquido = $wconv->sum('valorliquido');
                $conveniados->push($objtotalconv);
            }
            if(is_null($anterior) || $anterior != $convenio->conveniado_id){
                $objconv = (object) array();
                $objconv->conveniado_id = $convenio->conveniado_id;
                $objconv->convenio = $convenio->convenio;
                $conveniados->push($objconv);
                $countcon++;
            }
            if(is_null($setanterior) || $setanterior != $convenio->setor_id || $anterior != $convenio->conveniado_id){
                $objset = (object) array();
                $objset->setor_id = $convenio->setor_id;
                $objset->setor = $convenio->setor;
                $conveniados->push($objset);
                $countset = 0;
            }
            $objnormal = (object) array();
            $objnormal->set_id = $convenio->setor_id;
            $objnormal->convenio_id = $convenio->conveniado_id;
            $objnormal->pedido_id = $convenio->pedido_id;
            $objnormal->cliente = $convenio->nome;
            $objnormal->produto = $convenio->produto;
            $objnormal->quantidade = $convenio->quantidade;
            $objnormal->valorbruto = $convenio->precovenda * $objnormal->quantidade;
            $objnormal->desconto = $convenio->comissao;
            $objnormal->valorliquido = $objnormal->valorbruto - ($objnormal->valorbruto * $objnormal->desconto / 100);
            $conveniados->push($objnormal);
            $anterior = $convenio->conveniado_id;
            $setanterior = $convenio->setor_id;
            $count++;
            $countset++;
            if($count == count($convenios)){
                if($countset > 1){
                    $wset = $conveniados->where('convenio_id',$anterior)->where('set_id',$setanterior);
                    $objtotalset = (object) array();
                    $objtotalset->quantidadetot = $wset->sum('quantidade');
                    $objtotalset->totalsetbruto = $wset->sum('valorbruto');
                    $objtotalset->totalliquido = $wset->sum('valorliquido');
                    $conveniados->push($objtotalset);
                }
                if($countcon > 1){
                    $wconv = $conveniados->where('convenio_id',$anterior);
                    $objtotalconv = (object) array();
                    $objtotalconv->totalquantidade = $wconv->sum('quantidade');
                    $objtotalconv->totalbruto = $wconv->sum('valorbruto');
                    $objtotalconv->totalliquido = $wconv->sum('valorliquido');
                    $conveniados->push($objtotalconv);
                }
                $objtotal = (object) array();
                $objtotal->qtdclientes = count($convenios);
                $objtotal->quantidadetotal = $conveniados->sum('quantidade');
                $objtotal->totalgeralbruto = $conveniados->sum('valorbruto');
                $objtotal->totalgeraliquido = $conveniados->sum('valorliquido');
                $conveniados->push($objtotal);
            }
        }
        $filtro = "FIltro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $convenio_id == null ? $filtro . ", Convênio: todos, com compras." : $filtro . ", Convênio: " . Cliente::where('id',$convenio_id)->pluck('nome')->first().", com compras.";
        $retorno["filtro"] = $filtro;
        $retorno["conveniados"] = $conveniados;
        return $retorno;
    }

    private function buscaNaoConvenio($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $convenio_id = $filtro["conveniado"] == "0" ? null : $filtro["conveniado"];

        $forma = Condicaopagamento::where(['grupo_id'=>$this->grupo_id,'ativo'=>1,'tipo'=>4])->pluck('id')->toArray();
        $concluido = Pedidosituacao::where(['fechadoconcluido'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $realizado = Pedidosituacao::where(['entregafinalizada'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $situacao = array_merge($concluido,$realizado);

        $nclientes = DB::table('clientes')->join('clientes as convenio','clientes.convenio_id','convenio.id')
                            ->leftJoin('setors as setor','clientes.setor_id','setor.id')
                            ->join('cidades','clientes.cidade_id','cidades.id')
                            ->join('bairros','clientes.bairro_id','bairros.id')
                            ->join('ruas','clientes.rua_id','ruas.id')
                            ->where([
                                ['convenio.empresa_id',$this->empresa_id],
                                ['convenio.convenioativo',1],
                                ['clientes.ativo',1]
                            ])
                            ->whereRaw("clientes.id not in (select cliente_id from pedidos where datahoraprevisaoentrega between 
                                to_date('$datainicio','yyyy-mm-dd hh24:mi:ss') and to_date('$datafim','yyyy-mm-dd hh24:mi:ss')
                                and empresa_id = $this->empresa_id and condicaopagamento_id in (".implode(',',$forma).")
                                and pedidosituacao_id in (".implode(',',$situacao)."))");
        if(!is_null($convenio_id))
            $nclientes = $nclientes->where('clientes.convenio_id',$convenio_id);
        
        $nclientes = $nclientes->select('convenio.id as convenio_id','convenio.nome as convenio','clientes.id','clientes.nome',
                                    'clientes.setor_id', 'setor.descricao as setor',
                                    DB::raw("(bairros.descricao || ' - ' || ruas.descricao || ', ' || clientes.numero) as endereco"),
                                    DB::raw("(select listagg(tel.telefone,', ') within group (order by tel.telefone) as telefone 
                                    from clientetelefones tel where cliente_id = clientes.id and rownum <= 2) as telefone"))
                                ->orderBy('convenio')->orderBy('clientes.nome')->get();

        $clientes = collect([]);
        $anterior = null;
        $count = 0;
        $countcon = 0;
        foreach($nclientes as $cliente){
            if(!is_null($anterior) && $anterior != $cliente->convenio_id){
                $wconv = $nclientes->where('convenio_id',$anterior);
                $objtotconv = (object) array();
                $objtotconv->total = $wconv->count();
                $clientes->push($objtotconv);
            }
            if(is_null($anterior) || $anterior != $cliente->convenio_id){
                $objconv = (object) array();
                $objconv->convenio_id = $cliente->convenio_id;
                $objconv->convenio = $cliente->convenio;
                $clientes->push($objconv);
                $countcon++;
            }
            $objnormal = (object) array();
            $objnormal->cliente_id = $cliente->id;
            $objnormal->cliente = $cliente->nome;
            $objnormal->endereco = $cliente->endereco;
            $telefone = $cliente->telefone;
            $telefone = str_replace('(','',$telefone);
            $telefone = str_replace(')','',$telefone);
            $objnormal->telefone = $telefone;
            $objnormal->setor = $cliente->setor;
            $clientes->push($objnormal);
            $anterior = $cliente->convenio_id;
            $count++;
            if($count == count($nclientes)){
                if($countcon > 1){
                    $wconv = $nclientes->where('convenio_id',$anterior);
                    $objtotconv = (object) array();
                    $objtotconv->total = $wconv->count();
                    $clientes->push($objtotconv);
                }
                $objtotalgeral = (object) array();
                $objtotalgeral->totalgeral = $count;
                $clientes->push($objtotalgeral);
            }
        }
        $filtro = "FIltro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $convenio_id == null ? $filtro . ", Convênio: todos, sem compras." : $filtro . ", Convênio: " . Cliente::where('id',$convenio_id)->pluck('nome')->first().", sem compras.";
        $retorno["filtro"] = $filtro;
        $retorno["clientes"] = $clientes;
        return $retorno;
    }

    private function buscaConvenioOutros($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $convenio_id = $filtro["conveniado"] == 0 ? null : $filtro["conveniado"];

        $concluido = Pedidosituacao::where(['fechadoconcluido'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $realizado = Pedidosituacao::where(['entregafinalizada'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $situacao = array_merge($concluido,$realizado);
        $forma = Condicaopagamento::where(['grupo_id'=>$this->grupo_id,'ativo'=>1,'tipo'=>4])->pluck('id')->toArray();
        $convenios = DB::table('clienteconvenios as convenio')
                            ->join('clientes','convenio.cliente_id','clientes.convenio_id')
                            ->join('clientes as conveniado','convenio.cliente_id','conveniado.id')
                            ->join('pedidos','pedidos.cliente_id','clientes.id')
                            ->join('setors as setor','pedidos.entregasetor_id','setor.id')
                            ->leftJoin('condicaopagamentos','pedidos.condicaopagamento_id','condicaopagamentos.id')
                            ->join('pedidoitems as items','items.pedido_id','pedidos.id')
                            ->join('produtos','items.produto_id','produtos.id')
                            ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
                            ->whereIn('pedidos.pedidosituacao_id',$situacao)
                            ->whereNotIn('pedidos.condicaopagamento_id',$forma)
                            ->where([
                                ['clientes.empresa_id',$this->empresa_id],
                                ['items.precovendaunitario','>','0']
                            ]);
        if(!is_null($convenio_id))
            $convenios = $convenios->where('clientes.convenio_id',$convenio_id);
        
        $convenios = $convenios->select('clientes.id as cliente_id','clientes.nome','conveniado.nome as convenio','conveniado.id as conveniado_id','pedidos.valorvenda',
                                'clientes.id','pedidos.id as pedido_id','pedidos.entregasetor_id as setor_id','setor.descricao as setor','produtos.descricao as produto',
                                'items.quantidade','items.precovendaunitario','convenio.comissao', 'condicaopagamentos.descricao as forma_pagamento', 
                                DB::raw("TO_CHAR(pedidos.datahoraacao, 'DD/MM/YYYY')  as datahoraacao"),
                                DB::raw('coalesce(items.precovendaunitario, 0) as precovenda'))
                            ->orderBY('convenio')->orderBy('setor')->orderBy('clientes.nome')->orderBy('pedidos.datahoraacao')->get();

        $conveniados = collect([]);
        $anterior = null;
        $setanterior = null;
        $count = 0;
        $countcon = 0;
        $countset = 0;
        foreach ($convenios as $convenio) {
            if(!is_null($setanterior) && $setanterior != $convenio->setor_id 
            || !is_null($setanterior) && $anterior != $convenio->conveniado_id){
                $wset = $conveniados->where('convenio_id',$anterior)->where('set_id',$setanterior);
                $objtotalset = (object) array();
                $objtotalset->quantidadetot = $wset->sum('quantidade');
                $objtotalset->totalsetbruto = $wset->sum('valorbruto');
                $objtotalset->totalliquido = $wset->sum('valorliquido');
                $conveniados->push($objtotalset);
            }
            if(!is_null($anterior) && $anterior != $convenio->conveniado_id){
                $wconv = $conveniados->where('convenio_id',$anterior);
                $objtotalconv = (object) array();
                $objtotalconv->totalquantidade = $wconv->sum('quantidade');
                $objtotalconv->totalbruto = $wconv->sum('valorbruto');
                $objtotalconv->totalliquido = $wconv->sum('valorliquido');
                $conveniados->push($objtotalconv);
            }
            if(is_null($anterior) || $anterior != $convenio->conveniado_id){
                $objconv = (object) array();
                $objconv->conveniado_id = $convenio->conveniado_id;
                $objconv->convenio = $convenio->convenio;
                $conveniados->push($objconv);
                $countcon++;
            }
            if(is_null($setanterior) || $setanterior != $convenio->setor_id || $anterior != $convenio->conveniado_id){
                $objset = (object) array();
                $objset->setor_id = $convenio->setor_id;
                $objset->setor = $convenio->setor;
                $conveniados->push($objset);
                $countset = 0;
            }
            $objnormal = (object) array();
            $objnormal->set_id = $convenio->setor_id;
            $objnormal->convenio_id = $convenio->conveniado_id;
            $objnormal->pedido_id = $convenio->pedido_id;
            $objnormal->cliente = $convenio->nome;
            $objnormal->forma_pagamento = $convenio->forma_pagamento;
            $objnormal->datahoraacao = $convenio->datahoraacao;
            $objnormal->produto = $convenio->produto;
            $objnormal->quantidade = $convenio->quantidade;
            $objnormal->valorbruto = $convenio->precovenda * $objnormal->quantidade;
            $objnormal->desconto = 0;
            $objnormal->valorliquido = $convenio->precovenda * $objnormal->quantidade;
            $conveniados->push($objnormal);
            $anterior = $convenio->conveniado_id;
            $setanterior = $convenio->setor_id;
            $count++;
            $countset++;
            if($count == count($convenios)){
                if($countset > 1){
                    $wset = $conveniados->where('convenio_id',$anterior)->where('set_id',$setanterior);
                    $objtotalset = (object) array();
                    $objtotalset->quantidadetot = $wset->sum('quantidade');
                    $objtotalset->totalsetbruto = $wset->sum('valorbruto');
                    $objtotalset->totalliquido = $wset->sum('valorliquido');
                    $conveniados->push($objtotalset);
                }
                if($countcon > 1){
                    $wconv = $conveniados->where('convenio_id',$anterior);
                    $objtotalconv = (object) array();
                    $objtotalconv->totalquantidade = $wconv->sum('quantidade');
                    $objtotalconv->totalbruto = $wconv->sum('valorbruto');
                    $objtotalconv->totalliquido = $wconv->sum('valorliquido');
                    $conveniados->push($objtotalconv);
                }
                $objtotal = (object) array();
                $objtotal->qtdclientes = count($convenios);
                $objtotal->quantidadetotal = $conveniados->sum('quantidade');
                $objtotal->totalgeralbruto = $conveniados->sum('valorbruto');
                $objtotal->totalgeraliquido = $conveniados->sum('valorliquido');
                $conveniados->push($objtotal);
            }
        }
        $filtro = "FIltro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $convenio_id == null ? $filtro . ", Convênio: todos, com compras (outra forma pagto)." : $filtro . ", Convênio: " . Cliente::where('id',$convenio_id)->pluck('nome')->first().", com compras (outra forma pagto).";
        $retorno["filtro"] = $filtro;
        $retorno["conveniados"] = $conveniados;
        return $retorno;
    }


    private function definition(){
        //Id da empresa padrao
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //Grupo da empresa
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //Set id da empresa do repositorio
        $this->repository->setEmpresa($this->empresa_id);
        //Set id do grupo da empresa no repositorio
        $this->repository->setGrupo($this->grupo_id);
    }
}

/*

$nao = DB::select("select id,nome,endereco,listagg(telefone,', ') within group (order by telefone) as telefone,setor,segmento
from (select clientes.id,clientes.nome,tel.telefone,
		(select descricao from cidades where clientes.cidade_id = id) || ' - ' ||
		(select descricao from bairros where clientes.bairro_id = id) || ' - ' ||
		(select descricao from ruas where clientes.rua_id = id) || ', ' || clientes.numero as endereco,
	setor.descricao as setor, segmentos.descricao as segmento
	from clientes 
	inner join clientetelefones tel on tel.cliente_id = clientes.id 
	left join setors setor on clientes.setor_id = setor.id
	inner join segmentos on clientes.segmento_id = segmentos.id
	where clientes.empresa_id = 1 and clientes.ativo = 1 and clientes.cliente = 1)
group by id,nome,endereco,setor,segmento
order by segmento,nome");
dd($nao);


// $cli = Cliente::with('telefones')
//             ->leftJoin('setors as setor','clientes.setor_id','setor.id')
//             ->join('segmentos','clientes.segmento_id','segmentos.id')
//             ->join('bairros','clientes.bairro_id','bairros.id')
//             ->join('cidades','clientes.cidade_id','cidades.id')
//             ->join('ruas','clientes.rua_id','ruas.id')
//             ->whereNotIn('clientes.id',$ped)
//             ->where([
//                 ['clientes.empresa_id',$this->empresa_id],
//                 ['clientes.segmento_id',$operadorseg,$segmento],
//                 ['clientes.ativo',1]
//             ]);
// if($setor != null){
//     $cli = $cli->where('clientes.setor_id',$setor);
//     $set = Setor::where('id',$setor)->pluck('descricao')->first();
// }
// $cli = $cli->select('clientes.id','clientes.nome','clientes.setor_id','setor.descricao as setor',
//                        'ruas.descricao as rua', 'bairros.descricao as bairro',
//                        'cidades.descricao as cidade','clientes.numero','clientes.segmento_id',
//                        'segmentos.descricao as segmento')
//                     ->orderBy('segmento')->orderBy('clientes.nome')->get();

*/