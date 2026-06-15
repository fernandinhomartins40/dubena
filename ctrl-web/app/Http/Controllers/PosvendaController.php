<?php

namespace App\Http\Controllers;

use DB;
use Redirect;
use Exception;
use App\Setor;
use App\Pedido;
use App\Cliente;
use App\Posvenda;
use App\Services\CarbonCustom as Carbon;
use App\Pedidoitem;
use App\Colaborador;
use App\Http\Requests;
use App\Clientetelefone;
use App\Posvendaresposta;
use App\Posvendapesquisa;
use App\Posvendapergunta;
use Illuminate\Http\Request;
use App\Posvendapesquisaresposta;
use App\Repository\SelectRepository;
use Illuminate\Support\Facades\Session;

class  PosvendaController extends Controller {

    private $empresa_id;
    private $grupo_id;
    private $setores;
    private $colaboradores;
    private $repository;

    function __construct(SelectRepository $repository){
        $this->repository = $repository;
    }

    function index(Posvendapesquisa $pes) {
        $this->authorize('view',$pes);
        $this->definition();
        $setores = $this->repository->getSetores()->prepend("Selecione","");
        $colaborador = $this->repository->getColaborador()->prepend("Selecione","");
        return view('posvenda.posvendapesquisa')->with(compact('setores','colaborador'));
    }

    function create(Posvendapesquisa $pes) {
        $this->authorize('create', $pes);
        $this->definition();
        $get = $_GET;
        $pedido_id = $get["pedido"];
        $dataatual = str_replace('_',' ',$get["dataatual"]);
        $datahoje = Carbon::parse($dataatual);
        $posvenda = Posvenda::with('posvendaperguntas')
                    ->where([['empresa_id',$this->empresa_id],['ativo',1]])
                    ->whereRaw("to_date('".$datahoje."', 'yyyy-mm-dd hh24:mi:ss') between datahorainicio and datahorafim")
                    ->get()->first();
        if(!empty($posvenda)){
            $pedidos = Cliente::with('telefones')->join('pedidos','pedidos.cliente_id','clientes.id')
                            ->join('pedidoitems as items','items.pedido_id','pedidos.id')
                            ->join('produtos','items.produto_id','produtos.id')
                            ->join('setors as setor','pedidos.entregasetor_id','setor.id')
                            ->join('colaboradors as col','pedidos.colaborador_id','col.id')
                            ->join('clientes','pedidos.cliente_id','clientes.id')
                            ->join('cidades','clientes.cidade_id','cidades.id')
                            ->join('bairros','clientes.bairro_id','bairros.id')
                            ->join('ruas','clientes.rua_id','ruas.id')
                            ->where('pedidos.id',$pedido_id)
                            ->select('clientes.nome','clientes.id','pedidos.id as pedido_id','cidades.descricao as cidade',
                                'bairros.descricao as bairro','ruas.descricao as rua','clientes.numero','setor.descricao as setor',
                                'produtos.descricao as produto','pedidos.valorvenda','col.nome as colaborador')
                            ->get();
            $pedido = $pedidos->first();
            $cliente = (object) array();
            $posvenda->perguntas = $posvenda->posvendaperguntas;
            foreach($posvenda->perguntas as $pergunta){
                $pergunta->respostas = $pergunta->posvendarespostas;
            }
            $cliente->cliente_id = $pedido->id;
            $cliente->pedido_id = $pedido->pedido_id;
            $cliente->cliente = $pedido->nome;
            $cliente->telefone = implode(', ',$pedido->telefones->pluck('telefone')->toArray());
            $cliente->endereço = "$pedido->cidade, $pedido->bairro - $pedido->rua, $pedido->numero.";
            $cliente->setor = $pedido->setor;
            $cliente->colaborador = $pedido->colaborador;
            $cliente->produto = implode(', ',$pedidos->pluck('produto')->toArray());
            $cliente->valorvenda = $pedido->valorvenda;
            return view('posvenda.posvendapesquisa_form')->with(compact('posvenda','cliente'));
        }else{
            return redirect()->back()->withMessageInfo('Nenhum formulario ativo de pós-venda encontrado!');
        }
    }

    function store(Request $request){
        $link = Session::get('link');
        $link = explode('?',$link[0]);
        $data = $request->all();
        $posvenda = $this->dadosExtras($data);
        $respostas = json_decode($data["respostas"]);
        DB::beginTransaction();
        try{
            $pesquisa = Posvendapesquisa::create($posvenda);
            foreach($respostas as $resposta){
                $objresposta = new Posvendapesquisaresposta();
                $objresposta->posvendapesquisa_id = $pesquisa->id;
                $objresposta->posvendaresposta_id = $resposta->respostaid;
                $objresposta->save();
            }
        }catch(\Exception $ex){
            DB::rollback();
            return Redirect::to('posvenda/create?pedido=' . $posvenda->pedido_id . '&dataatual=' . $posvenda["dataatual"])
                            ->withErrors($ex->getMessage())
                            ->withInput();
        }
        DB::commit();
        return Redirect::to('posvenda.filtro?' . $link[1])->withMessageSuccess('Pesquisa feita com sucesso!');
    }

