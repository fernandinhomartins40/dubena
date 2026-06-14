<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\User;
use App\Setor;
use App\Services\CarbonCustom as Carbon;
use App\Empresaconfig;
use App\Configuracoesgerais;
use Illuminate\Http\Request;

class ReportEntregasController extends Controller
{

	public function indexMapaEntregas()
	{
		$setores = Setor::where('empresa_id', Session::get('empresa_padrao')->id)->select('descricao', 'id')->orderby('descricao')->get()->pluck('descricao', 'id')->prepend('Selecione', '');
		return $this->returnReport('reports.entregas.mapaentregas.mapaentregas', compact('setores'));
	}

	public function indexMapaEntregasPendentes()
	{
		return $this->returnReport('reports.entregas.mapaentregas.mapaentregaspendentes');
	}

	public function indexMapaEntregasCoordenadas()
	{
		return $this->returnReport('reports.entregas.painelentregas.painelentregas');
	}

	public function indexMapaEntregasAtrasadas()
	{
		return $this->returnReport('reports.entregas.painelentregas.painelatrasos');
	}

	public function indexTempoEntregas()
	{
		$years = DB::select("select to_char(add_months(trunc(sysdate, 'yyyy'), -12*(level -1)), 'yyyy') ano from dual connect by level <= 3");
		$years2 = [];
		foreach ($years as $year) {
			$years2[$year->ano] = $year->ano;
		}
		$produtos = ProdutoController::tipoGlp();
		return $this->returnReport('reports.entregas.tempoentregas.tempoentregas', array('years' => $years2, 'produtos' => $produtos));
	}

	private function returnReport($url, $parsExtra = [])
	{
		$config = Empresaconfig::where('empresa_id', Session::get('empresa_padrao')->id)->select('keygooglemaps')->get()->first();
		if(is_null($config) || (!is_null($config) && is_null($config->keygooglemaps)))
			$config = Configuracoesgerais::select('keygooglemaps')->first();

		if(!is_null($config) && !is_null($config->keygooglemaps)){
			$parsExtra['keygooglemaps'] = $config->keygooglemaps;
			return view($url, $parsExtra);
		} else {
			return redirect('home')->withMessageDanger('Não foi encontrada nenhuma chave do Google Maps para gerar este relatório.');
		}
	}

	// Mapa de Metas x Vendas
	public function indexMapaMetas()
	{
		$empresas_id = User::find(\Auth::user()->id)->empresas()->pluck('id')->toArray();
		$user_id = \Auth::user()->id;
		$grupo = collect(DB::select($this->getQuery('grupo',$user_id)))->pluck('descricao','id')->prepend('Selecione','');
		$regionais = collect(DB::select($this->getQuery('regional',$user_id)))->pluck('descricao','id')->prepend('Selecione','');
		$years = $this->getTime('ano');
		$meses = $this->getTime('mes');
		$produtos = ProdutoController::tipoGlp();
		$keygooglemaps = $this->getKey();

		return view('reports.vendas.metas.index')->with(compact('grupo','regionais','years','meses','produtos','keygooglemaps'));
	}

	public function filtroSetores()
	{
		$filtro = $_GET;
		$user_id = \Auth::user()->id;
		$grupo = $filtro["grupo"];
		$regiao = $filtro["regiao"] == "" ? -1 : $filtro["regiao"];
		$empresa_id = $filtro["empresa"] == "" ? null : $filtro["empresa"];
		$produto = $filtro["produto"];
		$ano = $filtro["ano"];
		$mes = $filtro["mes"];
		$hub = $filtro["hub"];
		$bairro = isset($filtro["bairro"]) ? str_replace('_',' ',$filtro["bairro"]) : null;
		$setor_id = isset($filtro["setor"]) && $filtro["setor"] != "" ? $filtro["setor"] : null;

		switch($hub){
			case '1':
				$which = 'metas';
				break;
			case '2':
				$which = 'bairros';
				break;
			case '3':
				$which = 'ruas';
				break;
			case '4':
				$which = 'bairrosSetor';
				break;
			default:
				$which = 'setores';
				break;
		}
		$query = $this->getQuery($which,$user_id, $grupo, $regiao, $empresa_id, $produto, $ano, $mes, $bairro, $setor_id);
		$metas = collect(DB::select($query));
		return $metas;
	}

