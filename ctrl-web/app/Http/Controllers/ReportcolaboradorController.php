<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CarbonCustom as Carbon;
use App\Empresa;
use App\Tipoexame;
use Session;
use App\User;
use App\Colaborador;
use Auth;
use DB;
use App\Colaboradorexame;

class ReportcolaboradorController extends Controller
{

	private $filtros;

	private function getEmpresaUser()
	{
		return User::find(Auth::user()->id)->empresas()->pluck('id')->toArray();
	}

	private function getEmpresas($empresas_id)
	{
		return Empresa::whereIn('id', $empresas_id)->orderBy('nome_informal')->get()->pluck('nome_informal', 'id');
	}
	public function reportColaboradoresAniversariantesFamiliaresPDF()
	{
		return $this->reportColaboradoresAniversariantes(true, true);
	}
	public function reportColaboradoresAniversariantesPDF()
	{
		return $this->reportColaboradoresAniversariantes(false, true);
	}
	public function reportColaboradoresAniversariantesFamiliares()
	{    
		return $this->reportColaboradoresAniversariantes(true);
	}
	public function reportColaboradoresAniversariantes($familiares = false, $pdf = false)
	{    
		$empresas_id = $this->getEmpresaUser();
		$empresas = $this->getEmpresas($empresas_id);

		if(count($_GET) > 0)
			return $this->getFiltrosColaboradoresAniversariantes($_GET, $empresas_id, $empresas, $familiares, $pdf);

		return view('reports.colaboradores.aniversariantes.colaboradoresaniversariantes', compact('empresas'));
	}

	private function getFiltrosColaboradoresAniversariantes($filtros, $empresas_id, $empresas, $familiares = false, $pdf)
	{
		$empresas_id = $filtros['empresa_id'] !== '' ? explode(',', $filtros['empresa_id']) : $empresas_id;

		$datainicio = explode('/', $filtros['datainicio']);
		$diaInicio = $datainicio[0];
		$mesInicio = $datainicio[1];
		$anoInicio = $datainicio[2];

		$datafim = explode('/', $filtros['datafim']);
		$diaFim = $datafim[0];
		$mesFim = $datafim[1];
		$anoFim = $datafim[2];

		$colaboradores = DB::table('colaboradors as colaborador')
		->join('empresas as empresa', 'empresa.id', 'colaborador.empresa_id')
		->whereIn('colaborador.empresa_id', $empresas_id)
		->where('colaborador.ativo', 1);

		$this->setFiltros($empresas, $datainicio, $datafim, $empresas_id);
		$filtro = $this->filtros;
		//relatorio de familiares de colaboradores
		if ($familiares) {
			$colaboradores = $colaboradores->join('colaboradorfamilias as familiar', 'colaborador.id', 'familiar.colaborador_id')
			->join('parentescos as parentesco', 'parentesco.id', 'familiar.parentesco_id');
			if($anoFim > $anoInicio) {
				$colaboradores = $colaboradores->where([
					[DB::raw("TO_CHAR(familiar.datanascimento, 'YYYY-MM-DD')"), '>=', array('0001' . $mesInicio . '-' . $diaInicio)],
					[DB::raw("TO_CHAR(familiar.datanascimento, 'YYYY-MM-DD')"), '<=', array(Carbon::now()->year . '-' . $mesFim . '-' .$diaFim)]
					]);
			} else {
				$colaboradores = $colaboradores->whereBetween(DB::raw("TO_CHAR(familiar.datanascimento, 'MM-DD')"), [array($mesInicio . '-' . $diaInicio), array( $mesFim . '-' . $diaFim)]);
			}

			$colaboradores = $colaboradores->select('empresa.nome_informal', 'parentesco.descricao as parentesco', 'colaborador.id as colaborador_id', 'colaborador.nome as colaborador', 
				'familiar.nome as familiar', 'familiar.datanascimento', 'empresa.nome_informal as empresa', 'empresa.id as empresa_id')
			->orderBy('empresa.nome_informal')->orderBy('colaborador.empresa_id')->orderBy(DB::raw("TO_CHAR(familiar.datanascimento, 'MM-DD')"))
			->orderBy(DB::raw("TO_CHAR(familiar.datanascimento, 'DD')"))->orderBy('colaborador.nome')->orderBy('familiar.colaborador_id')->get();  

			$titulo = 'Relatório de Familiares Aniversariantes';

			$colaboradores = $this->montarTableAniversariantes($colaboradores, $familiares);
		//relatorio de colaboradores
		} else {
			if($anoFim > $anoInicio) {
				$colaboradores = $colaboradores->where([
					[DB::raw("TO_CHAR(colaborador.datanascimento, 'YYYY-MM-DD')"), '>=', array('0001' . $mesInicio . '-' . $diaInicio)],
					[DB::raw("TO_CHAR(colaborador.datanascimento, 'YYYY-MM-DD')"), '<=', array(Carbon::now()->year . '-' . $mesFim . '-' .$diaFim)]
					]);
			} else {
				$colaboradores = $colaboradores->whereBetween(DB::raw("TO_CHAR(colaborador.datanascimento, 'MM-DD')"), [array($mesInicio . '-' . $diaInicio), array($mesFim . '-' . $diaFim)]);
			}

			$colaboradores = $colaboradores->join('cargos as cargo', 'cargo.id', 'colaborador.cargo_id')
			->select('empresa.nome_informal', 'colaborador.id as colaborador_id', 'cargo.descricao as cargo', 'colaborador.nome', 'colaborador.datanascimento', 'empresa.nome_informal as empresa', 
				'empresa.id as empresa_id')->orderBy('empresa.nome_informal')->orderBy('colaborador.empresa_id')->orderBy(DB::raw("TO_CHAR(datanascimento, 'MM-DD')"))
			->orderBy(DB::raw("TO_CHAR(datanascimento, 'DD')"))->orderBy('colaborador.nome')->orderBy('colaborador_id')->get();  

			$titulo = 'Relatório de Colaboradores Aniversariantes';

			$colaboradores = $this->montarTableAniversariantes($colaboradores, $familiares);
		}

		if($pdf) {
			$pdf = \App::make('dompdf.wrapper');
			$pdf->loadView('reports.colaboradores.aniversariantes.colaboradoresaniversariantesPDF', compact('titulo', 'filtro', 'colaboradores', 'familiares'));
			return $pdf->stream();
		}
		return view('reports.colaboradores.aniversariantes.colaboradoresaniversariantesPDF', compact('titulo', 'filtro', 'colaboradores', 'familiares'));
	}

