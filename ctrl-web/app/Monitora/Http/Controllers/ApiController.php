<?php

namespace App\Monitora\Http\Controllers;
use Exception;
use Session;
use Illuminate\Http\Request;
use App\Monitora\Models\Empresa;
use App\Monitora\Models\User;
use App\Monitora\Models\Position;
use App\Monitora\Models\Ultimaposicao;
use App\Monitora\Models\Veiculo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Ratchet\Client;

class ApiController extends Controller
{

    public function getUsuarios(Request $request)
    {
        $data = $request->all();
        $empresa = Empresa::find($data['revenda_id']);
        // FASE 1 (segurança): removido o campo 'password' (hash) da resposta.
        // Expor hashes de senha via API é vazamento de credenciais (S5).
        $users = User::where('empresa_id', $empresa->id)
            ->where('ativo', true)
            ->select('email', 'id', 'name')
            ->get();
        return json_encode(["status" => 'OK', "dados" => $users]);
    }

    public function getEmpresas(Request $request)
    {
        $data = $request->all();
        $empresa = Session::get('empresa_padrao');
        $registro = Array();
        $registro["codigo"] = $empresa->id;
        $registro["razao_social"] = $empresa->razao_social;
        $registro["nome_fantasia"] = $empresa->nome_fantasia;
        $registro["cnpj"] = $empresa->cnpj;
        $registro["inscricao_estadual"] = $empresa->inscricao_estadual;
        return json_encode(["status" => 'OK', "dados" => [$registro]]);
    }

    public function testarToken(Request $request)
    {
        // FASE 1 (segurança): a verificação `telefone == '123456'` era uma
        // "autenticação" fictícia (credencial trivial hardcoded). Substituída
        // por validação de token compartilhado (mesmo mecanismo do savePosition).
        if (! $this->validIntegrationToken($request)) {
            return response()->json(['status' => 'NOT'], 401);
        }
        return response()->json(['status' => 'OK'], 200);
    }

    /**
     * Valida o token compartilhado de integração (Traccar / serviços S2S).
     * O token vem do header `X-Integration-Token` ou do parâmetro `token`,
     * e é comparado em tempo constante com env('INTEGRATION_TOKEN').
     *
     * @param Request $request
     * @return bool
     */
    private function validIntegrationToken(Request $request)
    {
        $expected = (string) env('INTEGRATION_TOKEN', '');
        if ($expected === '') {
            // Sem token configurado no ambiente: nega por padrão (fail-safe).
            return false;
        }
        $provided = (string) ($request->header('X-Integration-Token', $request->input('token', '')));
        return hash_equals($expected, $provided);
    }

    /**
     * @param Request $request
     */
    public function savePosition(Request $request)
    {
        // FASE 1 (segurança): endpoint antes SEM autenticação (S3) — qualquer um
        // injetava posições GPS de qualquer veículo. Agora exige token de integração.
        if (! $this->validIntegrationToken($request)) {
            return response()->json(['status' => 'unauthorized'], 401);
        }

        // FASE 1: validação de input (antes os campos eram acessados direto).
        $validator = \Validator::make($request->all(), [
            'id'        => 'required|integer',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'altitude'  => 'nullable|numeric',
            'speed'     => 'required|numeric',
            'course'    => 'nullable|numeric',
            'fixTime'   => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'invalid', 'errors' => $validator->errors()], 422);
        }

        // FASE 1: só aceita posição de um veículo que exista para aquele device.
        $veiculo = Veiculo::where('deviceid', intval($request->input('id')))->first();
        if (is_null($veiculo)) {
            return response()->json(['status' => 'unknown_device'], 404);
        }

