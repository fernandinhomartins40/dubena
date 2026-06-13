<?php

namespace App\Helpers\Utils;

use Log;
use Exception;
use App\Events\NotifySGC;
use Illuminate\Support\Collection;

class Util
{

    /**
     * @param $lat
     * @param $lng
     * @param $latLgn
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

    public static function notify($message, $level = "error")
    {
        event(new NotifySGC($message, $level));
    }

    public static function log($message, $level = "debug")
    {
        try {
            switch ($level) {
                case "error":
                    Log::debug($message . PHP_EOL);
                    break;
                case "warn":
                    Log::warning($message . PHP_EOL);
                    break;
                case "sucess":
                    Log::info($message . PHP_EOL);
                    break;
                default:
                    Log::info($message . PHP_EOL);
                    break;
            }
        } catch (Exception $e) { }
    }

    /**
     * returns string formated address
     * @param Collection $address
     * @return string
     */
    public static function formatAddress(Collection $address)
    {
        $comp = $address->get("complemento");
        $pontoRef = $address->get("pontoreferencia");
        return $address->get("rua") . ", " .
            $address->get("numero") . ". " . ($comp ? "Complem.: " . $comp . ". " : "") . ($pontoRef ? "Ponto Ref.: " . $pontoRef . ". " : "") .
            $address->get("bairro") . ". " .
            $address->get("cidade") . " - " . $address->get("uf") . " " .
            $address->get("cep");
    }

    /**
     * Method to log messages to different file
     *
     * @param string $msg
     * @param string $logFile = "clientes_api"  log file name
     * @return void
     */
    public static function logDifferentFile($msg, $logFile = "clientes_api")
    {
        Log::useFiles(storage_path() . "/logs/$logFile.log", 'info');
        Log::info($msg);
    }
}
