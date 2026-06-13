<?php

namespace App\Http\Resources;

use App\Http\Resources\Classes\AppConfig;
use App\Repository\MobileRepository;
use DB;
use Exception;
use GuzzleHttp;
use GuzzleHttp\Client;

class ApiResources
{

    /**
     * @var AppConfig|Collection
     */
    public $config;

    /**
     * @var Client
     */
    public $guzzleClient;

    /**
     * Method to send request
     * @var string
     */
    public $method = "GET";

    /**
     * contains all allowed methods to send request
     * @var array
     */
    private $methods = ["GET", "POST", "DELETE", "PUT", "PATCH"];

    /**
     * Headers of HTTP Request
     * @var array
     */
    private $headers = [
        'content_type'  => 'application/x-www-form-urlencoded',
        'Accept'        => 'application/json'
    ];

    /**
     * ApiResources constructor.
     * @param null $baseUri
     * @param null $config
     * @param null $method
     * @throws Exception
     */
    public function __construct($baseUri = null, $config = null, $method = null)
    {
        if (is_array($baseUri)) {
            $this->setOptions((array) $baseUri);
        } else {
            $this->setClient($baseUri, $config);
            if ($method) {
                $this->setMethod($method);
            }
        }
    }

    /**
     * @param array $options
     */
    private function setOptions(array $options)
    {
        foreach ($options as $key => $option) {
            if (property_exists($this, $key)) {
                $this->{$key} = $option;
            }
        }
    }

    public function setConfig(AppConfig $config = null)
    {
        if (is_null($config)) {
            $this->config = new AppConfig();
            $this->config->setConfig();
        } else {
            $this->config = $config;
        }
    }

    /**
     * @param null $baseUri
     * @param null $config
     * @throws Exception
     */
    public function setClient($baseUri = null, $config = null)
    {
        if (! $baseUri) {
            $this->setConfig();
            if (! $this->config) {
                throw new Exception("Configurações de comunicação com API ainda não foram definidas");
            }
            $baseUri = $this->config->api_url . "api/";
        } else {
            $this->config = $config;
        }

        $this->guzzleClient = new Client([
            'base_uri' => $baseUri
        ]);
    }

