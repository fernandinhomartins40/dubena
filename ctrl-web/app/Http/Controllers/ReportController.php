<?php

namespace App\Http\Controllers;

use DB;
use Excel;
use App\Rua;
use Session;
use Redirect;
use App\Setor;
use App\Bairro;
use App\Pedido;
use App\Cliente;
use App\Empresa;
use App\Produto;
use App\Recesso;
use App\Segmento;
use App\Services\CarbonCustom as Carbon;
use App\Tipopessoa;
use App\Pedidoitem;
use App\Planoconta;
use Barryvdh\DomPDF;
use App\Centrocusto;
use App\Http\Requests;
use App\Pedidosituacao;
use App\Clientecontato;
use App\Clientecontatotipo;
use Illuminate\Http\Request;
use App\Clientecontatosituacao;
use PHPExcel_Worksheet_Drawing;
use App\Repository\SelectRepository;
use PHPExcel_Worksheet_MemoryDrawing;
use Illuminate\Support\Facades\Response;

class ReportController extends Controller
{

    protected $empresa_id;
    protected $grupo_id;
    private $repository;

    public function __construct(SelectRepository $repository)
    {
        $this->middleware('auth');
        $this->repository = $repository;
    }

    private function definition()
    {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $this->repository->setGrupo($this->grupo_id);
        $this->repository->setEmpresa($this->empresa_id);
    }

