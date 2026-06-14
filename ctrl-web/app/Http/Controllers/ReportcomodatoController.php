<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Session;
use App\Empresa;
use App\Services\CarbonCustom as Carbon;
use App\Cliente;

class ReportcomodatoController extends Controller
{

	private $filtro;

	public function reportComodatosAtivos($pdf = false)
	{
		if (count($_GET) > 0) 
			return $this->getFiltersComodatosAtivos($_GET, $pdf);

		return view('reports.comodatos.ativos.comodatosativos');
	}

	public function reportComodatosAtivosPDF()
	{
		return $this->reportComodatosAtivos(true);
	}

	private function getFiltersComodatosAtivos($filtros, $pdf)
	{
		$ativo = $filtros['ativo'] == 'true' ? 1 : 0;
		$tipo = $filtros['tipo'];
		$todas_datas = $filtros['todas_datas'] == 'true' ? 1 : 0;
		$datafim =  insertDataOracle($filtros['datafim']);
		$tipos = [$tipo];
		if($tipo==3){
			$tipos = [0,1];
		}
		if($todas_datas == 0){
			$comodatos = DB::table('comodatos as comodato')
			->join('clientes as cliente', 'cliente.id', 'comodato.cliente_id')
			->join('comodatoitems', 'comodatoitems.comodato_id', 'comodato.id')
			->join('produtos as produto', 'produto.id', 'comodatoitems.produto_id')
			->where('comodato.empresa_id', Session::get('empresa_padrao')->id)
			->whereIn('comodato.tipo', $tipos)
			->where('comodato.ativo', $ativo)
			->where(DB::raw("TO_CHAR(comodato.datavencimento, 'YYYY-MM-DD')"), '<=', $datafim)
			->select('comodato.id as comodato_id', 'comodato.datacontrato as datacomodato', DB::raw("TO_CHAR(comodato.datavencimento, 'YYYY-MM-DD') as vencimento"), 
				'cliente.nome as cliente', 'comodato.cliente_id as cliente_id', 'produto.descricao as produto', 'comodatoitems.id as comodatoitems_id', 'comodatoitems.quantidade')
			->orderBy('vencimento')->orderBy('cliente')->orderBy('comodato_id')->get();
		} else {
			$comodatos = DB::table('comodatos as comodato')
			->join('clientes as cliente', 'cliente.id', 'comodato.cliente_id')
			->join('comodatoitems', 'comodatoitems.comodato_id', 'comodato.id')
			->join('produtos as produto', 'produto.id', 'comodatoitems.produto_id')
			->where('comodato.empresa_id', Session::get('empresa_padrao')->id)
			->whereIn('comodato.tipo', $tipos)
			->where('comodato.ativo', $ativo)
			->select('comodato.id as comodato_id', 'comodato.datacontrato as datacomodato', DB::raw("TO_CHAR(comodato.datavencimento, 'YYYY-MM-DD') as vencimento"), 
				'cliente.nome as cliente', 'comodato.cliente_id as cliente_id', 'produto.descricao as produto', 'comodatoitems.id as comodatoitems_id', 'comodatoitems.quantidade')
			->orderBy('vencimento')->orderBy('cliente')->orderBy('comodato_id')->get();
			}

		$comodatos = $this->montarTableAtivos($comodatos);
		$titulo = "Relatório de Comodatos ";
		$titulo .= $ativo == 1 ? 'Ativos' : 'Inativos';

		$this->setFiltersAtivo($ativo, $tipo, $filtros['datafim'], $todas_datas);
		$filtro = $this->filtro;
		
		if($pdf) {
			$pdf = \App::make('dompdf.wrapper');
			$pdf->loadView('reports.comodatos.ativos.comodatosativosPDF', compact('comodatos', 'titulo', 'filtro'));
			return $pdf->stream();
		}
		
		return view('reports.comodatos.ativos.comodatosativosPDF', compact('comodatos', 'titulo', 'filtro'));
	}