    /**
     * @param array $formParams
     * @param $url
     * @return \Illuminate\Support\Collection|mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     * @throws Exception
     */
    private function send(array $formParams, $url)
    {
        try {
            $results = $this->request($formParams, $url);

            if ($results->status !== "OK") {
                throw new Exception("Erro ao enviar requisição: " . $results->msg);
            }

            return $results;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param array $formParams
     * @param $information
     * @return \Illuminate\Support\Collection|mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     * @throws Exception
     */
    protected function link(array $formParams, $information)
    {
        $this->setClient();
        $formParams["user_id"] = $this->config->apiuser_id;
        return $this->post($formParams, $information . "/link");
    }

    /**
     * @param array $formParams
     * @param $url
     * @return \Illuminate\Support\Collection|mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    public function post(array $formParams, $url)
    {
        $this->setMethod("POST");
        return $this->send($formParams, $url);
    }

    /**
     * @param array $formParams
     * @return string
     */
    protected function urlBuilder(array $formParams)
    {
        $params = "";
        $glue = "?";
        foreach ($formParams as $key => $formParam) {
            $formParam = utf8FormatJson($formParam);
            $params .= $glue . $key . "=" . $formParam;
            $glue = "&";
        }
        return $params;
    }

    /**
     * @param string|null $code
     */
    public function setAuthorizationCode($code = null)
    {
        if (! $this->config) {
            $this->setConfig();
        }
        $this->headers["Authorization"] = $code ? $code : $this->config->api_authorization;
    }

    /**
     * @param string $method
     * @return $this
     */
    public function setMethod(?string $method)
    {
        if (! in_array($method, $this->methods)) {
            throw new \InvalidArgumentException(
                "The Method param is not accepted in [" . implode(",", $this->methods) . "]"
            );
        }
        $this->method = $method;
        return $this;
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function setHeader(array $headers)
    {
        $this->headers = $headers;
        return $this;
    }

    public function prepareAndSend($formParams, $uri, $isAuth = false)
    {
        $data = [
            "headers"   => $this->headers,
        ];

        if (!is_null($formParams)) {
            if ($isAuth) {
                $data["contentType"] = "application/x-www-form-urlencoded";
                $data["form_params"] = $formParams;
            } else {
                $data["json"] = $formParams;
                $data["contentType"] = "application/json";
            }
        }

        // if (env("APP_ENV") != "local") {
        $cert = storage_path("certificados" . DIRECTORY_SEPARATOR . "certificado_final.crt");
        $key = storage_path("certificados" . DIRECTORY_SEPARATOR . "ARQUIVO_CHAVE_PRIVADA.key");
        $data["cert"] = $cert;
        $data["ssl_key"] = $key;
        // }

        return $this->finalizeRequest($uri, $data);
    }

    /**
     * @param array $formParams
     * @param string $uri
     * @param bool $sendAuthorization
     * @param bool $processData
     * @param bool $contentType
     * @return mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     * @throws Exception
     */
    public function request($formParams, $uri, $sendAuthorization = true, $processData = false, $contentType = false)
    {
        if (! $this->guzzleClient) {
            $this->setClient();
        }

        if (
            ((array_key_exists("Authorization", $this->headers) && ! $this->headers["Authorization"])
                || ! array_key_exists("Authorization", $this->headers))
            && $sendAuthorization
        ) {
            $this->setAuthorizationCode();
        }

        $data = [
            'headers'           => $this->headers,
            'form_params'       => $formParams,
            'processData'       => $processData,
            'contentType'       => $contentType,
            'connect_timeout'   => 20
        ];

        return $this->finalizeRequest($uri, $data);
    }

    private function finalizeRequest($uri, $data)
    {
        $response = $this->guzzleClient->request($this->method, $uri, $data)->getBody()->getContents();

        try {
            $response = json_decode($response);
        } catch (Exception $e) {
            if (env("APP_DEBUG")) {
                throw new Exception($response);
            } else {
                throw $e;
            }
        }

        return $response;
    }

    protected function sendOrders()
    {
        try {
            DB::beginTransaction();

            $this->setMethod("POST");
            $sit = MobileRepository::orderTracking();

            $this->setClient();
            if (is_null($sit) || $sit->isEmpty()) {
                return "Nenhum Pedido";
            }

            $orderJson = (clone $sit)->filter(function ($i) {
                return $i->pendente == 0;
            })->unique("cod_pedido")->toJson();

            $formParams = [
                "user_id"   => $this->config->apiuser_id,
                "orders"    => $orderJson
            ];

            $cods = implode(", ", $sit->pluck("codigo_pedidos_ativou_status")->toArray());
            $raw = "id in (" . ($cods ? $cods : "-1") . ")";

            $this->send($formParams, "order/tracking");

            DB::table("pedidosituacaohistoricos")
                ->whereRaw($raw)
                ->update(["enviadoapi" => true]);

            DB::commit();
            return "Sucesso";
        } catch (Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
    }

    protected function sendExpiredPix($pedidos_id)
    {
        $this->setMethod("POST");

        $this->setClient();

        $formParams = [
            "user_id"       => $this->config->apiuser_id,
            "pedidos_id"    => $pedidos_id
        ];

        $this->send($formParams, "order/expired");
    }

    /**
     * @param $method
     * @param $parameters
     * @return mixed
     * @throws Exception
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * @param $method
     * @param $parameters
     * @return mixed
     * @throws Exception
     */
    public function __call($method, $parameters)
    {
        return (new static(get_object_vars($this)))->$method(...$parameters);
    }
}