    public function filtroFollowUp()
    {
        $titulo = 'RELATÓRIO DE FOLLOW-UP';
        $tipos = Clientecontatotipo::where('ativo', 1)->where('grupo_id',  Session::get('empresa_padrao')->grupo_id)->pluck('descricao', 'id');
        $situacaos = Clientecontatosituacao::where('ativo', 1)->where('grupo_id',  Session::get('empresa_padrao')->grupo_id)->pluck('descricao', 'id');
      //dd($empresas);

        $centrocustos = Centrocusto::menuspermissoesAll();
        $centrocustoslnk =[];
        $centrocusto_descricao = '';
        $centrocusto_id = '';
        $planocontas = Planoconta::menuspermissoesAll();
        $planocontaslnk =[];
        $planoconta_descricao = '';
        $planoconta_id = '';


        return view('filtros.filtro_followup', compact('titulo', 'tipos', 'situacaos', 'centrocustos', 'centrocustoslnk', 'centrocusto_descricao', 'centrocusto_id', 'planocontas', 'planocontaslnk', 'planoconta_descricao', 'planoconta_id'));
    }
    public function reportFollowUp($par)
    {
        $pars = explode('|', $par);
        $dataini = Carbon::createFromFormat('d-m-Y H:i:s', $pars[0].' 00:00:00');
        $datafin = Carbon::createFromFormat('d-m-Y H:i:s', $pars[1].' 23:59:59');
        $tipos = explode(',', $pars[2]);
        $tips = implode(', ', Clientecontatotipo::whereIn('id', $tipos)->pluck('descricao')->toArray());
        $situacaos = explode(',', $pars[3]);
        $sits = implode(', ', Clientecontatosituacao::whereIn('id', $situacaos)->pluck('descricao')->toArray());

        $filtro = 'Empresa: '.Session::get('empresa_padrao')->nome_informal.'    Período: '.$pars[0].' a '.$pars[1].'    Tipos: '.$tips.'    Situações: '.$sits;
        $contatos = Clientecontato::where('empresa_id', Session::get('empresa_padrao')->id)
        ->whereIn('tipo_id', $tipos)
        ->whereIn('situacao_id', $situacaos)
        ->whereBetween('created_at', array($dataini, $datafin))
        ->orderBy('empresa_id')
        ->get();

        $titulo = 'RELATÓRIO DE FOLLOW-UP';
      //dd($Clientes);
      //return view('reports.interessados', compact('Clientes'));
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadView('reports.report_followup', compact('contatos', 'titulo', 'filtro'));
        return $pdf->stream();
    }
    public function reportFollowUpXls($par)
    {
        $pars = explode('|', $par);
        $dataini = Carbon::createFromFormat('d-m-Y H:i:s', $pars[0].' 00:00:00');
        $datafin = Carbon::createFromFormat('d-m-Y H:i:s', $pars[1].' 23:59:59');
        $tipos = explode(',', $pars[2]);
        $tips = implode(', ', Clientecontatotipo::whereIn('id', $tipos)->pluck('descricao')->toArray());
        $situacaos = explode(',', $pars[3]);
        $sits = implode(', ', Clientecontatosituacao::whereIn('id', $situacaos)->pluck('descricao')->toArray());

        $filtro = 'Empresa: '.Session::get('empresa_padrao')->nome_informal.'    Período: '.$pars[0].' a '.$pars[1].'    Tipos: '.$tips.'    Situações: '.$sits;
        $contatos = Clientecontato::where('empresa_id', Session::get('empresa_padrao')->id)
        ->whereIn('tipo_id', $tipos)
        ->whereIn('situacao_id', $situacaos)
        ->whereBetween('created_at', array($dataini, $datafin))
        ->orderBy('empresa_id')
        ->get();

        $xls = Array();
        array_push($xls, ['', 'RELATÓRIO DE CONTATOS']);
        array_push($xls, ['', $filtro]);
        array_push($xls, ['', 'Emitido em '.Carbon::now()->format('d/m/Y H:i:s')]);
        array_push($xls, ['Nome', 'Telefone', 'e-mail', 'Descrição']);
        foreach ($contatos as $contato){
            array_push($xls, ['', '']);
            array_push($xls, [$contato->cliente->nome, implode($contato->cliente->telefones->pluck('telefone')->toArray(),', '), $contato->cliente->email, $contato->descricao, $contato->contatosituacao->descricao]);
        }
        Excel::create('FollowUp', function($excel) use ($xls) {
            $excel->sheet('FollowUp', function($sheet) use($xls) {
                $sheet->setAutoSize(false);
                $sheet->setPageMargin(0.5);
                $sheet->fromArray($xls, null, 'A1', true, false);

                $sheet->cells('B1:B3', function($cells) {
                    $cells->setFontWeight('bold');
                    $cells->setFontSize(14);
                });

                $sheet->setWidth('A', 40);
                $sheet->setWidth('B', 50);
                $sheet->setWidth('C', 50);

                for($i=0;$i<count($xls);$i++){
                    if($xls[$i][0]=="Nome"){
                        $sheet->cells('A'.($i).':D'.($i), function($cells) {
                            $cells->setFontWeight('bold');
                            $cells->setFontSize(13);
                        });
                        $sheet->cells('A'.($i+1).':D'.($i+1), function($cells) {
                            $cells->setFontWeight('bold');
                            $cells->setFontSize(13);
                        });
                    }
                }
                if(Session::get('empresa_padrao')->logo!=null){
                    $image = imagecreatefromstring(Session::get('empresa_padrao')->logo);
                    imagesavealpha($image, true);
                    $drawing = new PHPExcel_Worksheet_MemoryDrawing();
                    $drawing->setName('teste');
                    $drawing->setImageResource($image);
                    $drawing->setRenderingFunction(PHPExcel_Worksheet_MemoryDrawing::RENDERING_PNG);
                    $drawing->setMimeType(PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_DEFAULT);
                    $drawing->setWidthAndHeight(148,70);
                    $drawing->setResizeProportional(true);
                    $drawing->setCoordinates('A1');
                    $drawing->setOffsetX(5);
                    $drawing->setOffsetY(5);
                    $drawing->setWorksheet($sheet);
                }
            });
        })->download('xlsx');

    }
    public function filtroFollowUpAll()
    {
        $titulo = 'RELATÓRIO DE FOLLOW-UP';
        $empresas = \Auth::user()->empresas->pluck('nome_informal', 'id');
        return view('filtros.filtro_followupall', compact('empresas', 'titulo'));
    }
    public function reportFollowUpAll($par)
    {
        $pars = explode('|', $par);
        $dataini = Carbon::createFromFormat('d-m-Y H:i:s', $pars[0].' 00:00:00');
        $datafin = Carbon::createFromFormat('d-m-Y H:i:s', $pars[1].' 23:59:59');
        $empresas = explode(',', $pars[2]);
        $emps = implode(', ', Empresa::whereIn('id', $empresas)->pluck('nome_informal')->toArray());

        $filtro = 'Período: '.$pars[0].' a '.$pars[1].'   Empresas: '.$emps;
        $contatos = Clientecontato::whereIn('empresa_id', $empresas)
        ->whereBetween('created_at', array($dataini, $datafin))
        ->orderBy('empresa_id')
        ->get();

        $titulo = 'RELATÓRIO DE FOLLOW-UP';
      //dd($Clientes);
      //return view('reports.interessados', compact('Clientes'));
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadView('reports.report_followupall', compact('contatos', 'titulo', 'filtro'));
        return $pdf->stream();
    }

