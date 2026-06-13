<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 28/08/2018
 * Time: 15:39
 */

namespace App\Helpers;

use App\Http\Resources\ApiResources;
use Carbon\Carbon;
use \Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Log;

class Util
{

    /**
     * @param $lat
     * @param $lng
     * @param Collection $latLgn
     * @return bool
     */
    public static function pointInPolygon($lat, $lng, $latLgn)
    {
        $polyCorners  = $latLgn->count();
        $polyX = $latLgn->pluck('latitude');
        $polyY = $latLgn->pluck('longitude');
        $j = $polyCorners - 1;
        $has = false;

        for ($i = 0; $i < $polyCorners; $j = $i++) {
            if ($polyY[$i] < $lng && $polyY[$j] >= $lng || $polyY[$j] < $lng && $polyY[$i] >= $lng) {
                if ($polyX[$i] + ($lng - $polyY[$i]) / ($polyY[$j] - $polyY[$i]) * ($polyX[$j] - $polyX[$i]) < $lat) {
                    $has = !$has;
                }
            }
        }

        return $has;
    }

    public static function getPayWays()
    {
        return collect([
            0 => "Dinheiro",
            1 => "Cartão de Débito",
            2 => "Cartão de Crédito",
            3 => "Vale Gás",
            4 => "Convênio",
            5 => "Cheque",
            6 => "Online",
        ]);
    }

    public static function getStatus()
    {
        return collect([
            0 => "Pendente",
            1 => "Motorista Saiu Para Entrega",
            2 => "Entregue",
            3 => "Cancelado"
        ]);
    }

    public static function convertImagesToBase64(&$values)
    {
        foreach ($values as &$value) {
            $value->base64Img = ! $value->thumbnail ? "" : "data:image/png;base64," . base64_encode($value->thumbnail);
            unset($value->thumbnail);
        }
    }

    /**
     * @param $message
     * @param string $level
     * @param null $user
     * @throws GuzzleException|Exception
     */
    public static function notify($message, $level = "error", $user = null)
    {
        try {
            if (strlen(Carbon::now()->toDateTimeString() . " | " . $message) > 250) {
                $message = str_limit($message, 250) ;
            }
            $url = "notify?message=" . urlencode($message) . "&level=" . $level;
            if ($user === null) {
                ApiResources::get([], $url);
            } else {
                $api = new ApiResources($user->erpurl . "api/", $user);
                $api->get([], $url);
            }
            try {
                Log::channel("api")->info($message . PHP_EOL);
            } catch (Exception $e) {}
        } catch (Exception $e) {
            try {
                static::log($e->getMessage(), $level);
            } catch (Exception $e) {}
        }
    }

    public static function log($message, $level = "debug")
    {
        try {
            switch ($level) {
                case "error":
                    Log::channel("debug")->debug($message . PHP_EOL);
                    break;
                case "warn":
                    Log::channel("warn")->debug($message . PHP_EOL);
                    break;
                case "sucess":
                    Log::channel("sucess")->debug($message . PHP_EOL);
                    break;
                default:
                    Log::channel("api")->info($message . PHP_EOL);
                    break;
            }
        } catch (Exception $e) {}
    }

    /**
     * @param \Illuminate\Http\Request $request
     */
    public static function logAccess($request)
    {
        try {
            $content = collect([]);
            $content->put("parameters", str_limit(\GuzzleHttp\json_encode($request->all()), 200));
            $content->put("uri", $request->server->get("REQUEST_URI"));
            $content->put("method", $request->server->get("REQUEST_METHOD"));
            $content->put("datetime", now()->toDateTimeString());
            $content->put("type", "access");
            static::saveLog($content);
        } catch (Exception $e) {}
    }

    /**
     * @param $message
     * @param $type
     * @param array $data
     */
    public static function logResponse($message, $type, $data = [])
    {
        try {
            $content = collect([]);
            $content->put("parameters", str_limit(\GuzzleHttp\json_encode($data), 200));
            $content->put("message", $message);
            $content->put("uri", request()->server->get("REQUEST_URI"));
            $content->put("method", request()->server->get("REQUEST_METHOD"));
            $content->put("datetime", now()->toDateTimeString());
            $content->put("type", $type);
            static::saveLog($content);
        } catch (Exception $e) {}
    }

    /**
     * @param Collection $content
     */
    public static function saveLog($content)
    {
        try {
            \DB::connection("sgcm_logs")->table("logs")->insert($content->toArray());
        } catch (Exception $e) {
            \Storage::disk("access")->append(
                    now()->toDateString() . "-errors.log",
                    "Erro ao salvar logs de acesso: " . str_limit($e->getMessage(), 200, ' (truncated) ') . PHP_EOL . $content
                );
        }
    }

    /**
     * @return false|string
     */
    public static function getUf()
    {
        return json_encode([
            ['uf' => 'AC', 'descricao' => 'Acre'],
            ['uf' => 'AL', 'descricao' => 'Alagoas'],
            ['uf' => 'AP', 'descricao' => 'Amapá'],
            ['uf' => 'AM', 'descricao' => 'Amazonas'],
            ['uf' => 'BA', 'descricao' => 'Bahia'],
            ['uf' => 'CE', 'descricao' => 'Ceará'],
            ['uf' => 'DF', 'descricao' => 'Distrito Federal'],
            ['uf' => 'ES', 'descricao' => 'Espírito Santo'],
            ['uf' => 'GO', 'descricao' => 'Goiás'],
            ['uf' => 'MA', 'descricao' => 'Maranhão'],
            ['uf' => 'MT', 'descricao' => 'Mato Grosso'],
            ['uf' => 'MS', 'descricao' => 'Mato Grosso do Sul'],
            ['uf' => 'MG', 'descricao' => 'Minas Gerais'],
            ['uf' => 'PA', 'descricao' => 'Pará'],
            ['uf' => 'PB', 'descricao' => 'Paraíba'],
            ['uf' => 'PR', 'descricao' => 'Paraná'],
            ['uf' => 'PE', 'descricao' => 'Pernambuco'],
            ['uf' => 'PI', 'descricao' => 'Piauí'],
            ['uf' => 'RJ', 'descricao' => 'Rio de Janeiro'],
            ['uf' => 'RN', 'descricao' => 'Rio Grande do Norte'],
            ['uf' => 'RS', 'descricao' => 'Rio Grande do Sul'],
            ['uf' => 'RO', 'descricao' => 'Rondônia'],
            ['uf' => 'RR', 'descricao' => 'Roraima'],
            ['uf' => 'SC', 'descricao' => 'Santa Catarina'],
            ['uf' => 'SP', 'descricao' => 'São Paulo'],
            ['uf' => 'SE', 'descricao' => 'Sergipe'],
            ['uf' => 'TO', 'descricao' => 'Tocantins']
        ]);
    }
}
