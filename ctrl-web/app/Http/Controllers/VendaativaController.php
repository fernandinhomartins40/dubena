<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Rua;
use Redirect;
use Exception;
use App\Setor;
use App\Estado;
use App\Cidade;
use App\Bairro;
use App\Pedido;
use App\Cliente;
use App\Segmento;
use App\Vendaativa;
use App\Pedidosituacao;
use App\Clientetelefone;
use App\Vendaativacliente;
use Illuminate\Http\Request;
use App\Vendaativaocorrencia;
use App\Vendaativaocorrenciatipo;
use App\Repository\SelectRepository;
use App\Services\CarbonCustom as Carbon;

class VendaativaController extends Controller
{
    private $setores;
    private $grupo_id;
    private $vendaativa;
    private $empresa_id;
    private $vendaativas;
    private $ocorrencias;
    private $empresa_cep;
    private $empresa_estado;
    private $repositorio;

    function index(Vendaativa $ven)
    {
        $this->authorize('view',$ven);
        $this->definition();
        $vendaativa = Vendaativa::where('empresa_id',$this->empresa_id)->get();
        return view('vendaativa.vendaativa')->with(compact('vendaativa'));
    }

    function create(Vendaativa $ven)
    {
        $this->authorize('create',$ven);
        $this->definition();
        $cidades = $this->repositorio->getCities();
        $cidade_id = Session::get('empresa_padrao')->cidade_id;
        $cidadecompra = $cidade_id;
        $cidademedia = $cidade_id;
        $setores = $this->repositorio->getSetores()->prepend('Selecione','');
        $ocorrencias = $this->repositorio->getTipoOcorrencia()->prepend('Selecione','');
        $segmentos = $this->repositorio->getSegmentos()->prepend('Selecione','');
        return view('vendaativa.vendaativa_form')->with(compact('cidades','setores','ocorrencias','segmentos','cidade_id','cidadecompra','cidademedia'));
    }

    function store(Request $request, Vendaativa $ven)
    {
        $this->authorize('create',$ven);
        $this->definition();
        $data = $request->all();
        $dadosextras = $this->dadosExtras($data,$data["filtro"]);
        $data = array_merge($data,$dadosextras);
        DB::beginTransaction();
        try{
            $vendaativa = Vendaativa::create($data);
            $clientes = $this->salvarClientes($data,$vendaativa);
            if(!$clientes){
                throw new exception('Ops, algo deu errado.');
            }
        }catch(Exception $ex){
            DB::rollback();
            return Redirect::back()->withErrors($ex->getMessage())->withInput();
        }
        DB::commit();
        return Redirect::to('vendaativa')->withMessageSuccess('Filtro criado com sucesso!');
    }