	private function montarTableAniversariantes($colaboradores, $familiares)
	{
		$dados = [];
		$empresaAnteriror = null;
		$mesAnterior = null;
		$totalMes = null;
		$totalColaborador = null;
		$count = 0;
		foreach ($colaboradores as $colaborador) {
			$mesAtual = explode('-', $colaborador->datanascimento)[1];
			if(!is_null($totalMes) && ($mesAnterior !== $mesAtual || $empresaAnteriror !== $colaborador->empresa_id)){
				$objColaborador = (object) [];
				$objColaborador->totalMes = $totalMes;
				$totalMes = 0;
				array_push($dados, $objColaborador);
			}
			if(is_null($empresaAnteriror) || $empresaAnteriror !== $colaborador->empresa_id){
				$objColaborador = (object) [];
				$objColaborador->empresa_id = $colaborador->empresa_id;
				$objColaborador->empresa = $colaborador->nome_informal;
				array_push($dados, $objColaborador);
			}
			if(is_null($mesAnterior) || $mesAnterior !== $mesAtual || $empresaAnteriror !== $colaborador->empresa_id){
				$objColaborador = (object) [];
				$objColaborador->mes = getDescricaoMes($mesAtual);
				array_push($dados, $objColaborador);
			}
			$empresaAnteriror = $colaborador->empresa_id;
			$mesAnterior = $mesAtual;
			$totalMes++;
			$dia = explode('-', $colaborador->datanascimento)[2];
			$dia = explode(' ', $dia)[0];
			$objColaborador = (object) [];
			$objColaborador->dia = $dia;
			$objColaborador->colaborador_id = $colaborador->colaborador_id;
			if($familiares) {
				$objColaborador->colaborador = $colaborador->colaborador;
				$objColaborador->familiar = $colaborador->familiar;
				$objColaborador->parentesco = $colaborador->parentesco;
			} else {	
				$objColaborador->nome = $colaborador->nome;
				$objColaborador->cargo = $colaborador->cargo;
			}
			array_push($dados, $objColaborador);
			$count++;
			if(count($colaboradores) == $count){
				$objColaborador = (object) [];
				$objColaborador->totalMes = $totalMes;
				array_push($dados, $objColaborador);
				$objColaborador = (object) [];
				$objColaborador->total = $count;
				array_push($dados, $objColaborador);
			}
		}
		return $dados;
	}

