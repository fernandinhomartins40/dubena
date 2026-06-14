<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Excel;
use Session;
use App\User;
use App\Setor;
use App\Bairro;
use App\Cidade;
use App\Empresa;
use App\Cliente;
use App\Segmento;
use App\Services\CarbonCustom as Carbon;
use App\Tipopessoa;
use App\Pedidosituacao;
use Illuminate\Http\Request;
use App\Repository\SelectRepository;
use PHPExcel_Worksheet_MemoryDrawing;

class ReportclientesaniversariantesController extends Controller
{

	private $filtro;
	private $repository;
	private $empresa_id;
	private $grupo_id;

	function __construct(SelectRepository $repository){
		$this->repository = $repository;
	}

	public function reportClientesAniversariantesCompramPDF()
	{
		return $this->reportClientesAniversariantesCompram(true);
	}

	public function reportClientesAniversariantesCompram($pdf = false)
	{
		return $this->reportClientesAniversariantes($pdf, true);
	}

	public function reportClientesAniversariantes($pdf = false, $compram = false)
	{    
		$empresas_id = User::find(Auth::user()->id)->empresas()->pluck('id')->toArray();
		$empresas = Empresa::whereIn('id', $empresas_id)->orderBy('nome_informal')->get();
		$setores = Setor::where([['ativo', 1]])->whereIn('empresa_id', $empresas_id)->orderBy('descricao')->get();
		$set = [];
		foreach ($empresas as $empresa) {
			$s = $setores->where('empresa_id', $empresa->id);
			$set[$empresa->nome_informal] = $s->pluck('descricao', 'id')->toArray();
		}
		$setores = $set;
		$empresas = $empresas->pluck('nome_informal', 'id');

		$segmentos = Segmento::where([['grupo_id', Session::get('empresa_padrao')->grupo_id], ['ativo', 1]])->orderBy('descricao')->get()->pluck('descricao', 'id')->prepend('Selecione', '');
		$bairros = Bairro::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');

		if(count($_GET) > 0)
			return $this->getFiltros($_GET, $setores, $segmentos, $bairros, $empresas, $empresas_id, $pdf, $compram);

		return view('reports.clientes.clientesaniversariantes', compact('setores','bairros','segmentos', 'empresas', 'compram'));
	}

