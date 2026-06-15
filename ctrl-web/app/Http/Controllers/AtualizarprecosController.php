<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use Exception;
use App\Setor;
use App\Produto;
use App\Segmento;
use App\Tipopessoa;
use App\Clienteproduto;
use Illuminate\Http\Request;
use App\Repository\SelectRepository;
use App\Atualizacaoprecos as Atualizacao;

class AtualizarprecosController extends Controller
{
    protected $repositorio;
    protected $errors;

    public function __construct(SelectRepository $repositorio) {
        $this->repositorio = $repositorio;
        $this->errors = [];
    }

    protected function addErrors($error)
    {
        array_push($this->errors, $error);
    }

    protected function getErrors()
    {
        return implode(', ',$this->error);
    }

    public function index(Atualizacao $at)
    {
        $this->authorize('view',$at);
        $atualizacoes = Atualizacao::join('produtos','atualizacaoprecos.produto_id','produtos.id')
            ->where('atualizacaoprecos.empresa_id',Session::get('empresa_padrao')->id)
            ->select('atualizacaoprecos.id','atualizacaoprecos.descricao','produtos.descricao as produto',
                'atualizacaoprecos.variacao','atualizacaoprecos.tipo','atualizacaoprecos.valor','atualizacaoprecos.mudoubase')
            ->get();
        return view('produtos.atualizacao.index')->with(compact('atualizacoes'));
    }

    public function create(Atualizacao $at)
    {
        $this->authorize('create',$at);
        $this->definition();
        $empresa_id = Session::get('empresa_padrao')->id;
        $produtos = $this->repositorio->getProdutos()->prepend('Selecione','');
        $segmento = $this->repositorio->getSegmentos()->prepend('Selecione','');
        $tipo = $this->repositorio->getTipoPessoa()->prepend('Selecione','');
        $setor = $this->repositorio->getSetores()->prepend('Selecione','');
        return view('produtos.atualizacao.atualizacao_form')->with(compact('produtos','segmento','tipo','setor'));
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $extras = $this->dadosExtras($data);
        $data = array_merge($data, $extras);
        DB::beginTransaction();
        try {
            $atualizacao = Atualizacao::create($data);
            $atualizado  = $this->updateOthers($data);
        } catch( Exception $e ) {
            DB::rollback();
            $error = "Error: " . $e->getMessage() . " Line: " . $e->getLine();
            $this->addErrors($error);
            return redirect('atualizarprecos/create')->withErrors("Oops! algo deu errado!")->withInput();
        }
        DB::commit();
        return redirect('atualizarprecos')->withMessageSuccess("Preços Atualizados com Sucesso");
    }

    public function show($id, Atualizacao $at)
    {
        $this->authorize('view',$at);
        $this->definition();
        $atualizacao = Atualizacao::find($id);
        $this->authorize('igualdade',$atualizacao);
        $empresa_id = Session::get('empresa_padrao')->id;
        $produtos = $this->repositorio->getProdutos()->prepend('Selecione','');
        $segmento = $this->repositorio->getSegmentos()->prepend('Selecione','');
        $tipo = $this->repositorio->getTipoPessoa()->prepend('Selecione','');
        $setor = $this->repositorio->getSetores()->prepend('Selecione','');
        $show = true;

        if ($atualizacao->tipo == '1' || $atualizacao->tipo == '2') $atualizacao->valor = requestNumeroDecimalOracle($atualizacao->valor);
        else $atualizacao->valor = requestPercentualOracle($atualizacao->valor);

        return view('produtos.atualizacao.atualizacao_form')->with(compact('atualizacao','produtos','segmento','tipo','setor','show'));
    }

    private function dadosExtras($data)
    {
        if ($data["tipo"] == "1" || $data["tipo"] == "2") $valor = insertNumeroDecimalOracle($data["valor0"]);
        else $valor = insertPercentualOracle($data["valor1"]);

        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $data["user_id"] = \Auth::user()->id;
        $data["variacao"] = $data["variacao"];
        $data["valor"] = $valor;
        $data["mudoubase"] = isset($data["mudoubase"]) && $data["mudoubase"];
        $data["descricao"] = $this->organizarDescricao($data);

        return $data;
    }

