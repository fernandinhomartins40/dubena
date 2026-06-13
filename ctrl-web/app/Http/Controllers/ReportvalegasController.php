<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Cliente;
use App\Services\CarbonCustom as Carbon;
use App\Tipopessoa;
use App\Valegasvenda;
use App\Valegassituacao;
use Illuminate\Http\Request;
use App\Repository\SelectRepository;

class ReportvalegasController extends Controller
{
    private $empresa_id;
    private $grupo_id;
    private $repository;

    function __construct(SelectRepository $repository){
        $this->repository = $repository;
    }

    public function valegasIndex(){
        $this->definition();
        $clientes = $this->clientesCompra()->prepend("Selecione","");
        $situacao = Valegassituacao::whereRaw("lower(descricao) = 'baixado' or lower(descricao) = 'cancelado'")->pluck('descricao','id')->prepend('Selecione','');
        return view('reports.vendas.valegas.report_valegas_index')->with(compact('clientes','situacao'));
    }

    public function valegasHub(){
        $this->definition();
        $get = $_GET;
        $rub = $get["rub"];
        switch($rub){
            case "1":
                return $this->pendente($get);
                break;
            case "2":
                return $this->baixado($get);
                break;
            case "3":
                return $this->venda($get);
                break;
            case "4":
                return $this->pedido($get);
                break;
            default:
                return $this->estoque($get);
                break;
        }
    }
    