        $data = $request->all();
        $position = new Position();
        $position->deviceid = intval($data['id']);
        $position->latitude = $data['latitude'];
        $position->longitude = $data['longitude'];
        $position->altitude = isset($data['altitude']) ? $data['altitude'] : 0;
        $position->speed = $data['speed']*1.852;
        $position->course = isset($data['course']) ? $data['course'] : 0;
        $position->address = null;
        $position->dhposition = Carbon::createFromTimestamp(substr($data['fixTime'],0,10), 'America/Sao_Paulo');
        if($position->dhposition < Carbon::tomorrow()){
          $position->save();
          $ultimaposicao = Ultimaposicao::where('deviceid', $data['id'])->first();
          if($ultimaposicao == null){
              $veiculo = Veiculo::where('deviceid', intval($data['id']))->first();
              if($veiculo!=null){
                  $ult = new Ultimaposicao();
                  $ult->grupo_id = $veiculo->grupo_id;
                  $ult->empresa_id = $veiculo->empresa_id;
                  $ult->datahora = $position->dhposition;
                  $ult->veiculo_id = $veiculo->id;
                  $ult->latitude = $position->latitude;
                  $ult->longitude = $position->longitude;
                  $ult->altitude = $position->altitude;
                  $ult->azimute = $position->course;
                  $ult->velocidade = $position->speed;
                  $ult->deviceid = $data['id'];
                  $ult->save();
  //                $this->sendLastPositionAPI($ult);
              }
          } else {
              if($position->dhposition > $ultimaposicao->datahora ){
                  $ultimaposicao->datahora = $position->dhposition;
                  $ultimaposicao->latitude = $position->latitude;
                  $ultimaposicao->longitude = $position->longitude;
                  $ultimaposicao->altitude = $position->altitude;
                  if($position->course != 0){
                      $ultimaposicao->azimute = $position->course;
                  }
                  $ultimaposicao->velocidade = $position->speed;
                  $ultimaposicao->save();
  //                $this->sendLastPositionAPI($ultimaposicao);
              }
          }
        }
    }

    /**
     * @param Ultimaposicao $pos
     */
    private function sendLastPositionAPI($pos)
    {
        try {
//            $address = env('WEBSOCKET_ADDRESS_HOMOLOG', null);
//            $apiKey = env('KEY_API_GCM_HOMOLOG', null);
//
//            if (is_null($address)) {
//                $this->log("URL do Websocket não encontrada!");
//            } else {
//                $this->sendWsMessage($address, $pos, $apiKey);
//            }
            $addressProd = env('WEBSOCKET_ADDRESS_PROD', null);
            $apiKey = env('KEY_API_GCM_PROD', null);

            if (is_null($addressProd)) {
                $this->log("URL do Websocket em PRODUÇÃO não encontrada!");
            } else {
                $this->sendWsMessage($addressProd, $pos, $apiKey);
            }
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }
    }

    private function sendWsMessage($address, $pos, $apiKey)
    {
        Client\connect($address . "?client=monitoramento&app_key=" . sha1($apiKey))->then(
            function(Client\WebSocket $conn) use ($pos, $address) {
                $pos->load("veiculo");
                if ($pos->veiculo) {
                    $placa = $pos->veiculo->placa;
                    $motorista = $pos->veiculo->motorista;
                    $azimuth = $pos->azimute;
                } else {
                    $placa = " ";
                    $azimuth = 0;
                    $motorista = "Sem informações sobre o entregador";
                }
                $data = (object) [
                    "placa"         => $placa,
                    "motorista"     => $motorista,
                    "latitude"      => $pos->latitude,
                    "longitude"     => $pos->longitude,
                    "azimuth"       => $azimuth
                ];
                $conn->send(json_encode(
                    (object) [
                        "data"          => json_encode($data),
                        "event"         => "POSITION_UPDATED",
                        "data_format"   => "json"
                    ]
                ));
                $this->log("Enviou: " . $address);
                $conn->close();
            },
            function (Exception $e) {
                $this->log("Não foi possível realizar a comunicação com o Websocket: {$e->getMessage()}");
            }
        );
    }

    public function log($message)
    {
        try {
            $disk = Storage::disk('local');
            $now = Carbon::now();
            $filename = $now->format("mY") . ".log";
            $message = $now->toDateTimeString() . " | " . $message . PHP_EOL;
            if (! $disk->has($filename)) {
                $disk->put($filename, $message);
            } else {
                $disk->append($filename, $message);
            }
        } catch (Exception $e) {}
    }

}