    public function vendaSetorIndex(){
        $this->definition();
        $setor = $this->repository->getSetores()->prepend("Selecione","");
        $segmentos = $this->repository->getSegmentos()->prepend('Selecione','');
        $produtos = $this->repository->getProdutos();

        return view('reports.vendas.setor.vendas_setor')->with(compact('setor', 'segmentos', 'produtos'));
    }

    public function filtroVendaSetor(){
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"] == "1";
        $vendas = $this->buscaVenda($get);
        $titulo = "Relatório de Vendas por Setor";
        $clientes = $vendas["clientes"];
        $filtro = $vendas["filtro"];
        if($tipo){
            return view('reports.vendas.setor.vendas_setor_pdf')->with(compact('titulo','filtro','clientes'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.vendas.setor.vendas_setor_pdf', compact('titulo','filtro','clientes'));
            return $pdf->stream();
        }
    }

    private function buscaVenda($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $setor_id = $filtro["setor"] == "0" ? null : $filtro["setor"];
        $segmento = $filtro["segmento"] == "0" ? null : $filtro["segmento"];
        $produtos_id = $filtro["produto"] == 0 ? null : explode(',',$filtro["produto"]);

        $situacao = DB::table('pedidosituacaos')->where(['grupo_id'=>$this->grupo_id,'ativo'=>1])
                            ->whereRaw('fechadoconcluido = 1 or entregafinalizada = 1')
                            ->pluck('id')->toArray();
        $pedidos = DB::table('pedidos')->join('pedidoitems as items','items.pedido_id','pedidos.id')
                            ->join('clientes','pedidos.cliente_id','clientes.id')
                            ->join('setors as setor','pedidos.entregasetor_id','setor.id')
                            ->join('produtos','items.produto_id','produtos.id')
                            ->join('bairros','clientes.bairro_id','bairros.id')
                            ->join('ruas','clientes.rua_id','ruas.id')
                            ->join('segmentos','clientes.segmento_id','segmentos.id')
                            ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
                            ->whereIn('pedidos.pedidosituacao_id',$situacao)
                            ->where([
                                ['pedidos.empresa_id',$this->empresa_id],
                                ['items.precovendaunitario','>','0']
                            ]);
        if(!is_null($setor_id))
            $pedidos = $pedidos->where('pedidos.entregasetor_id',$setor_id);

        if(!is_null($segmento))
            $pedidos = $pedidos->where('clientes.segmento_id',$segmento);

        if($produtos_id != null)
            $pedidos = $pedidos->whereIn('items.produto_id',$produtos_id);

        $pedidos = $pedidos->select('pedidos.entregasetor_id as setor_id','setor.descricao as setor','clientes.nome','produtos.descricao as produto',
                                    'pedidos.id','items.quantidade','items.precovendaunitario','pedidos.valorvenda','pedidos.valordesconto', 'segmentos.descricao as segmento',
                                    DB::raw("(bairros.descricao || ' - ' || ruas.descricao || ', ' || clientes.numero) as endereco"),
                                    'pedidos.datahoraprevisaoentrega')
                            ->orderBy('setor')->orderBy('clientes.nome')->get();
        $clientes = collect([]);
        $anterior = null;
        $count = 0;
        $countset = 0;
        foreach ($pedidos as $pedido) {
            if(!is_null($anterior) && $anterior != $pedido->setor_id){
                $wped = $pedidos->where('setor_id',$anterior);
                $objtotal = (object) array();
                $objtotal->quantidadetotal = $wped->sum('quantidade');
                $objtotal->totalsetor = $wped->unique('id')->sum('valorvenda');
                $clientes->push($objtotal);
            }
            if(is_null($anterior) || $anterior != $pedido->setor_id){
                $objsetor = (object) array();
                $objsetor->setor_id = $pedido->setor_id;
                $objsetor->setor = $pedido->setor;
                $clientes->push($objsetor);
                $countset++;
            }
            $bruto = $pedidos->unique('id')->where('id',$pedido->id)->sum('valorvenda') + $pedido->valordesconto;
            $objnormal = (object) array();
            $objnormal->data = requestDataOracle($pedido->datahoraprevisaoentrega,false);
            $objnormal->cliente = $pedido->nome;
            $objnormal->endereco = $pedido->endereco;
            $objnormal->produto = $pedido->produto;
            $objnormal->segmento = $pedido->segmento;
            $objnormal->quantidade = $pedido->quantidade;
            $valor = $pedido->precovendaunitario * $objnormal->quantidade;
            $liquido = ($valor - (($valor / $bruto) * $pedido->valordesconto));
            $objnormal->valor = $liquido;
            $clientes->push($objnormal);
            $count++;
            $anterior = $pedido->setor_id;
            if($count == count($pedidos)){
                if($countset > 1){
                    $wped = $pedidos->where('setor_id',$anterior);
                    $objtotal = (object) array();
                    $objtotal->quantidadetotal = $wped->sum('quantidade');
                    $objtotal->totalsetor = $wped->unique('id')->sum('valorvenda');
                    $clientes->push($objtotal);
                }
                $objtotalgeral = (object) array();
                $objtotalgeral->quantidade = $clientes->sum('quantidade');
                $objtotalgeral->totalgeral = $clientes->sum('valor');
                $clientes->push($objtotalgeral);
            }
        }
        $filtro = "Filtro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $setor_id == null ? $filtro . ", Setor: todos." : $filtro . ", Setor: " . Setor::where('id',$setor_id)->pluck('descricao')->first();
        $filtro = $segmento == null ? $filtro . ", Segmento: todos." : $filtro . ", Segmento: " . Segmento::where('id',$segmento)->pluck('descricao')->first();
        $filtro = $produtos_id == null ? $filtro . ", Produto: todos." : $filtro . ", Produto: " . implode(', ',Produto::whereIn('id',$produtos_id)->pluck('descricao')->toArray());
        $retorno["filtro"] = $filtro;
        $retorno["clientes"] = $clientes;
        return $retorno;
    }

    //Venda Diaria
    public function vendaDiariaIndex() {
        $this->definition();
        $setor = $this->repository->getSetores()->prepend('Selecione','');
        $produtos = $this->repository->getProdutos();
        return view('reports.vendas.setor.vendas_diarias')->with(compact('setor','produtos'));
    }

    public function filtroDiaria() {
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"] == "1";
        $vendas = $this->buscaDiaria($get);
        $titulo = "Relatório de Vendas Diárias";
        $filtro = $vendas["filtro"];
        $pedidos = $vendas["pedidos"];
        if($tipo){
            return view('reports.vendas.setor.vendas_diarias_pdf')->with(compact('titulo','filtro','pedidos'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.vendas.setor.vendas_diarias_pdf', compact('titulo','filtro','pedidos'));
            return $pdf->stream();
        }
    }

    private function buscaDiaria($filtro){
        $datainicio = Carbon::parse($filtro["data"])->startOfDay();
        $datafim = Carbon::parse($filtro["data"])->endOfDay();
        $setor_id = $filtro["setor"] == "0" ? null : $filtro["setor"];
        $produtos_id = $filtro["produto"] == "0" ? null : explode(',',$filtro["produto"]);

        $situacao = DB::table('pedidosituacaos')
            ->whereRaw('grupo_id = ' . $this->grupo_id . ' and (fechadoconcluido = 1 or entregafinalizada = 1)')
            ->pluck('id')->toArray();
        $clientes = DB::table('pedidos')
                ->join('clientes','pedidos.cliente_id','clientes.id')
                ->join('pedidoitems as items','items.pedido_id','pedidos.id')
                ->join('setors as setor','pedidos.entregasetor_id','setor.id')
                ->join('produtos','items.produto_id','produtos.id')
                ->join('bairros','clientes.bairro_id','bairros.id')
                ->join('ruas','clientes.rua_id','ruas.id')
                ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
                ->whereIn('pedidos.pedidosituacao_id', $situacao)
                ->where([
                    ['clientes.empresa_id',$this->empresa_id],
                    ['items.precovendaunitario','>','0']
                ]);
        if(!is_null($setor_id))
            $clientes = $clientes->where('pedidos.entregasetor_id',$setor_id);
        if(!is_null($produtos_id))
            $clientes = $clientes->whereIn('items.produto_id',$produtos_id);
        $clientes = $clientes->select('pedidos.id as pedido_id','clientes.nome','setor.descricao as setor','produtos.descricao as produto',
                    'items.quantidade','items.precovendatotal','items.precovendaunitario','pedidos.valorvenda','pedidos.valordesconto',
                    DB::raw("(bairros.descricao || ' - ' || ruas.descricao || ', ' || clientes.numero) as endereco"))
                ->orderBy('pedido_id')->orderBy('clientes.nome')->get();

        $pedidos = collect([]);
        $count = 0;
        foreach($clientes as $cliente) {
            $bruto = $clientes->where('pedido_id',$cliente->pedido_id)->unique('pedido_id')->sum('valorvenda') + $cliente->valordesconto;
            $objnormal = (object) array();
            $objnormal->pedido_id = $cliente->pedido_id;
            $objnormal->cliente = $cliente->nome;
            $objnormal->endereco = $cliente->endereco;
            $objnormal->setor = $cliente->setor;
            $objnormal->produto = $cliente->produto;
            $objnormal->quantidade = $cliente->quantidade;
            $valor = $cliente->precovendaunitario * $objnormal->quantidade;
            $liquido = ($valor - (($valor / $bruto) * $cliente->valordesconto));
            $objnormal->valor = $liquido;
            $pedidos->push($objnormal);
            $count++;
        }

        if($count >= 1 && $count == count($clientes)){
            $objtotal = (object) array();
            $objtotal->quantidadetotal = $pedidos->sum('quantidade');
            $objtotal->total = $pedidos->sum('valor');
            $pedidos->push($objtotal);
        }

        $filtro = "Filtro: Data " . requestDataOracle($datainicio,false);

        $filtro = $setor_id == null
            ? "$filtro, Setor: todos"
            : "$filtro, Setor: " . Setor::where('id',$setor_id)->pluck('descricao')->first();

        $filtro = $produtos_id == null
            ? "$filtro, Produto(s): todos."
            : "$filtro, Produto(s): " . implode(', ',Produto::whereIn('id',$produtos_id)->orderBy('descricao')->pluck('descricao')->toArray()).".";

        $retorno["filtro"] = $filtro;
        $retorno["pedidos"] = $pedidos;
        return $retorno;
    }

    public function vendaAppIndex(){
        $this->definition();
        $setor = $this->repository->getSetores()->prepend("Selecione","");
        return view('reports.vendas.app.vendas_app')->with(compact('setor'));
    }

    public function filtroVendaApp(){
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"] == "1";


        $vendas = $this->buscaVendaApp($get);
        $titulo = "Relatório de Vendas por Aplicativo";
        $clientes = $vendas["clientes"];
        $somarating = $vendas["somarating"];
        $qtrating = $vendas["qtrating"];
        $qtnaoentregue = $vendas["qtnaoentregue"];
        $filtro = $vendas["filtro"];
        if($tipo){
            return view('reports.vendas.app.vendas_app_pdf')->with(compact('titulo','filtro','clientes', 'somarating', 'qtrating', 'qtnaoentregue'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.vendas.app.vendas_app_pdf', compact('titulo','filtro','clientes', 'somarating', 'qtrating', 'qtnaoentregue'));
            return $pdf->stream();
        }
    }

    private function buscaVendaApp($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $setor_id = $filtro["setor"] == "0" ? null : $filtro["setor"];

        $pedidosapp = \DB::connection("sgcm_api")
            ->table('pedidos as p')
            ->leftJoin('pedidoavaliacoes as a', 'a.pedido_id', 'p.id')
            ->whereBetween('p.datahoraprevisao',[$datainicio,$datafim])
            ->orderBy('p.id')
            ->get();

        $clientes = collect([]);
        $somarating = 0;
        $qtrating = 0;
        $qtnaoentregue = 0;
        foreach ($pedidosapp as $pedido) {
            $pedidoweb = Pedido::find($pedido->erp_id);
            if(!$pedidoweb){
                continue;
            }
            if(!is_null($setor_id)){
                if($pedidoweb->entregasetor_id != $setor_id){
                    continue;
                }
            }
            if($pedido->rating != null){
                $somarating+=$pedido->rating;
                $qtrating++;
            }
            if($pedidoweb->pedidosituacao->fechadocancelado){
                $qtnaoentregue++;
            }
            $pedido->cliente = $pedidoweb->cliente->nome;
            $pedido->status = $pedidoweb->pedidosituacao->descricao;
            $pedido->data = requestDataOracle($pedido->datahoraprevisao,false);
            $clientes->push($pedido);
        }
        $clientes = $clientes->sort(function($a, $b) {
            if($a->status === $b->status) {
              if($a->erp_id === $b->erp_id) {
                return 0;
              }
              return $a->erp_id < $b->erp_id ? -1 : 1;
            }
            return $a->status < $b->status ? -1 : 1;
         });

        //$clientes = $clientes->sortBy('status')->sortBy('id');
        $filtro = "Filtro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $setor_id == null ? $filtro . ", Setor: todos." : $filtro . ", Setor: " . Setor::where('id',$setor_id)->pluck('descricao')->first();
        $retorno["filtro"] = $filtro;
        $retorno["clientes"] = $clientes;
        $retorno["somarating"] = $somarating;
        $retorno["qtrating"] = $qtrating;
        $retorno["qtnaoentregue"] = $qtnaoentregue;
        return $retorno;
    }

    public function vendaProdutoIndex(){
        $this->definition();
        //$setor = $this->repository->getSetores()->prepend("Selecione","");
        $segmentos = $this->repository->getSegmentos()->prepend('Selecione','');
        $produtos = $this->repository->getProdutos();

        return view('reports.vendas.produto.vendas_produto')->with(compact('segmentos', 'produtos'));
    }

    public function filtroVendaProduto(){
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"] == "1";
        $vendas = $this->buscaVendaProduto($get);
        $titulo = "Relatório de Vendas por Produto";
        $clientes = $vendas["clientes"];
        $filtro = $vendas["filtro"];
        if($tipo){
            return view('reports.vendas.produto.vendas_produto_pdf')->with(compact('titulo','filtro','clientes'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.vendas.produto.vendas_produto_pdf', compact('titulo','filtro','clientes'));
            return $pdf->stream();
        }
    }

    private function buscaVendaProduto($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $segmento = $filtro["segmento"] == "0" ? null : $filtro["segmento"];
        $produtos_id = $filtro["produto"] == 0 ? null : explode(',',$filtro["produto"]);

        $situacao = DB::table('pedidosituacaos')->where(['grupo_id'=>$this->grupo_id,'ativo'=>1])
                            ->whereRaw('fechadoconcluido = 1 or entregafinalizada = 1')
                            ->pluck('id')->toArray();
        $pedidos = DB::table('pedidos')->join('pedidoitems as items','items.pedido_id','pedidos.id')
                            ->join('clientes','pedidos.cliente_id','clientes.id')
                            ->join('setors as setor','pedidos.entregasetor_id','setor.id')
                            ->join('produtos','items.produto_id','produtos.id')
                            ->join('bairros','clientes.bairro_id','bairros.id')
                            ->join('ruas','clientes.rua_id','ruas.id')
                            ->join('segmentos','clientes.segmento_id','segmentos.id')
                            ->join('condicaopagamentos as cond', 'pedidos.condicaopagamento_id', 'cond.id')
                            ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
                            ->whereIn('pedidos.pedidosituacao_id',$situacao)
                            ->where([
                                ['pedidos.empresa_id',$this->empresa_id],
                                ['items.precovendaunitario','>','0']
                            ]);

        if(!is_null($segmento))
            $pedidos = $pedidos->where('clientes.segmento_id',$segmento);

        if($produtos_id != null)
            $pedidos = $pedidos->whereIn('items.produto_id',$produtos_id);

        $pedidos = $pedidos->select('pedidos.entregasetor_id as setor_id','setor.descricao as setor','clientes.nome','produtos.descricao as produto',
                                    'pedidos.id','items.quantidade','items.precovendaunitario','pedidos.valorvenda','pedidos.valordesconto', 'segmentos.descricao as segmento',
                                    DB::raw("(bairros.descricao || ' - ' || ruas.descricao || ', ' || clientes.numero) as endereco"),
                                    'pedidos.datahoraprevisaoentrega', 'cond.descricao as condicao', 'produtos.id as produto_id', 'clientes.segmento_id')
                            ->orderBy('produto')->orderBy('segmento')->get();

        $clientes = collect([]);
        $anterior = null;
        $count = 0;
        $countprod = 0;
        $seganterior = null;
        $countseg = 0;
        $quantProd = 0;
        $totalProd = 0;
        $quantSegm = 0;
        $totalSegm = 0;
        $quantGeral = 0;
        $totalGeral = 0;
        foreach ($pedidos as $pedido) {
            if (!is_null($seganterior) && ($seganterior != $pedido->segmento_id || $anterior != $pedido->produto_id)) {
                $objtotal = (object) array();
                $objtotal->quantidadetotal = $quantSegm;
                $objtotal->totalsegmento = $totalSegm;
                $clientes->push($objtotal);
                $quantSegm = 0;
                $totalSegm = 0;
            }

            if (!is_null($anterior) && $anterior != $pedido->produto_id) {
                $objtotal = (object) array();
                $objtotal->quantidadetotal = $quantProd;
                $objtotal->totalproduto = $totalProd;
                $clientes->push($objtotal);
                $quantProd = 0;
                $totalProd = 0;
            }

            if (is_null($anterior) || $anterior != $pedido->produto_id) {
                $objproduto = (object) array();
                $objproduto->produto_id = $pedido->produto_id;
                $objproduto->produto = $pedido->produto;
                $clientes->push($objproduto);
                $countprod++;
            }

            if (is_null($seganterior) || ($seganterior != $pedido->segmento_id || $anterior != $pedido->produto_id)) {
                $objsegmento = (object) array();
                $objsegmento->segmento_id = $pedido->segmento_id;
                $objsegmento->segmento = $pedido->segmento;
                $clientes->push($objsegmento);
                $countseg++;
            }

            $bruto = $pedido->valorvenda + $pedido->valordesconto;
            $objnormal = (object) array();
            $objnormal->data = requestDataOracle($pedido->datahoraprevisaoentrega,false);
            $objnormal->cliente = $pedido->nome;
            $objnormal->endereco = $pedido->endereco;
            $objnormal->pedido_id = $pedido->id;
            $objnormal->produto = $pedido->produto;
            $objnormal->segmento = $pedido->segmento;
            $objnormal->condicao = $pedido->condicao;
            $objnormal->quantidade = $pedido->quantidade;
            $valor = $pedido->precovendaunitario * $objnormal->quantidade;
            $liquido = ($valor - (($valor / $bruto) * $pedido->valordesconto));
            $objnormal->valor = $liquido;
            $clientes->push($objnormal);
            $count++;
            $anterior = $pedido->produto_id;
            $seganterior = $pedido->segmento_id;
            $quantProd += $pedido->quantidade;
            $totalProd += $pedido->valorvenda;
            $quantSegm += $pedido->quantidade;
            $totalSegm += $pedido->valorvenda;
            $quantGeral += $pedido->quantidade;
            $totalGeral += $liquido;
            if ($count == count($pedidos)) {
                if($countseg >= 1){
                    $objtotal = (object) array();
                    $objtotal->quantidadetotal = $quantSegm;
                    $objtotal->totalsegmento = $totalSegm;
                    $clientes->push($objtotal);
                }
                if($countprod >= 1){
                    $objtotal = (object) array();
                    $objtotal->quantidadetotal = $quantProd;
                    $objtotal->totalproduto = $totalProd;
                    $clientes->push($objtotal);
                }
                $objtotalgeral = (object) array();
                $objtotalgeral->quantidade = $quantGeral;
                $objtotalgeral->totalgeral = $totalGeral;
                $clientes->push($objtotalgeral);
            }
        }

        $filtro = "Filtro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $segmento == null ? $filtro . ", Segmento: todos." : $filtro . ", Segmento: " . Segmento::where('id',$segmento)->pluck('descricao')->first();
        $filtro = $produtos_id == null ? $filtro . ", Produto: todos." : $filtro . ", Produto: " . implode(', ',Produto::whereIn('id',$produtos_id)->pluck('descricao')->toArray());
        $retorno["filtro"] = $filtro;
        $retorno["clientes"] = $clientes;
        return $retorno;
    }

}