    //Pendente
    private function pendente($filtro){
        $tipo = $filtro["tipo"];
        $titulo = "Relatório de Vale Gás Pendente";
        $valgas = $this->buscaPendente($filtro);
        $filtro = $valgas["filtro"];
        $valegas = $valgas["valegas"];
        if($tipo == "1"){
            return view('reports.vendas.valegas.pendente.pendente_pdf')->with(compact('titulo','filtro','valegas'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.vendas.valegas.pendente.pendente_pdf', compact('titulo','filtro','valegas'));
            return $pdf->stream();
        }
    }

    private function buscaPendente($filtro){
        $cliente_id = $filtro["cliente"] == "0" ? null : $filtro["cliente"];
        $prevenda = $filtro["prevenda"] == "true";

        if(!$prevenda){
            $vendido = Valegassituacao::where(DB::raw('upper(descricao)'),mb_strtoupper('vendido'))
                                ->pluck('id')->toArray();
            $impresso = Valegassituacao::where(DB::raw('upper(descricao)'),mb_strtoupper('impresso'))
                                ->pluck('id')->toArray();
            $situacao = array_merge($vendido,$impresso);
        }else{
            $prevenda = Valegassituacao::where(DB::raw('upper(descricao)'),mb_strtoupper('pré-venda'))
                                ->pluck('id')->toArray();
            $impressopre = Valegassituacao::where(DB::raw('upper(descricao)'),mb_strtoupper('impresso pré-venda'))
                                ->pluck('id')->toArray();
            $situacao = array_merge($prevenda,$impressopre);
        }
        
        $pendente = DB::table('valegas')->join('valegasvendas as venda','valegas.valegasvenda_id','venda.id')
                        ->join('clientes','venda.cliente_id','clientes.id')
                        ->join('produtos','valegas.produto_id','produtos.id')
                        ->join('valegassituacaos as situacao','valegas.valegassituacao_id','situacao.id')
                        ->where([
                            ['valegas.empresa_id',$this->empresa_id]
                        ])
                        ->whereIn('valegas.valegassituacao_id',$situacao);
        if(!is_null($cliente_id))
            $pendente = $pendente->where('venda.cliente_id',$cliente_id);
        $pendente = $pendente->select('venda.cliente_id','clientes.nome','venda.id as venda_id','venda.datavenda',
                                'valegas.codigo','produtos.descricao as produto','venda.valorunitario',
                                'situacao.descricao as situacao')
                             ->orderBy('clientes.nome')->orderBy('venda.datavenda')->get();
        $valegas = collect([]);
        $anterior = null;
        $count = 0;
        $counval = 0;
        $councli = 0;
        foreach($pendente as $vale){
            if(!is_null($anterior) && $anterior != $vale->cliente_id){
                $wcli = $pendente->where('cliente_id',$anterior);
                $objtotalcliente = (object) array();
                $objtotalcliente->quantidade = $wcli->count();
                $valegas->push($objtotalcliente);
            }
            if(is_null($anterior) || $anterior != $vale->cliente_id){
                $objcliente = (object) array();
                $objcliente->cliente_id = $vale->cliente_id;
                $objcliente->cliente = $vale->nome;
                $valegas->push($objcliente);
                $councli++;
                $counval = 0;
            }
            $objnormal = (object) array();
            $objnormal->venda_id = $vale->venda_id;
            $objnormal->data = requestDataOracle($vale->datavenda,false);
            $objnormal->valegas = $vale->codigo;
            $objnormal->produto = $vale->produto;
            $objnormal->valor = $vale->valorunitario;
            $objnormal->situacao = $vale->situacao;
            $valegas->push($objnormal);
            $anterior = $vale->cliente_id;
            $count++;
            $counval++;
            if($count == count($pendente)){
                if($councli > 1 && $counval > 1){
                    $wcli = $pendente->where('cliente_id',$anterior);
                    $objtotalcliente = (object) array();
                    $objtotalcliente->quantidade = $wcli->count();
                    $valegas->push($objtotalcliente);
                }
                $objtotalgeral = (object) array();
                $objtotalgeral->quantidadegeral = $count;
                $valegas->push($objtotalgeral);
            }
        }
        $filtro = $cliente_id == null ? "Filtros: Clientes: todos" : "Filtros: Cliente: " . Cliente::where('id',$cliente_id)->pluck('nome')->first();
        $filtro = $prevenda ? $filtro . ", pré-venda." : $filtro . ", vendas.";
        $retorno["filtro"] = $filtro;
        $retorno["valegas"] = $valegas;
        return $retorno;
    }

    //Baixado
    private function baixado($filtro){
        $tipo = $filtro["tipo"];
        $titulo = "Relatório de Vale Gás Baixado";
        $valgas = $this->buscaBaixados($filtro);
        $filtro = $valgas["filtro"];
        $valegas = $valgas["valegas"];
        if($tipo == "1"){
            return view('reports.vendas.valegas.baixado.baixado_pdf')->with(compact('titulo','filtro','valegas'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.vendas.valegas.baixado.baixado_pdf', compact('titulo','filtro','valegas'));
            return $pdf->stream();
        }
    }

    private function buscaBaixados($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $cliente_id = $filtro["cliente"] == "0" ? null : $filtro["cliente"];
        $situacaobaixado = $filtro["situacao"] == "0" ? null : $filtro["situacao"];
        
        $baixado = DB::table('valegas')->join('valegasvendas as venda','valegas.valegasvenda_id','venda.id')
                            ->join('clientes','venda.cliente_id','clientes.id')
                            ->join('produtos','valegas.produto_id','produtos.id')
                            ->join('valegassituacaos as situacao','valegas.valegassituacao_id','situacao.id')
                            ->where('valegas.empresa_id',$this->empresa_id)
                            ->whereNotNull('valegas.databaixa')
                            ->whereBetween('valegas.databaixa',[$datainicio,$datafim]);
        if(!is_null($cliente_id))
            $baixado = $baixado->where('venda.cliente_id',$cliente_id);
        if(!is_null($situacaobaixado))
            $baixado = $baixado->where('valegas.valegassituacao_id',$situacaobaixado);
        $baixado = $baixado->select('venda.cliente_id','clientes.nome','valegas.databaixa','venda.datavenda',
                                'valegas.codigo','produtos.descricao as produto','situacao.descricao as situacao')
                            ->orderBy('clientes.nome')->orderBy('valegas.databaixa')->get();
        
        $valegas = collect([]);
        $anterior = null;
        $count = 0;
        $countcli = 0;
        $countval = 0;
        foreach($baixado as $vale){
            if(!is_null($anterior) && $anterior != $vale->cliente_id){
                $wbai = $baixado->where('cliente_id',$anterior);
                $objtotalcliente = (object) array();
                $objtotalcliente->quantidade = $wbai->count();
                $valegas->push($objtotalcliente);
            }
            if(is_null($anterior) || $anterior != $vale->cliente_id){
                $objcliente = (object) array();
                $objcliente->cliente_id = $vale->cliente_id;
                $objcliente->cliente = $vale->nome;
                $valegas->push($objcliente);
                $countcli++;
                $countval = 0;
            }
            $objnormal = (object) array();
            $objnormal->databaixa = requestDataOracle($vale->databaixa,false);
            $objnormal->datavenda = requestDataOracle($vale->datavenda,false);
            $objnormal->valegas = $vale->codigo;
            $objnormal->produto = $vale->produto;
            $objnormal->situacao = $vale->situacao;
            $valegas->push($objnormal);
            $anterior = $vale->cliente_id;
            $count++;
            $countval++;
            if($count == count($baixado)){
                if($countcli > 1 && $countval > 1){
                    $wbai = $baixado->where('cliente_id',$anterior);
                    $objtotalcliente = (object) array();
                    $objtotalcliente->quantidade = $wbai->count();
                    $valegas->push($objtotalcliente);
                }
                $objtotal = (object) array();
                $objtotal->quantidadetotal = $count;
                $valegas->push($objtotal);
            }
        }
        $filtro = "Filtro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $cliente_id == null ? $filtro . ", Clientes: todos" : $filtro . ", Cliente: " . Cliente::where('id',$cliente_id)->pluck('nome')->first();
        $filtro = is_null($situacaobaixado) ? "$filtro, Situação: todas." : "$filtro, Situação: " . Valegassituacao::where('id',$situacaobaixado)->pluck('descricao')->first() . ".";
        $retorno["filtro"] = $filtro;
        $retorno["valegas"] = $valegas;
        return $retorno;
    }

    //Pedido
    private function pedido($filtro){
        $tipo = $filtro["tipo"];
        $titulo = "Relatório de Pedidos Baixados Vale Gás";
        $valgas = $this->buscaPedidos($filtro);
        $filtro = $valgas["filtro"];
        $valegas = $valgas["valegas"];
        if($tipo == "1"){
            return view('reports.vendas.valegas.pedido.pedido_pdf')->with(compact('titulo','filtro','valegas'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.vendas.valegas.pedido.pedido_pdf', compact('titulo','filtro','valegas'));
            return $pdf->stream();
        }
    }

    private function buscaPedidos($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $cliente_id = $filtro["cliente"] == "0" ? null : $filtro["cliente"];
        
        $situacao = DB::table('pedidosituacaos')
        ->whereRaw('grupo_id = ' . $this->grupo_id . ' and (fechadoconcluido = 1 or entregafinalizada = 1)')
        ->pluck('id')->toArray();

        $condicao = DB::table('condicaopagamentos')
        ->whereRaw('grupo_id = ' . $this->grupo_id . ' and tipo = 5')
        ->pluck('id')->toArray();

        $pedidos = DB::table('pedidos')
            ->join('clientes','pedidos.cliente_id','clientes.id')
            ->join('pedidoitems as items','items.pedido_id','pedidos.id')
            ->join('setors as setor','pedidos.entregasetor_id','setor.id')
            ->join('produtos','items.produto_id','produtos.id')
            ->join('bairros','clientes.bairro_id','bairros.id')
            ->join('ruas','clientes.rua_id','ruas.id')
            ->leftJoin('valegas', function($join)
            {
                $join->on('pedidos.id', '=', 'valegas.pedido_id');
                $join->on('items.produto_id', '=', 'valegas.produto_id');
        
            })
            ->leftJoin('valegasvendas as venda','valegas.valegasvenda_id','venda.id')
            ->leftJoin('clientes as pdv','venda.cliente_id','pdv.id')
            ->leftJoin('valegassituacaos as situacao','valegas.valegassituacao_id','situacao.id')
            ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
            ->whereIn('pedidos.pedidosituacao_id', $situacao)
            ->whereIn('pedidos.condicaopagamento_id', $condicao)
            ->where([
                ['clientes.empresa_id',$this->empresa_id],
            ]);
        if(!is_null($cliente_id))
            $pedidos = $pedidos->where('venda.cliente_id',$cliente_id);

        $pedidos = $pedidos->select('pedidos.id as pedido_id','clientes.nome','setor.id as setor_id', 'setor.descricao as setor','produtos.descricao as produto',
            'items.quantidade','items.precovendatotal','items.precovendaunitario','pedidos.valorvenda','pedidos.valordesconto', 'pdv.nome as pdv',
            'pedidos.datahoraprevisaoentrega as datapedido', 
            'valegas.codigo', 'situacao.descricao as valegas_situacao',
            DB::raw("(bairros.descricao || ' - ' || ruas.descricao || ', ' || clientes.numero) as endereco"),
            DB::raw("(select listagg(tel.telefone,', ') within group (order by tel.telefone) as telefone ". 
                                "from clientetelefones tel where cliente_id = clientes.id and rownum <= 2) as telefone"))
        ->orderBy('setor.descricao')->orderBy('clientes.nome')->orderBy('pedidos.id')->get();

        $valegas = collect([]);
        $anterior = null;
        $count = 0;
        $countcli = 0;
        $countval = 0;
        foreach($pedidos as $vale){
            if(!is_null($anterior) && $anterior != $vale->setor_id){
                $wbai = $pedidos->where('setor_id',$anterior);
                $objtotalsetor = (object) array();
                $objtotalsetor->quantidade = $wbai->count();
                $valegas->push($objtotalsetor);
            }
            if(is_null($anterior) || $anterior != $vale->setor_id){
                $objsetor = (object) array();
                $objsetor->setor_id = $vale->setor_id;
                $objsetor->setor = $vale->setor;
                $valegas->push($objsetor);
                $countcli++;
                $countval = 0;
            }
            $objnormal = (object) array();
            $objnormal->datapedido = requestDataOracle($vale->datapedido,false);
            $objnormal->valegas = $vale->codigo;
            $objnormal->produto = $vale->produto;
            $objnormal->situacao = $vale->valegas_situacao;
            $objnormal->cliente = $vale->nome;
            $objnormal->pdv = $vale->pdv;
            $objnormal->endereco = $vale->endereco;
            $objnormal->telefone = $vale->telefone;
            $valegas->push($objnormal);
            $anterior = $vale->setor_id;
            $count++;
            $countval++;
            if($count == count($pedidos)){
                if($countcli > 1 && $countval > 1){
                    $wbai = $pedidos->where('setor_id',$anterior);
                    $objtotalsetor = (object) array();
                    $objtotalsetor->quantidade = $wbai->count();
                    $valegas->push($objtotalsetor);
                }
                $objtotal = (object) array();
                $objtotal->quantidadetotal = $count;
                $valegas->push($objtotal);
            }
        }
        $filtro = "Filtro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $cliente_id == null ? $filtro . ", Clientes: todos" : $filtro . ", Cliente: " . Cliente::where('id',$cliente_id)->pluck('nome')->first();
        $retorno["filtro"] = $filtro;
        $retorno["valegas"] = $valegas;
        return $retorno;
    }


    private function clientesCompra(){
        $cliente = Cliente::rightJoin('valegasvendas as vendas','vendas.cliente_id','clientes.id')
                        ->where(['clientes.ativo'=>1,'clientes.empresa_id'=>$this->empresa_id])
                        ->orderBy('nome')->pluck('clientes.nome','clientes.id');
        return $cliente;
    }
    
    //Venda vale gás
    private function venda($filtro){
        $tipo = $filtro["tipo"]  == "1";
        $compram = $filtro["compram"] == "1";
        if($compram){
            $valgas = $this->buscaVendaCompram($filtro);
            $titulo = "Relatório de Venda de Vale Gás";
            $filtro = $valgas["filtro"];
            $clientes = $valgas["clientes"];
            if($tipo){
                return view('reports.vendas.valegas.venda.venda_pdf')->with(compact('titulo','filtro','clientes'));
            }else{
                $pdf = \App::make('dompdf.wrapper');
                $pdf->loadView('reports.vendas.valegas.venda.venda_pdf', compact('titulo','filtro','clientes'));
                return $pdf->setPaper('a4','landscape')->stream();
            }
        }else{
            $valgas = $this->buscaVendaNaoCompram($filtro);
            $titulo = "Relatório de Clientes sem Compras de Vale Gás";
            $filtro = $valgas["filtro"];
            $clientes = $valgas["clientes"];
            if($tipo){
                return view('reports.vendas.valegas.venda.venda_nao_pdf')->with(compact('titulo','filtro','clientes'));
            }
        }
    }

    private function buscaVendaCompram($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $cliente_id = $filtro["cliente"] == "0" ? null : $filtro["cliente"];
        $situacao_val = $filtro["situacao"] == "02";

        $vendido = Valegassituacao::where(DB::raw('upper(descricao)'),mb_strtoupper('vendido'))->pluck('id')->toArray();
        $impresso = Valegassituacao::where(DB::raw('upper(descricao)'),mb_strtoupper('impresso'))->pluck('id')->toArray();
        $situacao = array_merge($vendido,$impresso);
        $sit = implode(',',$situacao);
        $cliente = DB::table('clientes')->rightJoin('valegasvendas as venda','venda.cliente_id','clientes.id')
                            ->join('produtos','venda.produto_id','produtos.id')
                            ->join('condicaopagamentos as condicao','venda.condicaopagamento_id','condicao.id')
                            ->rightJoin('valegas',function($join) use ($situacao){
                                $join->on('valegas.valegasvenda_id','venda.id');
                                $join->on('valegas.produto_id','venda.produto_id');
                                $join->on('valegas.cliente_id','venda.cliente_id');
                                $join->on('valegas.cliente_id','clientes.id');
                                $join->on('valegas.produto_id','produtos.id');
                            })
                            ->join('cidades','clientes.cidade_id','cidades.id')
                            ->join('bairros','clientes.bairro_id','bairros.id')
                            ->join('ruas','clientes.rua_id','ruas.id')
                            ->whereBetween('venda.datavenda',[$datainicio,$datafim])
                            ->where([
                                ['venda.empresa_id',$this->empresa_id],
                                ['venda.cancelado',$situacao_val]
                            ]);
        if(!is_null($cliente_id))
            $cliente = $cliente->where('clientes.id',$cliente_id);
        $cliente = $cliente->select('venda.id as venda_id','clientes.id','clientes.nome','venda.datavenda','produtos.descricao as produto',
                                DB::raw("(SELECT count(*) FROM valegas WHERE cliente_id = clientes.id AND valegasvenda_id = venda.id) as quantidade"),
                                'venda.valorunitario','venda.valortotal','condicao.descricao as condicao',
                                'cidades.descricao as cidade','bairros.descricao as bairro','ruas.descricao as rua',
                                'clientes.numero',
                                DB::raw("(select listagg(tel.telefone,', ') within group (order by tel.telefone) as telefone ". 
                                "from clientetelefones tel where cliente_id = clientes.id and rownum <= 2) as telefone"))
                            ->orderBy('clientes.nome')->orderBy('venda.datavenda')->distinct()->get();
        $clientes = collect([]);
        $anterior = null;
        $count = 0;
        $countcli = 0;
        $countval = 0;
        foreach($cliente as $vale){
            if(!is_null($anterior) && $anterior != $vale->id){
                $wcli = $clientes->where('id',$anterior);
                $objtotalcliente = (object) array();
                $objtotalcliente->quantidadetotal = $wcli->sum('quantidade');
                $objtotalcliente->total = $wcli->sum('valortotal');
                $objtotalcliente->media = ($objtotalcliente->total / $objtotalcliente->quantidadetotal);
                $clientes->push($objtotalcliente);
            }
            if(is_null($anterior) || $anterior != $vale->id){
                $objcliente = (object) array();
                $objcliente->cliente_id = $vale->id;
                $objcliente->cliente = $vale->nome;
                $clientes->push($objcliente);
                $countcli++;
                $countval = 0;
            }
            $objnormal = (object) array();
            $objnormal->id = $vale->id;
            $objnormal->venda_id = $vale->venda_id;
            $objnormal->datavenda = requestDataOracle($vale->datavenda,false);
            $objnormal->produto = $vale->produto;
            $objnormal->quantidade = $vale->quantidade;
            $objnormal->valorunitario = $vale->valorunitario;
            $objnormal->valortotal = $objnormal->quantidade * $objnormal->valorunitario;
            $objnormal->condicao = $vale->condicao;
            $objnormal->endereco = "$vale->bairro - $vale->rua, $vale->numero";
            $telefone = $vale->telefone;
            $telefone = str_replace('(','',$telefone);
            $telefone = str_replace(')','',$telefone);
            $objnormal->telefone = $telefone;
            $clientes->push($objnormal);
            $anterior = $vale->id;
            $count++;
            $countval++;
            if($count == count($cliente)){
                if($countcli > 1 && $countval > 1){
                    $wcli = $clientes->where('id',$anterior);
                    $objtotalcliente = (object) array();
                    $objtotalcliente->quantidadetotal = $wcli->sum('quantidade');
                    $objtotalcliente->total = $wcli->sum('valortotal');
                    $objtotalcliente->media = ($objtotalcliente->total / $objtotalcliente->quantidadetotal);
                    $clientes->push($objtotalcliente);
                }
                $objtotal = (object) array();
                $objtotal->quantidade = $clientes->sum('quantidade');
                $objtotal->valor = $clientes->sum('valortotal');
                $objtotal->media = ($objtotal->valor / $objtotal->quantidade);
                $clientes->push($objtotal);
            }
        }
        $filtro = "Filtro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $cliente_id == null ? "$filtro, Clientes: todos" : "$filtro, Cliente: " . Cliente::where('id',$cliente_id)->pluck('nome')->first();
        $filtro = $situacao_val == true ? "$filtro, canceladas." : "$filtro, ativos.";
        $retorno["filtro"] = $filtro;
        $retorno["clientes"] = $clientes;
        return $retorno;
    }

    private function buscaVendaNaoCompram($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $juridicos = Tipopessoa::where(['grupo_id'=>$this->grupo_id,'ativo'=>1,'tipopessoacadastro'=>'J'])->pluck('id')->toArray();
        $clientes = DB::table('clientes')->join('cidades','clientes.cidade_id','cidades.id')
                            ->rightJoin('valegasvendas vendas','vendas.cliente_id','clientes.id')
                            ->join('bairros','clientes.bairro_id','bairros.id')
                            ->join('ruas','clientes.rua_id','ruas.id')
                            ->whereRaw("clientes.id not in (SELECT cliente_id FROM valegasvendas vendas WHERE ".
                                "datavenda BETWEEN to_date('$datainicio','yyyy-mm-dd hh24:mi:ss') AND ".
                                "to_date('$datafim','yyyy-mm-dd hh24:mi:ss') AND empresa_id = $this->empresa_id)")
                            ->where([
                                ['clientes.empresa_id',$this->empresa_id],
                                ['vendas.prevenda',0],
                                ['clientes.ativo',1]
                            ])
                            ->whereIn('clientes.tipopessoa_id',$juridicos)
                            ->select('clientes.nome as cliente',
                                DB::raw("(cidades.descricao || ' - ' || bairros.descricao ".
                                "|| ' - ' || ruas.descricao || ', ' || clientes.numero) as endereco"),
                                DB::raw("(select listagg(telefone,', ') within group (order by telefone) from ".
                                "clientetelefones where cliente_id = clientes.id) as telefone"))
                            ->orderBy('clientes.nome')->distinct()->get();

        $filtro = "FIltro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $retorno["filtro"] = $filtro;
        $retorno["clientes"] = $clientes;
        return $retorno;
    }

    private function estoque($filtro){
        $tipo = $filtro["tipo"] == "1";
        $est = $this->buscaEstoque();
        $titulo = "Relatório de Estoque a Considerar de Vale Gás";
        $filtro = $est["filtro"];
        $estoque = $est["estoque"];
        if($tipo){
            return view('reports.vendas.valegas.estoque.estoque_pdf')->with(compact('titulo','filtro','estoque'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.vendas.valegas.estoque.estoque_pdf', compact('titulo','filtro','estoque'));
            return $pdf->stream();
        }
    }

    private function buscaEstoque(){
        $vendido = Valegassituacao::where(DB::raw('upper(descricao)'),mb_strtoupper('vendido'))->pluck('id')->toArray();
        $impresso = Valegassituacao::where(DB::raw('upper(descricao)'),mb_strtoupper('impresso'))->pluck('id')->toArray();
        $situacao = array_merge($vendido,$impresso);
        $sit = implode(',',$situacao);
        $estoque = DB::table('produtos')
                        ->rightJoin('valegasvendas as venda','venda.produto_id','produtos.id')
                        ->join('estoquesetors as estoque','estoque.produto_id','produtos.id')
                        ->where([
                            ['produtos.empresa_id',$this->empresa_id],
                            ['produtos.ativo',1]
                        ])
                        ->whereRaw("(SELECT count(*) FROM valegas WHERE produto_id = produtos.id and valegassituacao_id in ($sit)) > 0")
                        ->select('produtos.id','produtos.descricao as produto',DB::raw("(SELECT sum(quantidade)
                            FROM estoquesetors WHERE produto_id = produtos.id) as cheios"),
                            DB::raw("(SELECT count(*) FROM valegas WHERE produto_id = produtos.id and valegassituacao_id in ($sit)) as valegas"),
                            DB::raw("((SELECT sum(quantidade) FROM estoquesetors WHERE produto_id = produtos.id) - 
                            (SELECT count(*) FROM valegas WHERE produto_id = produtos.id and valegassituacao_id in ($sit))) as considerar"))
                        ->orderBy('produtos.descricao')->groupBy('produtos.id')->groupBy('produtos.descricao')->get();
        $filtro = "&nbsp;";
        $retorno["filtro"] = $filtro;
        $retorno["estoque"] = $estoque;
        return $retorno;
    }

    private function definition(){
        //id empresa logada
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //id grupo empresa logada
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //set empresa repository
        $this->repository->setEmpresa($this->empresa_id);
        //set grupo repository
        $this->repository->setGrupo($this->grupo_id);
    }
}