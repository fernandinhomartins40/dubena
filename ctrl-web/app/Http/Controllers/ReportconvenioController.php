<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Cliente;
use Session;
use DB;

class ReportconvenioController extends Controller
{
	private $filtro;

	public function reportConvenioFuncionario($pdf = false)
	{    
		$convenios = Cliente::where([
			['empresa_id', Session::get('empresa_padrao')->id],
			['convenioativo', 1],
			['ativo', 1]
			])->orderBy('nome')->pluck('nome', 'id')->prepend('Selecione', '');

		if(count($_GET) > 0)
			return $this->getFiltrosFuncionario($_GET, $pdf);

		return view('reports.convenio.funcionarios.conveniofuncionarios', compact('convenios'));
	}

	public function reportConvenio($pdf = false)
	{    
		if(count($_GET) > 0)
			return $this->getFiltrosConvenio($_GET, $pdf);

		return view('reports.convenio.conveniado.convenio');
	}

	private function getFiltrosConvenio($filtros, $pdf)
	{
		$status = $filtros['status'] !== '' ?$filtros['status'] : 2;

		$clientes = DB::table('clientes as cliente')->join('clienteconvenios as convenio', 'convenio.cliente_id', 'cliente.id')
		->where([
			['cliente.empresa_id', Session::get('empresa_padrao')->id],
			['cliente.ativo', 1]
			]);

		if ($status != 2) 
			$clientes = $clientes->where('cliente.convenioativo', $status);

		$clientes = $clientes->select('cliente.id as cliente_id', 'cliente.nome as nome', 'convenio.diafechamento as entrega', 'convenio.diavencimento as recebimento', 'cliente.convenioativo as situacao', 'convenio.comissao as desconto')->orderBy('cliente.nome')->orderBy('cliente.id')->get();  

		$this->setFiltersReportConvenio(null, false, $status);
		$filtro = $this->filtro;

		$clientes = $this->montarTableConvenio($clientes);

		$titulo = 'Relatório de Convênios';

		if ($pdf) {
			$pdf = \App::make('dompdf.wrapper');
			$pdf->setPaper('A4', 'landscape');
			$pdf->loadView('reports.convenio.conveniado.convenioPDF', compact('titulo', 'clientes', 'filtro'));
			return $pdf->stream();
		}

		return view('reports.convenio.conveniado.convenioPDF', compact('titulo', 'clientes', 'filtro'));
	}
	private function montarTableConvenio($clientes)
	{
		$dados = [];
		$count = 0;
		foreach ($clientes as $cliente) {
			$objCliente = (object) [];
			$objCliente->cliente_id = $cliente->cliente_id;
			$objCliente->nome = $cliente->nome;
			$objCliente->entrega = $cliente->entrega;
			$objCliente->nome = $cliente->nome;
			$telefones = DB::table('clientetelefones')->where('cliente_id', $cliente->cliente_id)->limit(2)->pluck('telefone')->toArray();
			$telefones = count($telefones) > 0 ? str_replace('-', '', implode(', ', $telefones)) : '';
			$telefones = str_replace('(', '', $telefones);
			$telefones = str_replace(')', '', $telefones);
			$objCliente->telefones = $telefones;
			$objCliente->recebimento = $cliente->recebimento;
			$objCliente->situacao = $cliente->situacao;
			$objCliente->desconto = requestPercentualOracle($cliente->desconto);
			array_push($dados, $objCliente);
			$count++;
			if(count($clientes) == $count){
				$objCliente = (object) [];
				$objCliente->total = $count;
				array_push($dados, $objCliente);
			}
		}	
		// dd($dados);
		return $dados;
	}
	private function getFiltrosFuncionario($filtros, $pdf)
	{
		$convenio_id = $filtros['convenio_id'] !== '' ?$filtros['convenio_id'] : 0;

		$clientes = DB::table('clientes as cliente')->join('bairros', 'bairros.id', 'cliente.bairro_id')
		->join('clientes as convenio', 'convenio.id', 'cliente.convenio_id')
		->leftJoin('setors as setor', 'setor.id', 'cliente.setor_id')
		->join('ruas', 'ruas.id', 'cliente.rua_id')
		->where([
			['cliente.convenio', 1], 
			['cliente.empresa_id', Session::get('empresa_padrao')->id],
			['cliente.ativo', 1],
			['convenio.convenioativo', 1]
			]);

		if ($convenio_id != 0) 
			$clientes = $clientes->where('cliente.convenio_id', $convenio_id);
		
		$clientes = $clientes->select('cliente.id as cliente_id', 'cliente.nome as nome', 'ruas.descricao as rua', 'bairros.descricao as bairro', 'cliente.numero', 'convenio.nome as empresaconvenio', 'convenio.id as empresaconvenio_id', 'setor.descricao as setor', 'setor.id as setor_id')->orderBy('convenio.nome')->orderBy('convenio.id')->orderBy('setor.descricao')->orderBy('setor.id')->orderBy('cliente.nome')->orderBy('cliente.id')->get();  

		$this->setFiltersReportConvenio($convenio_id);
		$filtro = $this->filtro;

		$clientes = $this->montarTableConvenioFuncionarios($clientes);


		$titulo = 'Relatório de Funcionários de Convênio';

		if ($pdf) {
			$pdf = \App::make('dompdf.wrapper');
			$geraPdf = true;
			$pdf->loadView('reports.convenio.funcionarios.conveniofuncionariosPDF', compact('titulo', 'clientes', 'filtro', 'geraPdf'));
			return $pdf->stream();
		}

		return view('reports.convenio.funcionarios.conveniofuncionariosPDF', compact('titulo', 'clientes', 'filtro'));
	}