	private function setFiltros($empresas, $datainicio, $datafim, $empresas_id)
	{
		$this->filtros = "Período " . implode('/', $datainicio) . " a " . implode('/', $datafim) . ';';
		$this->filtros .= ' Empresa (s): ' . implode(', ', $empresas->only($empresas_id)->toArray()) . '.';
	}

	public function reportColaboradoresExames($pdf = false)
	{
		$empresas_id = $this->getEmpresaUser();
		$empresas = $this->getEmpresas($empresas_id);
		$tipoexames = Tipoexame::where([
			['grupo_id', Session::get('empresa_padrao')->grupo_id],
			['ativo', 1]
			])->orderBy('descricao')->pluck('descricao', 'id');

		if(count($_GET) > 0)
			return $this->getFiltrosColaboradoresExames($_GET, $empresas_id, $empresas, $tipoexames, $pdf);

		return view('reports.colaboradores.exames.colaboradoresexames', compact('empresas', 'tipoexames'));
	}


	public function reportColaboradoresExamesPDF() {
		return $this->reportColaboradoresExames(true);
	}

	private function getFiltrosColaboradoresExames($filtros, $empresas_id, $empresas, $tipoexames, $pdf)
	{
		$empresas_id = $filtros['empresa_id'] == '' ? $empresas_id : explode(',', $filtros['empresa_id']);
		$tipoexame_id = $filtros['tipoexame_id'] == '' ? 0 : explode(',', $filtros['tipoexame_id']);	

		$datainicio = insertDataOracle($filtros['datainicio']);
		$datafim = insertDataOracle($filtros['datafim']);

		$this->setFiltrosExames($empresas, $empresas_id, $filtros['datainicio'], $filtros['datafim'], $tipoexame_id, $tipoexames);
		$filtro = $this->filtros;
		
		$colaboradores = Colaborador::join('colaboradorexames as exame', 'colaboradors.id', 'exame.colaborador_id')
		->join('tipoexames', 'tipoexames.id', 'exame.tipoexame_id')
		->join('empresas as empresa', 'empresa.id', 'colaboradors.empresa_id')
		->where('colaboradors.ativo', 1)
		->whereIn('colaboradors.empresa_id', $empresas_id);

		$colaboradores = $colaboradores->whereBetween("datavencimento", [$datainicio . ' 00:00:00', $datafim . ' 23:59:59']);

		if($tipoexame_id !== 0)
			$colaboradores = $colaboradores->whereIn('exame.tipoexame_id', $tipoexame_id);

		$colaboradores = $colaboradores->select('exame.id as colaboradorexame_id', 'tipoexames.descricao as exame', 'empresa.id as empresa_id', 
			'empresa.nome_informal as empresa', 'datavencimento', 'data',
			'tipoexames.id as exame_id' , 'colaboradors.nome as nome', 'colaboradors.id as colaborador_id')
		->orderBy('empresa.nome_informal')
		->orderBy('tipoexames.descricao')->orderBy('nome')->orderBy('colaborador_id')->get();
		
		$colaboradores = $this->montarTableExames($colaboradores, $tipoexame_id);

		$titulo = "Relatório de Exames de Colaboradores";

		if($pdf){
			$pdf = \App::make('dompdf.wrapper');
			$pdf->loadView('reports.colaboradores.exames.colaboradoresexamesPDF', compact('titulo', 'filtro', 'colaboradores'));
			return $pdf->stream();
		}

		return view('reports.colaboradores.exames.colaboradoresexamesPDF', compact('titulo', 'filtro', 'colaboradores'));
	}

