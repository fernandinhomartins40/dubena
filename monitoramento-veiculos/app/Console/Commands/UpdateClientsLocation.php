<?php

namespace App\Console\Commands;

use App\Config;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use stdClass;

class UpdateClientsLocation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update-clients-location {--p}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Limit
     *
     * @var string
     */
    protected $limitByQuery = 25;

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
        if ($this->option("p")) {
            $addresses = \DB::connection("mysql2")->table("tbl_pedidos as p")
                ->join("tbl_cidades as ci", "p.cod_cidade", "ci.codigo_cidade")
                ->join("tbl_ruas as r", "p.cod_rua_entrega", "r.codigo_rua")
                ->join("tbl_estados as uf", "ci.cod_estado", "uf.codigo_estado")
                ->selectRaw("p.cod_cliente as codigo_clientes, p.codigo_pedido, p.numero_entrega as numero, p.bairro_entrega as bairro, ci.desc_cidade as cidade, r.desc_rua as rua, uf.desc_uf as uf")
                ->whereRaw("(latitude = 0 OR latitude IS NULL OR longitude = 0 OR longitude IS NULL) AND data_pedido >= '2019-08-01 00:00:00'")
                ->limit($this->limitByQuery)->get();
        } else {
            $addresses = \DB::connection("mysql2")->table("tbl_clientes as c")
                ->join("tbl_cidades as ci", "c.cod_Cidades", "ci.codigo_cidade")
                ->join("tbl_ruas as r", "c.cod_rua", "r.codigo_rua")
                ->join("tbl_estados as uf", "ci.cod_estado", "uf.codigo_estado")
                ->selectRaw("c.codigo_clientes, c.numero, c.bairro, ci.desc_cidade as cidade, r.desc_rua as rua, c.CEP as cep, uf.desc_uf as uf")
                ->whereRaw("latitude = 0 OR latitude IS NULL OR longitude = 0 OR longitude IS NULL")
                ->limit($this->limitByQuery)->get();
        }
        if ($addresses->count() == 0) {
            $this->info("Nada para atualizar");
        } else {

            $key = Config::first()->keygooglemaps;
            foreach ($addresses as $address) {
                try {
                    $this->getLatLngFromAddress($address, $key);
                    $this->info("sucesso");
                    sleep(1);
                } catch (Exception $e) {
                    $message = Carbon::now()->toDateTimeString() . ": " . $e->getMessage();
                    $filename = "atualiza_posicoes_" . Carbon::now()->format("M-Y") . ".log";
                    if (Storage::disk("public")->has($filename)) {
                        Storage::disk("public")->append($filename, $message);
                    } else {
                        Storage::disk("public")->put($filename, $message);
                    }
                    $this->info($message);
                }
            }
        }
        return true;
    }

    /**
     * @param stdClass $address
     * @param $key
     * @param int $tries
     * @throws Exception
     */
    private function getLatLngFromAddress(stdClass &$address, $key, $tries = 0)
    {
        if ($tries === 0) {
            $addressString = urlencode(
                $address->numero . " " . $address->rua . ", " . (isset($address->cep) && $address->cep ? $address->cep . ", " : "")
                . $address->bairro . ", " . $address->cidade . ", " . $address->uf . ", Brazil"
            );
        } else if ($tries === 1) {
            $addressString = urlencode(
                $address->numero . " " . $address->rua . ", " . $address->bairro . ", " . $address->cidade . ", " . $address->uf . ", Brazil"
            );
        } else if ($tries === 2) {
            $addressString = urlencode(
                $address->rua . ", " . $address->bairro . ", " . $address->cidade . ", " . $address->uf . ", Brazil"
            );
        } else {
            $addressString = urlencode(
                $address->bairro . ", " . $address->cidade . ", " . $address->uf . ", Brazil"
            );
        }
        $urlMaps = "https://maps.google.com/maps/api/geocode/";
        $url = $urlMaps . "json?address=" . $addressString . "&sensor=false&key=" . $key;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $geocode = curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
            $response = json_decode($geocode);
            $results = $response->results;
            if ($this->responseIsValid($response, $results)) {
                $this->savePosition($address, $results);
            } else {
                if ($response->status == "ZERO_RESULTS" && $tries <= 3) {
                    $this->getLatLngFromAddress($address, $key, $tries + 1);
                } else {
                    curl_close($ch);
                    throw new Exception(isset($response->error_message) ? $response->error_message : json_encode($response));
                }
            }
        } else {
            curl_close($ch);
            throw new Exception("Error status code " . curl_getinfo($ch, CURLINFO_HTTP_CODE));
        }
        curl_close($ch);
    }

    /**
     * @param $response
     * @param $results
     * @return bool
     */
    private function responseIsValid($response, $results)
    {
        return $response->status == 'OK' && count($results) > 0 && isset($results[0]->geometry) && isset($results[0]->geometry->location);
    }

    /**
     * @param $address
     * @param $results
     */
    private function savePosition($address, $results)
    {

        if ($this->option("p")) {
            \DB::connection("mysql2")->table("tbl_pedidos as c")
                ->whereRaw("codigo_pedido = " . $address->codigo_pedido)
                ->update([
                    "latitude" => $results[0]->geometry->location->lat,
                    "longitude" => $results[0]->geometry->location->lng
                ]);
        } else {
            \DB::connection("mysql2")->table("tbl_clientes as c")
                ->whereRaw("codigo_clientes = " . $address->codigo_clientes)
                ->update([
                    "latitude" => $results[0]->geometry->location->lat,
                    "longitude" => $results[0]->geometry->location->lng,
                    "location_type" => $results[0]->geometry->location_type
                ]);
        }
    }
}
