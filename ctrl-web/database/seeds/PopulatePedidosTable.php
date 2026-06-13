<?php

use Illuminate\Database\Seeder;
use App\Http\Requests\PedidoRequest as Request;
use Carbon\Carbon;

class PopulatePedidosTable extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
	private $usedClients = [];
    public function run()
    {
    	$date = Carbon::parse('2018-05-21');
    	$empresa = App\Empresa::where('id', 2)->get()->first();
    	$produtos = DB::table('produtos p')
    		->join('produtoclasses c', 'c.id', 'p.produtoclasse_id')
    		->whereRaw("empresa_id = $empresa->id and p.id in (50, 310, 318)")
    		->select('p.id', 'precovenda', 'p.descricao', 'customedio')
    		->get();

    	$clientes = DB::table('clientes')->whereRaw("cliente = 1 and ativo = 1 and empresa_id = $empresa->id")->get();
		
    	$setores = DB::table('SETORCOLABORADORES')->get();
		
    	$condicaopagamentos = DB::table('condicaopagamentos c')
			->whereRaw("grupo_id = $empresa->grupo_id and tipo not in (2, 3, 4, 5)")->get();
			
		$count = 0;
		$countDays = 1;
    	for ($i = 0; $i < $countDays; $i++) {
            $qdePe = rand(80, 100);
			$this->usedClients = [];
    		for ($j = 0; $j < $qdePe; $j++) {
		    	$request = new Request();
				$request->replace($this->generateForm($date, 0, $clientes, $produtos, $empresa, $setores, $condicaopagamentos));
		    	$controller = new App\Http\Controllers\PedidoController();
				$store = $controller->store($request);
				if (!is_bool($store)) {
					throw new \Exception($store);
				}
				$count++;
				dump("j:" . $j . "; i:" . $i . "; count: " . $count);
    		}
			$date->addDay();
    	}
    }

    private function generateForm($date, $i, $clientes, $produtos, $empresa, $setores, $condicaopagamentos)
    {
		$cliente = $this->getCliente($clientes);
    	$qde = rand(1, 3);
    	$produtos = $produtos->random(rand(1, 3));
    	$setor = $setores->random();
    	$condicaopagamento = $condicaopagamentos->random();
    	// $desc = rand(0, 20);
        $desc = 0;
        $taxa = 0;
    	// $taxa = rand(0, 12);
    	$totalProd = $produtos->sum('precovenda') * $qde;
    	$valor = $totalProd - $desc + $taxa;
        $h = rand(7, 22);
        $m = rand(0, 59);
        $s = rand(0, 59);
        $h = strlen($h) === 1 ? '0' . $h : $h;
        $m = strlen($m) === 1 ? '0' . $m : $m;
        $s = strlen($s) === 1 ? '0' . $s : $s;
		if ($cliente->setor_id) {
			$setor = $setores->where('setor_id', $cliente->setor_id)->first();
		}
		$setor_id = $setor->setor_id;
		$colab_id = $setor->colaborador_id;
    	return [
		 	"numerocartao" => "",
		 	"datahoraacao" => $date->copy()->format('d/m/y') . " $h:$m:$s",
		 	"datahoraprevisaoentrega" => $date->copy()->format('d/m/y') . " $h:$m:$s",
		 	"pedidosituacao_id" => "23",
		 	"entregatelefone" => "",
		 	"cliente_id" => $cliente->id,
		 	"entregacep" => $cliente->cep,
		 	"ufentrega" => $cliente->uf,
		 	"entregacidade_id" => $cliente->cidade_id,
		 	"entregabairro_id" => $cliente->bairro_id,
		 	"entregarua_id" => $cliente->rua_id,
		 	"entreganumero" => $cliente->numero,
		 	"entregacomplemento" => $cliente->complemento,
		 	"observacao" => "",
		 	"entregapontoreferencia" => "",
		 	"pedidooperacao_id" => "21",
		 	"entregasetor_id" => $setor_id,
		 	"colaborador_id" => $setor->colaborador_id,
		 	"condicaopagamento_id" => $condicaopagamento->id."-0",
		 	"produtospedido" => $this->toJson($produtos, $qde),
		 	"valordesconto" => "R$ " . $desc . ",00",
		 	"entregataxa" => "R$ " . $taxa . ",00",
		 	"valorvenda" => str_replace("R$ ", "", requestNumeroDecimalOracle($valor)),
		 	"empresa_id" => "2",
			"grupo_id" => "2"
		];
    }

	private function getCliente($clientes, $self = false)
	{
    	$cliente = $clientes->random();
		
		if (in_array($cliente->id, $this->usedClients)) {
			$cliente = $clientes->random();
		}
		
		if (!$self) 
			array_push($this->usedClients, $cliente->id);
		
		return $cliente;
	}
	
    private function toJson($produtos, $qde)
    {
    	$produtosJson = [];
    	foreach ($produtos as $p) {
    		$pJson = collect([]);
    		$pJson->push($p->id); //prod
    		$pJson->push($p->descricao); //prod desc
    		$pJson->push(str_replace("R$ ", "", requestNumeroDecimalOracle($p->precovenda))); //valor
    		$pJson->push($qde); //qde
    		array_push($produtosJson, $pJson->toArray());
    	}
    	return json_encode($produtosJson);
    }
}