	private function setFiltrosExames($empresas, $empresas_id, $datainicio, $datafim, $tipoexame_id, $tipoexames)
	{
		$this->filtros = "Período " . $datainicio . " a " . $datafim . ';';
		$arrayExames = $tipoexames->only($tipoexame_id)->toArray();
		$this->filtros .= " Tipos de Exames: ";
		$this->filtros .= count($arrayExames) > 0 ? implode(', ', $arrayExames) : 'todos' . ';';
		$this->filtros .= ' Empresa (s): ' . implode(', ', $empresas->only($empresas_id)->toArray()) . '.';
	}

	private function montarTableExames($colaboradores, $tipoexame_id)
	{
		$dados = [];
		$empresaAnteriror = null;
		$exameAnteriror = null;
		$count = 0;
		$colaboradorexames = Colaboradorexame::with('tipoexame')->select('id', 'colaborador_id', 'datavencimento')->whereIn('id', $colaboradores->pluck('colaboradorexame_id')->toArray())->get();
		$colaboradorexamesdata = Colaboradorexame::join('tipoexames as tipo', 'tipo.id', 'colaboradorexames.tipoexame_id')->where('admissional', 0)->select('datavencimento', 'colaborador_id', 'colaboradorexames.id')->whereIn('colaborador_id', $colaboradores->pluck('colaborador_id')->toArray())->get();
		foreach ($colaboradores as $colaborador) {
			$colaboradorexame = $colaboradorexames->where('colaborador_id', $colaborador->colaborador_id)->sortByDesc('datavencimento')->first();
			$dataExame = $colaboradorexamesdata->where('colaborador_id', $colaborador->colaborador_id)->sortByDesc('datavencimento')->first();
			if(($tipoexame_id == 0 && $colaboradorexame->id == $colaborador->colaboradorexame_id) || ($tipoexame_id != 0 && (is_null($dataExame) || (!is_null($dataExame) && strtotime($dataExame->datavencimento) <= strtotime($colaborador->datavencimento))))) {
				if (is_null($empresaAnteriror) || $empresaAnteriror != $colaborador->empresa_id) {
					$objColaborador = (object) [];
					$objColaborador->empresa = $colaborador->empresa;
					array_push($dados, $objColaborador);
				}
				if (is_null($exameAnteriror) || $exameAnteriror != $colaborador->exame_id || $empresaAnteriror != $colaborador->empresa_id) {
					$objColaborador = (object) [];
					$objColaborador->exame = $colaborador->exame;
					array_push($dados, $objColaborador);
				}
				$objColaborador = (object) [];
				$objColaborador->colaborador_id = $colaborador->colaborador_id;
				$objColaborador->nome = $colaborador->nome;
				$objColaborador->datavencimento = requestDataOracle($colaborador->datavencimento, false);
				$objColaborador->data = requestDataOracle($colaborador->data, false);
				array_push($dados, $objColaborador);
				$empresaAnteriror = $colaborador->empresa_id;
				$exameAnteriror = $colaborador->exame_id;
				$count++;
			}
		}
		if($count > 1) {
			$objColaborador = (object) [];
			$objColaborador->total = $count;
			array_push($dados, $objColaborador);
		}
		return $dados;
	}

	public function reportColaboradoresFaixaEtaria($pdf = false)
	{
		$empresas_id = $this->getEmpresaUser();
		$empresas = $this->getEmpresas($empresas_id);

		if(count($_GET) > 0)
			return $this->getFiltrosFaixaEtaria($_GET, $empresas_id, $empresas, $pdf);

		return view('reports.colaboradores.faixaetaria.colaboradoresfaixaetaria', compact('empresas'));
	}