	public function setFiltersAtivo($ativo, $tipo, $datafim, $todas_datas)
	{
		if($todas_datas == 0){
			$this->filtro = $ativo == 1 ? 'Vencimento até ' : 'Inativos até ';
			$this->filtro .= $datafim . '; Tipo: ';
		} else {
			$this->filtro = 'Tipo: ';
		}
		if($tipo == 0)
			$this->filtro .= ' Revenda para Cliente PJ.';
		elseif($tipo == 1)
			$this->filtro .= ' Revenda para Cliente PF.';
		elseif($tipo == 3)
			$this->filtro .= ' Revenda para Cliente.';
		else
			$this->filtro .= ' Distribuidora para Revenda.';
	}

	public function montarTableAtivos($comodatos)
	{
		$dados = [];
		$vencimentoAnterior = null;
		$clienteAnterior = null;
		$count = 0;
		foreach ($comodatos as $comodato) {
			$comodatoFromVcto = $comodatos->where('vencimento', $vencimentoAnterior);
			if(!is_null($vencimentoAnterior) && $vencimentoAnterior != $comodato->vencimento && count($comodatoFromVcto->unique('comodatoitems_id')) > 1) {
				$objComodato = (object) [];
				$objComodato->totalData = $comodatoFromVcto->sum('quantidade');
				array_push($dados, $objComodato);
			}
			if(is_null($vencimentoAnterior) || $vencimentoAnterior != $comodato->vencimento){
				$objComodato = (object) [];
				$objComodato->vencimento = requestDataOracle($comodato->vencimento, false);
				array_push($dados, $objComodato);
			}
			if(is_null($clienteAnterior) || $clienteAnterior != $comodato->cliente_id || $vencimentoAnterior != $comodato->vencimento){
				$objComodato = (object) [];
				$objComodato->cliente = $comodato->cliente;
				array_push($dados, $objComodato);
			}
			$objComodato = (object) [];
			$objComodato->produto = $comodato->produto;
			$objComodato->datacomodato = requestDataOracle($comodato->datacomodato, false);
			$objComodato->quantidade = $comodato->quantidade;
			$objComodato->comodato_id = $comodato->comodato_id;
			array_push($dados, $objComodato);
			$count++;
			if ($count == count($comodatos)) {
				$comodatoFromVcto = $comodatos->where('vencimento', $comodato->vencimento);
				if(count($comodatoFromVcto->unique('comodatoitems_id')) > 1) {	
					$objComodato = (object) [];
					$objComodato->totalData = $comodatoFromVcto->sum('quantidade');
					array_push($dados, $objComodato);
				}
				$objComodato = (object) [];
				$objComodato->totalGeral = $comodatos->sum('quantidade');
				array_push($dados, $objComodato);
			}
			$clienteAnterior = $comodato->cliente_id;
			$vencimentoAnterior = $comodato->vencimento;
		}
		return $dados;
	}

	public function reportControleComodatos($pdf = false)
	{
		if (count($_GET) > 0) 
			return $this->getFiltersControleComodatos($_GET, $pdf);

		return view('reports.comodatos.controle.controlecomodatos');
	}

	public function reportControleComodatosPDF()
	{
		return $this->reportControleComodatos(true);
	}

