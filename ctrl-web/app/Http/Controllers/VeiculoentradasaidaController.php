<?php

namespace App\Http\Controllers;

use DB;
use Redirect;
use Exception;
use App\Setor;
use App\Pedido;
use App\Veiculo;
use App\Services\CarbonCustom as Carbon;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Veiculoentradasaida;
use App\Repository\SelectRepository;
use Illuminate\Support\Facades\Session;
use App\Veiculoentradasaidapedidos as Veiculopedidos;

class  VeiculoentradasaidaController extends Controller {

    protected $empresa_id;
    protected $grupo_id;
    protected $veiculos;
    protected $setores;
    protected $empresa_config;
    protected $entradasaida;
    protected $repository;


    function __construct(SelectRepository $repository) {
        $this->repository = $repository;
    }

    public function index(Veiculoentradasaida $ent) {
        $this->authorize('view',$ent);
        $this->definition();
        $veiculoentradasaida = Veiculoentradasaida::where('empresa_id', $this->empresa_id)->get();
        return view('veiculoentradasaida.veiculoentradasaida')->with(compact('veiculoentradasaida'));
    }

    public function create(Veiculoentradasaida $ent) {
        $this->authorize('create',$ent);
        $this->definition();
        $veiculos = corrigirVeiculo($this->veiculos);
        $setores = $this->repository->getSetores()->prepend('Selecione','');
        
        return view('veiculoentradasaida.veiculoentradasaida_form')->with(compact('veiculoentradasaidas','veiculos','setores','entradasaida'));
    }

    public function store(Request $request, Veiculoentradasaida $ent) {
        $this->authorize('create',$ent);
        $this->definition();
        $data  = $request->all();
        $dadosextras = $this->dadosExtras($data);
        $data = array_merge($data,$dadosextras);
        DB::begintransaction();
        try{
            $entradasaida = Veiculoentradasaida::create($data);
            $pedidoentradasaida = $this->salvarPedidos($data["tblvincpedidos_hd"],$entradasaida);
            if(isset($data["telacontrolakm"])){
                $updateothers = $this->updateOthers($data["km"],$entradasaida);
            }else{
                $updateothers = true;
            }
        }catch (\Exception $ex){
            DB::rollback();
            return Redirect::to('veiculoentradasaida/create')
                            ->withErrors($ex->getMessage())
                            ->withInput();
        }
        if($pedidoentradasaida && $updateothers){
            DB::commit();
            return Redirect::to('veiculoentradasaida')->withMessageSuccess('Movimentação criada com sucesso!');
        }else{
            $errors = is_bool($pedidoentradasaida) ? $updateothers : $pedidoentradasaida;
            return Redirect::to('veiculoentradasaida.veiculoentradasaida_form')
                            ->withErrors($errors->getMessage())
                            ->withInput();
        }
    }

    public function show($id, Veiculoentradasaida $ent) {
        $this->authorize('view',$ent);
        $veiculoentradasaidas = Veiculoentradasaida::find($id);
        $this->authorize('igualdade',$veiculoentradasaidas);
        $this->definition();
        $pedidos = $this->movimentacaoPedidos($veiculoentradasaidas);
        $veiculos = corrigirVeiculo($this->veiculos);
        $setores = $this->repository->getSetores()->prepend('Selecione','');
        $veiculoentradasaidas->ultimadatahora = requestDataOracle($veiculoentradasaidas->ultimadatahora,true,true);
        $veiculoentradasaidas->datahora = requestDataOracle($veiculoentradasaidas->datahora,true,true);
        $veiculoentradasaidas->entradasaida = $veiculoentradasaidas->entrada == 1 ? 1 : 0;
        $show = 1;
        
        return view('veiculoentradasaida.veiculoentradasaida_form')->with(compact('veiculoentradasaidas','pedidos','veiculos','setores','show'));
    }

