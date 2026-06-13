<?php

namespace App\Http\Controllers;

use App\Pedido;
use App\Pedidosituacao;
use App\Sorteio;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Session;
use Validator;

class SorteioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Sorteio $sor)
    {
        $this->authorize("view", $sor);

        $empresa_id = Session::get("empresa_padrao")->id;

        $sorteios = Sorteio::with("cliente")
            ->where("empresa_id", $empresa_id)
            ->get()
            ->transform(function ($it) {
                $it->nome = $it->cliente->nome;
                $it->datainicio = requestDataOracle($it->datainicio, false);
                $it->datafim = requestDataOracle($it->datafim, false);
                $it->datasorteio = requestDataOracle($it->datasorteio);

                return $it;
            });

        return view("sorteio.index", compact("sorteios"));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Sorteio $sor)
    {
        $this->authorize("create", $sor);

        return view("sorteio.form");
    }

    public function getPedidos()
    {
        $empresa_id = Session::get("empresa_padrao")->id;
        $inicio = request()->get("inicio");
        $fim = request()->get("fim");
        $app = request()->get("app", false);

        $datainicio = Carbon::createFromFormat("d/m/Y", $inicio)->startOfDay();
        $datafim = Carbon::createFromFormat("d/m/Y", $fim)->endOfDay();

        $pedidos = $this->getPedidosAptosQuery($empresa_id, $datainicio, $datafim, $app)
            ->select(
                "ped.id",
                "ped.cliente_id",
                "ped.datahoraprevisaoentrega as data",
                "cli.nome",
                DB::raw("(CASE WHEN ped.apipedido_id > 0 THEN 1 ELSE 0 END) is_app")
            )
            ->get()
            ->transform(function ($ped) {
                $ped->data = requestDataOracle($ped->data);
                $ped->is_app = $ped->is_app == 1;

                return $ped;
            });

        return responseSuccess($pedidos);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Sorteio $sor, Request $request)
    {
        $this->authorize("create", $sor);

        $validator = Validator::make($request->all(), [
            "datainicio" => "required|date_format:d/m/Y",
            "datafim" => "required|date_format:d/m/Y",
        ]);

        if ($validator->fails()) {
            return responseError(implode("<br>", $validator->errors()->all()));
        }

        try {
            $empresa = Session::get("empresa_padrao");
            $app = $request->has("app") ? 1 : 0;

            $datainicio = Carbon::createFromFormat("d/m/Y", $request->get("datainicio"))->startOfDay();
            $datafim = Carbon::createFromFormat("d/m/Y", $request->get("datafim"))->endOfDay();

            if ($datafim->lt($datainicio)) {
                return responseError("A data final deve ser maior ou igual a data inicial.");
            }

            $pedidos = $this->getPedidosAptosQuery($empresa->id, $datainicio, $datafim, $app)
                ->select(
                    "ped.id",
                    "ped.cliente_id",
                    "cli.nome"
                )
                ->orderBy("ped.id")
                ->get();

            if ($pedidos->count() === 0) {
                return responseError("Nenhum pedido apto encontrado para o sorteio.");
            }

            $indiceSorteado = random_int(0, $pedidos->count() - 1);
            $pedidoSorteado = $pedidos->get($indiceSorteado);

            DB::beginTransaction();

            $sorteio = Sorteio::create([
                "grupo_id" => $empresa->grupo_id,
                "empresa_id" => $empresa->id,
                "datainicio" => $datainicio,
                "datafim" => $datafim,
                "datasorteio" => Carbon::now(),
                "app" => $app,
                "pedido_id" => $pedidoSorteado->id,
                "cliente_id" => $pedidoSorteado->cliente_id,
            ]);

            DB::commit();

            return responseSuccess([
                "id" => $sorteio->id,
                "pedido_id" => $pedidoSorteado->id,
                "cliente_id" => $pedidoSorteado->cliente_id,
                "cliente_nome" => $pedidoSorteado->nome,
                "datasorteio" => requestDataOracle($sorteio->datasorteio),
                "app" => $app == 1,
            ], "Sorteio realizado com sucesso!");
        } catch (\Exception $e) {
            DB::rollback();

            return responseError("Erro ao realizar sorteio: " . $e->getMessage());
        }
    }

    private function getPedidosAptosQuery($empresa_id, $datainicio, $datafim, $app = false)
    {
        $situacoes = Pedidosituacao::where("empresa_id", $empresa_id)
            ->where("fechadoconcluido", 1)
            ->select("id")
            ->pluck("id")
            ->toArray();

        $pedidos = Pedido::from("pedidos ped")
            ->join("clientes cli", "ped.cliente_id", "cli.id")
            ->where("ped.empresa_id", $empresa_id)
            ->whereIn("ped.pedidosituacao_id", $situacoes)
            ->whereBetween("ped.datahoraprevisaoentrega", [$datainicio, $datafim]);

        if ($app) {
            $pedidos = $pedidos->whereRaw("ped.apipedido_id > 0 AND ped.apipedido_id IS NOT NULL");
        }

        return $pedidos;
    }
}
