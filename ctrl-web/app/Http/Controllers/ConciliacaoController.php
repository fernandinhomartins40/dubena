<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResources;
use Cache;
use DB;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Session;

class ConciliacaoController extends Controller
{

    public function index()
    {
        return view("conciliacao.index");
    }

    public function getFinCont()
    {
        $inicio = Carbon::createFromFormat("d/m/Y", request()->get("inicio"))->startOfDay();
        $fim = Carbon::createFromFormat("d/m/Y", request()->get("fim"))->endOfDay();
        $pagarReceber = request()->get("pagarReceber");
        $empresa_id = Session::get("empresa_padrao")->id;

        $queryFin = $this->getQuery();
        $inicioFtd = $inicio->format("Y-m-d");
        $fimFtd = $fim->format("Y-m-d");
        $queryParam = "inicio={$inicioFtd}&fim={$fimFtd}&pagarReceber={$pagarReceber}";

        $saldos = Cache::remember("saldos" . $queryParam, 10, function () use ($queryParam) {
            return $this->getSaldos($queryParam);
        });

        $finDb = DB::select($queryFin, [
            "empresa_id" => $empresa_id,
            "inicio" => $inicio->format("Y-m-d H:i:s"),
            "fim" => $fim->format("Y-m-d H:i:s"),
            "pagarReceber" => $pagarReceber
        ]);

        $financeiros = $this->processData($finDb, $saldos, $pagarReceber);

        return response()->json([
            "data" => $financeiros,
        ]);
    }

    public function getFinDet()
    {
        $inicio = Carbon::createFromFormat("d/m/Y", request()->get("inicio"))->startOfDay();
        $fim = Carbon::createFromFormat("d/m/Y", request()->get("fim"))->endOfDay();
        $pagarReceber = request()->get("pagarReceber");
        $cliente_id = request()->get("cliente_id");

        $query = $this->getFinDetQuery();

        $detalhe = collect(DB::select($query, [
            "cliente_id" => $cliente_id,
            "inicio" => $inicio->format("Y-m-d H:i:s"),
            "fim" => $fim->format("Y-m-d H:i:s"),
            "pagarReceber" => $pagarReceber
        ]));

        $detalhe->transform(function ($item) {
            $data = Carbon::createFromFormat("Y-m-d H:i:s", $item->emissao)->format("d/m/Y");

            $item->emissao = $data;

            return $item;
        });

        return response()->json([
            "data" => $detalhe->all(),
        ]);
    }

    public function getContDet()
    {
        $inicio = Carbon::createFromFormat("d/m/Y", request()->get("inicio"))->startOfDay();
        $fim = Carbon::createFromFormat("d/m/Y", request()->get("fim"))->endOfDay();
        $pagarReceber = request()->get("pagarReceber");
        $cliente_id = request()->get("cliente_id");

        $inicioFtd = $inicio->format("Y-m-d");
        $fimFtd = $fim->format("Y-m-d");
        $queryParam = "inicio={$inicioFtd}&fim={$fimFtd}&pagarReceber={$pagarReceber}&cliente_id={$cliente_id}";

        $contabil = $this->getSaldos($queryParam, true);

        return response()->json([
            "data" => $contabil,
        ]);
    }

    private function getQuery()
    {
        $query = "SELECT id, descricao AS cliente, consisa_id, sum(valor) AS valor_fin, 0 AS valor_cont
            FROM (
                SELECT
                (CASE WHEN cli.consisa_id IS NULL THEN NULL ELSE cli.id END) AS id,
                (CASE WHEN cli.consisa_id IS NULL THEN 'S/CÓD CONTÁBIL' ELSE trim(cli.nome) END) AS descricao,
                cli.consisa_id,
                parc.VALOR
                FROM financeiros fin
                INNER JOIN financeiroparcelas parc ON parc.financeiro_id = fin.id
                LEFT JOIN clientes cli ON fin.CLIENTE_ID = cli.id
                WHERE fin.empresa_id = :empresa_id
                AND fin.DATAEMISSAO BETWEEN to_date(:inicio, 'YYYY-MM-DD HH24:MI:SS')
                AND to_date(:fim, 'YYYY-MM-DD HH24:MI:SS')
                AND parc.AGRUPAMENTO_STATUS <= 1
                AND parc.PAGARRECEBER = :pagarReceber
            ) parc
            GROUP BY id, descricao, consisa_id
            ORDER BY descricao";

        return $query;
    }

    private function getFinDetQuery()
    {
        $query = "SELECT parc.financeiro_id AS id, parc.valor, fin.documento,
            fin.dataemissao AS emissao, fin.datacompetencia
            FROM financeiros fin
            INNER JOIN financeiroparcelas parc ON parc.financeiro_id = fin.id
            LEFT JOIN clientes cli ON fin.CLIENTE_ID = cli.id
            WHERE cli.id = :cliente_id
            AND fin.DATACOMPETENCIA BETWEEN to_date(:inicio, 'YYYY-MM-DD HH24:MI:SS')
            AND to_date(:fim, 'YYYY-MM-DD HH24:MI:SS')
            AND parc.AGRUPAMENTO_STATUS <= 1
            AND parc.PAGARRECEBER = :pagarReceber";

        return $query;
    }

    private function getSaldos($queryParam, $isDet = false)
    {
        $apiUrl = env("CONSISA_API_URL");

        $api = new ApiResources($apiUrl);

        $api->setMethod("GET");

        $endpoint = $isDet ? "get_detalhe" : "get_contabil";

        try {
            $response = $api->request(null, "api/{$endpoint}?{$queryParam}", false, false, "application/json");

            return $response->data;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            $error_response = $ex->getResponse();

            $error = json_decode($error_response->getBody()->getContents());

            throw new \Exception($error->message);
        } catch (\Exception $ex) {
            throw new \Exception("Houve um erro ao buscar os dados do Sistema Contábil.");
        }
    }

    private function processData($financeiros, $saldos, $pagarReceber)
    {
        $saldos = collect($saldos)->keyBy(function ($item) {
            return is_null($item->id) ? -1 : $item->id;
        });

        foreach ($financeiros as $fin) {
            $key = is_null($fin->consisa_id) ? -1 : $fin->consisa_id;
            $saldo = null;

            if (isset($saldos[$key])) $saldo = $saldos[$key];

            if (is_null($saldo)) continue;

            $fin->credito = $saldo->credito;
            $fin->debito = $saldo->debito;
            $fin->diff = $pagarReceber == "R" ? $fin->valor_fin - $saldo->debito : $fin->valor_fin - $saldo->credito;
        }

        return $financeiros;
    }
}