	private function getFiltros($filtros, $setores, $segmentos, $bairros, $empresas, $empresas_id, $pdf = false, $compram)
	{ 
		$setor_id = $filtros['setor_id'] !== '' ? explode(',', $filtros['setor_id']) : 0;

		$empresas_id = $filtros['empresa_id'] !== '' ? explode(',', $filtros['empresa_id']) : $empresas_id;

		$segmento_id = $filtros['segmento_id'] !== '' ?$filtros['segmento_id'] : 0;

		// $bairro_id = $filtros['bairro_id'] !== '' ?$filtros['bairro_id'] : 0;
		$get = '?';

		$datainicio = explode('/', $filtros['datainicio']);
		$diaInicio = $datainicio[0];
		$mesInicio = $datainicio[1];
		$anoInicio = $datainicio[2];

		$datafim = explode('/', $filtros['datafim']);
		$diaFim = $datafim[0];
		$mesFim = $datafim[1];
		$anoFim = $datafim[2];

		$clientes = Cliente::with('telefones')
		->join('empresas', 'empresas.id', 'clientes.empresa_id')
		->join('bairros', 'bairros.id', 'clientes.bairro_id')
		->join('ruas', 'ruas.id', 'clientes.rua_id');
		
		if($segmento_id != 0)
			$clientes = $clientes->where('clientes.segmento_id', $segmento_id);

		if ($setor_id != 0) 
			$clientes = $clientes->whereIn('clientes.setor_id', $setor_id);

		// if ($bairro_id != 0) 
		// 	$clientes = $clientes->where('clientes.bairro_id', $bairro_id);

		$clientes = $clientes->whereIn("clientes.empresa_id", $empresas_id);
		if($anoFim > $anoInicio) {
			$clientes = $clientes->where([
				[DB::raw("TO_CHAR(clientes.datanascimento, 'YYYY-MM-DD')"), '>=', array('0001' . $mesInicio . '-' . $diaInicio)],
				[DB::raw("TO_CHAR(clientes.datanascimento, 'YYYY-MM-DD')"), '<=', array(Carbon::now()->year . '-' . $mesFim . '-' .$diaFim)]
				]);
		} else {
			$clientes = $clientes->whereBetween(DB::raw("TO_CHAR(datanascimento, 'MM-DD')"), 
				[array($mesInicio . '-' . $diaInicio), array( $mesFim . '-' . $diaFim)]);
		}

		$clientes = $clientes->select('clientes.id as id', 'clientes.nome as nome', 'ruas.descricao as rua', 'bairros.descricao as bairro', 'clientes.numero', 'clientes.complemento', 'clientes.empresa_id', 'empresas.nome_informal', 'clientes.datanascimento')->orderBy('empresas.nome_informal')->orderBy('clientes.empresa_id')->orderBy(DB::raw("TO_CHAR(datanascimento, 'MM-DD')"))->orderBy(DB::raw("TO_CHAR(datanascimento, 'DD')"))->orderBy('clientes.nome')->orderBy('bairros.descricao')->orderBy('bairros.id')->get();  

		$this->setFiltersReport($diaInicio, $mesInicio, $anoInicio, $diaFim, $mesFim, $anoFim, $setor_id, $empresas_id, $segmento_id);
		$filtro = $this->filtro;

		$clientes = $this->montarTable($clientes, $compram);

		$titulo = 'Relatório de Clientes Aniversariantes';
		$titulo .= $compram ? ' que Compram' : '';

		if ($pdf) {
			$pdf = \App::make('dompdf.wrapper');
			$pdf->setPaper('A4', 'landscape');
			$pdf->loadView('reports.clientes.clientesaniversariantesPDF', compact('titulo', 'setores','bairros','segmentos','tipopessoas', 'uf', 'cidade_id', 'tipopessoa_id', 'setor_id', 'segemento_id', 'datainicio', 'datafim', 'clientes', 'filtro', 'compram'));
			return $pdf->stream();
		}

		return view('reports.clientes.clientesaniversariantesPDF', compact('titulo', 'setores','bairros','segmentos','tipopessoas', 'uf', 'cidade_id', 'tipopessoa_id', 'setor_id', 'segemento_id', 'datainicio', 'datafim', 'clientes', 'filtro', 'compram'));
	}

	private function montarTable($clientes, $compram)
	{
		$dados = [];
		$empresaAnterior = null;
		$mesAnterior = null;
		$totalMes = null;
		$totalClientes = count($clientes);
		$count = 0;
		foreach ($clientes as $cliente) {
			$ultimaCompra = requestDataOracle($cliente->dataultimopedidoconcluido(), false);

			if(($compram && $ultimaCompra != '') || !$compram) {
				$mesAtual = explode('-', $cliente->datanascimento)[1];
				if(!is_null($totalMes) && ($mesAnterior !== $mesAtual || $empresaAnterior !== $cliente->empresa_id)){
					if($totalMes > 1){
						$objCliente = (object) [];
						$objCliente->total = $totalMes;
						array_push($dados, $objCliente);
					}
					$totalMes = 0;
				}
				if(is_null($empresaAnterior) || $empresaAnterior !== $cliente->empresa_id){
					$objCliente = (object) [];
					$objCliente->empresa_id = $cliente->empresa_id;
					$objCliente->empresa = $cliente->nome_informal;
					array_push($dados, $objCliente);
				} 
				if(is_null($mesAnterior) || $mesAnterior !== $mesAtual || $empresaAnterior !== $cliente->empresa_id){
					$objCliente = (object) [];
					$objCliente->mes = getDescricaoMes($mesAtual);
					array_push($dados, $objCliente);
				}
				$objCliente = (object) [];
				$dia = explode('-', $cliente->datanascimento)[2];
				$dia = explode(' ', $dia)[0];
				$objCliente->dia = $dia;
				$objCliente->cliente_id = $cliente->id;
				$objCliente->nome = $cliente->nome;
				$telefones = DB::table('clientetelefones')->where('cliente_id', $cliente->id)->limit(3)->pluck('telefone')->toArray();
				$telefones = count($telefones) > 0 ? str_replace('-', '', implode(', ', $telefones)) : '';
				$telefones = str_replace('(', '', $telefones);
				$telefones = str_replace(')', '', $telefones);
				$objCliente->telefones = $telefones;
				$objCliente->ulltimaCompra = requestDataOracle($cliente->dataultimopedidoconcluido(), false);
				$objCliente->endereco = $cliente->rua. ', ' .  $cliente->numero. ' - ' .  $cliente->complemento. ' - '.$cliente->bairro;
				array_push($dados, $objCliente);
				$empresaAnterior = $cliente->empresa_id;
				$mesAnterior = $mesAtual;
				$totalMes++;
				$count++;
			}
		}
		if($totalClientes > 1) {
			if($totalMes > 1 ) {
				$objCliente = (object) [];
				$objCliente->total = $totalMes;
				array_push($dados, $objCliente);
			}
			$objCliente = (object) [];
			$objCliente->totalGeral = $count;
			array_push($dados, $objCliente);
		}
		return $dados;
	}

