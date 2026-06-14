<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Carbon\Carbon;

class ReportVendasController extends Controller
{
    protected $empresa_id;

    protected $grupo_id;

    public function index()
    {
        return view("reports.vendas.geral.venda_geral");
    }

    public function filtroVendas()
    {
        $this->empresa_id = Session::get("empresa_padrao")->id;
        $this->grupo_id = Session::get("empresa_padrao")->grupo_id;

        $inicio = Carbon::parse(request()->get("datainicio"));
        $fim = Carbon::parse(request()->get("datafim"));
        $tipo = request()->get("tipo");
        $vendas = $this->getVendas($inicio, $fim);
        $titulo = "Relatório de Vendas Geral GLP";
        $filtro = "Filtro: Período " . requestDataOracle($inicio, false) . " a " . requestDataOracle($fim, false);

        if ($tipo == "1") return view("reports.vendas.geral.venda_geral_pdf")->with(compact("titulo", "filtro", "vendas"));

        $pdf = \App::make("dompdf.wrapper");

        $pdf->loadView("reports.vendas.geral.venda_geral_pdf", compact("titulo", "filtro", "vendas"));

        return $pdf->stream();
    }

    private function getVendas($inicio, $fim)
    {
        $inicio = $inicio->startOfDay();
        $fim = $fim->endOfDay();

        $situacao = DB::table("pedidosituacaos")
            ->where(["grupo_id" => $this->grupo_id, "ativo" => 1])
            ->whereRaw("(fechadoconcluido = 1 or entregafinalizada = 1)")
            ->pluck("id")
            ->toArray();

        $classe = DB::table("produtoclasses")
            ->where("grupo_id", $this->grupo_id)
            ->where("tipo", "G")
            ->pluck("id")
            ->toArray();

        $vendas = DB::table("pedidos as ped")
            ->join("pedidoitems as it", "it.pedido_id", "ped.id")
            ->join("produtos as pro", "it.produto_id", "pro.id")
            ->whereBetween("ped.datahoraprevisaoentrega", [$inicio, $fim])
            ->where("ped.empresa_id", $this->empresa_id)
            ->whereIn("pro.produtoclasse_id", $classe)
            ->whereIn("ped.pedidosituacao_id", $situacao)
            ->select(
                "pro.id as produto_id",
                "pro.descricao as produto",
                DB::raw("sum(it.quantidade) as quant"),
                DB::raw("(sum(it.precovendatotal - (coalesce(ped.valordesconto,0)) / (coalesce(ped.valordesconto,0) + ped.valorvenda) * it.precovendatotal)) as valor")
            )
            ->groupBy("pro.id", "pro.descricao")
            ->orderBy("pro.descricao")
            ->get();

        return $vendas;
    }
}