	public function reportColaboradoresFaixaEtariaPDF()
	{
		return $this->reportColaboradoresFaixaEtaria(true);
	}
	private function getFiltrosFaixaEtaria($filtros, $empresas_id, $empresas, $pdf)
	{
		$empresas_id = $filtros['empresa_id'] == '' ? $empresas_id : explode(',', $filtros['empresa_id']);

		$datanascimento = Carbon::now()->subYears($filtros['idade'] + 1)->startOfDay()->toDateTimeString();

		$colaboradores = DB::table('colaboradors as colaborador')->join('empresas as empresa', 'empresa.id', 'colaborador.empresa_id')
		->join('colaboradorfamilias as familiar', 'colaborador.id', 'familiar.colaborador_id')
		->join('parentescos as parentesco', 'parentesco.id', 'familiar.parentesco_id')
		->whereIn('colaborador.empresa_id', $empresas_id)
		->where('familiar.datanascimento', '>=', $datanascimento)->select('colaborador.nome as colaborador', 'colaborador.id as colaborador_id', 'familiar.nome as familiar', 'familiar.datanascimento',
			'parentesco.descricao as parentesco', 'empresa.id as empresa_id', 'empresa.nome_informal as empresa')
		->orderBy('empresa.nome_informal')->orderBy('colaborador.nome')
		->orderBy('familiar.datanascimento', 'desc')->orderBy('familiar')->get();

		$colaboradores = $this->montarTableFaixaEtaria($colaboradores);

		$this->setFiltrosFaixaEtaria($empresas, $empresas_id, $filtros['idade']);
		$filtro = $this->filtros;
		$titulo = "Relatório de Familiares por Faixa Etária";
		
		if($pdf){
			$pdf = \App::make('dompdf.wrapper');
			$pdf->loadView('reports.colaboradores.faixaetaria.colaboradoresfaixaetariaPDF', compact('titulo', 'filtro', 'colaboradores'));
			return $pdf->stream();
		}
		
		return view('reports.colaboradores.faixaetaria.colaboradoresfaixaetariaPDF', compact('titulo', 'filtro', 'colaboradores'));
	}

	private function setFiltrosFaixaEtaria($empresas, $empresas_id, $idade)
	{
		$this->filtros = 'Idade até ' . $idade . ' anos;';
		$this->filtros .= ' Empresa (s): ' . implode(', ', $empresas->only($empresas_id)->toArray()) . '.';
	}

	private function montarTableFaixaEtaria($colaboradores)
	{
		$dados = [];
		$empresaAnteriror = null;
		$colaboradorAnteriror = null;
		$count = 0;
		foreach ($colaboradores as $colaborador) {
			if (is_null($empresaAnteriror) || $empresaAnteriror !== $colaborador->empresa_id) {
				$objColaborador = (object) ['empresa' => $colaborador->empresa];
				array_push($dados, $objColaborador);
			}
			if (is_null($colaboradorAnteriror) || $colaboradorAnteriror !== $colaborador->colaborador_id) {
				$objColaborador = (object) ['colaborador' => $colaborador->colaborador];
				array_push($dados, $objColaborador);
			}
			$objColaborador = (object) [];
			$objColaborador->familiar = $colaborador->familiar;
			$dtParsedCarbon = Carbon::parse($colaborador->datanascimento);
			Carbon::setLocale('pt-BR');
			$dia = Carbon::now()->diffForHumans($dtParsedCarbon, true);
			$objColaborador->idade = $dia;
			$objColaborador->parentesco = $colaborador->parentesco;
			array_push($dados, $objColaborador);
			$empresaAnteriror = $colaborador->empresa_id;
			$colaboradorAnteriror = $colaborador->colaborador_id;
			$count++;
			if (count($colaboradores) === $count ) {
				$objColaborador = (object) [];
				$objColaborador->totalGeral = count($colaboradores);
				array_push($dados, $objColaborador);
			}
		}
		return $dados;
	}

	public function reportFeriasVencimento($pdf = false)
	{
		$empresas_id = $this->getEmpresaUser();
		$empresas = $this->getEmpresas($empresas_id);

		if(count($_GET) > 0)
			return $this->getFiltrosFeriasVencimento($_GET, $empresas_id, $empresas, $pdf);

		return view('reports.colaboradores.ferias.colaboradorferiasvencimento', compact('empresas'));
	}

	public function reportFeriasVencimentoPDF()
	{
		return $this->reportFeriasVencimento(true);
	}

