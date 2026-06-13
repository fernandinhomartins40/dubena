<?php

use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PopulateNfEntradaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
    	$date = Carbon::parse('2017-01-01');
    	$empresa = App\Empresa::where('id', 2)->get()->first();
    	$produtos = DB::table('produtos p')
    		->join('produtoclasses c', 'c.id', 'p.produtoclasse_id')
    		->whereRaw("tipo in ('G') and pesoliquido is not null and pesobruto is not null and empresa_id = $empresa->id")
    		->select('p.id', 'precovenda', 'nfedescricaofiscal', 'customedio')
    		->get();
    	$clientes = DB::table('clientes')->whereRaw("fornecedor = 1 and cnpj is not null and empresa_id = $empresa->id")->get();
    	$setores = DB::table('setors')->whereRaw("ativo = 1 and empresa_id = $empresa->id")->get();
    	$condicaopagamentos = DB::table('condicaopagamentos c')
			->whereRaw("grupo_id = $empresa->grupo_id and tipo not in (4, 5)")->get();
    	for ($i=1; $i < 366; $i++) {
	    	$request = new Request();
			$request->replace($this->generateForm($date, 0, $clientes, $produtos, $empresa, $setores, $condicaopagamentos));
	    	$controller = new App\Http\Controllers\NfrecebidaController();
			$store = $controller->store($request);
			if(!is_bool($store))
				throw new \Exception($store);
	    	$request = new Request();
			$request->replace($this->generateForm($date, 0, $clientes, $produtos, $empresa, $setores, $condicaopagamentos));
	    	$controller = new App\Http\Controllers\NfrecebidaController();
			$store = $controller->store($request);
			if(!is_bool($store))
				throw new \Exception($store);
				
			$date->addDay();
    	}
    }

    private function generateForm($date, $i, $clientes, $produtos, $empresa, $setores, $condicaopagamentos)
    {
    	$cliente = $clientes->random();
    	$produtos = $produtos->random(2);
    	$condicaopagamento = $condicaopagamentos->random();
    	$qde = rand(20, 180);
    	return [
		  "chaveacesso" => "",
		  "nfnumero" => $i,
		  "nfserie" => "1",
		  "nfmodelo" => "02",
		  "nftipoemissao" => "1",
		  "nfsubserie" => "",
		  "datahoraemissao" => requestDataOracle($date, false) ." 14:53",
		  "datahoraentradasaida" => requestDataOracle($date, false) ." 14:53",
		  "nfefinalidade" => "1",
		  "operacaotiponf" => "0",
		  "operacaocadastronf" => "F",
		  "nfoperacao_id" => "210",
		  "empresa_id" => "2",
		  "cliente_id" => $cliente->id,
		  "cliente_id_erro" => $cliente->id,
		  "cliente_nome_erro" => $cliente->nome,
		  "nomecliente" => $cliente->id,
		  "informacaocomplementar" => "",
		  "emitrazaosocial" => $empresa->razao_social,
		  "emitnomefantasia" => $empresa->nome_fantasia,
		  "emitie" => $empresa->inscricao_estadual,
		  "emitcnpj" => $empresa->cnpj,
		  "emitcpf" => "",
		  "emitinscricaomunicipal" => "",
		  "emitcnae" => "",
		  "codcrt" => "3",
		  "emitpaiscodigoibge" => "1058",
		  "emitpaisnome" => "Brasil",
		  "emitufcodigoibge" => "41",
		  "emituf" => "PR",
		  "emitcidadecodigoibge" => "4109401",
		  "emitcidadenome" => "Guarapuava",
		  "emitcidade_id" => "4109401",
		  "emitbairro" => "Primavera",
		  "emitendereco" => "Rodovia PR-466",
		  "emitnumero" => "1277",
		  "emitcep" => "85050-290",
		  "emitcomplemento" => "Sala 04",
		  "emittelefone" => "(42) 36293-586",
		  "destcliente_id" => $cliente->id,
		  "destrazaosocial" => $cliente->nome,
		  "destie" => $cliente->inscricao_estadual,
		  "destcnpj" => $cliente->cnpj,
		  "destcpf" => "",
		  "destindicadorie" => "1",
		  "destindicadorietext" => "1 - Contribuinte  ICMS",
		  "destpaiscodigoibge" => "1058",
		  "destpaisnome" => "Brasil",
		  "destuf" => "PR",
		  "destcidadecodigoibge" => "4101804",
		  "destcidadenome" => "Araucária",
		  "destcidade_id" => "4101804",
		  "destbairro" => "Chapada",
		  "destendereco" => "Rua Edson Queiroz",
		  "destnumero" => "214",
		  "destcep" => "83707-741",
		  "destcomplemento" => "",
		  "desttelefone" => "",
		  "destemail" => "juliany@qti.inf.br",
		  "fretemodalidade" => "9",
		  "freterazaosocial" => "",
		  "fretie" => "",
		  "fretecnpj" => "",
		  "fretecpf" => "",
		  "fretuf" => "",
		  "fretecidadenome" => "",
		  "freteenderecocompl" => "",
		  "freteplacauf" => "",
		  "freteplaca" => "",
		  "vfrete" => "",
		  "fretevista" => "",
		  "freteboleto" => "",
		  "fretecartao" => "",
		  "fretecondicaoparcelas" => "",
		  "fretecondicao" => "",
		  "frete_parcelas_financeiro" => "",
		  "fretecentrocusto_id" => "",
		  "fretecentrocusto_descricao" => "",
		  "freteplanoconta_id" => "",
		  "freteplanoconta_descricao" => "",
		  "precoprodutos" => "[]",
		  "produto_valor" => "",
		  "produto_quantidade" => "",
		  "qVol" => "",
		  "pesoLhidden" => "",
		  "pesoL" => "",
		  "pesoBhidden" => "",
		  "pesoB" => "",
		  "produtos" => $this->toJson($produtos, $setores, $qde),
		  "vbruto" => "R$ " . requestNumeroDecimalOracle($produtos->sum('precovenda') * $qde),
		  "vdesc" => "",
		  "vnf" => "R$ " . requestNumeroDecimalOracle($produtos->sum('precovenda') * $qde),
		  "descricaofinanceiro" => "Inserção de NF" . $i,
		  "condicaopagamento_id" => $condicaopagamento->id,
		  "centrocusto_id" => "5",
		  "planoconta_id" => "249",
		  "vprod" => "R$ " . requestNumeroDecimalOracle($produtos->sum('precovenda') * $qde),
		  "statusevento" => "",
		  "xml" => ""
		];
    }

    private function toJson($produtos, $setores, $qde)
    {
    	$produtosJson = [];
    	foreach ($produtos as $p) {
    		$setor = $setores->random();
    		$pJson = collect([]);
    		$pJson->push(210); //cfop
    		$pJson->push("1403 - Compra de GLP"); //cfop desc
    		$pJson->push($setor->id); //setor
    		$pJson->push($setor->descricao); //setor desc
    		$pJson->push($p->id); //prod
    		$pJson->push($p->nfedescricaofiscal); //prod desc
    		$pJson->push($p->precovenda); //valor
    		$pJson->push($qde); //qde
    		array_push($produtosJson, $pJson->toArray());
    	}
    	return json_encode($produtosJson);
    }
}
