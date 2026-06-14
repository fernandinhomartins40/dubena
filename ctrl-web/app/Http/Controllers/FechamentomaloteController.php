<?php

namespace App\Http\Controllers;

use App\Condicaopagamento;
use App\Empresaconfig;
use App\Financeiro;
use App\Financeiroparcela;
use App\Pedido;
use App\Pedidosituacao;
use Illuminate\Http\Request;
use App\Repository\ClienteRepository as Repository;
use App\Services\CarbonCustom as Carbon;
use DB;
use Session;
use stdClass;

class FechamentomaloteController extends Controller
{

    // ? Baseado no NFC TPAG para checar se precisa ser baixada posteriormente
    // ? 02 - Cheque
    // ? 03 - Cartão de Crédito
    // ? 04 - Cartão de Débito
    private $disallowConditions = ["02", "03", "04"];

    public function index()
    {
        $empresa_id = Session::get("empresa_padrao")->id;

        $setores = Repository::getSetores($empresa_id);
        $colaboradores = Repository::getColaboradores($empresa_id);

        $condPagamento = Condicaopagamento::where("ativo", 1)
            ->where("empresa_id", $empresa_id)
            ->select("id", "descricao", "tipo")
            ->orderBy("descricao")
            ->get();

        // ? 5 => Vale Gás - CondicaopagamentoController
        $condValeGas = $condPagamento->where("tipo", 5)->pluck("id")->toJson();

        $condicoes = $condPagamento->pluck("descricao", "id")->toJson();

        return view("malote.fechamento.index", compact("setores", "colaboradores", "condicoes", "condValeGas", "empresa_id"));
    }

    public function getPedidos()
    {
        $empresa_id = Session::get("empresa_padrao")->id;
        $inicio = Carbon::createFromFormat("d/m/Y", request()->get("inicio"))->startOfDay();
        $fim = Carbon::createFromFormat("d/m/Y", request()->get("fim"))->endOfDay();
        $setor_id = request()->get("setor_id", -1);
        $colaborador_id = request()->get("colaborador_id", -1);
        $excecoes = Pedidosituacao::where("empresa_id", $empresa_id)
            ->where("fechadocancelado", 1)
            ->orWhere("fechadoconcluido", 1)
            ->get();

        $query = $this->getQuery();

        $query = str_replace(":sit_not_in", $excecoes->implode("id", ", "), $query);

        $pedidos = collect(DB::select($query, [
            "inicio" => $inicio->format("Y-m-d H:i:s"),
            "fim" => $fim->format("Y-m-d H:i:s"),
            "setor_id" => $setor_id,
            "colaborador_id" => $colaborador_id,
            "empresa_id" => $empresa_id
        ]));

        $condicoes = [];
        $valorTotal = 0;
        foreach ($pedidos as $ped) {
            if (isset($condicoes[$ped->condicao_id])) {
                $val = $condicoes[$ped->condicao_id]->valor;
                $val += $ped->valorvenda;
                $condicoes[$ped->condicao_id]->valor = $val;
            } else {
                $obj = new stdClass();
                $obj->id = $ped->condicao_id;
                $obj->descricao = $ped->condicao;
                $obj->valor = $ped->valorvenda;
                $condicoes[$ped->condicao_id] = $obj;
            }

            $valorTotal += $ped->valorvenda;

            $ped->cancelar = false;
            $ped->valegas = null;
        }

        $total = new stdClass();
        $total->id = -1;
        $total->descricao = "Total";
        $total->valor = $pedidos->count();
        $condicoes["total"] = $total;

        $valTotal = new stdClass();
        $valTotal->id = -2;
        $valTotal->descricao = "Valor Total";
        $valTotal->valor =  $valorTotal;
        $condicoes["valortotal"] = $valTotal;

        $conds = collect($condicoes)->sortBy("descricao");

        return response()->json([
            "condicoes" => $conds->all(),
            "pedidos" => $pedidos
        ]);
    }

    public function updatePedido(Request $request, $pedido_id)
    {
        $data = $request->all();

        DB::beginTransaction();
        try {
            $pedController = new PedidoController();
            $updated = $pedController->editFromMonitoramento($request, $pedido_id, 0, 0);

            if ($updated !== "OK|") throw new \Exception("Algo deu errado ao alterar o pedido: " . $updated);

            if ($data["cartaoautorizacao"]) {
                $financeiro = Pedido::find($pedido_id)->financeiro;

                if ($financeiro->cartaoautorizacao != $data["cartaoautorizacao"]) {
                    $financeiro->update(["cartaoautorizacao" => $data["cartaoautorizacao"]]);
                }
            }
            DB::commit();
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(["msg" => $ex->getMessage()], 400);
        }

        return $this->getPedido($pedido_id);
    }

