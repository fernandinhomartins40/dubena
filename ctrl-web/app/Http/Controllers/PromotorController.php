<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Rua;
use stdClass;
use App\Bairro;
use App\Cidade;
use App\Cliente;
use App\Clienteproduto;
use App\Clientetelefone;
use App\Tipopessoa;
use App\Promotorvenda;
use Illuminate\Http\Request;
use App\Http\Requests\ClienteRequest;
use App\Http\Requests\PromotorRequest;
use App\Repository\ClienteRepository as Repository;
use Carbon\Carbon;
use Input;

class PromotorController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Promotorvenda $pro)
    {
        $this->authorize("view", $pro);
        $inicio = Input::get('datainicio', Carbon::today());
        $fim = Input::get('datafim', Carbon::today());

        $datainicio = Carbon::parse($inicio)->startOfDay();
        $datafim = Carbon::parse($fim)->endOfDay();

        $empresa_id = Session::get("empresa_padrao")->id;

        $promotores =   Promotorvenda::from("promotorvendas as prom")
            ->join("users as us", "prom.user_id", "us.id")
            ->leftJoin("clientes as cli", "prom.cliente_id", "cli.id")
            ->leftJoin("ruas as ru", "prom.rua_id", "ru.id")
            ->leftJoin("cidades as ci", "prom.cidade_id", "ci.id")
            ->whereBetween("prom.updated_at", [$datainicio, $datafim])
            ->where("prom.empresa_id", $empresa_id)
            ->select(
                DB::raw("to_char(prom.updated_at, 'DD/MM/YYYY HH24:MI:SS') as datahora"),
                "prom.id",
                "us.name as usuario",
                "cli.nome as cliente",
                "prom.ausente",
                "prom.numero",
                "ru.descricao as rua",
                "ci.descricao as cidade"
            )
            ->get();

        return view("promotor.index", compact("promotores"));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Promotorvenda $pro)
    {
        $this->authorize("create", $pro);
        $data = $this->getCompact();
        $oldFields = Session::get("prom-oldFields");

        if (!is_null($oldFields)) {
            $data["rua"] = $oldFields["rua"];
            $data["rua_id"] = $oldFields["rua_id"];
            $data["cidade_id"] = $oldFields["cidade_id"];
        }

        return view("promotor.form", $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(PromotorRequest $request, Promotorvenda $pro)
    {
        $this->authorize("create", $pro);
        $data = $request->all();
        $dataProm = $this->dadosExtras($data);

        DB::beginTransaction();
        try {
            $foundClient = isset($data["cliente_id"]) && $data["cliente_id"] > 0;

            $dataProm["cliente_id"] = $foundClient ? $data["cliente_id"] : null;

            $promotor = $this->getPrevious($data);

            if (!$dataProm["ausente"]) {
                $data = $this->dadosExtrasClientes($data, $foundClient);

                $clientesC = new ClienteController();
                $newreq = new ClienteRequest();
                $newreq->replace($data);
                $newreq->setContainer(app())
                    ->setRedirector(app(\Illuminate\Routing\Redirector::class))
                    ->validate();

                $cliente = null;
                if ($foundClient) {
                    $cliente = $clientesC->update($newreq, $data["cliente_id"], (new Cliente()), true);
                } else {
                    $cliente = $clientesC->store($newreq, (new Cliente()), true);
                }

                $dataProm["cliente_id"] = $cliente->id;
            }

            if (is_null($promotor)) {
                Promotorvenda::create($dataProm);
            } else {
                $promotor->update($dataProm);
            }

            $oldFields = [];
            $oldFields["rua"] = $data["rua"];
            $oldFields["rua_id"] = $data["rua_id"];
            $oldFields["cidade_id"] = $data["cidade_id"];

            DB::commit();

            Session::flash("prom-oldFields", $oldFields);
            return \Redirect::to('promover/create');
        } catch (\Exception $ex) {
            DB::rollback();

            $errors = $ex->getMessage();
            if (isset($ex->validator)) {
                $errors = $ex->validator->errors();
            } else if (str_contains($errors, "Já existe um cliente cadastrado para este endereço")) {
                $errors .= ". Informe um complemento";
            }

            return \Redirect::to('/promover/create')->withErrors($errors)->withInput();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Promotorvenda $pro)
    {
        $this->authorize("view", $pro);
        return $this->showEdit($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id, Promotorvenda $pro)
    {
        $this->authorize("update", $pro);
        return $this->showEdit($id, false);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    private function showEdit($id, $show = true)
    {
        $promotor = Promotorvenda::from("promotorvendas as prom")
            ->leftJoin("clientes as cli", "prom.cliente_id", "cli.id")
            ->leftJoin("ruas as ruaprom", "prom.rua_id", "ruaprom.id")
            ->leftJoin("bairros as bairroprom", "prom.bairro_id", "bairroprom.id")
            ->leftJoin("ruas as ruacli", "cli.rua_id", "ruacli.id")
            ->leftJoin("bairros as bairrocli", "cli.bairro_id", "bairrocli.id")
            ->where("prom.id", $id)
            ->selectRaw(
                "prom.id, prom.cliente_id, prom.ausente, " .
                    "coalesce(prom.uf, cli.uf) as uf, " .
                    "coalesce(prom.cidade_id, cli.cidade_id) as cidade_id, " .
                    "coalesce(bairroprom.descricao, bairrocli.descricao) as bairro, " .
                    "coalesce(bairroprom.id, bairrocli.id) as bairro_id, " .
                    "coalesce(ruaprom.descricao, ruacli.descricao) as rua, " .
                    "coalesce(ruaprom.id, ruacli.id) as rua_id, " .
                    "coalesce(prom.numero, cli.numero) as numero, " .
                    "coalesce(prom.complemento, cli.complemento) as complemento, " .
                    "coalesce(prom.ponto_referencia, cli.ponto_referencia) as ponto_referencia, " .
                    "coalesce(prom.setor_id, cli.setor_id) as setor_id, " .
                    "cli.tipopessoa_id, cli.nome as cliente, cli.nome, cli.cpf, cli.rg, cli.cnpj, cli.inscricao_estadual, " .
                    "cli.segmento_id, cli.sexo, to_char(cli.datanascimento, 'DD/MM/YYYY') as datanascimento"
            )->first();

        $promotor->clienteProduto = [];
        if (!is_null($promotor->cliente_id)) {
            $promotor->clienteProduto = Clienteproduto::with("prod")->where("cliente_id", $promotor->cliente_id)->get();
        }

        $promotor->telefones = [];
        if (!is_null($promotor->cliente_id)) {
            $promotor->telefones = Clientetelefone::with("teltipo")->where("cliente_id", $promotor->cliente_id)->get();
        }

        $compact = $this->getCompact();
        $compact["promotor"] = $promotor;

        if ($show) $compact["show"] = $show;
        else $compact["edit"] = true;

        return view("promotor.form", $compact);
    }

    public function getClienteByNome()
    {
        $empresa_id = Session::get("empresa_padrao")->id;
        $cliente = str_encode_to_query(request()->get("nome"));
        $cidade_id = str_encode_to_query(request()->get("cidade_id"));

        $clientesDB = Cliente::with(
            "rua",
            "bairro",
            "telefones",
            "telefones.teltipo",
            "clienteProduto",
            "clienteProduto.prod",
            "clienteConvenio",
            "convenioempresa.clienteConvenio"
        )
            ->where("empresa_id", $empresa_id)
            ->where("cidade_id", $cidade_id)
            ->whereRaw("(" . rawTranslateSpecialChars("nome") . " LIKE '%$cliente%' OR "
                . rawTranslateSpecialChars("fantasia") . " LIKE '%$cliente%' " . ")")
            ->limit(5)
            ->get();

        $clientes = collect([]);
        foreach ($clientesDB as $cli) {
            $cli = $this->mountClientObject($cli, false, true);
            $clientes->push($cli);
        }

        return responseSuccess($clientes);
    }

    public function getStreet()
    {
        $grupo_id = Session::get("empresa_padrao")->grupo_id;
        $cidade_id = intVal(str_encode_to_query(request()->get("cidade_id")));
        $descricao = str_encode_to_query(request()->get("rua"));

        $streets = Rua::leftJoin("bairros", "ruas.bairro_id", "bairros.id")
            ->whereRaw(rawTranslateSpecialChars("ruas.descricao") . " LIKE '%$descricao%' AND ruas.cidade_id = '$cidade_id' "
                . "AND (ruas.grupo_id IS NULL OR ruas.grupo_id = $grupo_id)")
            ->select(
                "ruas.id as rua_id",
                "ruas.descricao as rua_desc",
                "bairros.id as bairro_id",
                "bairros.descricao as bairro_desc"
            )
            ->limit(10)
            ->get();

        return responseSuccess($streets);
    }

    public function getNeighborhood()
    {
        $grupo_id = Session::get("empresa_padrao")->grupo_id;
        $cidade_id = intVal(str_encode_to_query(request()->get("cidade_id")));
        $descricao = str_encode_to_query(request()->get("bairro"));

        $bairros = Bairro::whereRaw(rawTranslateSpecialChars("descricao") . " LIKE '%$descricao%' AND cidade_id = '$cidade_id' "
            . "AND (grupo_id IS NULL OR grupo_id = $grupo_id)")
            ->select("id as bairro_id", "descricao as bairro_desc")
            ->limit(10)
            ->get();

        return responseSuccess($bairros);
    }

    public function getClientByStreet()
    {
        $query = request()->all();
        $empresa_id = Session::get('empresa_padrao')->id;
        $rua_id = $query["rua_id"];
        $numero = $query["numero"];
        $cidade_id = intVal($query["cidade_id"]);

        $raw = "cidade_id = $cidade_id AND bairro_id IS NOT NULL"
            . " AND rua_id = $rua_id AND numero = $numero AND empresa_id = $empresa_id";

        $clientesDB = Cliente::with(
            "rua",
            "bairro",
            "telefones",
            "telefones.teltipo",
            "clienteProduto",
            "clienteProduto.prod",
            "clienteConvenio",
            "convenioempresa.clienteConvenio"
        )
            ->whereRaw($raw)
            ->limit(5)
            ->get();

        $clientes = collect([]);
        foreach ($clientesDB as $cli) {
            $cli = $this->mountClientObject($cli, false, true);
            $clientes->push($cli);
        }

        return responseSuccess($clientes);
    }

    public function getHistoricoCliente($cliente_id, $limit)
    {
        $pedCon = new PedidoController();
        $historico = $pedCon->historicoToCliente($cliente_id, $limit);

        return responseSuccess($historico);
    }

    private function mountClientObject($clienteDB, $load = true, $withRoad = false)
    {
        $fields = [
            "id",
            "nome",
            "datanascimento",
            "rg",
            "cpf",
            "sexo",
            "tipopessoa_id",
            "segmento_id",
            "cnpj",
            "inscricao_estadual",
            "setor_id",
            "bairro",
            "uf",
            "cidade_id",
            "telefones",
            "clienteProduto",
            "ponto_referencia",
            "complemento",
            "numero",
            "cidade_id"
        ];

        if (is_null($clienteDB)) {
            return null;
        }

        if ($load) $clienteDB = $clienteDB->load(
            "telefones",
            "telefones.teltipo",
            "clienteProduto",
            "clienteProduto.prod",
            "clienteConvenio",
            "convenioempresa.clienteConvenio"
        );

        $cliente = new stdClass();
        foreach ($fields as $field) {
            if (str_contains($field, "data")) {
                $cliente->$field = requestDataOracle($clienteDB->$field);
            } else {
                $cliente->$field = $clienteDB->$field;
            }
        }

        if (!is_null($clienteDB->convenioempresa)) {
            $cliente->conveniado = $clienteDB->convenioempresa->convenioativo == 1 || $clienteDB->convenioempresa->ativo != 1;
            $cliente->convenio = $cliente->conveniado ? $clienteDB->convenioempresa->nome : null;
        } else {
            $cliente->conveniado = false;
            $cliente->convenio = "";
        }

        if ($withRoad) {
            $cliente->rua = $clienteDB->rua;
        }

        return $cliente;
    }

    private function selectTipoPessoas()
    {
        $grupo_id = Session::get("empresa_padrao")->grupo_id;
        $tipopessoasdados = Tipopessoa::where('ativo', 1)->where('grupo_id', $grupo_id)->orderBy('descricao')->get();
        $tipopessoas = ['' => 'Selecione'];
        foreach ($tipopessoasdados as $tipopessoa) {
            $tipopessoas[$tipopessoa->id . $tipopessoa->tipopessoacadastro] = $tipopessoa->descricao;
        }
        return $tipopessoas;
    }

    private function dadosExtras($data)
    {
        $auxData = [];
        $isAusente = isset($data["ausente"]) && $data["ausente"];
        $auxData["empresa_id"] = Session::get("empresa_padrao")->id;
        $auxData["user_id"] = auth()->user()->id;
        $auxData["ausente"] = $isAusente;

        if ($isAusente) {
            $auxData["uf"] = Session::get("empresa_padrao")->uf;
            $auxData["cidade_id"] = $data["cidade_id"];
            $auxData["bairro_id"] = $data["bairro_id"];
            $auxData["setor_id"] = $data["setor_id"];
            $auxData["rua_id"] = $data["rua_id"];
            $auxData["numero"] = $data["numero"];
            $auxData["ponto_referencia"] = $data["ponto_referencia"];
            $auxData["complemento"] = $data["complemento"];
        } else {
            $auxData["uf"] = null;
            $auxData["cidade_id"] = null;
            $auxData["bairro_id"] = null;
            $auxData["setor_id"] = null;
            $auxData["rua_id"] = null;
            $auxData["numero"] = null;
            $auxData["ponto_referencia"] = null;
            $auxData["complemento"] = null;
        }

        return emptyToNull($auxData);
    }

    private function dadosExtrasClientes($data, $update)
    {
        $data["cliente"] = "1";
        $data["ativo"] = "1";

        if ($update) return $this->processUpdate($data);

        $data["uf"] = Session::get("empresa_padrao")->uf;

        return $data;
    }

    private function getCompact()
    {
        $empresa_id = Session::get("empresa_padrao")->id;
        $grupo_id = Session::get("empresa_padrao")->grupo_id;
        $compCidadeId = Session::get("empresa_padrao")->cidade_id;
        $cidades = Cidade::where("uf", Session::get("empresa_padrao")->uf)
            ->orderBy('descricao')
            ->pluck('descricao', 'id');
        $setores = Repository::getSetores($empresa_id);
        $segmentos = Repository::getSegmentos($grupo_id);
        $telefonetipos = Repository::getTelefoneTipos($grupo_id);
        $produtos = Repository::getProdutos($empresa_id);
        $tipopessoa = Tipopessoa::where([
            ['tipopessoacadastro', 'F'],
            ['ativo', 1],
            ['grupo_id', $grupo_id]
        ])->get()->pluck('id')->first() . 'F';
        $tipopessoas = $this->selectTipoPessoas();
        $descpara = $this->getDescontoPara();

        return compact("compCidadeId", "setores", "tipopessoa", "tipopessoas", "segmentos", "produtos", "telefonetipos", "descpara", "cidades");
    }

    private function processUpdate($data)
    {
        $cliente = Cliente::find($data["cliente_id"]);
        $cliente->load(['clienteConvenio']);

        $data["rg"] = $cliente->rg;
        $data["consumidor_final"] = $cliente->consumidor_final;
        $data["simples"] = $cliente->simples;
        $data["fornecedor"] = $cliente->fornecedor;
        $data["cliente"] = $cliente->cliente;
        $data["transportador"] = $cliente->transportador;
        $data["nfemite"] = $cliente->nfemite;
        $data["ativo"] = $cliente->ativo;
        $data["convenio"] = $cliente->convenio;
        $data["codigo_convenio"] = $cliente->codigo_convenio;
        $data["conveniolimite"] = $cliente->conveniolimite;

        if ($cliente->convenioativo && !is_null($cliente->clienteConvenio)) {
            $data["convenioativo"] = $cliente->convenioativo;

            $data["datacontrato"] = requestDataOracle($cliente->clienteConvenio->datacontrato);
            $data["diafechamento"] = $cliente->clienteConvenio->diafechamento;
            $data["diavencimento"] = $cliente->clienteConvenio->diavencimento;
            $data["comissao"] = $cliente->clienteConvenio->comissao;
            $data["comissaodestino"] = $cliente->clienteConvenio->comissaodestino;
            $data["limitecompra"] = $cliente->clienteConvenio->limitecompra;
            $data["cpfrepresentante"] = $cliente->clienteConvenio->cpfrepresentante;
            $data["rgrepresentante"] = $cliente->clienteConvenio->rgrepresentante;
            $data["nomerepresentante"] = $cliente->clienteConvenio->nomerepresentante;
        }

        return $data;
    }

    private function getPrevious($data)
    {
        $foundClient = isset($data["cliente_id"]) && $data["cliente_id"] > 0;

        if ($foundClient) return Promotorvenda::where("cliente_id", $data["cliente_id"])->first();

        return Promotorvenda::where("bairro_id", $data["bairro_id"])
            ->where("rua_id", $data["rua_id"])
            ->where("numero", $data["numero"])
            ->first();
    }

    private function getDescontoPara()
    {
        return [
            1 => "Todos",
            2 => "Aplicativo",
            3 => "Disk"
        ];
    }
}