	private function getFiltrosFeriasVencimento($filtros, $empresas_id, $empresas, $pdf)
	{
		$empresas_id = $filtros['empresa_id'] == '' ? $empresas_id : explode(',', $filtros['empresa_id']);

		$datainicio = explode('/', $filtros['datainicio']);
		$datafim = explode('/', $filtros['datafim']);
		$datainicio = $datainicio[1] . '-' . $datainicio[0];
		$datafim = $datafim[1] . '-' . $datafim[0];

		$this->setFiltrosVencimento($empresas, $empresas_id, $filtros['datainicio'], $filtros['datafim']);
		$filtro = $this->filtros;
		$colaboradores = collect([]);

		if(strtotime(insertDataOracle($filtros['datafim'])) == false) {
			$erro = "A data final do filtro não é uma data válida";
		} else if (strtotime(insertDataOracle($filtros['datainicio'])) == false) { 
			$erro = "A data de início do filtro não é uma data válida";
		} else {	
			$erro = false;
			
			$colaboradores = DB::table('colaboradors')
								->join('empresas as empresa', 'empresa.id', 'colaboradors.empresa_id')
								->where('colaboradors.ativo', 1)
								->whereIn('colaboradors.empresa_id', $empresas_id)
								->select('empresa.id as empresa_id', 'empresa.nome_informal as empresa', 
									'colaboradors.nome as nome', 'colaboradors.id as colaborador_id', 'colaboradors.dataadmissao')
								->orderBy('empresa.nome_informal')
								->orderBy(DB::raw("to_char(dataadmissao, 'mm-dd')"), 'asc')->orderBy('nome')->orderBy('colaborador_id')
							->get();

			$ferias = DB::table('colaboradorferias as ferias')
						->whereIn('colaborador_id', $colaboradores->pluck('colaborador_id')->toArray())
						->whereIn('empresa_id', $empresas_id)
						->where('gozada', true)
						->select('datainicio', 'dias', 'gozada', 'colaborador_id')
						->orderBy('datainicio', 'desc')
					->get()->unique('colaborador_id');
		
			$colaboradores = $this->montarTableFerias($colaboradores, $ferias, insertDataOracle($filtros['datafim']), insertDataOracle($filtros['datainicio']));
		}

		$titulo = "Relatório de Férias de Colaboradores";

		if($pdf){
			$pdf = \App::make('dompdf.wrapper');
			$pdf->loadView('reports.colaboradores.ferias.colaboradorferiasvencimentoPDF', compact('titulo', 'filtro', 'colaboradores', 'erro'));
			return $pdf->stream();
		}

		return view('reports.colaboradores.ferias.colaboradorferiasvencimentoPDF', compact('titulo', 'filtro', 'colaboradores', 'erro'));
	}

