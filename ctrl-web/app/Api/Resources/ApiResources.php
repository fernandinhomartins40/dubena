<?php

/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 20/08/2018
 * Time: 17:26
 */

namespace App\Api\Resources;

use App\Helpers\Util;
use App\Api\Models\User;
use Auth;
use Exception;
use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use http\Exception\InvalidArgumentException;

/**
 * Class ApiResources
 * @package App\Http\Resources
 */
class ApiResources
{

    /**
     * @var User
     */
    private $user;

    /**
     * @var Client
     */
    private $guzzleClient;

    /**
     * Method to send request
     * @var string
     */
    private $method = "GET";

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
     * Headers of HTTP Request
     * @var array
     */
    private $notificationHeaders;

    /**
     * SCOPE required for FCM authorization
     */
    private $scope = "https://www.googleapis.com/auth/firebase.messaging";

    /**
     * ApiResources constructor.
     * @param string|null|array $baseUri
     * @param null $user
     * @param null $method
     */
    public function __construct($baseUri = null, $user = null,  $method = null)
    {
        if (is_array($baseUri)) {
            $this->setOptions($baseUri);
        } elseif (!defined("IS_CURL_OPT") || (defined("IS_CURL_OPT") && !constant("IS_CURL_OPT"))) {
            $this->setUser($user ? $user : Auth::user());
            $this->setClient($baseUri);
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
        if (!$this->guzzleClient) {
            $this->setClient();
        }

        if (
            ((array_key_exists("Authorization", $this->headers) && !$this->headers["Authorization"])
                || !array_key_exists("Authorization", $this->headers))
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

        $response = $this->guzzleClient->request($this->method, $uri, $data)->getBody()->getContents();

        try {
            $response = GuzzleHttp\json_decode($response);
        } catch (Exception $e) {
            if (env("APP_DEBUG")) {
                throw new Exception($response);
            } else {
                throw $e;
            }
        }

        return $response;
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

    /**
     * @param string $method
     * @return $this
     */
    public function setMethod($method)
    {
        if (!is_string($method)) {
            throw new \InvalidArgumentException(
                "The Method param need to be a string"
            );
        }
        if (!in_array($method, $this->methods)) {
            throw new \InvalidArgumentException(
                "The Method param is not accepted in [" . implode(",", $this->methods) . "]"
            );
        }

        $this->method = $method;

        return $this;
    }

    /**
     * @param null $code
     * @throws Exception
     */
    public function setAuthorizationCode($code = null)
    {
        if (!$this->user && !$code) {
            throw new Exception("Usuário ou código não definidos");
        }
        $this->headers["Authorization"] = $code ? $code : $this->user->erp_authorization;
    }

    /**
     * @param array $formParams
     * @param $information
     * @param string $name
     * @return \Illuminate\Support\Collection|mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     * @throws Exception
     */
    protected function getLinked(array $formParams, $information, $name = "dados")
    {
        return $this->get($formParams, $information . "/getLinked", $name);
    }

    /**
     * @param array $formParams
     * @param $url
     * @param string $name
     * @return \Illuminate\Support\Collection|mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     * @throws Exception
     */
    protected function get(array $formParams, $url, $name = "dados")
    {
        try {
            $results = $this->request($formParams, $url);
            if ($results->status !== "OK" && $results->status !== "OPS") {
                throw new Exception("Erro ao buscar " . $name . ": " . $results->msg);
            } else if ($results->status === "OPS") {
                return $results->msg ? $results->msg : null;
            } else {
                $results = collect($results->data);
            }

            return $results;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param array $formParams
     * @param $information
     * @param string $name
     * @return \Illuminate\Support\Collection|mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    public function getToLink(array $formParams, $information, $name = "dados")
    {
        $url = $information . "/getToLink" . $this->urlBuilder($formParams);

        return $this->get([], $url, $name);
    }

    /**
     * @param array $formParams
     * @param $information
     * @param string $name
     * @return \Illuminate\Support\Collection|mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    public function getData(array $formParams, $information, $name = "dados")
    {
        $url = $information . $this->urlBuilder($formParams);
        return $this->get([], $url, $name);
    }

    /**
     * @param array $formParams
     * @return string
     */
    protected function urlBuilder(array $formParams)
    {
        return urlBuilder($formParams);
    }

    /**
     * @param array $formParams
     * @param $url
     * @return \Illuminate\Support\Collection|mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    private function post(array $formParams, $url)
    {
        $this->setMethod("POST");
        return $this->send($formParams, $url);
    }

    /**
     * @param array $formParams
     * @param $information
     * @return \Illuminate\Support\Collection|mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    protected function link(array $formParams, $information)
    {
        return $this->post($formParams, $information . "/link");
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
        $results = $this->request($formParams, $url);

        return $results;
    }

    /**
     * @param array $formParams
     * @param $url
     * @return \Illuminate\Support\Collection|mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     * @throws Exception
     */
    private function sendNotifications(array $formParams, $url)
    {
        try {
            $results = $this->request($formParams, $url);

            if ($results->status !== "OK") {
                throw new Exception(" " . $results->msg);
            }

            return $results;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param null $baseUri
     */
    public function setClient($baseUri = null)
    {
        if (is_null($this->user)) return;

        if (!$baseUri) {
            $baseUri = $this->user->erpurl . "api/";
        }
        if ($this->isLocalEnv()) {
            //            $baseUri = str_replace("192.168.10.7", "localhost", $baseUri);
        }
        $this->guzzleClient = new Client([
            'base_uri' => $baseUri
        ]);
    }

    /**
     * @return bool
     */
    private function isLocalEnv()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * @param string $title The title of the notification
     * @param string $body The body of the notification
     * @param $to Registration Id or Topic
     * @param array $data Additional information
     * @param bool $returnResponse Should return the response
     * @param bool $isTopic Is it a topic?
     * @return \Illuminate\Http\JsonResponse
     */
    protected function notifyDevices(string $title, string $body, $to, $data = [], $returnResponse = false, $isTopic = false, $imageUrl = null)
    {
        try {
            $credentials = env("GASEMCASA_CRED");

            putenv("GOOGLE_APPLICATION_CREDENTIALS=$credentials");

            $data["content-available"] = "1";
            $fields = [
                "notification"  => [
                    "body"      => $body,
                    "title"     => $title,
                ],
                "android"       => [
                    "notification"  => [
                        "icon"      => "fcm_push_icon",
                    ],
                    "priority"  => "HIGH"
                ],
                "data"          => $data,
            ];

            if ($isTopic) $fields["topic"] = $to;
            else $fields["token"] = $to;

            if (!is_null($imageUrl)) {
                $fields["notification"]["image"] = $imageUrl;
                $fields["apns"] = [
                    "payload" => [
                        "aps" => [
                            "mutable-content" => 1,
                        ],
                    ],
                    "fcm_options" => [
                        "image" => $imageUrl
                    ]
                ];
                $fields["data"]["imageurl"] = $imageUrl;
            }

            $result = $this->sendNotificationRequest($fields, env("FCM_URL"));

            // $result = $response->getBody()->getContents();

            // if (is_string($result)) {
            //     $result = json_decode($result);
            // }

            if ($returnResponse) {
                return (object) [
                    "error"     => false,
                    "code"      => 200,
                    "message"   => "Success",
                ];
            }

            return responseSuccess($result);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();

            $content = $response->getBody()->getContents();

            $payload = json_decode($content);

            $error = $payload->error;

            Util::log('Notificação não enviada ' . $error->code . ' ' . $error->message);

            if ($returnResponse) {
                return (object) [
                    "error"     => true,
                    "code"      => $error->code,
                    "message"   => $error->message,
                ];
            }

            return responseError($error->code . ' ' . $error->message);
        } catch (\Exception $e) {
            $msg = 'Erro desconhecido ao enviar notificação ' . $e->getCode() . ' ' . $e->getMessage();

            Util::log($msg);

            if ($returnResponse) {
                return (object) [
                    "error"     => true,
                    "code"      => $e->getCode(),
                    "message"   => $e->getMessage(),
                ];
            }

            return responseError($e->getCode() . ' ' . $e->getMessage());
        }
    }

    protected function notifyDelivery($payload, $url)
    {
        try {
            $this->sendNotificationRequest($payload, $url);

            return (object) [
                "error"     => false,
                "code"      => 200,
                "message"   => "Success",
            ];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();

            $content = $response->getBody()->getContents();

            $payload = json_decode($content);

            $error = $payload->error;

            if ($e->getCode() != 404) {
                Util::log('Notificação não enviada aos entregadores ' . $error->code . ' ' . $error->message);
                Util::notify('Notificação não enviada aos entregadores ' . $error->code . ' ' . $error->message);
            }

            return (object) [
                "error"     => true,
                "code"      => $error->code,
                "message"   => $error->message,
            ];
        } catch (\Exception $e) {
            $msg = 'Erro desconhecido ao enviar notificação aos entregadores ' . $e->getCode() . ' ' . $e->getMessage();

            Util::log($msg);
            Util::notify($msg);

            return (object) [
                "error"     => true,
                "code"      => $e->getCode(),
                "message"   => $e->getMessage(),
            ];
        }
    }

    protected function sendNotificationRequest($fields, $url)
    {
        $middleware = ApplicationDefaultCredentials::getMiddleware($this->scope);
        $stack = HandlerStack::create();
        $stack->push($middleware);

        $client = new Client([
            "handler"   => $stack,
            "base_uri"  => "https://fcm.googleapis.com",
            "auth"      => "google_auth",
            "headers"   => ['Content-Type: application/json']
        ]);

        $response = $client->request("POST", $url, [
            "json" => [
                "message" => $fields
            ]
        ]);

        $result = $response->getBody()->getContents();

        if (is_string($result)) {
            $result = json_decode($result);
        }

        return $result;
    }

    /**
     * @param $user
     */
    protected function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return User
     */
    protected function getUser()
    {
        return $this->user;
    }

    /**
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return (new static(get_object_vars($this)))->$method(...$parameters);
    }
}