    function show(){
        //
    }

    public function filtroPedididos(Posvendapesquisa $pes) {
        $this->authorize('view',$pes);
        Session::forget('link');
        $this->definition();
        $setores = $this->repository->getSetores()->prepend("Selecione","");
        $colaborador = $this->repository->getColaborador()->prepend("Selecione","");
        $link = array();
        $urlatual = $_SERVER['REQUEST_URI'];
        array_unshift($link, $urlatual);
        Session::put('link', $link);
        $filtro = $_GET;
        $datastart = str_replace('_',' ',$filtro["datainicio"]);
        $dataend = str_replace('_',' ',$filtro["datafim"]);
        $datainicio = Carbon::parse($datastart);
        $datafim = Carbon::parse($dataend);
        $setor_id = $filtro["setor"] != "0" ? $filtro["setor"] : null;
        $colaborador_id = $filtro["colaborador"] !== "0" ? $filtro["colaborador"] : null;

        $situacao = DB::table('pedidosituacaos as sit')->whereRaw("grupo_id = $this->grupo_id and entregafinalizada = 1 or fechadoconcluido = 1 or fechadocancelado = 1 or entregacancelada = 1")->pluck('id')->toArray();
        $pedidos = DB::table('clientes')->join('pedidos','pedidos.cliente_id','clientes.id')
                        ->join('pedidosituacaos as situacao','pedidos.pedidosituacao_id','situacao.id')
                        ->join('condicaopagamentos as condicao','pedidos.condicaopagamento_id','condicao.id')
                        ->join('setors as setor','pedidos.entregasetor_id','setor.id')
                        ->join('cidades','clientes.cidade_id','cidades.id')
                        ->join('bairros','clientes.bairro_id','bairros.id')
                        ->join('ruas','clientes.rua_id','ruas.id')
                        ->leftJoin('posvendapesquisas as pesquisa','pesquisa.pedido_id','pedidos.id')
                        ->where([
                            ['clientes.empresa_id',$this->empresa_id],
                            ['clientes.ativo',1]
                        ])
                        ->whereIn('pedidos.pedidosituacao_id',$situacao)
                        ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim]);

        if(!is_null($colaborador_id))
            $pedidos = $pedidos->where('pedidos.colaborador_id',$colaborador_id);
        if(!is_null($setor_id))
            $pedidos = $pedidos->where('pedidos.entregasetor_id',$setor_id);
        
        $pedidos = $pedidos->select('clientes.id','clientes.nome','clientes.numero','cidades.descricao as cidade',
                                'bairros.descricao as bairro','ruas.descricao as rua','pedidos.id as pedido_id',
                                'pedidos.valorvenda','condicao.descricao as condicao','situacao.descricao as situacao',
                                'setor.descricao as setor','pesquisa.posvenda_id','pedidos.datahoraprevisaoentrega',
                                DB::raw("(select string_agg(tel.telefone, ', ' order by tel.telefone) from
                                (select telefone from clientetelefones where cliente_id = clientes.id order by telefone limit 2) tel) as telefone"))
                            ->orderBy('clientes.nome')->get();

        $clientes = collect([]);
        foreach($pedidos as $pedido){
            $objnormal = (object) array();
            $objnormal->pedido_id = $pedido->pedido_id;
            $objnormal->data = requestDataOracle($pedido->datahoraprevisaoentrega);
            $objnormal->cliente = $pedido->nome;
            $objnormal->telefone = $pedido->telefone;
            $objnormal->status = $pedido->situacao;
            $objnormal->valor = requestnumeroDecimalOracle($pedido->valorvenda);
            $objnormal->condicao = $pedido->condicao;
            $objnormal->setor = $pedido->setor;
            $objnormal->endereco = "$pedido->cidade - $pedido->bairro - $pedido->rua, $pedido->numero";
            $objnormal->posvenda = $pedido->posvenda_id;
            $clientes->push($objnormal);
        }
        return view('posvenda.posvendapesquisa')->with(compact('setores','colaborador','clientes'));
    }

    private function dadosExtras($data){
        $pedido_id = $data["idpedido"];
        $pedido = Pedido::find($pedido_id);
        $datanow = Carbon::now()->toDateTimeString();
        $posvenda["cliente_id"] = $pedido->cliente_id;
        $posvenda["setor_id"] = $pedido->entregasetor_id;
        $posvenda["pedido_id"] = $pedido->id;
        $posvenda["posvenda_id"] = $data["idposvenda"];
        $posvenda["datahora"] = $datanow;
        $posvenda["observacao"] = $data["observacoes"];
        return $posvenda;
    }

    private function definition(){
        //busca id da empresa logada
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //busca id do grupo
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //set empresa repositorio
        $this->repository->setEmpresa($this->empresa_id);
        //set grupo repositorio
        $this->repository->setGrupo($this->grupo_id);
    }
}