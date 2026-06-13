<?php

namespace App\Http\Controllers;

use App\{
    Helpers\Util,
    Repository\EnderecoRepository,
    Repository\PedidoAvaliacaoRepository as PedidoAvaliacao,
    Repository\UserRepository as User,
    Repository\UserRepository
};
use DB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Input;

class EmpresaController extends Controller
{

    /**
     * @param null|string|int $enderecopadrao_id
     * @return JsonResponse|string|UserRepository
     * @throws Exception
     */
    public function get($enderecopadrao_id = null)
    {
        try {

            if (is_null($enderecopadrao_id)) {
                $endereco_id = getOrFail("endereco_id");
            } else {
                $endereco_id = $enderecopadrao_id;
            }
            $endereco = EnderecoRepository::findOrFail($endereco_id, "Endereço");

            $lat = (float) $endereco->latitude;
            $lng = (float) $endereco->longitude;

            $this->throwIf(
                !$lng || !$lat,
                "Latitude e/ou Longitude do endereço não informada ou inválida, tente recadastrar seu endereço."
            );

            $users = $this->usersInCoords($lat, $lng, $endereco->uf);

            if ($users->count()) {
                if (!is_null($enderecopadrao_id)) {
                    return $users;
                }
                return responseSuccess($users);
            } else {
                $msg = "Nenhuma revenda aberta está disponível nessa Cidade e/ou Bairro.";
                if (!is_null($enderecopadrao_id)) {
                    return $msg;
                }

                return responseReject($msg, "DEFAULT", 419);
            }
        } catch (Exception $e) {
            if (!is_null($enderecopadrao_id)) {
                return $e->getMessage();
            }
            return responseError($e->getMessage());
        }
    }

    public function siGpAllowed()
    {
        //
        try {
            $user = auth("api")->user();

            if (is_null($user)) throw new \Exception("User não encontrado");

            return responseSuccess(["isAllowed" => $user->gaspovoativado == 1]);
        } catch (\Exception $ex) {
            return responseSuccess(["isAllowed" => false]);
        }
    }

    /**
     * @param $lat
     * @param $lng
     * @param $uf
     * @return Collection
     * @throws Exception
     */
    private function usersInCoords($lat, $lng, $uf)
    {
        $userPoligons = User::getAllowedToOrder($uf);
        $users = $userPoligons->unique("id");

        $usersInCoords = collect([]);

        foreach ($users as $user) {
            $poligonsActualUser = $userPoligons->where("id", $user->id);

            if (Util::pointInPolygon($lat, $lng, $poligonsActualUser)) {
                $usersInCoords->push([
                    "revenda_id"            => $user->id,
                    "revenda_nome"          => $user->fantasia,
                    "horariofuncionamento"  => substr($user->abertura, 0, 5) . " às " . substr($user->fechamento, 0, 5),
                    "horariodom"            => substr($user->domingohoraabertura, 0, 5) . " às " . substr($user->domingohorafechamento, 0, 5),
                    "enderecocompleto"      => $user->enderecocompleto,
                    "delivery_time"         => "Tempo de entrega é de " . $user->delivery_time_start . " a " . $user->delivery_time_end . " min.",
                    "delivery_res"          => $user->delivery_time_start . " a " . $user->delivery_time_end . " minutos.",
                    "avaliacao"             => $user->avaliacao ? $user->avaliacao : 0,
                    "totalavaliacoes"       => $user->totalavaliacoes ? $user->totalavaliacoes : 0,
                    "telefone"              => $user->telefone,
                    "permiteagendamento"    => $user->permiteagendamento,
                    //                    "base64Img"             => $user->thumbnail ? "data:image/png;base64," . $user->thumbnail : "",
                    "base64Img"             => "",
                    "whatsapp"              => env('REVENDA_WHATSAPP_NUMBER'),
                    "abertura"              => $user->abertura,
                    "fechamento"            => $user->fechamento,
                    "latitude"              => $user->rev_lat,
                    "longitude"             => $user->rev_long,
                    "valorfretegp"          => !is_null($user->valorfretegp) ? floatval($user->valorfretegp) : null,
                    "gaspovoativado"        => $user->gaspovoativado,
                ]);
            }
        }

        return $usersInCoords;
    }
}