    public function getProdPedido($pedido_id)
    {
        $pedido = Pedido::with("pedidoitem")->find($pedido_id);

        $pedidoitens = collect([]);
        foreach ($pedido->pedidoitem as $item) {
            for ($i = 0; $item->quantidade > $i; $i++) {
                $dados = (object) [];
                $dados->pedido_id = $pedido->id;
                $dados->produto_descricao = $item->produto->descricao;
                $dados->produto_id = $item->produto_id;
                $pedidoitens->push($dados);
            }
        }

        return response()->json(["itens" => $pedidoitens]);
    }

    public function getPedido($pedido_id)
    {
        $query = $this->getQuery(true);
        $updatedPed = DB::select($query, ["pedido_id" => $pedido_id]);

        return response()->json(["pedido" => array_first($updatedPed)]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $this->validateConfig();
            $empresa_id = Session::get("empresa_padrao")->id;
            $data = $request->all();
            $data["pedidos"] = json_decode($data["pedidos"]);
            // * 1° Parte de pedido
            $this->updatePedidoStatus($data);
            // * 2° Financeiro
            $condicoes = Condicaopagamento::where("ativo", 1)
                ->select("id", "contamovimentotipo_id", "nfc_tpag")
                ->where("empresa_id", $empresa_id)
                ->orderBy("descricao")
                ->get()
                ->keyBy("id");

            $this->processaFinanceiro($data, $condicoes);

            DB::commit();

            return "OK";
        } catch (\Exception $ex) {
            DB::rollBack();
            info($ex);
            return response()->json(["msg" => $ex->getMessage()], 400);
        }
    }

    public function getParcelas()
    {
        $parcelasStr = request()->get("parc", null);

        if (is_null($parcelasStr)) {
            return response()->json(["msg" => "Código das Parcelas são obrigatórios."], 400);
        }

        $parcela_ids = explode(",", $parcelasStr);
        $parcelas = Financeiroparcela::from("financeiroparcelas parc")
            ->join("financeiros as fin", "parc.financeiro_id", "fin.id")
            ->leftJoin("chequerecebidofinanceiros as chq", "chq.financeiroparcela_id", "parc.id")
            ->leftJoin("boletos as bo", "bo.financeiroparcela_id", "parc.id")
            ->whereIn("parc.id", $parcela_ids)
            ->select(
                "parc.id",
                "fin.documento",
                "parc.numero",
                "fin.dataemissao",
                "parc.datavencimento",
                "chq.numerocheque",
                "bo.nossonumero AS nossonumeroboleto",
                "parc.agrupamento_status",
                "parc.valor",
                "parc.desconto"
            )
            ->orderBy("parc.id")
            ->get();

        return response()->json(["parcelas" => $parcelas]);
    }