	public function montarTableFerias($colaboradores, $ferias, $datafim, $datainicio)
	{
		$dados = collect([]);
		Carbon::setLocale('pt_BR');
		$count = 0;
		$countGeral = 0;
		$totalEmpresa = 0;
		foreach ($colaboradores as $colaborador) {
			$now = Carbon::now();
			$admissaoCarbon = Carbon::parse($colaborador->dataadmissao);
			$years = $now->diffInYears($admissaoCarbon);

			$obj = (object) [];
			$obj->proximasferias = $this->checaDataAdmissao($admissaoCarbon->copy(), $years, $datafim, $datainicio);

			$countGeral++;
			// dump($obj->proximasferias, strtotime($obj->proximasferias) >= strtotime($datainicio) && strtotime($obj->proximasferias) <= strtotime($datafim));
			if (strtotime($obj->proximasferias) >= strtotime($datainicio) && strtotime($obj->proximasferias) <= strtotime($datafim)) {
				$obj->proximasferias = $obj->proximasferias;
				$obj->colaborador = $colaborador->nome;
				$obj->empresa_id = $colaborador->empresa_id;
				$obj->colaborador_id = $colaborador->colaborador_id;
				$obj->empresa = $colaborador->empresa;
				$obj->dataadmissao = requestDataOracle($colaborador->dataadmissao, false);
				$ultimasFerias = $ferias->where('colaborador_id', $colaborador->colaborador_id)->first();
				$obj->mesessemferias = $now->diffInMonths(is_null($ultimasFerias) ? $admissaoCarbon : Carbon::parse($ultimasFerias->datainicio));
				if($obj->mesessemferias <= 0){
					$obj->mesessemferias = 'Programadas';
				} else {
					$obj->mesessemferias .=  ' meses';
					if($obj->mesessemferias < 12 && strtotime($obj->proximasferias->copy()->toDateString()) < strtotime(Carbon::now()->toDateString()))
						$obj->proximasferias = $obj->proximasferias->addYear();
				}
				$obj->limitemaximo = $obj->proximasferias->copy()->addMonths(11)->toDateString();
				$obj->proximasferias = $obj->proximasferias->toDateString();
				$obj->ultimasferias = is_null($ultimasFerias) ? 'Sem Férias' : requestDataOracle($ultimasFerias->datainicio, false);
				 // && substr($obj->mesessemferias, 0, strlen($obj->mesessemferias) - 6) > 0
				$obj->diasultimasferias = is_null($ultimasFerias) ? '' : $ultimasFerias->dias;
				$dados->push($obj);
			}
		}
// dd();
		$colabFinal = collect([]);
		$count = 0;

		foreach ($dados as $colaborador) {
			$count++;
			if ((isset($empresaAnterior) && $empresaAnterior != $colaborador->empresa_id) || $count == count($dados)){
				$empresa_id = !isset($empresaAnterior) ? $colaborador->empresa_id : $empresaAnterior;
				$dadosMerge = $dados->sortBy('proximasferias')->where('empresa_id', $empresa_id)->whereNotIn('colaborador_id', $colabFinal->pluck('colaborador_id')->toArray());
				$colabFinal = $colabFinal->merge($dadosMerge);
				if($totalEmpresa > 1 && $count != count($dados))
					$colabFinal->push($this->pushTotalEmpresa($totalEmpresa));
			}
			$hasColabInEmpresa = $dados->filter(function($c) use($colaborador) {
					return isset($c->colaborador) && $c->empresa_id == $colaborador->empresa_id;
				})->count() > 0;
			if ((!isset($empresaAnterior) || $empresaAnterior != $colaborador->empresa_id) && $hasColabInEmpresa) {
				$objEmpresa = (object) [];
				$objEmpresa->empresa = $colaborador->empresa;
				$objEmpresa->empresa_id = $colaborador->empresa_id;
				$objEmpresa->empresaExists = true;
				$colabFinal->push($objEmpresa);
				$totalEmpresa = 0;
			}
			$totalEmpresa++;
			$empresaAnterior = $colaborador->empresa_id;
			if ($count == count($dados)){
				$dadosMerge = $dados->sortBy('proximasferias')->where('empresa_id', $empresaAnterior)->whereNotIn('colaborador_id', 
								$colabFinal->pluck('colaborador_id')->toArray());
				$colabFinal = $colabFinal->merge($dadosMerge);
				if($totalEmpresa > 1)
					$colabFinal->push($this->pushTotalEmpresa($totalEmpresa));
			}
		}
		if(count($dados) == 1 )
			$dados = $colabFinal->reverse();
		else 
			$dados = $colabFinal;
		

		if($dados->count() > 0) {	
			$obj = (object) [];
			$obj->totalgeral = $dados->filter(function($c) {return isset($c->colaborador_id);})->count();
			$dados->push($obj);
		}
		return $dados;
	}

	private function pushTotalEmpresa($totalEmpresa)
	{
		$objTotal = (object) [];
		$objTotal->totalEmpresa = $totalEmpresa;
		return $objTotal;
	}

	private function checaDataAdmissao($data, $years, $datafim, $datainicio)
	{
		$data->addYears($years);

		if (strtotime($data->toDateString()) < strtotime($datainicio))
			$data->addYear();

		if (strtotime($data->toDateString()) > strtotime($datafim))
			$data->subYear();

		return $data;
	}
	public function setFiltrosVencimento($empresas, $empresas_id, $datainicio, $datafim)
	{
		$this->filtros = "Período " . $datainicio . " a " . $datafim . ';';
		$this->filtros .= ' Empresa (s): ' . implode(', ', $empresas->only($empresas_id)->toArray()) . '.';

	}

}