    function show($id, Vendaativa $ven)
    {
        $this->authorize('work',$ven);
        $vendaativa = Vendaativa::find($id);
        $this->authorize('igualdade',$vendaativa);
        $this->definition();
        $ocorrencias = $this->repositorio->getTipoOcorrencia()->prepend('Selecione','');
        $segmentos = $this->repositorio->getSegmentos()->prepend('Selecione','');
        $cidades = $this->repositorio->getCities();
        $cidade_id = Session::get('empresa_padrao')->cidade_id;
        $cidadecompra = $cidade_id;
        $cidademedia = $cidade_id;

        $setores = $this->repositorio->getSetores()->prepend('Selecione','');
        $sit = implode(',',Pedidosituacao::whereRaw("grupo_id = $this->grupo_id and fechadoconcluido = 1")->pluck('id')->toArray());
        $ativaclientes = DB::table('vendaativaclientes as ativa')
                                ->rightJoin('clientes','clientes.id','ativa.cliente_id')
                                ->leftJoin('setors','clientes.setor_id','setors.id')
                                ->join('segmentos','clientes.segmento_id','segmentos.id')
                                ->join('cidades','clientes.cidade_id','cidades.id')
                                ->join('bairros','clientes.bairro_id','bairros.id')
                                ->join('ruas','clientes.rua_id','ruas.id')
                                ->where([
                                    ['clientes.empresa_id',$this->empresa_id],
                                    ['ativa.vendaativa_id',$id]
                                ])->select('clientes.id','clientes.nome','setors.descricao','ativa.vendaativaocorrencia_id','segmentos.descricao as segmento',
                                    'ativa.ligarnovamente','ativa.previsaoproxcompra','clientes.datanascimento','clientes.setor_id','setors.descricao','ativa.pedido_id',
                                    DB::raw("(cidades.descricao || ', ' || bairros.descricao || ' - ' ||".
                                    " ruas.descricao || ', ' || clientes.numero) as endereco"),
                                    DB::raw("(select listagg(tel.telefone,', ') within group (order by tel.telefone) from clientetelefones tel ".
                                    "where tel.cliente_id = clientes.id and rownum <= 2) as telefone"),
                                    DB::raw("(select max(datahoraprevisaoentrega) from pedidos where pedidosituacao_id in ($sit) and" .
                                    " cliente_id = clientes.id) as dataultima"))
                                ->get();
        $clientes = collect([]);
        foreach($ativaclientes as $cli){
            $objcliente = (object) array();
            $objcliente->id = $cli->id;
            $objcliente->nome = $cli->nome;
            $objcliente->usuario_id = \Auth::user()->id;
            $objcliente->usuario_nome = \Auth::user()->name;
            $objcliente->ultimopedido = requestDataOracle($cli->dataultima,true);
            $objcliente->telefones = $cli->telefone;
            $objcliente->datanascimento = requestDataOracle($cli->datanascimento,false);
            $objcliente->endereco = $cli->endereco;
            $objcliente->setor_cliente = isset($cli->setor_id) ? $cli->descricao : null;
            $objcliente->datarevisao = $cli->previsaoproxcompra == null ? null : requestDataOracle($cli->previsaoproxcompra);
            $objcliente->ligarnovamente = $cli->ligarnovamente;
            $objcliente->segmento = $cli->segmento;
            $clientes->push($objcliente);
        }
        $id = $vendaativa->id;
        $trampo = 1;
        $clientes = $clientes->unique('id');
        return view('vendaativa.vendaativa_form')->with(compact('ocorrencias','clientes','cidades',
            'setores','ativaclientes','id','vendaativa','trampo','segmentos','cidade_id','cidadecompra','cidademedia'));
    }

    function update(Request $request, $id, Vendaativa $ven)
    {
        $data = $request->all();
        $vendaativa_id = $data["vendaativa_id"];
        $this->authorize('update',$ven);
        $vendaativa = Vendaativa::find($vendaativa_id);
        $this->authorize('igualdade',$vendaativa);
        $data["ativo"] = isset($data["ativo"]) ? $data["ativo"] : false;
        $clientes = Vendaativacliente::where('vendaativa_id',$id)->whereNotNull('pedido_id')->orWhere('vendaativa_id',$id)->whereNotNull('vendaativaocorrencia_id')->limit(5)->get();
        DB::beginTransaction();
        try{
            if($data["ativo"] == false && count($clientes) > 0){
                throw new Exception('Não é possivel inativar um filtro que gerou um pedido ou uma ocorrencia.');
            }else{
                $vendaativa->update(["ativo"=>$data["ativo"]]);
                $clientes = $this->updateCiente($vendaativa,$data["novamente"]);
            }
        }catch(Exception $ex){
            DB::rollback();
            return Redirect::back()->withErrors($ex->getMessage())->withInput();
        }
        DB::commit();
        return Redirect::to('vendaativa')->withMessageSuccess('Venda Ativa atualizada com sucesso!');
    }