	private function montarTableConvenioFuncionarios($clientes)
	{
		$dados = [];
		$convenioAnterior = null;
		$setorAnterior = 'none';
		$totalSetor = null;
		$totalSetoresEmpresa = 0;
		$totalConvenio = null;
		$count = 0;
		foreach ($clientes as $cliente) {
			if(!is_null($totalSetor) && $setorAnterior != $cliente->setor_id || $convenioAnterior != $cliente->empresaconvenio_id) {
				if($totalSetor > 1){	
					$objCliente = (object) [];
					$objCliente->totalSetor = $totalSetor;
					array_push($dados, $objCliente);
				}
				$totalSetor = 0;
			}
			if(!is_null($totalConvenio) && $convenioAnterior != $cliente->empresaconvenio_id) {
				if ($totalSetoresEmpresa > 1) {
					$objCliente = (object) [];
					$objCliente->totalConvenio = $totalConvenio;
					array_push($dados, $objCliente);
				}
				$totalSetoresEmpresa = 0;
				$totalSetor = 0;
				$totalConvenio = 0;
			}
			if(is_null($convenioAnterior) || $convenioAnterior !== $cliente->empresaconvenio_id) {
				$objCliente = (object) [];
				$objCliente->empresaconvenio = $cliente->empresaconvenio;
				$objCliente->empresaconvenio_id = $cliente->empresaconvenio_id;
				array_push($dados, $objCliente);
				$totalConvenio = 0;
			}
			if($setorAnterior == 'none' || $setorAnterior != $cliente->setor_id || $convenioAnterior !== $cliente->empresaconvenio_id) {
				$objCliente = (object) [];
				$objCliente->setor = is_null($cliente->setor) ? 'Sem setor' : $cliente->setor;
				$objCliente->setor_id = $cliente->setor_id;
				array_push($dados, $objCliente);
			}
			$objCliente = (object) [];
			$objCliente->cliente_id = $cliente->cliente_id;
			$objCliente->nome = $cliente->nome;
			$objCliente->endereco = $cliente->bairro . ', ' . rtrim($cliente->rua). ', ' .  $cliente->numero;
			array_push($dados, $objCliente);
			$convenioAnterior = $cliente->empresaconvenio_id;
			$setorAnterior = $cliente->setor_id;
			$totalConvenio++;
			$totalSetor++;
			$totalSetoresEmpresa++;
			$count++;
			if(count($clientes) == $count) {
				if($totalSetor > 1){	
					$objCliente = (object) [];
					$objCliente->totalSetor = $totalSetor;
					array_push($dados, $objCliente);
				}
				if($totalSetor > 1){	
					$objCliente = (object) [];
					$objCliente->totalConvenio = $totalConvenio;
					array_push($dados, $objCliente);
				}

				$objCliente = (object) [];
				$objCliente->totalGeral = $count;
				array_push($dados, $objCliente);
			}
		}
		return $dados;
	}

	private function setFiltersReportConvenio($convenio_id, $funcionario = true, $status = null){
		if($funcionario){	
			$this->filtro .= 'Convênio: ';
			$this->filtro .= $convenio_id != 0 ? Cliente::where('id', $convenio_id)->pluck('nome')->first() . ';' : ' todos.';
		} else {
			$this->filtro .= 'Situação: ';
			if($status == 0)
				$this->filtro .= ' Inativo.';
			else if ($status == 1)
				$this->filtro .= ' Ativo.';
			else
				$this->filtro .= ' Ambos.';
		}
	}

	public function reportConvenioFuncionarioPDF()
	{    
		return $this->reportConvenioFuncionario(true);
	}

	public function reportConvenioPDF()
	{    
		return $this->reportConvenio(true);
	}
}
