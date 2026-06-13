<?php

namespace App\Http\Controllers;

use App\Enums\LogCercaTipo;
use App\Logcerca;
use App\Repository\SelectRepository;
use App\Setor;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Session;
use stdClass;

class LogcercaController extends Controller
{

    public function index()
    {
        // $status = [
        //     ""  => "Todos",
        //     LogCercaTipo::Inside => LogCercaTipo::getDesc(LogCercaTipo::Inside),
        //     LogCercaTipo::Outside => LogCercaTipo::getDesc(LogCercaTipo::Outside),
        // ];
        $empresa_id = Session::get("empresa_padrao")->id;
        $repo = new SelectRepository($empresa_id);
        $setores = $repo->getSetores()->prepend("Selecione", "");
        $cercas = $this->getCercas($empresa_id);

        return view("reports.logcercas.index", compact("setores", "cercas"));
    }

    public function getPdfLog()
    {
        $empresa_id = Session::get("empresa_padrao")->id;
        $inicio = Carbon::createFromFormat("Y-m-d", request()->get("datainicio"))->startOfDay();
        $fim = Carbon::createFromFormat("Y-m-d", request()->get("datafim"))->endOfDay();
        $setor_id = request()->get("setor_id", null);
        $cerca_id = request()->get("cerca_id", null);
        $tipo = request()->get("tipo");
        $filtro = "Filtro: Período " . $inicio->format("d/m/Y") . " a " . $fim->format("d/m/Y");

        $binds = [
            "inicio" => $inicio->format("Y-m-d H:i:s"),
            "fim" => $fim->format("Y-m-d H:i:s"),
            "empresa_id" => $empresa_id,
        ];

        if (!is_null($setor_id)) {
            $setor = Setor::find($setor_id);
            $filtro .= ", Setor: " . $setor->descricao;
            $binds["setor_id"] = $setor_id;
        }

        if (!is_null($cerca_id)) {
            $cerca = Logcerca::where("cerca_id", $cerca_id)->first();
            $filtro .= ", Cerca: " . $cerca->cerca;
            $binds["cerca_id"] = $cerca_id;
        }

        $query = $this->getLogQuery(!is_null($setor_id), !is_null($cerca_id));

        $logs = DB::select($query, $binds);

        // $logcercas->transform(function ($log) {
        //     $log->entrada = Carbon::createFromFormat("Y-m-d H:i:s", $log->entrada)->format("d/m/Y H:i:s");

        //     if (!is_null($log->saida))
        //         $log->saida = Carbon::createFromFormat("Y-m-d H:i:s", $log->saida)->format("d/m/Y H:i:s");

        //     return $log;
        // });
        $logcercas = collect([]);

        $setor_id = null;
        foreach ($logs as $log) {
            if (is_null($setor_id) || $log->setor_id != $setor_id) {
                $obj = new stdClass();
                $obj->setor = $log->setor;
                $obj->setor_id = $log->setor_id;
                $obj->is_setor = true;
                $logcercas->push($obj);
            }
            $log->entrada = Carbon::createFromFormat("Y-m-d H:i:s", $log->entrada)->format("d/m/Y H:i:s");

            if (!is_null($log->saida))
                $log->saida = Carbon::createFromFormat("Y-m-d H:i:s", $log->saida)->format("d/m/Y H:i:s");

            $log->is_setor = false;
            $logcercas->push($log);

            $setor_id = $log->setor_id;
        }

        $titulo = "Relatório de Entrada/Saída de Setores";

        if ($tipo == 2) {
            $pdf = \App::make("dompdf.wrapper");
            $pdf->loadView("reports.logcercas.logcercas_pdf", compact("logcercas", "titulo", "filtro"));
            return $pdf->stream();
        }

        return view("reports.logcercas.logcercas_pdf", compact("logcercas", "titulo", "filtro"));
    }

    private function getCercas($empresa_id)
    {
        return Logcerca::where("empresa_id", $empresa_id)
            ->select("cerca_id AS id", "cerca AS descricao")
            ->groupBy("cerca_id", "cerca")
            ->orderBy("descricao")
            ->pluck("descricao", "id")
            ->prepend("Selecione", "");
    }

    private function getLogQuery($withSector, $withPolygon)
    {
        $sector = "";
        $polygon = "";

        if ($withSector) $sector = "AND lc.setor_id = :setor_id ";
        if ($withPolygon) $polygon = "AND lc.cerca_id = :cerca_id ";

        $query = "WITH logs_next AS (
                    SELECT lc.veiculo_id, lc.setor_id, lc.cerca_id, lc.COLABORADOR_ID,
                    lc.datahora AS entrada, lc.cerca, lc.latitude, lc.longitude, lc.created_at,
                    (
                        SELECT min(l2.datahora)
                        FROM logcercas l2
                        WHERE l2.empresa_id = :empresa_id
                        AND l2.tipo = 2
                        AND l2.veiculo_id = lc.veiculo_id
                        AND l2.setor_id = lc.setor_id
                        AND l2.cerca_id = lc.cerca_id
                        AND l2.datahora > lc.datahora
                    ) saida
                    FROM logcercas lc
                    WHERE lc.empresa_id = :empresa_id
                    AND lc.tipo = 1
                    AND lc.datahora BETWEEN to_date(:inicio, 'YYYY-MM-DD HH24:MI:SS')
                    AND to_date(:fim, 'YYYY-MM-DD HH24:MI:SS')
                    $sector
                    $polygon
                    ORDER BY lc.datahora, setor_id
                )
                SELECT
                lc.setor_id, lc.colaborador_id, lc.cerca_id, lc.cerca,
                st.descricao AS setor, col.nome AS colaborador, lc.entrada,
                lc.saida, ROUND((NVL(lc.saida, SYSDATE) - lc.entrada) * 24 * 60, 2) AS minutos,
                lc.latitude, lc.longitude
                FROM logs_next lc
                INNER JOIN setors st ON lc.setor_id = st.id
                INNER JOIN colaboradors col ON lc.colaborador_id = col.id
                ORDER BY lc.setor_id, lc.created_at DESC";

        return $query;
    }
}