	private function getFiltersControleComodatos($filtros, $pdf)
	{
		$empresa_id = Session::get("empresa_padrao")->id;

		$comodatos = DB::table('comodatoitems as item')
		->join('comodatos as comodato', 'comodato.id', 'item.comodato_id')
		->where('comodato.empresa_id', $empresa_id)
		->where('comodato.ativo', 1)
		->select('item.produto_id as produto_id', DB::raw('sum(item.quantidade) quantidadecomodato'), 'comodato.tipo')
		->orderBy('item.produto_id')
		->groupBy('item.produto_id', 'comodato.tipo')
		->get();

		$estoqueTotal = DB::table('estoquesetors as estoque')
		->join('produtos as produto', 'produto.id', 'estoque.produto_id')
		->join('setors as setor', 'setor.id', 'estoque.setor_id')
		->where('setor.empresa_id', $empresa_id)
		->whereIn('produto_id', $comodatos->pluck('produto_id')->toArray())
		->select('produto_id', DB::raw('sum(quantidade) quantidadeestoque'), 'produto.descricao')
		->orderBy('produto_id')
		->groupBy('produto_id', 'produto.descricao')
		->get();

		$comodatosView = collect([]);
		foreach ($estoqueTotal as $estoque) {
			$obj = (object) [];
			$itemComodato = $comodatos->where('produto_id', $estoque->produto_id);
			$obj->produto_id = $estoque->produto_id;
			$obj->produto = $estoque->descricao;
			$obj->quantidaderecebido = $itemComodato->where('tipo', 2)->sum('quantidadecomodato');
			$obj->quantidadeenviado = $itemComodato->whereIn('tipo', [0,1])->sum('quantidadecomodato');
			$obj->quantidadeestoque = $estoque->quantidadeestoque;
			$obj->estoqueproprio = $obj->quantidadeestoque - $obj->quantidaderecebido + $obj->quantidadeenviado;
			$comodatosView->push($obj);
		}

		$obj = (object) [];
		$obj->totalrecebido = $comodatosView->sum('quantidaderecebido');
		$obj->totalenviado = $comodatosView->sum('quantidadeenviado');
		$obj->totalestoqueproprio = $comodatosView->sum('estoqueproprio');
		$obj->totalestoque = $comodatosView->sum('quantidadeestoque');
		$comodatosView->push($obj);
		
		$comodatos = $comodatosView;

		$titulo = "Relatório de Controle de Comodatos";
		$filtro = "Empresa: " . Empresa::where('id', $empresa_id)->pluck('nome_informal')->first();

		if($pdf) {
			$pdf = \App::make('dompdf.wrapper');
			$pdf->loadView('reports.comodatos.controle.controlecomodatosPDF', compact('comodatos', 'titulo', 'filtro'));
			return $pdf->stream();
		}
		
		return view('reports.comodatos.controle.controlecomodatosPDF', compact('comodatos', 'titulo', 'filtro'));
	}


	public function reportGiroComodatos($pdf = false)
	{
		if (count($_GET) > 0) 
			return $this->getFiltersGiroComodatos($_GET, $pdf);

		return view('reports.comodatos.giro.girocomodatos');
	}

	public function reportGiroComodatosPDF()
	{
		return $this->reportGiroComodatos(true);
	}

