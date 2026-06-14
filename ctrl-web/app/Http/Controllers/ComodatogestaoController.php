<?php

namespace App\Http\Controllers;

use App\Comodato;
use App\Produtoclasse;
use DB;
use Illuminate\Http\Request;
use Session;

class ComodatogestaoController extends Controller
{
    public function index()
    {
        return view("comodatos.gestao.index");
    }

    public function getSaldos()
    {
        $empresa_id = Session::get("empresa_padrao")->id;
        $classes = Produtoclasse::whereIn("tipo", ["G", "V"])->get();

        $query = $this->getQuerySaldos();

        $query = str_replace(":produto_classe", $classes->implode("id", ", "), $query);

        $saldos = DB::select($query, [":empresa_id" => $empresa_id]);

        return response()->json(["data" => $saldos]);
    }

    public function getComodatosVencimento()
    {
        $page = request()->get("page", 1);
        $size = request()->get("size", 10);
        $empresa_id = Session::get("empresa_padrao")->id;

        $comodatos = $this->getVencimentoQuery($empresa_id);

        $total = $comodatos->get()->count();

        $comodatos = $comodatos->orderBy(DB::raw("ordem, vencido DESC, com.datavencimento, cli.nome"))
            ->skip(($page - 1) * $size)
            ->take($size)
            ->get();

        return response()->json([
            "data" => $comodatos,
            "last_page" => ceil($total / $size)
        ]);
    }

    public function getGiro()
    {
        $empresa_id = Session::get("empresa_padrao")->id;

        $query = $this->getQueryGiro();

        $giro = DB::select($query, [":empresa_id" => $empresa_id]);

        return response()->json([
            "data" => $giro,
        ]);
    }

    public function getComodatosVencimentoPdf()
    {
        $empresa_id = Session::get("empresa_padrao")->id;

        $comodatos = $this->getVencimentoQuery($empresa_id)
            ->orderBy(DB::raw("ordem, vencido DESC, com.datavencimento, cli.nome"))
            ->get();

        $titulo = "Relatório de Vencimento de Comodatos";

        $pdf = \App::make("dompdf.wrapper");

        $pdf->loadView("reports.comodatos.gestao.vencidos_pdf", compact("comodatos", "titulo"));

        return $pdf->setPaper("A4", "landscape")->stream();
    }

    public function getGiroPdf()
    {
        $empresa_id = Session::get("empresa_padrao")->id;

        $query = $this->getQueryGiro();

        $giro = DB::select($query, [":empresa_id" => $empresa_id]);

        $titulo = "Relatório de Giro de Comodtas";

        $pdf = \App::make("dompdf.wrapper");

        $pdf->loadView("reports.comodatos.gestao.giro_pdf", compact("giro", "titulo"));

        return $pdf->setPaper("A4", "landscape")->stream();
    }

    private function getQuerySaldos()
    {
        $query = "SELECT produto_id, descricao,
            sum(qtde_estoque) AS qtde_estoque,
            sum(qtde_cliente) AS qtde_cliente,
            sum(qtde_comp) AS qtde_comp,
            (sum(qtde_estoque + qtde_cliente) - sum(qtde_comp)) AS saldo,
            (sum(qtde_estoque + qtde_cliente) - sum(qtde_comp)) * max(CUSTOMEDIO) AS valorsaldo
            FROM (
                SELECT pro.id AS produto_id, pro.descricao,
                sum(est.quantidade) AS qtde_estoque, 0 AS qtde_cliente, 0 AS qtde_comp, max(pro.CUSTOMEDIO) AS customedio
                FROM estoquesetors est
                INNER JOIN produtos pro ON est.produto_id = pro.id
                WHERE pro.produtoclasse_id IN (:produto_classe)
                AND est.empresa_id = :empresa_id
                GROUP BY pro.id, pro.descricao

                UNION ALL

                SELECT pro.id AS produto_id, pro.descricao, 0 AS qtde_estoque,
                sum(CASE WHEN com.tipo IN (0,1) THEN comit.quantidade ELSE 0 END) AS qtde_cliente,
                sum(CASE WHEN com.tipo = 2 THEN comit.quantidade ELSE 0 END) AS qtde_comp, max(pro.CUSTOMEDIO) AS customedio
                FROM comodatos com
                INNER JOIN comodatoitems comit ON comit.comodato_id = com.id
                INNER JOIN produtos pro ON comit.produto_id = pro.id
                WHERE pro.produtoclasse_id IN (:produto_classe)
                AND com.empresa_id = :empresa_id
                AND com.ativo = 1
                GROUP BY pro.id, pro.descricao
            ) est
            GROUP BY produto_id, descricao
            ORDER BY TO_NUMBER(REGEXP_SUBSTR(descricao, 'P([0-9]+)', 1, 1, NULL, 1)),
            (CASE WHEN descricao LIKE 'Glp%' THEN 1 WHEN descricao LIKE 'Vasilha%' THEN 2 ELSE 3 END)";

        return $query;
    }

