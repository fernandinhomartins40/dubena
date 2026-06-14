<?php

namespace App\Http\Controllers;

use App\Setor;
use App\User;
use Carbon\Carbon;
use DB;
use Session;
use Illuminate\Http\Request;

class ReportpromotorController extends Controller
{

    public function indexAusentes()
    {
        $setores = $this->getSetores()->prepend("Selecione", "");

        return view("reports.promotores.ausentes.index")->with(compact("setores"));
    }

    public function getPdfAusentes(Request $request)
    {
        $inicio = Carbon::parse($request->get("datainicio"))->startOfDay();
        $fim = Carbon::parse($request->get("datafim"))->endOfDay();
        $setor_id = $request->get("setor_id");
        $isPdf = $request->get("tipo") == 2;

        $ausentes = $this->getAusentesData($inicio, $fim, $setor_id);

        $total = $ausentes->count();

        $setor = $setor_id == -1 ? "Todos" : Setor::find($setor_id)->descricao;

        $filtro = "Filtro: Período " . requestDataOracle($inicio, false) . " a " . requestDataOracle($fim, false);

        $filtro .= ", Setor: $setor";

        $titulo = "Relatório de Clientes Ausentes - Promotores";

        $compact = compact("ausentes", "filtro", "titulo", "total");

        if ($isPdf) {
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.promotores.ausentes.pdf', $compact);
            return $pdf->stream();
        }

        return view("reports.promotores.ausentes.pdf")->with($compact);
    }

    private function getAusentesData($inicio, $fim, $setor_id)
    {
        $empresa_id = Session::get("empresa_padrao")->id;

        $ausentesDB = DB::table("promotorvendas as pro")
            ->join("users as us", "pro.user_id", "us.id")
            ->join("cidades as ci", "pro.cidade_id", "ci.id")
            ->join("bairros as ba", "pro.bairro_id", "ba.id")
            ->join("ruas", "pro.rua_id", "ruas.id")
            ->join("setors as se", "pro.setor_id", "se.id")
            ->whereBetween("pro.created_at", [$inicio, $fim])
            ->where("pro.ausente", 1)
            ->where("pro.empresa_id", $empresa_id);

        if ($setor_id > 0) {
            $ausentesDB = $ausentesDB->where("pro.setor_id", $setor_id);
        }

        $ausentesDB = $ausentesDB->select(
            "pro.id",
            "us.name as responsa",
            "pro.created_at as visitado_em",
            "ci.descricao as cidade",
            "ba.descricao as bairro",
            "ruas.descricao as rua",
            "se.descricao as setor",
            "pro.numero",
            "pro.complemento",
            "pro.ponto_referencia"
        )
            ->orderBy("pro.created_at")
            ->get();

        return $ausentesDB;
    }

    public function indexVisitas()
    {
        $users = $this->getPromotores()->prepend("Selecione", "");
        $setores = $this->getSetores()->prepend("Selecione", "");

        return view("reports.promotores.visitas.index")->with(compact("users", "setores"));
    }

    public function getPdfVisitas(Request $request)
    {
        $inicio = Carbon::parse($request->get("datainicio"))->startOfDay();
        $fim = Carbon::parse($request->get("datafim"))->endOfDay();
        $user_id = $request->get("user_id");
        $user_id = $user_id == "null" ? null : $user_id;
        $setor_id = $request->get("setor_id", -1);
        $isPdf = $request->get("tipo") == 2;

        $visitados = $this->getVisitados($inicio, $fim, $user_id, $setor_id);

        $total = $visitados->count();

        $setor = $setor_id == -1 ? "Todos" : Setor::find($setor_id)->descricao;

        $filtro = "Filtro: Período " . requestDataOracle($inicio, false) . " a " . requestDataOracle($fim, false);

        if (!is_null($user_id)) {
            $promotor = User::find($user_id)->name;
        } else {
            $promotor = "Todos";
        }

        $filtro .= ", Promotor: $promotor";

        $filtro .= ", Setor: $setor";

        $titulo = "Relatório de Clientes Visitados - Promotores";

        $compact = compact("visitados", "filtro", "titulo", "total");

        if ($isPdf) {
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.promotores.visitas.pdf', $compact);
            return $pdf->stream();
        }

        return view("reports.promotores.visitas.pdf")->with($compact);
    }

    private function getPromotores()
    {
        $empresa_id = Session::get("empresa_padrao")->id;

        $users = User::rightJoin("promotorvendas as pro", "pro.user_id", "users.id")
            ->where("users.empresa_id", $empresa_id)
            ->select("users.id", "users.name as descricao")
            ->groupBy("users.id", "users.name")
            ->orderBy("users.name");

        return $users->pluck("descricao", "id");
    }

    private function getSetores()
    {
        $empresa_id = Session::get("empresa_padrao")->id;

        $setores = Setor::where(['empresa_id' => $empresa_id, 'ativo' => 1])->orderby('descricao')->pluck('descricao', 'id');

        return $setores;
    }

    private function getVisitados($inicio, $fim, $user_id, $setor_id)
    {
        $empresa_id = Session::get("empresa_padrao")->id;

        $visitadosDB = DB::table("promotorvendas as pro")
            ->join("users as us", "pro.user_id", "us.id")
            ->join("clientes as cli", "pro.cliente_id", "cli.id")
            ->join("cidades as ci", "cli.cidade_id", "ci.id")
            ->join("bairros as ba", "cli.bairro_id", "ba.id")
            ->join("ruas", "cli.rua_id", "ruas.id")
            ->leftJoin("setors as se", "cli.setor_id", "se.id")
            ->leftJoin("clienteprodutos as clipro", "clipro.cliente_id", "cli.id")
            ->leftJoin("produtos as prod", "clipro.produto_id", "prod.id")
            ->whereBetween("pro.updated_at", [$inicio, $fim])
            ->where("pro.empresa_id", $empresa_id)
            ->where("pro.ausente", 0);

        if (!is_null($user_id)) {
            $visitadosDB = $visitadosDB->where("pro.user_id", $user_id);
        }

        $visitadosDB = $visitadosDB->whereRaw("(cli.setor_id = $setor_id or $setor_id = -1)")
            ->select(
                "pro.id",
                "cli.nome as cliente",
                "ci.descricao as cidade",
                "ba.descricao as bairro",
                "ruas.descricao as rua",
                "se.descricao as setor",
                "cli.numero",
                "cli.complemento",
                "cli.ponto_referencia",
                "pro.created_at as visitado_em",
                "clipro.tipo as tipo_desconto",
                "prod.descricao as produto",
                DB::raw("(case when clipro.desconto <> 0 then clipro.desconto else clipro.preco end) as valor_negociado"),
                DB::raw("(case when clipro.desconto <> 0 then 'Desconto' else 'Fixo' end) as tipo_negociado")
            )
            ->orderBy("pro.created_at")
            ->orderBy("cli.nome")
            ->get();

        foreach ($visitadosDB as $visitado) {
            $visitado->visitado_em = requestDataOracle($visitado->visitado_em, false);

            if ($visitado->tipo_negociado == "Fixo") {
                $visitado->valor_negociado = requestNumeroDecimalOracle($visitado->valor_negociado);
            } else {
                $valor = $visitado->valor_negociado;
                $valor = $visitado->tipo_desconto == 1 ? requestNumeroDecimalOracle($valor) : requestPercentualOracle($valor * 100);
                $visitado->valor_negociado = $valor;
            }
        }

        return $visitadosDB;
    }
}