    public function gerarOcorrencia(Request $request, Vendaativa $ven)
    {
        $data = $request->all();
        $this->authorize('update',$ven);
        $venda = Vendaativa::find($data["vendaativa_id"]);
        $this->authorize('igualdade',$venda);
        $this->definition();
        $dadosextras = $this->dadosExtras($data,'ocorrencia');
        $data = array_merge($data,$dadosextras);
        DB::beginTransaction();
        try{
            $ocorrencia = Vendaativaocorrencia::create($data);
            $update = $this->updateVendaCliente($ocorrencia,$venda);
            $clientes = $this->updateCiente($venda,$data["novamente_ocorrencia"]);
        }catch(Exception $ex){
            DB::rollback();
            return Redirect::to('vendaativa/'.$venda->id)->withErrors($ex->getMessage())->withInput();
        }
        DB::commit();
        return Redirect::to('vendaativa/'.$venda->id)->withMessageSuccess("Ocorrência salva com sucesso!");
    }

    public function filtroEndereco(Vendaativa $ven)
    {
        $this->authorize('view',$ven);
        $this->definition();
        $get = $_GET;
        $setores = $this->repositorio->getSetores()->prepend('Selecione','');
        $ocorrencias = $this->repositorio->getTipoOcorrencia()->prepend('Selecione','');
        $segmentos = $this->repositorio->getSegmentos()->prepend('Selecione','');
        $cidades = $this->repositorio->getCities();
        $cidade_id = $get["cidade"];
        $cidadecompra = Session::get('empresa_padrao')->cidade_id;
        $cidademedia = $cidadecompra;

        $cidade = $get["cidade"];
        $bairro = $get["bairro"] == "0" ? null : $get["bairro"];
        $rua = $get["rua"] == "0" ? null : $get["rua"];
        $setor = $get["setor"] == "0" ? null : $get["setor"];
        $segmento = $get["segmento"] == "0" ? null : $get["segmento"];
        $filtro = 1;

        $pedidosituacao = implode(',',Pedidosituacao::whereRaw("fechadoconcluido = 1 and grupo_id = $this->grupo_id")->pluck('id')->toArray());
        $clientesgeral = Cliente::leftJoin('setors','clientes.setor_id','setors.id')
                        ->join('segmentos','clientes.segmento_id','segmentos.id')
                        ->join('bairros','clientes.bairro_id','bairros.id')
                        ->join('cidades','clientes.cidade_id','cidades.id')
                        ->join('ruas','clientes.rua_id','ruas.id')
                        ->where([
                                ["clientes.cidade_id",$cidade],
                                ['clientes.empresa_id', $this->empresa_id],
                                ['clientes.ativo', 1],
                                ['clientes.cliente',1]
                        ]);

        if(!is_null($bairro))
            $clientesgeral = $clientesgeral->where('clientes.bairro_id',$bairro);
        if(!is_null($rua))
            $clientesgeral = $clientesgeral->where('clientes.rua_id',$rua);
        if(!is_null($setor))
            $clientesgeral = $clientesgeral->where('clientes.setor_id',$setor);
        if(!is_null($segmento))
            $clientesgeral = $clientesgeral->where('clientes.segmento_id',$segmento);

        $clientesgeral = $clientesgeral->select('clientes.id','clientes.nome','clientes.setor_id','clientes.datanascimento',
                                'ruas.descricao as rua', 'bairros.descricao as bairro','cidades.descricao as cidade',
                                'clientes.numero','clientes.rua_id','setors.descricao','segmentos.descricao as segmento',
                                DB::raw("(select max(datahoraprevisaoentrega) from pedidos where cliente_id = clientes.id and pedidosituacao_id ".
                                "in ($pedidosituacao)) as dataultimopedido"),
                                DB::raw("(cidades.descricao || ', ' || bairros.descricao || ' - ' ||".
                                    " ruas.descricao || ', ' || clientes.numero) as endereco"),
                                DB::raw("(select listagg(tel.telefone,', ') within group (order by tel.telefone) from clientetelefones tel ".
                                "where tel.cliente_id = clientes.id and rownum <= 2) as telefone"))
                        ->get();

        $clientes = collect([]);
        foreach($clientesgeral as $cliente){
            $objclientes = (object) array();
            $objclientes->id = $cliente->id;
            $objclientes->nome = $cliente->nome;
            $objclientes->segmento = $cliente->segmento;
            $objclientes->usuario_id = \Auth::user()->id;
            $objclientes->usuario_nome = \Auth::user()->name;
            $objclientes->ultimopedido = requestDataOracle($cliente->dataultimopedido,true);
            $objclientes->telefones = $cliente->telefone;
            $objclientes->datanascimento = requestDataOracle($cliente->datanascimento,false);
            $objclientes->endereco = $cliente->endereco;
            $objclientes->setor_cliente = !is_null($cliente->setor_id) ? $cliente->descricao : null;
            $clientes->push($objclientes);
        }
        $bairro_id = $bairro;
        $rua_id = $rua;

        return view('vendaativa.vendaativa_form')->with(compact('clientes','cidades','setores','ocorrencias',
            'filtro','segmentos','cidade_id','cidadecompra','cidademedia','bairro_id','rua_id'));
    }

