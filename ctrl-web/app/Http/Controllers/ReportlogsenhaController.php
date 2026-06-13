<?php

namespace App\Http\Controllers;

use App\Enums\LogSenhaStatus;
use App\Logsenha;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Session;

class ReportlogsenhaController extends Controller
{

    public function index()
    {
        $status = [
            ""  => "Todos",
            "1" => LogSenhaStatus::Correto,
            "2" => LogSenhaStatus::Incorreto,
        ];

        return view("reports.logsenhas.index", compact("status"));
    }

    public function getPdfLog()
    {
        $empresa_id = Session::get("empresa_padrao")->id;
        $inicio = Carbon::createFromFormat("Y-m-d", request()->get("datainicio"))->startOfDay();
        $fim = Carbon::createFromFormat("Y-m-d", request()->get("datafim"))->endOfDay();
        $status = request()->get("status", null);
        $tipo = request()->get("tipo");
        $filtro = "Filtro: Período " . $inicio->format("d/m/Y") . " a " . $fim->format("d/m/Y");

        if (!is_null($status)) {
            $status = LogSenhaStatus::getFromID($status);
            $filtro .= ", Status: " . $status;
        }

        $logsenhas = Logsenha::from("logsenhas lg")
            ->join("users", "lg.user_id", "users.id")
            ->where("lg.empresa_id", $empresa_id)
            ->whereBetween("lg.datahora", [$inicio, $fim]);

        if (!is_null($status)) {
            $logsenhas = $logsenhas->where("status", $status);
        }

        $logsenhas = $logsenhas->select(DB::raw("lg.*"), "users.name as usuario")->get();

        $logsenhas->transform(function ($log) {
            $log->datahora = Carbon::createFromFormat("Y-m-d H:i:s", $log->datahora)->format("d/m/Y H:i:s");
            $log->tela = $this->tituloPorRota($log->rota);

            return $log;
        });

        $titulo = "Relatório de Uso de Senha Mestre";

        if ($tipo == 2) {
            $pdf = \App::make("dompdf.wrapper");
            $pdf->loadView("reports.logsenhas.logsenhas_pdf", compact("logsenhas", "titulo", "filtro"));
            return $pdf->stream();
        }

        return view("reports.logsenhas.logsenhas_pdf", compact("logsenhas", "titulo", "filtro"));
    }

    private function tituloPorRota($rota)
    {
        $titulo = [
            "cliente.edit" => "Edição de Clientes",
            "estoquerequisicao.index" => "Requisição de Estoque",
            "estoquesetor.create" => "Acerto de Estoque.",
            "consultaestoquesetor.index" => "Reabertura de Estoque.",
            "contasreceber.index" => "Contas a Pagar/Receber",
            "pedido.edit" => "Edição de pedido",
            "pedido.index" => "Edição de pedido",
            "vendavalegas.index" => "Venda de Vale Gás.",
        ];

        return isset($titulo[$rota]) ? $titulo[$rota] : null;
    }
}
