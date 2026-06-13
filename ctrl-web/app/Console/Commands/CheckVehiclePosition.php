<?php

namespace App\Console\Commands;

use App\Enums\LogCercaTipo;
use App\Logcerca;
use App\Notificacaouser;
use App\Notificacoes;
use Carbon\Carbon;
use DB;
use Illuminate\Console\Command;

class CheckVehiclePosition extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:positions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks the position of vehicles and reports if outside polygon';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $setoresMon = $this->getSetoresMonitora();

        foreach ($setoresMon as $setor) {
            $veiculos = $this->getVeiculos($setor->setor_id);

            foreach ($veiculos as $vei) {
                $position = array_first($this->getUltimaPosicao($vei->veiculo_id));

                if (!is_null($position)) {
                    try {
                        $polygon = array_first($this->checkIfInside($setor->cerca_id, $position->lat, $position->lng));

                        if (is_null($polygon)) continue;

                        $ultimo = Logcerca::where("empresa_id", $vei->empresa_id)
                            ->where("veiculo_id", $vei->veiculo_id)
                            ->where("cerca_id", $setor->cerca_id)
                            ->latest()
                            ->first();

                        $dataHora = Carbon::createFromFormat("Y-m-d H:i:s", $position->datahora);

                        $tipo = $polygon->is_inside == 1 ? LogCercaTipo::Inside : LogCercaTipo::Outside;
                        $data = [
                            "grupo_id" => $vei->grupo_id,
                            "empresa_id" => $vei->empresa_id,
                            "setor_id" => $setor->setor_id,
                            "colaborador_id" => $vei->colaborador_id,
                            "veiculo_id" => $vei->veiculo_id,
                            "cerca" => $setor->cerca,
                            "cerca_id" => $setor->cerca_id,
                            "datahora" => $dataHora,
                            "placa" => $position->placa,
                            "veiculo" => $vei->descricao,
                            "motorista" => $position->motorista,
                            "latitude" => $position->lat,
                            "longitude" => $position->lng,
                            "tipo" => $tipo,
                        ];

                        $action = $tipo == LogCercaTipo::Outside ? "deixou o" : "voltou ao";
                        $msg = "{$position->motorista} {$action} Setor: {$setor->descricao} Cerca: {$setor->cerca}.";
                        $now = now("America/Sao_Paulo")->format("Y-m-d H:i:s");

                        if (is_null($ultimo)) {
                            Logcerca::create($data);
                            $this->notify($vei->empresa_id, $msg, $setor->cerca_id);
                            continue;
                        }

                        $curTipo = LogCercaTipo::createFrom($ultimo->tipo);
                        if ($curTipo != $tipo) {
                            Logcerca::create($data);
                            $this->notify($vei->empresa_id, $msg, $setor->cerca_id);
                            continue;
                        }
                    } catch (\Exception $ex) {
                        $this->info("Error ao checar cercas: " . $ex->getMessage());
                    }
                }
            }
        }
    }

    private function getSetoresMonitora()
    {
        $query = "SELECT se.descricao, cer.descricao AS cerca, cer.id AS cerca_id, se.setor_ctrlmais AS setor_id
            FROM setors se
            INNER JOIN cercas cer ON cer.setor_id = se.id
            INNER JOIN empresas emp ON se.empresa_id = emp.id
            WHERE emp.ativo = 1
            AND se.ativo = 1
            AND se.setor_ctrlmais IS NOT NULL
            ORDER BY setor_ctrlmais, cer.id";

        return DB::connection("monitora")->select($query);
    }

    private function getVeiculos($setor_id)
    {
        $query = "SELECT vei.id AS veiculo_id, se.descricao, vei.placa, vei.descricao,
            col.id AS colaborador_id, col.nome as colaborador, vei.grupo_id, vei.empresa_id
            FROM SETORS se
            INNER JOIN SETOR_VEICULO sv ON sv.SETOR_ID = se.id
            INNER JOIN VEICULOS vei ON sv.VEICULO_ID = vei.id
            INNER JOIN setorcolaboradores scol ON scol.SETOR_ID = se.id
            INNER JOIN colaboradors col ON scol.COLABORADOR_ID = col.id
            WHERE vei.USARASTREAMENTO = 1
            AND se.id = :setor_id
            ORDER BY se.DESCRICAO";

        return DB::select($query, ["setor_id" => $setor_id]);
    }

    private function getUltimaPosicao($veiculoerp_id)
    {
        $query = "SELECT vei.id AS veiculomonitora_id, ult.latitude AS lat, ult.longitude AS lng,
            ult.datahora, vei.placa, vei.motorista
            FROM ultimaposicaos ult
            INNER JOIN veiculos vei ON ult.veiculo_id = vei.id
            WHERE vei.veiculoerp_id = :veiculoerp_id
            ORDER BY ult.datahora DESC";

        return DB::connection("monitora")->select($query, ["veiculoerp_id" => $veiculoerp_id]);
    }


    private function checkIfInside($cerca_id, $lat, $lng)
    {
        $query = "SELECT cerca_id,
            ST_CONTAINS(wkt, ST_GEOMFROMTEXT('POINT($lat $lng)', 4326)) AS is_inside
            FROM (
                SELECT cerca_id,
                ST_GEOMFROMTEXT(
                    CONCAT(
                        'POLYGON((', GROUP_CONCAT(CONCAT(c.latitude, ' ', c.longitude) ORDER BY c.id SEPARATOR ','),
                        ',',
                        SUBSTRING_INDEX(GROUP_CONCAT(CONCAT(c.latitude, ' ', c.longitude) ORDER BY c.id SEPARATOR ','), ',', 1),
                        '))'
                    ),
                4326) AS wkt
                FROM cercas cer
                INNER JOIN cercapoligonos c ON c.cerca_id = cer.id
                WHERE cer.id = :cerca_id
                GROUP BY cerca_id
            ) is_in";

        return DB::connection("monitora")->select($query, ["cerca_id" => $cerca_id]);
    }

    private function notify($empresa_id, $msg, $cerca_id)
    {
        $users = $this->getUserAlert($empresa_id);

        foreach ($users as $user) {
            $notification = $this->getOrCreateNotification($user->grupo_id, $user->empresa_id, $msg, $cerca_id);

            $notify = Notificacaouser::where("user_id", $user->user_id)
                ->where("empresa_id", $user->empresa_id)
                ->where("notificacao_id", $notification->id)
                ->first();

            if (!is_null($notify)) {

                if ($notify->status == "N") continue;

                $notify->update(["status" => "N"]);

                continue;
            }

            $notify = new Notificacaouser();
            $notify->user_id = $user->user_id;
            $notify->empresa_id = $user->empresa_id;
            $notify->notificacao_id = $notification->id;
            $notify->tela = $notification->tela;
            $notify->status = "N";
            $notify->created_at = now();
            $notify->updated_at = now();

            $notify->save();
        }
    }

    private function getUserAlert($empresa_id)
    {
        $qry = "SELECT mu.user_id, em.id AS empresa_id, em.grupo_id
            FROM menus me
            INNER JOIN menuusers mu ON mu.menu_id = me.id
            INNER JOIN empresas em ON mu.empresa_id = em.id
            WHERE me.descricao = 'report.logcercas'
            AND mu.alerta = 1
            AND em.id = :empresa_id";

        return DB::select($qry, ["empresa_id" => $empresa_id]);
    }

    private function getOrCreateNotification($grupo_id, $empresa_id, $msg, $cerca_id)
    {
        $notification = Notificacoes::where("grupo_id", $grupo_id)
            ->where("empresa_id", $empresa_id)
            ->where("tela", "logcercas")
            ->where("identificador", $cerca_id)
            ->first();

        if (!is_null($notification)) return $notification;

        $noti = new Notificacoes();
        $noti->grupo_id = $grupo_id;
        $noti->empresa_id = $empresa_id;
        $noti->descricao = $msg;
        $noti->identificador = $cerca_id;
        $noti->tela = "logcercas";
        $noti->dangerlevel = 1;
        $noti->created_at = now();
        $noti->updated_at = now();
        $created = $noti->save();

        if ($created) return $noti;

        throw new \Exception("Houve um erro ao criar a notificação");
    }
}