	private function getFiltersGiroComodatos($filtros, $pdf)
	{
		$tipo = $filtros['tipo'];
		$datafim = insertDataOracle($filtros['datafim']);
		$datainicio = insertDataOracle($filtros['datainicio']);
		$cliente_id = $filtros['cliente_id'];
		$maior = $filtros['maior'];
		$menor = $filtros['menor'];
		$empresa_id = Session::get('empresa_padrao')->id;

		$comodatos = DB::table('comodatos as comodato')
		->join('comodatoitems as item', 'item.comodato_id', 'comodato.id')
		->join('clientes as cliente', 'cliente.id', 'comodato.cliente_id')
		->join('produtos as produto', 'produto.id', 'item.produto_id')
		->select('produto.descricao as produto', 'produto.id as produto_id', 'cliente.id as cliente_id', 
			'cliente.nome as cliente', 'comodato.id as comodato_id', 'item.quantidade')
		->where('comodato.tipo', $tipo)
		->where('comodato.ativo', 1)
		->where('comodato.empresa_id', $empresa_id)
		->whereBetween('comodato.datacontrato', [$datainicio . ' 00:00:00', $datafim . ' 23:59:59']);

		if(strlen($cliente_id) > 0)
			$comodatos->where('cliente_id', $cliente_id);

		$comodatos = $comodatos->orderBy('cliente.nome')->orderBy('produto.descricao')->get();

		if($tipo == 2) {
			$pedidosOrCompras = DB::table('nfrecebidas as nf')
							->select('nf.id as nf_id', 'item.qcom as produto_id', 'nf.cliente_id', 'produto.produtoretornavel_id as vasilhame_id', 'item.qcom as quantidade')
							->join('nfrecebidaitems as item', 'item.NFRECEBIDA_ID', 'nf.id')
							->join('produtos as produto', 'produto.id', 'item.cprod')
							->where('nf.empresa_id', $empresa_id)
							->whereIn('nf.cliente_id', $comodatos->pluck('cliente_id')->toArray())
							->whereIn('produto.produtoretornavel_id', $comodatos->pluck('produto_id')->toArray())
							->whereIn('nf.nfoperacao_id', DB::table('nfoperacaos')->whereIn('cfop', [1403])->pluck('id')->toArray())
						->get();
		} else {
			$pedidosOrCompras = DB::table('pedidos as pedido')
							->select('pedido.id as pedido_id', 'item.produto_id', 'pedido.cliente_id', 'produto.produtoretornavel_id as vasilhame_id', 'item.quantidade')
							->join('pedidoitems as item', 'item.pedido_id', 'pedido.id')
							->join('produtos as produto', 'produto.id', 'item.produto_id')
							->where('pedido.empresa_id', $empresa_id)
							->whereIn('pedido.cliente_id', $comodatos->pluck('cliente_id')->toArray())
							->whereIn('produto.produtoretornavel_id', $comodatos->pluck('produto_id')->toArray())
							->whereIn('pedido.pedidosituacao_id', DB::table('pedidosituacaos')->where('fechadoconcluido', 1)->pluck('id'))
						->get();
		}
		$mediaMeses = (Carbon::parse($datainicio)->diffInDays(Carbon::parse($datafim)) + 1) / 30.5;
		$comodatos = $this->montarTableGiro($comodatos, $pedidosOrCompras, $mediaMeses, $maior, $menor);
		$titulo = "Relatório de Giro de Comodatos";

		$this->setFiltersGiro($cliente_id, $tipo, $filtros['datafim'], $filtros['datainicio'], $mediaMeses);
		$filtro = $this->filtro;

		if($pdf) {
			$pdf = \App::make('dompdf.wrapper');
			$pdf->loadView('reports.comodatos.giro.girocomodatosPDF', compact('comodatos', 'titulo', 'filtro'));
			return $pdf->stream();
		}
		
		return view('reports.comodatos.giro.girocomodatosPDF', compact('comodatos', 'titulo', 'filtro'));
	}
	private function setFiltersGiro($cliente_id, $tipo, $datafim, $datainicio, $mediaMeses)
	{
		$this->filtro = 'Período de ' . $datainicio . ' a ' . $datafim .';';
		if(strlen($cliente_id) > 0)
			$this->filtro .= ' Cliente: ' . Cliente::where('id', $cliente_id)->select('nome')->first()->nome . ';';
		$this->filtro .= ' Tipo:';
		if($tipo == 0)
			$this->filtro .= ' Distribuidora para Cliente PJ.';
		elseif($tipo == 1)
			$this->filtro .= ' Distribuidora para Cliente PF.';
		else
			$this->filtro .= ' Fornecedor para Distribuidora.';

	}
	public function montarTableGiro($comodatos, $pedidosOrCompras, $mediaMeses, $maior, $menor)
	{
		$dados = collect([]);
		$giros = collect([]);
		$clienteAnterior = null;
		$produtoAnterior = null;
		$count = 0;
		$totalCliente = 0;
		$maior = empty($maior) ? -1 : (float) $maior;
		$menor = empty($menor) ? 999999999999999999 : (float) $menor;
		foreach ($comodatos as $comodato) {
			if(!is_null($clienteAnterior) && $clienteAnterior != $comodato->cliente_id){
				$objTotalCliente = $this->createObjTotalCliente($comodatos, $dados, $giros, $clienteAnterior, $mediaMeses, $comodato->produto_id);
				if($objTotalCliente->mediaGiroCliente > $maior && $objTotalCliente->mediaGiroCliente < $menor) {
					if($totalCliente > 1)
						$dados->push($objTotalCliente);
				} else {
					$dados = $dados->whereNotIn('cliente_id', [$clienteAnterior]);
				}
				$totalCliente = 0;
			}

			if(is_null($clienteAnterior) || $clienteAnterior != $comodato->cliente_id) {
				$objGiro = (object) [];
				$objGiro->cliente = $comodato->cliente;
				$objGiro->cliente_id = $comodato->cliente_id;
				$dados->push($objGiro);
				$totalCliente = 0;
			}
			if(is_null($produtoAnterior) || $clienteAnterior != $comodato->cliente_id || $produtoAnterior != $comodato->produto_id) {
				$objGiro = (object) [];
				$objGiro->produto_id = $comodato->produto_id;
				$objGiro->cliente_id = $comodato->cliente_id;
				$objGiro->produto = $comodato->produto;
				$qdeComodatado = $comodatos->where('produto_id', $comodato->produto_id)->where('cliente_id', $comodato->cliente_id)->sum('quantidade');
				$objGiro->qdecomodatado = $qdeComodatado;
				// $qdeComprado = $pedidosOrCompras->where('cliente_id', $comodato->cliente_id)->where('vasilhame_id', $comodato->produto_id);
				$qdeComprado = $pedidosOrCompras->where('vasilhame_id', $comodato->produto_id)->where('cliente_id', $comodato->cliente_id);
				$qdeComprado = $qdeComprado->sum('quantidade');
				$objGiro->qdecomprado = $qdeComprado;
				$objGiro->giro = round($qdeComprado / $qdeComodatado / $mediaMeses, 2);
				$dados->push($objGiro);
				$giros->push((object) ['giro' => $objGiro->giro, 'cliente_id' => $comodato->cliente_id]);
			}

			$produtoAnterior = $comodato->produto_id;
			$clienteAnterior = $comodato->cliente_id;
			$count++;
			$totalCliente++;
			if(count($comodatos) == $count){
				$objTotalCliente = $this->createObjTotalCliente($comodatos, $dados, $giros, $clienteAnterior, $mediaMeses, $comodato->produto_id);
				if($objTotalCliente->mediaGiroCliente > $maior && $objTotalCliente->mediaGiroCliente < $menor) {
					if($totalCliente > 1)
						$dados->push($objTotalCliente);
				} else {
					$dados = $dados->whereNotIn('cliente_id' , [$clienteAnterior]);
				}
				$totalCliente = 0;
			}
		}
		if(count($dados) > 0){
			$objGiro = (object) [];
			$objGiro->totalComodatado = $dados->sum('qdecomodatado') > 1 ? $dados->sum('qdecomodatado') : 1;
			$objGiro->totalComprado = $dados->sum('qdecomprado');
			$objGiro->mediaGiro = round($objGiro->totalComprado / $objGiro->totalComodatado / $mediaMeses, 2);
			$dados->push($objGiro);
		}
		return $dados;
	}

	private function createObjTotalCliente($comodatos, $dados, $giros, $cliente_id, $mediaMeses, $produto_id)
	{
		$objGiroTotal = (object) [];
		$objGiroTotal->totalCliente = count($comodatos->where('cliente_id', $cliente_id));
		$objGiroTotal->totalComodatadoCliente = $comodatos->where('cliente_id', $cliente_id)->sum('quantidade');
		$objGiroTotal->totalCompradoCliente = $dados->where('cliente_id', $cliente_id)->sum('qdecomprado');
		$objGiroTotal->mediaGiroCliente = round($objGiroTotal->totalCompradoCliente / $objGiroTotal->totalComodatadoCliente / $mediaMeses, 2);
		return $objGiroTotal;
	}

}