	private function setFiltersReport($diaInicio, $mesInicio, $anoInicio, $diaFim, $mesFim, $anoFim, $setor_id, $empresas_id, $segmento_id){
		$this->filtro = "Período " . $diaInicio . '/' . $mesInicio . '/' . $anoInicio . " a " . $diaFim . '/' . $mesFim . '/' . $anoFim . ';';
		$this->filtro .= ' Empresa (s): ' . implode(', ', Empresa::whereIn('id', $empresas_id)->pluck('nome_informal')->toArray()) . ';';
		$this->filtro .= ' Setor: ';
		$this->filtro .=  $setor_id != 0 ? implode(', ', Setor::whereIn('id', $setor_id)->pluck('descricao')->toArray()) . ';' : ' todos;';
		// $this->filtro .= ' Bairro: ';
		// $this->filtro .= $bairro_id != 0 ? Bairro::where('id', $bairro_id)->pluck('descricao')->first() . ';' : 'todos;';
		$this->filtro .= ' Segmento: ';
		$this->filtro .= $segmento_id != 0 ? Segmento::where('id', $segmento_id)->pluck('descricao')->first() . '.' : 'todos.';
	}

	public function reportClientesAniversariantesPDF()
	{    
		$empresas_id = User::find(Auth::user()->id)->empresas()->pluck('id')->toArray();
		$empresas = Empresa::whereIn('id', $empresas_id)->orderBy('nome_informal')->get()->pluck('nome_informal', 'id');
		$empresas = count($empresas) > 1 ? $empresas->prepend('Selecione','') : $empresas;
		$setores = Setor::where([['empresa_id', Session::get('empresa_padrao')->id], ['ativo', 1]])->get()->pluck('descricao', 'id')->prepend('Selecione', '');
		$tipopessoas = Tipopessoa::where([['grupo_id', Session::get('empresa_padrao')->grupo_id], ['ativo', 1]])->get()->pluck('descricao', 'id')->prepend('Selecione', '');
		$segmentos = Segmento::where([['grupo_id', Session::get('empresa_padrao')->grupo_id], ['ativo', 1]])->get()->pluck('descricao', 'id')->prepend('Selecione', '');
		$bairros = Bairro::where('grupo_id', Session::get('empresa_padrao')->id)->pluck('descricao', 'id')->prepend('Selecione', '');

		if(count($_GET) > 0)
			return $this->getFiltros($_GET, $setores, $tipopessoas, $segmentos, $bairros, $empresas, $empresas_id, true);

		return \Redirect::to('report.ClientesAniversariantes');
	}