	private function getTime($which)
	{
		$anos = [];
		for ($y=0; $y < 3; $y++) { 
			$year = Carbon::now()->year - $y;
			$anos[$year] = $year;
		}
		
		$meses = [];
		for ($m=1; $m < 13; $m++) {
			$mes = getDescricaoMes($m);
			$meses[$m] = $mes;
		}
		
		return $which == 'ano' ? $anos : $meses;
	}

	private function getKey()
	{
		$config = Empresaconfig::where('empresa_id', Session::get('empresa_padrao')->id)->select('keygooglemaps')->get()->first();

		if(is_null($config) || (!is_null($config) && is_null($config->keygooglemaps)))
			$config = Configuracoesgerais::select('keygooglemaps')->first();

		if(!is_null($config) && !is_null($config->keygooglemaps)){
			$key = $config->keygooglemaps;
			return $key;
		} 
		return null;
	}

	private function getQuery($which, $user_id, $grupo = null, $regiao = null, $empresa_id = null,
		$produto = null, $ano = null, $mes = null, $bairro = null, $setor_id = null)
	{
		$queryGrupos = "select id, descricao ".
			"from empresas_grupos ".
			"where id in " .
			"(select grupo_id from empresas " .
			"inner join empresa_user empu on empu.empresa_id = empresas.id where user_id = $user_id)";
		
		$queryRegional = "select reg.id, reg.descricao ".
			"from empresas " .
			"inner join regiaos reg on empresas.regiao_id = reg.id " .
			"inner join empresa_user emp on emp.empresa_id = empresas.id " .
			"where user_id = $user_id";

		//a soma do percentual está na validação do js, mas não removi daqui para que a consulta não apresente problemas
		$querySetores = "select setor_id, cidade, uf, setor, mes, " .
				"longitude, latitude, ".
				"sum(items) / (case when sum(meta) = 0 then 1 else sum(meta) end) * 100 as percentage, ".
				"sum(meta) as meta, ".
				"sum(items) as quant, ".
				"(".
				"case when $produto = 1 then cast('GLP P02' as varchar(7)) ".
				"else case when $produto = 2 then cast('GLP P08' as varchar(7)) ".
				 "else case when $produto = 3 then cast('GLP P13' as varchar(7)) ".
				  "else case when $produto = 4 then cast('GLP P20' as varchar(7)) ".
				   "else case when $produto = 5 then cast('GLP P45' as varchar(7)) ".
					"else cast('GLP P90' as varchar(7)) ".
					"end ".
			       "end ".
				  "end ".
				 "end ".
				"end ".
				") as produto,  (longitude || latitude) as latlong " .
				"from( ".
					"select setor.id as setor_id, cidades.descricao as cidade, setor.uf as uf, setor.descricao as setor, to_char(datameta,'mm/yyyy') as mes, ".
					"setor.latitude, setor.longitude, meta.quantidade as meta, 0 as items ".
					"from metavendas meta ".
					"inner join empresas on meta.empresa_id = empresas.id ".
					"inner join setors setor on meta.setor_id = setor.id ".
					"inner join produtos on meta.produto_id = produtos.id ".
					"inner join cidades on setor.cidade_id = cidades.id ".
					"where empresas.grupo_id = $grupo and (empresas.regiao_id = $regiao or $regiao = -1) and ";
					if(is_null($empresa_id))
						$querySetores .= "empresas.id in (select empresa_id from empresa_user where user_id = $user_id) and ";
					else
						$querySetores .= "empresas.id = $empresa_id and ";					
					$querySetores .= "extract(year from datameta) = $ano and extract(month from datameta) = $mes and ".
					"produtos.tipo_glp = $produto ".
					
					"union all ".
					
					"select setor.id as setor_id, cidades.descricao as cidade, setor.uf, setor.descricao as setor, to_char(datahoraprevisaoentrega,'mm/yyyy') as mes, ".
					"setor.latitude, setor.longitude, 0 as meta, sum(items.quantidade) as items ".
					"from pedidos ".
					"inner join pedidoitems items on items.pedido_id = pedidos.id ".
					"inner join produtos on items.produto_id = produtos.id ".
					"inner join setors setor on pedidos.entregasetor_id = setor.id ".
					"inner join empresas on pedidos.empresa_id = empresas.id ".
					"inner join cidades on setor.cidade_id = cidades.id ".
					"where empresas.grupo_id = $grupo and (empresas.regiao_id = $regiao or $regiao = -1) and ";
					if(is_null($empresa_id))
						$querySetores .= "empresas.id in (select empresa_id from empresa_user where user_id = $user_id) and ";
					else
						$querySetores .= "empresas.id = $empresa_id and ";
					$querySetores .= "extract(year from datahoraprevisaoentrega) = $ano and  ".
					"extract(month from datahoraprevisaoentrega) = $mes and produtos.tipo_glp = $produto and ".
					"pedidos.pedidosituacao_id in (select id from pedidosituacaos where grupo_id = $grupo and (fechadoconcluido = 1 or entregafinalizada = 1)) " .
					"group by setor.id, setor.descricao, setor.latitude, setor.longitude, cidades.descricao,  ".
					"setor.uf, to_char(datahoraprevisaoentrega,'mm/yyyy') ".
				") metas ".
				"group by setor_id, cidade, uf, setor, mes, latitude, longitude " .
				"order by latlong";
		
		$queryBairros = "select bairros.id as bairro_id, bairros.descricao as bairro, sum(items.quantidade) as quant ".
				"from pedidos ".
				"inner join pedidoitems items on items.pedido_id = pedidos.id ".
				"inner join produtos on items.produto_id = produtos.id ".
				"inner join bairros on pedidos.entregabairro_id = bairros.id ".
				"inner join empresas on pedidos.empresa_id = empresas.id ".
				"where empresas.grupo_id = $grupo and (empresas.regiao_id = $regiao or $regiao = -1) and ";
				if(is_null($empresa_id))
					$queryBairros .= "empresas.id in (select empresa_id from empresa_user where user_id = $user_id) and ";
				else
					$queryBairros .= "empresas.id = $empresa_id and ";
				$queryBairros .= "pedidos.pedidosituacao_id in (select id from pedidosituacaos where grupo_id = $grupo and (fechadoconcluido = 1 or entregafinalizada = 1)) and ".
				"produtos.tipo_glp = $produto and extract(year from datahoraprevisaoentrega) = $ano and ".
				"extract(month from datahoraprevisaoentrega) = $mes ".
				"group by bairros.id, bairros.descricao";
                $bairro = str_encode_to_query($bairro);
		$queryRuas = "select rua, items as quantidade ".
				"from( ".
					"select ruas.id as rua_id, ruas.descricao as rua, sum(items.quantidade) as items ".
					"from pedidos ".
					"inner join pedidoitems items on items.pedido_id = pedidos.id ".
					"inner join produtos on items.produto_id = produtos.id ".
					"inner join bairros on pedidos.entregabairro_id = bairros.id ".
					"inner join ruas on pedidos.entregarua_id = ruas.id ".
					"where pedidos.empresa_id = $empresa_id and ".
					rawTranslateSpecialChars("bairros.descricao") . " like lower(cast('$bairro' as varchar(55))) and ".
					"extract(year from datahoraprevisaoentrega) = $ano and ".
					"extract(month from datahoraprevisaoentrega) = $mes and ".
					"produtos.tipo_glp = $produto and pedidos.pedidosituacao_id in  ".
					"(select id from pedidosituacaos where grupo_id = $grupo and (fechadoconcluido = 1 or entregafinalizada = 1)) ";
					if(!is_null($setor_id))
						$queryRuas .= "and pedidos.entregasetor_id = $setor_id ";
					$queryRuas .= "group by ruas.id, ruas.descricao, bairros.id ".
					"order by items desc ".
				") quilos ".
				"limit 10";
		
		$querySetorBairros = "select bairros.descricao as bairro, sum(items.quantidade) as quant ".
				"from pedidos ".
				"inner join pedidoitems items on items.pedido_id = pedidos.id ".
				"inner join produtos on items.produto_id = produtos.id ".
				"inner join setors setor on pedidos.entregasetor_id = setor.id ".
				"inner join bairros on pedidos.entregabairro_id = bairros.id ".
				"where extract(year from datahoraprevisaoentrega) = $ano and ".
				"extract(month from datahoraprevisaoentrega) = $mes and ".
				"setor.id = $setor_id and pedidos.pedidosituacao_id in  ".
				"(select id from pedidosituacaos where grupo_id = $grupo and (fechadoconcluido = 1 or entregafinalizada = 1)) and ".
				"produtos.tipo_glp = $produto ".
				"group by bairros.descricao";

		switch ($which) {
			case 'grupo':
				return $queryGrupos;
				break;
			case 'regional':
				return $queryRegional;
				break;
			case 'bairros':
				return $queryBairros;
				break;
			case 'ruas':
				return $queryRuas;
				break;
			case 'bairrosSetor':
				return $querySetorBairros;
				break;
			default:
				return $querySetores;
				break;
		}
	}

}