    private function getVencimentoQuery($empresa_id)
    {
        $comodatos = Comodato::from("comodatos com")
            ->join("comodatoitems as comit", "comit.comodato_id", "com.id")
            ->join("clientes as cli", "com.cliente_id", "cli.id")
            ->where("com.empresa_id", $empresa_id)
            ->where("com.ativo", 1)
            ->select(
                "com.id",
                "com.cliente_id",
                "cli.nome",
                "com.nomerepresentante AS representante",
                "com.datavencimento",
                DB::raw("(CASE WHEN com.datavencimento < now() THEN 1 ELSE 0 END) AS vencido"),
                DB::raw("(CASE WHEN to_char(com.datavencimento, 'YYYY-MM') = to_char(now(), 'YYYY-MM') THEN 1 ELSE 2 END) AS ordem"),
                DB::raw("sum(comit.QUANTIDADE) AS quantidade")
            )
            ->groupBy("com.id", "com.cliente_id", "cli.nome", "com.nomerepresentante", "com.datavencimento");

        return $comodatos;
    }

    private function getQueryGiro()
    {
        $query = "SELECT id, cliente_id, nome,
            diff, qtde_comodato, qtde_compras,
            (CASE WHEN diff <> 0 THEN (qtde_compras / diff) ELSE 0 END) AS giro
            from(
                SELECT giro.id, giro.cliente_id,
                cli.nome,
                max(diff) AS diff,
                sum(qtde_comodato) AS qtde_comodato,
                sum(qtde_compras) AS qtde_compras
                FROM (
                    SELECT com.id, com.cliente_id, 0 AS diff,
                    sum(comit.quantidade) AS qtde_comodato, 0 AS qtde_compras
                    FROM comodatos com
                    INNER JOIN comodatoitems comit ON comit.comodato_id = com.id
                    WHERE com.empresa_id = :empresa_id
                    AND com.ativo = 1
                    AND com.tipo in (0, 1)
                    GROUP BY com.id, com.cliente_id

                    UNION ALL

                    SELECT com.id, com.cliente_id, com.diff,
                    0 AS qtde_comodato,
                    sum(pit.quantidade) AS qtde_compras
                    FROM (
                        SELECT com.id, com.cliente_id, com.datacontrato,
                        round(months_between(now(), com.datacontrato),0) AS diff,
                        coalesce(pro.id, comit.produto_id) AS produto_id
                        FROM comodatos com
                        INNER JOIN comodatoitems comit ON comit.comodato_id = com.id
                        LEFT JOIN produtos pro ON pro.produtoretornavel_id = comit.produto_id
                        WHERE com.empresa_id = :empresa_id
                        AND com.ativo = 1
                        AND com.tipo in (0, 1)
                    ) com
                    INNER JOIN pedidos ped ON com.cliente_id = ped.cliente_id
                    INNER JOIN pedidoitems pit ON pit.pedido_id = ped.id
                        AND pit.produto_id = com.produto_id
                    WHERE ped.datahoraprevisaoentrega BETWEEN
                    com.datacontrato AND now()
                    AND ped.pedidosituacao_id in (SELECT id FROM pedidosituacaos WHERE ENTREGAFINALIZADA = 1 OR FECHADOCONCLUIDO = 1)
                    GROUP BY com.id, com.cliente_id, com.diff
                ) giro
                INNER JOIN clientes cli ON giro.cliente_id = cli.id
                GROUP BY giro.id, giro.cliente_id, cli.nome
            ) qry
            ORDER BY giro DESC";

        return $query;
    }
}