	////////////// Clientes Sem Compra
	public function indexSemCompra(){
		$this->definition();
		$empresas = $this->repository->getEmpresasUser()->prepend('Selecione','');
		$tipopessoa = $this->repository->getTipoPessoa()->prepend('Selecione','');
		$segmento = $this->repository->getSegmentos()->prepend('Selecione','');
		$cidades = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id)
			->orWhere('grupo_id', null)->select('descricao', 'id')->orderBy('descricao')->get();
		$bairrosa = Bairro::where('grupo_id', Session::get('empresa_padrao')->grupo_id)
				->orderBy('cidade_id')->select('descricao', 'id', 'cidade_id')->orderBy('descricao')->get();
		$bairros = [];
		$bairros["Selecione"] = [""=>"Selecione"];
		foreach ($cidades as $cidade) {
            $s = $bairrosa->where('cidade_id', $cidade->id)->pluck('descricao', 'id')->toArray();
            if ($s) {
                $bairros[$cidade->descricao] = array_key_exists($cidade->descricao, $bairros) ? array_merge($s, $bairros[$bairros->descricao]) : $s;
            }
		}
		return view('reports.clientes.nao_compram.naocompram_index')->with(compact('empresas','tipopessoa','segmento','bairros'));
	}

	public function semCompraFiltro(){
		$this->definition();
		$get = $_GET;
		$tipo = $get["tipo"] == "1";
		$clientes = $this->buscaClienteCompra($get);
		$filtro = $this->filtro;
		$titulo = "Relatório de Clientes Sem Compras";
		if($get["exportar"] != 1) {
			return view('reports.clientes.nao_compram.naocompram_pdf')->with(compact('titulo','filtro','clientes'));
        } else {
            $this->semCompraXls($clientes, $filtro, $titulo);
        }
		
	}

	private function buscaClienteCompra($filtro){
		$emp = User::find(Auth::user()->id)->empresas()->pluck('id')->toArray();
		$empresa_id = $filtro["empresa"] ==  "0" ? null : $filtro["empresa"];
		$setor_id = $filtro["setor"] == "0" ? null : $filtro["setor"];
		$tipopessoa_id = $filtro["tipopessoa"] == "0" ? null : $filtro["tipopessoa"];
		$segmento_id = $filtro["segmento"] == "0" ? null : $filtro["segmento"];
		$bairro_id = $filtro["bairro"] == "0" ? null : $filtro["bairro"];
		
		$temcompra = Carbon::today()->subDays($filtro["temcompra"])->startOfDay();
		$naocompra = Carbon::today()->subDays($filtro["naocompra"])->endOfDay();
		$dataatual = Carbon::now()->endOfDay();

		$pedidosituacao = Pedidosituacao::whereRaw("grupo_id = $this->grupo_id and fechadoconcluido = 1")->pluck('id')->toArray();
		$sit = implode(',',$pedidosituacao);
		$selectJoin = "SELECT cliente_id FROM pedidos WHERE pedidosituacao_id IN ($sit) "
		. " AND datahoraprevisaoentrega BETWEEN"
		. " TO_DATE('$temcompra', 'YYYY-MM-DD HH24:MI:SS') AND TO_DATE('$naocompra', 'YYYY-MM-DD HH24:MI:SS')"
		. " AND datahoraprevisaoentrega NOT BETWEEN "
		. "TO_DATE('" . $naocompra->copy()->startOfDay() . "', 'YYYY-MM-DD HH24:MI:SS') AND TO_DATE('$dataatual', 'YYYY-MM-DD HH24:MI:SS')"
		." GROUP by cliente_id";
		
		$joinUltima = "SELECT cliente_id, MAX(datahoraprevisaoentrega) AS datacompra FROM pedidos WHERE pedidosituacao_id IN ($sit) GROUP BY cliente_id";

		
		$clientela = DB::table('clientes')->join('empresas','clientes.empresa_id','empresas.id')
									->rightJoin(DB::raw("($selectJoin) pedido"),'pedido.cliente_id','clientes.id')
									->rightJoin(DB::raw("($joinUltima) ultima"),function($join)use($naocompra){
										$join->on('ultima.cliente_id','clientes.id')
											 ->whereRaw("ultima.datacompra < to_date('$naocompra','yyyy-mm-dd hh24:mi:ss')");
									})
									->leftJoin('setors as setor','clientes.setor_id','setor.id')
									->join('bairros','clientes.bairro_id','bairros.id')
									->join('ruas','clientes.rua_id','ruas.id')
									->where([
										['clientes.cliente',1]
									]);
		if(is_null($empresa_id)){
			$clientela = $clientela->whereIn('clientes.empresa_id',$emp);
		}else{
			$clientela = $clientela->where('clientes.empresa_id',$empresa_id);
		}
		if(!is_null($setor_id))
			$clientela = $clientela->where('clientes.setor_id',$setor_id);
		if(!is_null($segmento_id))
			$clientela = $clientela->where('clientes.segmento_id',$segmento_id);
		if(!is_null($tipopessoa_id))
			$clientela = $clientela->where('clientes.tipopessoa_id',$tipopessoa_id);
		if(!is_null($bairro_id))
			$clientela = $clientela->where('clientes.bairro_id',$bairro_id);
		$clientela = $clientela->select('empresas.id as empresa_id','empresas.nome_informal as empresa','clientes.id as cliente_id','clientes.nome',
									'setor.descricao as setor','setor.id as setor_id','bairros.descricao as bairro',"ultima.datacompra as ult_compra",
									DB::raw("(select listagg(tel.telefone) within group(order by tel.telefone)".
									"from clientetelefones tel where tel.cliente_id = clientes.id) as telefone"),
									DB::raw("(trunc(to_date('$dataatual','yyyy-mm-dd hh24:mi:ss') -". 
									"ultima.datacompra)) as diff"), 'ruas.descricao as rua', 'clientes.numero', 'clientes.complemento')
								->orderBy('empresa')->orderBy('setor')->orderBy('nome')->get();
		
		$clientes = collect([]);
		$anterior = null;
		$setanterior = null;
		$nulo = true;
		$count=0;
		$countnull = 0;
		$countult = 0;
		$counttotal=0;
		foreach ($clientela as $cliente) {
			if(!is_null($setanterior) && $cliente->setor_id != $setanterior){
				$wset = $clientela->where('setor_id',$setanterior);
				$objtotalset = (object) array();
				$objtotalset->totalset = $wset->count();
				$clientes->push($objtotalset);
			}
			if(!is_null($anterior) && $anterior != $cliente->empresa_id){
				$wcli = $clientela->where('empresa_id',$anterior);
				$objtotalemp = (object) array();
				$objtotalemp->totalemp = $wcli->count();
				$clientes->push($objtotalemp);
			}
			if(is_null($anterior) || $anterior != $cliente->empresa_id){
				$objempresa = (object) array();
				$objempresa->empresa_id = $cliente->empresa_id;
				$objempresa->empresa = $cliente->empresa;
				$clientes->push($objempresa);
				$nulo = true;
				$count = 0;
				$countnull = 0;
				$countult = 0;
			}
			if((is_null($setanterior) || $setanterior != $cliente->setor_id || $anterior != $cliente->empresa_id) && !is_null($cliente->setor_id)){
				$objsetor = (object) array();
				$objsetor->setor_id = $cliente->setor_id;
				$objsetor->setor = $cliente->setor;
				$clientes->push($objsetor);
				$countult = 0;
			}
			if($nulo && is_null($cliente->setor_id)){
				$objsetor = (object) array();
				$objsetor->setor_id = 'none';
				$objsetor->setor = "Sem Setor";
				$clientes->push($objsetor);
				$nulo = false;
				$count = 0;
				$countnull = $clientela->where('setor_id',null)->where('empresa_id',$anterior)->count();
				$countult = 0;
				$countcli = 0;
			}
			$objnormal = (object) array();
			$objnormal->cliente_id = $cliente->cliente_id;
			$objnormal->nome = $cliente->nome;
			$objnormal->telefone = $cliente->telefone;
			$objnormal->ultcompra = $cliente->ult_compra;
			$objnormal->diff = $cliente->diff;
			$objnormal->bairro = $cliente->bairro;
			$objnormal->endereco = $cliente->rua. ', ' .  $cliente->numero. ' - ' .  $cliente->complemento." - ".$cliente->bairro;
			$clientes->push($objnormal);
			$anterior = $cliente->empresa_id;
			$setanterior = $cliente->setor_id;
			$count++;
			$countult++;
			$counttotal++;
			if($count == $countnull && !$nulo && $counttotal != count($clientela)){
				$objnull = (object) array();
				$objnull->totalset = $clientela->where('setor_id',null)->where('empresa_id',$anterior)->count();
				$clientes->push($objnull);
				$countnull = 0;
			}
		}
		if(count($clientes) > 0){
			if($countult > 0){
				$wset = $clientela->where('setor_id',$setanterior)->where('empresa_id',$anterior);
				$objtotalset = (object) array();
				$objtotalset->totalset = $wset->count();
				$clientes->push($objtotalset);
			}
			$wcli = $clientela->where('empresa_id',$anterior);
			$objtotalemp = (object) array();
			$objtotalemp->totalemp = $wcli->count();
			$clientes->push($objtotalemp);
			$objtotal = (object) array();
			$objtotal->total = count($clientela);
			$clientes->push($objtotal);
		}
		$this->filtroSemCompra($empresa_id,$setor_id,$tipopessoa_id,$segmento_id,$bairro_id,
				$filtro["temcompra"],$filtro["naocompra"]);
		return $clientes;
	}

	private function filtroSemCompra($empresa_id,$setor_id,$tipopessoa_id,$segmento_id,$bairro_id,$tem,$nao){
		$empresa = is_null($empresa_id) ? "todas" : Empresa::where('id',$empresa_id)->pluck('nome_informal')->first();
		$setor = is_null($setor_id) ? "todos" : Setor::where('id',$setor_id)->pluck('descricao')->first();
		$tipopep = is_null($tipopessoa_id) ? "todos" : Tipopessoa::where('id',$tipopessoa_id)->pluck('descricao')->first();
		$segmento = is_null($segmento_id) ? "todos" : Segmento::where('id',$segmento_id)->pluck('descricao')->first();
		$bairro = is_null($bairro_id) ? "todos" : Bairro::where('id',$bairro_id)->pluck('descricao')->first();
		$this->filtro = "Filtro: Empresa: $empresa, Setor: $setor, Tipo Pessoa: $tipopep, Segmento: $segmento, Bairro: $bairro" . 
						" que não compram a $nao dias mas tem compras a $tem dias.";
	}

	/////Clientes Incompletos
	public function incompletosIndex()
	{
		return view('reports.clientes.incompletos.index');
	}

	public function incompletosFiltro()
	{
		$this->definition();
		$get = $_GET;
		$titulo = "Relatório de Clientes Incompletos";
		$clientes = $this->buscaIncompletos($get);
		$filtro = $this->filtro;

		return view('reports.clientes.incompletos.inc_pdf')->with(compact('titulo','filtro','clientes'));
	}

	private function buscaIncompletos($filtro)
	{
		$dados = $filtro["dados"] == "0" ? null : $filtro["dados"];
		$tipos = Tipopessoa::where(['grupo_id'=>$this->grupo_id,'tipopessoacadastro'=>'F','ativo'=>1])->pluck('id')->toArray();
		$incompletos = DB::table('clientes')
						->leftJoin('setors as setor','clientes.setor_id','setor.id')
						->join('tipopessoas as tipo','clientes.tipopessoa_id','tipo.id')
						->where([
							['clientes.empresa_id',$this->empresa_id],
							['clientes.cliente',1],
							['clientes.ativo',1]
						]);
		if($dados == "01"){
			$sem = "Clientes sem data de nascimento.";
			$incompletos = $incompletos->whereIn('tipopessoa_id',$tipos)
							->where('clientes.datanascimento',null);
		}
		if($dados == "02"){
			$sem = "Clientes sem setor.";
			$incompletos = $incompletos->where('clientes.setor_id',null);
		}
		if($dados == "03"){
			$sem = "Clientes sem telefone(s).";
			$incompletos = $incompletos->whereRaw("clientes.id not in (select cliente_id from".
							" clientetelefones where empresa_id = $this->empresa_id)");
		}
		$incompletos = $incompletos->select('clientes.id','clientes.nome','clientes.datanascimento as data',
							'setor.descricao as setor','tipo.tipopessoacadastro as tipo',
							DB::raw("(select listagg(tel.telefone,', ') within group(order by tel.telefone) from".
								" clientetelefones tel where tel.cliente_id = clientes.id and rownum <= 2) as telefone"))
						->orderBy('clientes.nome')->get();

		$this->filtro = "Filtro $sem";
		return $incompletos;
	}

	private function definition(){
		//get empresa_id
		$this->empresa_id = Session::get('empresa_padrao')->id;
		//get grupo
		$this->grupo_id = Session::get('empresa_padrao')->grupo_id;
		//set empresa
		$this->repository->setEmpresa($this->empresa_id);
		//set grupo
		$this->repository->setGrupo($this->grupo_id);
	}

	private function semCompraXls($clientes, $filtro, $titulo){
        $newclientes[] = [$titulo];
        $newclientes[] = [$filtro];
        $newclientes[] = ['Emitido em '.Carbon::now()->format('d/m/Y H:i:s')];
        $newclientes[] = [''];
		$total = 0;
		$totalgeral = 0;
		$primeiroSetor = true;
        foreach($clientes as $cliente)
        {
			if(isset($cliente->setor_id)){
				if(!$primeiroSetor){
					$newcliente = array();
					$newclientes[] =  ['Total', $total];
					$newclientes[] = [''];
					$total = 0;
				}
				$primeiroSetor = false;
				$newcliente = array();
				array_push($newcliente, $cliente->setor);
				$newclientes[] =  $newcliente;
				$newclientes[] = ['Cód', 'Nome/Razão Social', 'Telefone', 'Últ. Compra', 'Dias s/ Compra', 'Endereço'];
			} elseif(isset($cliente->cliente_id)){
				$newcliente = array();
				array_push($newcliente, $cliente->cliente_id);
				array_push($newcliente, $cliente->nome);
				array_push($newcliente, $cliente->telefone);
				array_push($newcliente, Carbon::parse($cliente->ultcompra)->format('d/m/Y'));
				array_push($newcliente, $cliente->diff);
				array_push($newcliente, $cliente->endereco);
				$newclientes[] =  $newcliente;
				$total++;
				$totalgeral++;
			}
        }
        $newclientes[] =  ['Total', $total];
		$newclientes[] = [''];
        $newclientes[] =  ['Total Geral', $totalgeral];
        $clientes = $newclientes;
        Excel::create('Clientes Sem Compra', function($excel) use ($clientes) {

            $excel->sheet('Clientes Sem Compra', function($sheet) use($clientes) {
                $sheet->setAutoSize(false);
                $sheet->setPageMargin(0.5);
                $sheet->fromArray($clientes, null, 'A1', true, false);
                $sheet->mergeCells('A1:F1');
                $sheet->mergeCells('A2:F2');
                $sheet->mergeCells('A3:F3');
                $sheet->cells('A1:A1', function($cells) {
                    $cells->setFontWeight('bold');
                    $cells->setFontSize(14);
                });
                $sheet->cells('A2:A2', function($cells) {
                    $cells->setFontWeight('bold');
                    $cells->setFontSize(12);
                });
                $sheet->cells('A3:A3', function($cells) {
                    $cells->setFontSize(10);
                });
                $sheet->cells('A5:F5', function($cells) {
                    $cells->setFontWeight('bold');
                });
                $sheet->cells('A'.(count($clientes)).':F'.(count($clientes)), function($cells) {
                    $cells->setFontWeight('bold');
                });
                $sheet->setWidth('A', 12);
                $sheet->setWidth('B', 50);
                $sheet->setWidth('C', 50);
                $sheet->setWidth('D', 20);
                $sheet->setWidth('E', 20);
				$sheet->setWidth('F', 60);

                if(Session::get('empresa_padrao')->logo!=null){
                    $image = imagecreatefromstring(base64_decode(Session::get('empresa_padrao')->logo));
                    imagesavealpha($image, true);
                    $drawing = new PHPExcel_Worksheet_MemoryDrawing();
                    $drawing->setName('teste');
                    $drawing->setImageResource($image);
                    $drawing->setRenderingFunction(PHPExcel_Worksheet_MemoryDrawing::RENDERING_PNG);
                    $drawing->setMimeType(PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_DEFAULT);
                    $drawing->setWidthAndHeight(148,70);
                    $drawing->setResizeProportional(true);
                    $drawing->setCoordinates('G1');
                    $drawing->setOffsetX(5);
                    $drawing->setOffsetY(5);
                    $drawing->setWorksheet($sheet);
                }
				for($i=0; $i<count($clientes);$i++){
					$cliente = $clientes[$i];
					if(isset($cliente[0])){
						if($cliente[0]=='Cód'){
							$sheet->cells('A'.($i+1).':F'.($i+1), function($cells) {
								$cells->setFontWeight('bold');
							});
						} elseif(substr($cliente[0], 0, 5)=="Total"){
							$sheet->cells('A'.($i+1).':F'.($i+1), function($cells) {
								$cells->setFontWeight('bold');
							});
						} elseif(!isset($cliente[1])){
							$sheet->cells('A'.($i+1).':F'.($i+1), function($cells) {
								$cells->setFontWeight('bold');
							});
						}
					}
				}
            });
        })->download('xlsx');

    }

}