    private function updateOthers($data)
    {
        DB::beginTransaction();
        try {
            if($data["mudoubase"]) {
                $produto = Produto::find($data["produto_id"]);
                $precovenda = $produto->precovenda;
    
                if($data["tipo"] == '1') $valor = $data["valor"];
    
                else if ($data["tipo"] == '2') $valor = $precovenda + $data["valor"];
    
                else if($data["tipo"] == '3') $valor = ($precovenda * ($data["valor"] / 100)) + $precovenda;
    
                $produto->update(["precovenda" => $valor]);
            }
            $updated = $this->updateCliente($data);
        } catch (Exception $e) {
            DB::rollback();
            $error = "Error: " . $e->getMessage() . " Line: " . $e->getLine();
            $this->addErrors($error);
            return false;
        }
        DB::commit();
        return $updated;
    }

    private function updateCliente($data)
    {
        // Filtros opcionais com bindings (eram interpolados → SQLi).
        $bindings = [];
        $filtros = "";
        if ($data["segmento_id"] !== "") {
            $filtros .= " and clientes.segmento_id = ?";
            $bindings[] = (int) $data["segmento_id"];
        }
        if ($data["tipopessoa_id"] !== "") {
            $filtros .= " and clientes.tipopessoa_id = ?";
            $bindings[] = (int) $data["tipopessoa_id"];
        }
        if ($data["setor_id"] !== "") {
            $filtros .= " and clientes.setor_id = ?";
            $bindings[] = (int) $data["setor_id"];
        }
        $valor = (float) $data["valor"];
        $op = $data["variacao"] == "A" ? "+" : "-";
        $empresa_id = Session::get('empresa_padrao')->id;
        $produto_id = (int) $data["produto_id"];

        DB::beginTransaction();
        try{
            // $calc é uma expressão SQL; $op é controlado (+/-) e $valor é numérico (cast acima).
            switch ($data["tipo"]) {
                case '1':
                    $calc = "$valor";
                    break;
                case '2':
                    $calc = "pro.preco $op $valor";
                    break;
                default:
                    $calc = "pro.preco $op (pro.preco * ( $valor / 100 ))";
                    break;
            }
            // Postgres: UPDATE ... FROM (Oracle usava UPDATE(subquery)SET, inválido em PG).
            $query = "update clienteprodutos as pro " .
                "set preco = (case when ($calc) <= 0 then 0 else ($calc) end) " .
                "from clientes " .
                "where pro.cliente_id = clientes.id " .
                "and clientes.empresa_id = ? " .
                "and pro.produto_id = ? " .
                $filtros;
            $params = array_merge([$empresa_id, $produto_id], $bindings);
            $update = DB::update($query, $params);
        } catch (\Exception $e) {
            DB::rollback();
            $error = "Error: " . $e->getMessage() . " Line: " . $e->getLine();
            $this->addErrors($error);
            return false;
        }
        DB::commit();
        return $update;
    }

    private function organizarDescricao($data)
    {
        $segmento = $data["segmento_id"] == "" ? "Segmento: todos" : "Segmento: " . Segmento::find($data["segmento_id"])->descricao;
        $tipo = $data["tipopessoa_id"] == "" ? ", Tipo Pessoa: todos" : ", Tipo Pessoa: " . Tipopessoa::find($data["tipopessoa_id"])->descricao;
        $setor = $data["setor_id"] == "" ? ", Setor: todos" : ", Setor: " . Setor::find($data["setor_id"])->descricao;

        return "Filtro: " . $segmento . $tipo . $setor;
    }

    private function definition()
    {
		//Set Empresa
		$this->repositorio->setEmpresa(Session::get('empresa_padrao')->id);
		//Set Grupo
		$this->repositorio->setGrupo(Session::get('empresa_padrao')->grupo_id);
	}
}
