<?php

namespace App\Api\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Helpers\Util;
use Carbon\Carbon;
use Input;
use Intervention\Image\ImageManagerStatic as Image;
use Exception;

class ApiController extends Controller
{

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAddressFromLatLng()
    {
        try {
            $address = getAddressFromLatLng(getOrFail("lat"), getOrFail("lng"));
            return responseSuccess($this->formatAddress($address[0]->address_components));
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function logs()
    {
        return view("logs.index", array("pageTitle" => "Relatório de Acessos na API", "jsFile" => "logs", "date" => now()->toDateString()));
    }

    /**
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function getLog()
    {
        try {
            $dateS = Carbon::parse(\Input::get("dateStart", now()->toDateString()))->startOfDay();
            $dateE = Carbon::parse(\Input::get("dateEnd", now()->toDateString()))->endOfDay();

            $results = new \stdClass();
            $results->logs = \DB::connection("sgcm_logs")->table("logs")
                ->whereBetween("datetime", [$dateS, $dateE])
                ->selectRaw("message, DATE_FORMAT(datetime,'%d/%m/%y %H:%i') as datetime, method, parameters, uri, type, DATE_FORMAT(datetime,'%d/%m/%y %H') as datehour ")
                ->groupBy([
                    "message", "method", "parameters", "uri", "type",
                    \DB::raw("DATE_FORMAT(datetime,'%d/%m/%y %H:%i')"), \DB::raw("DATE_FORMAT(datetime,'%d/%m/%y %H')")
                ])
                ->orderBy("type", "asc")
                ->orderBy("datetime", "desc")
                ->get()->unique(function ($log) {
                    return $log->datehour . $log->message . $log->method . $log->parameters . $log->uri . $log->type;
                });

            $results->reported = \DB::connection("sgcm_logs")
                ->table("reported_logs")
                ->selectRaw("DATE_FORMAT(datetime,'%d/%m/%y %H:%i') as datetime, content, DATE_FORMAT(datetime,'%d/%m/%y %H') as datehour ")
                ->whereBetween("datetime", [$dateS, $dateE])
                ->orderBy(\DB::raw("DATE_FORMAT(datetime,'%d/%m/%y %H:%i')"), "desc")
                ->get();

            return responseSuccess($results);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function reportLog()
    {
        try {
            \DB::connection("sgcm_logs")->table("reported_logs")->insert([
                "datetime"  => now(),
                "content"   => \Input::get("error", " ")
            ]);
            return responseSuccess([]);
        } catch (Exception $e) {
            Util::log($e->getMessage());
            return responseError($e->getMessage());
        }
    }

    public function downloadApp()
    {
        return redirect()->to(
            Input::get("platform", 'ios') === "ios"
                ? "https://itunes.apple.com/us/app/gás-em-casa/id1448242692?l=pt"
                : "https://play.google.com/store/apps/details?id=com.qti.gasemcasa"
        );
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function marker()
    {
        $name = \Input::get("name", "default");
        $s = DIRECTORY_SEPARATOR;

        $file = base_path() . $s . 'resources' . $s . 'assets' . $s . 'img' . $s . 'markers' . $s . $name . ".png";
        if (! file_exists($file)) {
            $file = str_replace($name, "default", $file);
        }
        return Image::make($file)->response();
    }

    /**
     * @param $address
     * @return \stdClass
     */
    public function formatAddress($address)
    {
        $formatted = new \stdClass();
        $formatted->countryCode = "";
        $formatted->countryName = "";
        $formatted->postalCode = "";
        $formatted->uf = "";
        $formatted->subAdministrativeArea = "";
        $formatted->subLocality = "";
        $formatted->subThoroughfare = "";
        $formatted->thoroughfare = "";
        foreach ($address as $ad) {
            if (in_array("country", $ad->types)) {
                $formatted->countryCode = $ad->short_name;
                $formatted->countryName = $ad->long_name;
            } elseif (in_array("postal_code", $ad->types)) {
                $formatted->postalCode = $ad->long_name;
            } elseif (in_array("administrative_area_level_1", $ad->types)) {
                $formatted->uf = $ad->short_name;
            } elseif (in_array("administrative_area_level_2", $ad->types)) {
                $formatted->subAdministrativeArea = $ad->long_name;
            } elseif (in_array("sublocality", $ad->types)) {
                $formatted->subLocality = $ad->long_name;
            } elseif (in_array("route", $ad->types)) {
                $formatted->thoroughfare = $ad->long_name;
            } elseif (in_array("street_number", $ad->types)) {
                $formatted->subThoroughfare = $ad->long_name;
            }
        }
        return $formatted;
    }
}