    public function filtroCompra(Vendaativa $ven)
    {
        $this->authorize('view',$ven);
        $get = $_GET;
        $this->definition();
        $setores = $this->repositorio->getSetores()->prepend('Selecione','');
        $ocorrencias = $this->repositorio->getTipoOcorrencia()->prepend('Selecione','');
        $segmentos = $this->repositorio->getSegmentos()->prepend('Selecione','');
        $cidades = $this->repositorio->getCities();
        $cidade_id = Session::get('empresa_padrao')->cidade_id;
        $cidadecompra = $get["cidade"];
        $cidademedia = $cidade_id;

        $cidade = $get["cidade"];
        $bairro = $get["bairro"] == "0" ? null : $get["bairro"];
        $rua = $get["rua"] == "0" ? null : $get["rua"];
        $setor = $get["setor"] == "0" ? null : $get["setor"];
        $segmento = $get["segmento"] == "0" ? null : $get["segmento"];
        $filtro = 2;

        $datatemcompra = Carbon::today()->subDays($get["temcompra"])->startOfDay();
        $datanaocompra = Carbon::today()->subDays($get["naocompra"])->endOfDay();
        $dataatual = Carbon::now()->endOfDay();
        $pedidosituacao = Pedidosituacao::where(['fechadoconcluido'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $sit = implode(',',$pedidosituacao);
        $joinUltima = "SELECT cliente_id, MAX(datahoraprevisaoentrega) AS datacompra FROM pedidos ".
            "WHERE pedidosituacao_id IN ($sit) GROUP BY cliente_id";

        $clientespedidos = Cliente::rightJoin('pedidos','pedidos.cliente_id','clientes.id')
                                ->rightJoin(DB::raw("($joinUltima) ultima"), function ($join) use ($datanaocompra) {
                                    $join->on('ultima.cliente_id','clientes.id')
                                    ->whereRaw("ultima.datacompra < to_date('$datanaocompra','yyyy-mm-dd hh24:mi:ss')");
                                })
                                ->leftJoin('setors','clientes.setor_id','setors.id')
                                ->join('segmentos','clientes.segmento_id','segmentos.id')
                                ->join('bairros','clientes.bairro_id','bairros.id')
                                ->join('cidades','clientes.cidade_id','cidades.id')
                                ->join('ruas','clientes.rua_id','ruas.id')
                                ->whereBetween('pedidos.datahoraprevisaoentrega',[$datatemcompra,$datanaocompra])
                                ->whereNotBetween('pedidos.datahoraprevisaoentrega',[$datanaocompra->copy()->startOfDay(),$dataatual])
                                ->whereIn('pedidos.pedidosituacao_id',$pedidosituacao)
                                ->where([
                                    ["clientes.cidade_id",$cidade],
                                    ['clientes.empresa_id', $this->empresa_id],
                                    ['clientes.ativo', 1],
                                    ['clientes.cliente',1]
                                ]);

        if(!is_null($bairro))
            $clientespedidos = $clientespedidos->where('clientes.bairro_id',$bairro);
        if(!is_null($rua))
            $clientespedidos = $clientespedidos->where('clientes.rua_id',$rua);
        if(!is_null($setor))
            $clientespedidos = $clientespedidos->where('clientes.setor_id',$setor);
        if(!is_null($segmento))
            $clientespedidos = $clientespedidos->where('clientes.segmento_id',$segmento);

        $clientespedidos = $clientespedidos->select('clientes.id','clientes.nome','clientes.setor_id','clientes.datanascimento',
                                'ultima.datacompra','clientes.rua_id','setors.descricao as setor','segmentos.descricao as segmento',
                                DB::raw("(cidades.descricao || ', ' || bairros.descricao || ' - ' ||".
                                " ruas.descricao || ', ' || clientes.numero) as endereco"),
                                DB::raw("(select listagg(tel.telefone,', ') within group (order by tel.telefone) from clientetelefones tel ".
                                "where tel.cliente_id = clientes.id and rownum <= 2) as telefone"))
                            ->groupBy('clientes.id','clientes.nome','clientes.setor_id','clientes.datanascimento','ruas.descricao',
                                'bairros.descricao','cidades.descricao','ultima.datacompra','clientes.numero','segmentos.descricao',
                                'clientes.rua_id','setors.descricao')->get();

        $clientes = [];
        foreach ($clientespedidos as $cliente) {
            $objclientes = (object) array();
            $objclientes->id = $cliente->id;
            $objclientes->nome = $cliente->nome;
            $objclientes->segmento = $cliente->segmento;
            $objclientes->usuario_id = \Auth::user()->id;
            $objclientes->usuario_nome = \Auth::user()->name;
            $objclientes->ultimopedido = requestDataOracle($cliente->datacompra,true);
            $objclientes->telefones = $cliente->telefone;
            $objclientes->datanascimento = requestDataOracle($cliente->datanascimento,false);
            $objclientes->endereco = $cliente->endereco;
            $objclientes->setor_cliente = is_null($cliente->setor_id) ? "" : $cliente->setor;
            array_push($clientes,$objclientes);
        }
        $bairrocompra = $bairro;
        $ruacompra = $rua;
        return view('vendaativa.vendaativa_form')->with(compact('clientes','cidades',
            'setores','ocorrencias','filtro','segmentos','cidade_id','cidadecompra','bairrocompra','ruacompra','cidademedia'));
    }

    public function filtroMediaGiro(Vendaativa $ven) {
        $this->authorize('view',$ven);
        $this->definition();
        $get = $_GET;
        $cep = $this->empresa_cep;
        $setores = $this->repositorio->getSetores()->prepend('Selecione','');
        $ocorrencias = $this->repositorio->getTipoOcorrencia()->prepend('Selecione','');
        $segmentos = $this->repositorio->getSegmentos()->prepend('Selecione','');
        $cidades = $this->repositorio->getCities();
        $cidade_id = Session::get('empresa_padrao')->cidade_id;
        $cidadecompra = $cidade_id;
        $cidademedia = $get["cidade"];

        $mesesantes = (int) $get["mesantes"];
        $dias = (int) $get["entre"];
        $date = $get["giroate"];
        $datagiro = Carbon::parse($date)->startOfDay();
        $dataanterior = Carbon::parse($date)->subDays($dias);
        $dataapartir = Carbon::parse($date)->addDays($dias);
        $datamesesantes = Carbon::parse($date)->subMonths($mesesantes)->startOfDay();
        $cidade = $get["cidade"];
        $bairro = $get["bairro"] == "0" ? null : $get["bairro"];
        $setor = $get["setor"] == "0" ? null : $get["setor"];
        $segmento = $get["segmento"] == "0" ? null : $get["segmento"];
        $filtro = 3;

        $pedidosituacao = Pedidosituacao::where(['fechadoconcluido'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $sit = implode(',',$pedidosituacao);
        $joinUltima = "SELECT cliente_id, MAX(datahoraprevisaoentrega) AS datacompra FROM pedidos ".
            "WHERE pedidosituacao_id IN ($sit) GROUP BY cliente_id";

        $clientespedidos = Cliente::leftJoin('pedidos','clientes.id','=','pedidos.cliente_id')
                            ->leftJoin('setors','clientes.setor_id','setors.id')
                            ->rightJoin(DB::raw("($joinUltima) ultima"), function ($join) use ($datamesesantes, $datagiro) {
                                $join->on('ultima.cliente_id','clientes.id')
                                ->whereRaw("ultima.datacompra <= to_date('$datagiro','yyyy-mm-dd hh24:mi:ss') and ".
                                           "ultima.datacompra >= to_date('$datamesesantes','yyyy-mm-dd hh24:mi:ss')");
                            })
                            ->join('segmentos','clientes.segmento_id','segmentos.id')
                            ->join('bairros','clientes.bairro_id','bairros.id')
                            ->join('cidades','clientes.cidade_id','cidades.id')
                            ->join('ruas','clientes.rua_id','ruas.id')
                            ->whereBetween('pedidos.datahoraprevisaoentrega',[$datamesesantes,$datagiro])
                            ->where([
                                ["clientes.cidade_id",$cidade],
                                ['clientes.empresa_id', $this->empresa_id],
                                ['clientes.ativo', 1],
                                ['clientes.cliente',1]
                            ])
                            ->whereIn('pedidos.pedidosituacao_id',$pedidosituacao);

        if(!is_null($bairro))
            $clientespedidos = $clientespedidos->where('clientes.bairro_id',$bairro);
        if(!is_null($setor))
            $clientespedidos = $clientespedidos->where('clientes.setor_id',$setor);
        if(!is_null($segmento))
            $clientespedidos = $clientespedidos->where('clientes.segmento_id',$segmento);

        $clientespedidos = $clientespedidos->select('clientes.id','ultima.datacompra','setors.descricao as setor',
                                     'clientes.nome','clientes.setor_id','clientes.datanascimento','segmentos.descricao as segmento',
                                     DB::raw("(cidades.descricao || ', ' || bairros.descricao || ' - ' ||".
                                     " ruas.descricao || ', ' || clientes.numero) as endereco"),
                                     DB::raw("(select listagg(tel.telefone,', ') within group (order by tel.telefone) from clientetelefones tel ".
                                     "where tel.cliente_id = clientes.id and rownum <= 2) as telefone"))
                                ->orderBy('clientes.id')
                                ->get();

        $datadiff=collect([]);
        foreach($clientespedidos->unique('id') as $pedido){
            $count = $clientespedidos->where('id',$pedido->id)->count();
            if($count > 1){
                $ultimoped = $pedido->datacompra;
                $diffdays = $datamesesantes->diffInDays(Carbon::parse($ultimoped)->endOfDay());
                $objdiff = (object) array();
                $objdiff->media = floor($diffdays / $count);
                $objdiff->cliente = $pedido->id;
                $objdiff->diff = $diffdays;
                $objdiff->ultimopedido = $ultimoped;
                $datadiff->push($objdiff);
            }
        }

        $clientesped = $clientespedidos->unique('id');
        $clientes = [];
        foreach($clientesped as $cliente){
            foreach($datadiff as $data){
                if($cliente->id == $data->cliente){
                    $dataultimo = Carbon::parse($data->ultimopedido);
                    $datad = Carbon::parse($dataultimo)->addDays($data->media);
                    if($datad >= $dataanterior && $datad <= $dataapartir){
                        $objclientes = (object) array();
                        $objclientes->id = $cliente->id;
                        $objclientes->nome = $cliente->nome;
                        $objclientes->segmento = $cliente->segmento;
                        $objclientes->usuario_id = \Auth::user()->id;
                        $objclientes->usuario_nome = \Auth::user()->name;
                        $objclientes->ultimopedido = requestDataOracle($dataultimo);
                        $objclientes->telefones = $cliente->telefone;
                        $objclientes->datanascimento = requestDataOracle($cliente->datanascimento,false);
                        $objclientes->endereco = $cliente->endereco;
                        $objclientes->setor_cliente = empty($cliente->setor_id) ? $cliente->descricao : null;
                        $objclientes->datarevisao = requestDataOracle($datad);
                        $objclientes->setor_cliente = is_null($cliente->setor_id) ? "" : $cliente->setor;
                        array_push($clientes,$objclientes);
                    }
                }
            }
        }
        $bairromedia = $bairro;
        return view('vendaativa.vendaativa_form')->with(compact('clientes','cidades',
        'setores','ocorrencias','filtro','segmentos','cidade_id','cidadecompra','cidademedia','bairromedia'));
    }

    public function salvarFiltro(Request $request, Vendaativa $ven)
    {
        $this->authorize('update',$ven);
        $data = $request->all();
        $clientes = json_decode($data["clientes"]);
        $vendaativa_id = $data["vendaativa_id"];
        if(count($clientes)>0){
            $vendaativa = Vendaativa::find($vendaativa_id);
            $cliente = $this->updateCiente($vendaativa,$data["clientes"]);
            if($cliente){
                return "true";
            }else{
                return "false";
            }
        }else{
            return "true";
        }
    }

    public function destroy($id, Vendaativa $ven)
    {
        $this->authorize('delete',$ven);
        $vendaativa = Vendaativa::find($id);
        $this->authorize('igualdade',$vendaativa);
        DB::beginTransaction();
        try {
            $clientes = DB::table('vendaativaclientes cli')
                            ->where('vendaativa_id',$id)
                            ->whereRaw('(vendaativaocorrencia_id is not null or pedido_id is not null)')
                            ->get();

            if(count($clientes) > 0)
                throw new exception('A venda ativa não pôde ser excluída pois gerou um pedido ou uma ocorrência!');

            $vendaativa->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />' . $e->getMessage();
        }
        return "OK|";
    }

    private function dadosExtras($data,$tipo)
    {
        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = $this->empresa_id;
        $data["user_id"] = \Auth::user()->id;
        $data["datahora"] = $tipo != "ocorrencia" ? Carbon::now()->toDateTimeString() : $data["datahora"];
        $data["comprevisagiro"] = $tipo == "3";
        $data["ativo"] = 1;
        switch ($tipo) {
            case '1':
                $city = Cidade::where('id',$data["cidade_id"])->get()->first();
                $uf = $city->uf;
                $cidade = isset($city->descricao) ? " " . $city->descricao : null;
                $bairro = isset($data["bairro_id"]) && $data["bairro_id"] ? " " . Bairro::where('id',$data["bairro_id"])->pluck('descricao')->first() . "," : null;
                $rua    = !$data["rua_id"] ? null : " " . Rua::where('id',$data["rua_id"])->pluck('descricao')->first() . ",";
                $setor  = !$data["setor_id"] ? null : " " . Setor::where('id',$data["setor_id"])->pluck('descricao')->first() . ",";
                $segmento = !$data["segmento_id"] ? null : " Segmento: " . Segmento::where('id',$data["segmento_id"])->pluck('descricao')->first().",";
                $data["descricaofiltro"] = "Filtro". "$segmento" . "$setor" . "$rua" . "$bairro" . "$cidade, $uf.";
                break;

            case '2':
                $city = Cidade::where('id',$data["cidadecompra"])->get()->first();
                $uf = $city->uf;
                $cidade = isset($city->descricao) ? " " . $city->descricao : null;
                $bairro = isset($data["bairrocompra"]) && $data["bairrocompra"] ? " " . Bairro::where('id',$data["bairrocompra"])->pluck('descricao')->first() . "," : null;
                $rua    = !$data["ruacompra"] ? null : " " . Rua::where('id',$data["ruacompra"])->pluck('descricao')->first() . ",";
                $setor  = !$data["setorcompra"] ? null : " " . Setor::where('id',$data["setorcompra"])->pluck('descricao')->first() . ",";
                $segmento = !$data["segmentocompra"] ? null : " Segmento: " . Segmento::where('id',$data["segmentocompra"])->pluck('descricao')->first() . ",";
                $naocompra = $data["naocompra"];
                $temcompra = $data["temcompras"];
                $data["descricaofiltro"] = "Filtro"."$segmento"." não compram: $naocompra, tem compras: $temcompra," .
                    $setor . $rua . $bairro . "$cidade, $uf.";
                break;

            case '3':
                $city = Cidade::where('id',$data["cidademedia"])->get()->first();
                $uf = $city->uf;
                $cidade = isset($city->descricao) ? " " . $city->descricao : null;
                $bairro = !$data["bairromedia"] ? null : " " . Bairro::where('id',$data["bairromedia"])->pluck('descricao')->first() . ",";
                $setor = !$data["setormedia"] ? null : " " . Setor::where('id',$data["setormedia"])->pluck('descricao')->first() . ",";
                $segmento = !$data["segmentomedia"] ? null : " Segmento: " . Segmento::where('id',$data["segmentomedia"])->pluck('descricao')->first() . ",";
                $mediagiro = $data["mediagiro"];
                $mesesantes = $data["mediamesantes"];
                $entre = $data["compraentre"];
                $data["descricaofiltro"] = "Filtro"."$segmento"." média de giro até: $mediagiro, meses antes: $mesesantes, compra entre: $entre dias," .
                    $setor . $bairro . "$cidade, $uf.";
                break;

            default:
                $data["datahora"] = insertDataOracle($data["datahora"]);
                break;
        }

        return $data;
    }

    private function salvarClientes($data,$vendaativa)
    {
        $clientes = json_decode($data["clientes"]);
        DB::beginTransaction();
        try{
            foreach($clientes as $cliente){
                $objcliente = new Vendaativacliente();
                $objcliente->vendaativa_id = $vendaativa->id;
                $objcliente->cliente_id = $cliente->cliente_id;
                $objcliente->ligarnovamente = $cliente->ligarnovamente;
                $objcliente->datahora = $vendaativa->datahora;
                $objcliente->previsaoproxcompra = !empty($cliente->mediagiro) ? insertDataOracle($cliente->mediagiro,true) : null;
                $objcliente->save();
            }
        }catch(Exception $ex){
            DB::rollback();
            return false;
        }
        DB::commit();
        return true;
    }

    private function updateVendaCliente($ocorrencia,$venda)
    {
        $cliente = Vendaativacliente::where(['vendaativa_id'=>$venda->id,'cliente_id'=>$ocorrencia->cliente_id])->get()->first();
        DB::beginTransaction();
        try{
            $cliente->update(["vendaativaocorrencia_id"=>$ocorrencia->id]);
        }catch(Exception $ex){
            DB::rollback();
            return Redirect::to('vendaativa/'.$venda->id)->withErrors($ex->getMessage())->withInput();
        }
        DB::commit();
        return true;
    }

    private function updateCiente($vendaativa,$novamente)
    {
        $clientes = $vendaativa->vendaativacliente;
        $novamente = json_decode($novamente);
        DB::beginTransaction();
        try{
            foreach($clientes as $cliente){
                foreach($novamente as $nov){
                    if($cliente->cliente_id == $nov->cliente_id){
                        $cliente->update(["ligarnovamente"=>$nov->ligarnovamente]);
                    }
                }
            }
        }catch(Exception $ex){
            DB::rollback();
            return Redirect::back()->withErrors($ex->getMessage())->withInput();
        }
        DB::commit();
        return true;
    }

    private function definition()
    {
        //busca id da empresa
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //busca grupo da empresa
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //cep da empresa
        $this->empresa_cep = Session::get('empresa_padrao')->cep;
        //busca estado da empresa
        $this->empresa_estado = Session::get('empresa_padrao')->uf;
        //new repositorio
        $this->repositorio = new SelectRepository($this->empresa_id, $this->grupo_id);
    }
}