    private function getQuery($withPedId = false)
    {
        $whereStatement = " WHERE ped.pedidosituacao_id NOT IN (:sit_not_in)
            AND ped.datahoraprevisaoentrega BETWEEN to_date(:inicio, 'YYYY-MM-DD HH24:MI:SS')
            AND to_date(:fim, 'YYYY-MM-DD HH24:MI:SS')
            AND (ped.entregasetor_id = :setor_id OR :setor_id = -1)
            AND (ped.colaborador_id = :colaborador_id OR :colaborador_id = -1)
            AND ped.empresa_id = :empresa_id ";

        $whereStatementFinancial = "$whereStatement
            AND parc.agrupamento_status in (0,1) ";

        if ($withPedId) {
            $whereStatement = " WHERE ped.id = :pedido_id ";
            $whereStatementFinancial = " WHERE ped.id = :pedido_id
                AND parc.agrupamento_status in (0,1) ";
        }

        $query = "SELECT id, cliente, cliente_id, colaborador_id, entregasetor_id, endereco,
            valorvenda, pedidosituacao_id, status,
            condicao_id, condicao, max(financeiro_id) AS financeiro_id,
            max(cartaoautorizacao) cartaoautorizacao,
            (CASE WHEN max(parcelas) IS NOT NULL THEN '[{' || max(parcelas) || '}]' ELSE NULL END) AS parcelas
            FROM (
                SELECT ped.id, cli.nome AS cliente, cli.id as cliente_id,
                ped.colaborador_id, ped.entregasetor_id,
                (ru.descricao || ', ' || ped.entreganumero || ' - ' || ba.descricao) AS endereco,
                ped.valorvenda, ped.pedidosituacao_id, sit.descricao AS status,
                cond.id AS condicao_id, cond.descricao AS condicao,
                null AS financeiro_id, '' AS cartaoautorizacao, '' AS parcelas
                FROM pedidos ped
                INNER JOIN clientes cli ON ped.cliente_id = cli.id
                INNER JOIN cidades ci ON ped.entregacidade_id = ci.id
                INNER JOIN bairros ba ON ped.entregabairro_id = ba.id
                INNER JOIN ruas ru ON ped.entregarua_id = ru.id
                INNER JOIN condicaopagamentos cond ON ped.condicaopagamento_id = cond.id
                INNER JOIN pedidosituacaos sit ON ped.pedidosituacao_id = sit.id
                $whereStatement

                UNION ALL

                SELECT ped.id, cli.nome AS cliente, cli.id as cliente_id,
                ped.colaborador_id, ped.entregasetor_id,
                (ru.descricao || ', ' || ped.entreganumero || ' - ' || ba.descricao) AS endereco,
                ped.valorvenda, ped.pedidosituacao_id, sit.descricao AS status,
                cond.id AS condicao_id, cond.descricao AS condicao,
                fi.id AS financeiro_id, fi.cartaoautorizacao,
                listagg('\"id\": ' || parc.id || ', \"agrupamento\": ' || parc.agrupamento_status || ', \"baixado\": ' || parc.baixado
                        || ', \"valor\": ' || parc.valor || ', \"desconto\": ' || parc.desconto
                        || ', \"possui_boleto\": ' || (CASE WHEN bo.nossonumero IS NULL THEN 0 ELSE 1 END)
                        || ', \"possui_cheque\": ' || (CASE WHEN chq.numerocheque IS NULL THEN 0 ELSE 1 END), '}, {')
                        WITHIN GROUP (ORDER BY ped.id)
                        OVER (PARTITION BY ped.id) AS parcelas
                FROM pedidos ped
                INNER JOIN clientes cli ON ped.cliente_id = cli.id
                INNER JOIN cidades ci ON ped.entregacidade_id = ci.id
                INNER JOIN bairros ba ON ped.entregabairro_id = ba.id
                INNER JOIN ruas ru ON ped.entregarua_id = ru.id
                INNER JOIN condicaopagamentos cond ON ped.condicaopagamento_id = cond.id
                INNER JOIN pedidosituacaos sit ON ped.pedidosituacao_id = sit.id
                LEFT JOIN financeiros fi ON fi.documento = to_char(ped.id)
                LEFT JOIN financeiroparcelas parc ON parc.financeiro_id = fi.id
                LEFT JOIN chequeemitidofinanceiros chq ON chq.financeiroparcela_id = parc.id
                LEFT JOIN boletos bo ON bo.financeiroparcela_id = parc.id
                $whereStatementFinancial
            ) peds
            GROUP BY id, cliente, cliente_id, colaborador_id, entregasetor_id,
            endereco, valorvenda, pedidosituacao_id, status, condicao_id, condicao
            ORDER BY cliente";

        return $query;
    }

    private function validateConfig()
    {
        $empresa_id = Session::get("empresa_padrao")->id;
        $config = Empresaconfig::where("empresa_id", $empresa_id)->first();

        if (!$config->maloteconta_id) {
            throw new \Exception("Conta padrão para malotes não cadastrada.");
        }
    }

    private function updatePedidoStatus($data)
    {
        $pedidos = $data["pedidos"];
        $empresa_id = Session::get("empresa_padrao")->id;
        $cancelado = Pedidosituacao::where("empresa_id", $empresa_id)
            ->where("fechadocancelado", 1)
            ->first();
        $fechado = Pedidosituacao::where("empresa_id", $empresa_id)
            ->where("fechadoconcluido", 1)
            ->first();
        $pedController = new PedidoController();
        // * a Loop por todos os pedidos
        foreach ($pedidos as $pedido) {
            // * b pra cada pedido atualizar o status dependendo se for pra cancelar ou mandar pra fechado concluido
            $situacao = $pedido->cancelar ? $cancelado : $fechado;
            $valegas = "";
            if ($pedido->valegas) {
                $valegas = $pedido->valegas;
            }
            $informouVale = $valegas != "" ? 1 : 0;

            $payload = [
                "modalpedido_id" => $pedido->id,
                "modalcolaborador_id" => $pedido->colaborador_id,
                "modalsetor_id" => $pedido->entregasetor_id,
                "modalpedidosituacao_id" => $situacao->id,
                "produtosvalegas" => $valegas,
                "modalcondicaopagamento_id" => $pedido->condicao_id,
                "pedidomotivoatraso_id" => "",
            ];

            $newRequest = new Request();
            $newRequest->replace($payload);

            $isOK = $pedController->editFromMonitoramento($newRequest, $pedido->id, 0, $informouVale, true);

            if ($isOK != "OK|") {
                throw new \Exception($isOK);
            }
        }

        return true;
    }