    public function destroy($id, Veiculoentradasaida $ent) {
        $this->authorize('view',$ent);
        $movimentacao = Veiculoentradasaida::find($id);
        $this->authorize('igualdade', $movimentacao);
        $this->definition();
        DB::beginTransaction();
        try {
            if($movimentacao->telacontrolakm == "1"){
                $veiculo_id = $movimentacao->veiculo_id;
                $veiculo = Veiculo::find($veiculo_id);
                $kmatualveiculo = $veiculo->kmatual;
                $km = $movimentacao->kmrodado;
                $antigokm = ($kmatualveiculo - $km);
                DB::table('veiculos')->where('id',$veiculo_id)
                    ->update(['kmatual'=>$antigokm]);
            }
            $movimentacao->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        return "OK|";
    }

    private function dadosExtras($data){
        $data["empresa_id"] = $this->empresa_id;
        $data["grupo_id"] = $this->grupo_id;
        $data["datahora"] = insertDataOracle($data["datahora"]);
        $data["ultimadatahora"] = insertDataOracle($data["ultimadatahora"]);
        if($data["entradasaida"] == "1"){
            $data["entrada"] = 1;
        }else{
            $data["saida"] = 1;
        }
        if($this->empresa_config->telacontrolakm == "1"){
            $data["telacontrolakm"] = true;
        }
        return $data;
    }

    private function salvarPedidos($pedidos,$entradasaida){
        $pedidos = json_decode($pedidos);
        DB::beginTransaction();
        try{
            for($i=0;$i<count($pedidos);$i++){
                $veiculopedidos = new Veiculopedidos();
                $veiculopedidos->entradasaida_id = $entradasaida->id;
                $veiculopedidos->pedido_id = $pedidos[$i][0];
                $veiculopedidos->save();
            }
        }catch(Exception $ex){
            DB::rollback();
            return Redirect::to('veiculoentradasaida/create')
                            ->withErrors($ex->getMessage())
                            ->withInput();
        }
        DB::commit();
        return true;
    }

    private function updateOthers($kmatual,$entrada){
        $veiculo = Veiculo::find($entrada->veiculo_id);
        try{
            $veiculo->update(["kmatual"=>$kmatual]);
        }catch (\Exception $ex){
            return $ex->getMessage();
        }
        return true;
    }

    private function movimentacaoPedidos($movimentacao){
        $pedidos = Veiculopedidos::with('pedidos')->where("entradasaida_id",$movimentacao->id)->get();
        foreach($pedidos as $pedido){
            $pedido->cliente = $pedido->pedidos->cliente->nome;
            $pedido->rua = $pedido->pedidos->entregacidade->descricao . " - " .
                           $pedido->pedidos->entregabairro->descricao . " - " . 
                           $pedido->pedidos->entregarua->descricao . ", " . 
                           $pedido->pedidos->entreganumero;
            $pedido->valor = $pedido->pedidos->valorvenda;
            $pedido->status = $pedido->pedidos->pedidosituacao->descricao;
        }
        return $pedidos;
    }

    //Ajax Busca Pedidos por Periodo e Setor
	public function ajaxBuscaPedidoSetor(){
        $filtro = $_GET;
		$datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
		$datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $setor_id = $filtro["setor"] == "0" ? null : $filtro["setor"];

        $pedidos = DB::table('pedidos')
                ->leftJoin('veiculoentradasaidapedidos as veiculosped','veiculosped.pedido_id','pedidos.id')
                ->join('pedidosituacaos as situacao','pedidos.pedidosituacao_id','situacao.id')
                ->join('clientes','pedidos.cliente_id','clientes.id')
                ->join('cidades','pedidos.entregacidade_id','cidades.id')
                ->join('bairros','pedidos.entregabairro_id','bairros.id')
                ->join('ruas','pedidos.entregarua_id','ruas.id')
                ->whereNull('veiculosped.pedido_id')
                ->where('pedidos.empresa_id',Session::get('empresa_padrao')->id)
                ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim]);
		if(!is_null($setor_id))
            $pedidos = $pedidos->where('pedidos.entregasetor_id',$setor_id);
        $pedidos = $pedidos->select('pedidos.id','situacao.descricao as situacao','clientes.nome',
                                'cidades.descricao as cidade','bairros.descricao as bairro','ruas.descricao as rua',
                                'pedidos.entreganumero as numero','pedidos.valorvenda')
                            ->get();

		$pedidosentrega = collect([]);
		foreach($pedidos as $pedido){
            $objnormal = (object) array();
            $objnormal->id = $pedido->id;
            $objnormal->status = $pedido->situacao;
            $objnormal->cliente = $pedido->nome;
            $objnormal->endereco = "$pedido->bairro - $pedido->rua, $pedido->numero.";
            $objnormal->valorvenda = requestNumeroDecimalOracle($pedido->valorvenda);
            $pedidosentrega->push($objnormal);
		}
		return $pedidosentrega;
	}

    //Entrada e saida de Veiculos Ajax
    public function ajaxBuscarDadosVeiculo($id){
        $veiculo = Veiculo::where('veiculos.id',$id)
                ->leftJoin('veiculoentradasaidas as entrada','entrada.veiculo_id','veiculos.id')
                ->select('entrada.id','entrada.datahora as ultimadatahora','entrada.entrada','entrada.saida',
                DB::raw("(case when entrada.km is null then veiculos.kmatual else entrada.km end) as kmatual"))
                ->orderBy('entrada.id','DESC')
                ->orderBy('ultimadatahora','DESC')
                ->get()->first();
        return $veiculo;
    }

    private function definition(){
        //busca id da empresa padrao
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //busca id do grupo da empresa
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //busca dados da empresa config
        $this->empresa_config = Session::get('empresa_config');
        //busca veiculos da empresa
        $this->veiculos = Veiculo::where(['empresa_id'=>$this->empresa_id,'ativo'=>1])->get();
        //set Empresa
        $this->repository->setEmpresa($this->empresa_id);
        //set Grupo
        $this->repository->setGrupo($this->grupo_id);
    }

}