    private function processaFinanceiro($data, $condicoes)
    {
        $config = Session::get("empresa_config");
        $conta_id = $config->maloteconta_id;
        $pedidos = $data["pedidos"];
        $empresa_id = Session::get("empresa_padrao")->id;
        $caixaCon = new CaixaController();
        // ? 4 => Convênio - CondicaopagamentoController
        // ? 5 => Vale Gás - CondicaopagamentoController
        $condPagamento = Condicaopagamento::where("ativo", 1)
            ->where("empresa_id", $empresa_id)
            ->whereIn("tipo", [4, 5])
            ->pluck("id")
            ->all();

        // * Loop por cada um dos pedidos
        foreach ($pedidos as $ped) {
            if ($ped->cancelar) continue;
            // * Pegar as parcelas e fazer verificação para checar se são cheque ou já estão baixadas;
            // $parcelasJson = collect(json_decode($ped->parcelas));
            // $parc_ids = $parcelasJson->pluck("id")->all();
            // $parcelas = Financeiroparcela::whereIn("id", $parc_ids)
            //     ->where("agrupamento_status", "<=", 1)
            //     ->where("baixado", 0)
            //     ->get();
            $parcelas = $this->getParcelasStore($ped->id);

            if ($parcelas->isEmpty() || in_array($ped->condicao_id, $condPagamento)) {
                info("Malote: Pedido {$ped->id} nâo foi possui parcela." . PHP_EOL);
                continue;
            }

            $valorTotal = 0;
            $parcelasArr = [];
            // * Loop pelas parcelas criando array e agregando valor
            foreach ($parcelas as $parc) {
                $condicao = $condicoes[$parc->condicao_id];
                $valorTotal += $parc->valor;

                if (in_array($condicao->nfc_tpag, $this->disallowConditions)) {
                    info("Malote baixa: Parcela {$parc->id} do pedido: {$ped->id} possui parcela que exige autorização de cartão");
                    $financeiro = Financeiro::find($parc->financeiro_id);
                    $financeiro->update(["cartaoautorizacao" => $ped->cartaoautorizacao]);
                    info("Malote baixa: Autorização '{$ped->cartaoautorizacao}' adicionado ao financeiro {$parc->financeiro_id}.");
                    $ped->cartaoautorizacao = "";
                    continue;
                }

                $venc = requestDataOracle($parc->datavencimento, false);
                array_push($parcelasArr, [
                    $parc->id,
                    "R",
                    $venc,
                    $ped->cliente,
                    "",
                    $parc->valor,
                    0,
                    $parc->valor
                ]);
            }
            // * Formar um objeto de request e baixar pelo método do Caixa
            $payload = [
                "conta_id" => $conta_id,
                "data_pagamentoM" => $data["data_fechamento"],
                "valor_desconto" => requestNumeroDecimalOracle(0),
                "valor_multa" => requestNumeroDecimalOracle(0),
                "valor_juros" => requestNumeroDecimalOracle(0),
                "parcelas" => json_encode(["data" => $parcelasArr]),
                "valor_total" => requestNumeroDecimalOracle($valorTotal),
                "valor_liquido" => requestNumeroDecimalOracle($valorTotal),
                "parcial" => "0",
                "valor_parcial" => requestNumeroDecimalOracle(0),
                "descricao" => "QUANTIDADE DE PARCELAS: " . count($parcelasArr),
                "baixarfechado" => "0"
            ];

            $caixaReq = new Request();
            $caixaReq->replace($payload);

            $isSaved = $caixaCon->baixar($caixaReq, false);

            if (!starts_with($isSaved, "OK")) {
                throw new \Exception($isSaved);
            }

            info("Malote: Parcelas " . json_encode($parcelasArr) . " baixadas com retorno {$isSaved}." . PHP_EOL);
        }
    }

    private function getParcelasStore($pedido_id)
    {
        $query = "SELECT par.id, financeiro_id, agrupador_id,
            nivel, par.valor, fin.condicaopagamento_id as condicao_id, datavencimento
            FROM (
                SELECT
                parc.id,
                parc.financeiro_id,
                parc.AGRUPADOR_FINANCEIRO_ID AS agrupador_id,
                parc.valor,
                parc.datavencimento,
                CONNECT_BY_ROOT parc.id AS root_financeiro_id,
                LEVEL AS nivel
                FROM financeiroparcelas parc
                WHERE parc.AGRUPADOR_FINANCEIRO_ID IS NULL
                CONNECT BY NOCYCLE PRIOR parc.AGRUPADOR_FINANCEIRO_ID = parc.financeiro_id
                START WITH parc.financeiro_id = (SELECT financeiro_id FROM pedidos WHERE id = ?)
            ) par
            INNER JOIN financeiros fin ON par.financeiro_id = fin.id";

        $parcelas = DB::select($query, [$pedido_id]);

        return collect($parcelas);
    }
}
