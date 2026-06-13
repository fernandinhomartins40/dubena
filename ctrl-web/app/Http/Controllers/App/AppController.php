<?php

namespace App\Http\Controllers\App;

use App\Exceptions\RejectedException;
use App\Helpers\Utils\Util;
use App\Http\Controllers\Auth\OauthClientController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResources;
use Illuminate\Http\Request;
use App\Http\Resources\Classes\AppConfig;
use App\Pedido;
use App\Pixpedido;
use App\Pixtransaction;
use App\Processors\MobileAppProcessor;
use App\Repository\MobileRepository;
use App\Services\CarbonCustom as Carbon;
use App\User;
use Exception;
use Auth;
use DB;
use Illuminate\Support\Facades\Input;

class AppController extends Controller
{

    private $user;

    public function storeConfig(Request $request)
    {
        try {
            $post = $request->all();

            $this->authAttempt($post);

            $config = new AppConfig();
            $config->setConfig();

            $data = $this->setData($config, $post);

            $response = ApiResources::link($data["configAPI"], "config");
            if ($response->status === "OPS") {
                throw new Exception($response->msg ? $response->msg : "Erro desconhecido ao vincular usuário com API.");
            } elseif ($response->status === "NOK") {
                throw new Exception($response->message ? $response->message : "Erro desconhecido ao vincular usuário com API.");
            } else {
                return $response->data;
            }
        } catch (Exception $ex) {
            return responseError($ex->getMessage());
        }
    }

    private function authAttempt($post)
    {
        $validated = true;

        if (!isset($post["email"]))
            $validated = false;
        if (!isset($post["password"]))
            $validated = false;

        $this->throwIf(!$validated, "Email or password missing!", 401);

        $attempt = Auth::attempt(['email' => $post["email"], 'password' => $post["password"]]);

        $this->throwIf(!$attempt, "Wrong email or password");

        $this->user = User::whereEmail($post["email"])->first();
    }

    private function setData(AppConfig $config, $post)
    {
        $data = [];
        $config->setDescriptions();

        $token = $this->getToken($post);

        $latLng = $this->getLatLng($config);

        $data["configAPI"] = [
            "id"                    => 2,
            "name"                  => "Distribuidora Dubena",
            "email"                 => "nfe@grupodubena.com.br",
            "erpempresa_id"         => $config->empresa_id,
            "erpurl"                => str_finish(url('/'), '/'),
            "admin"                 => 0,
            "ativo"                 => 1,
            "updated_at"            => Carbon::now()->toDateTimeString(),
            "erp_authorization"     => $token,
            "serviceuser_id"        => $this->user->id,
            "permiteagendamento"    => 0,
            "fantasia"              => $config->nomeEmpresaApp,
            "semanahoraabertura"    => $config->horaUteis["open"],
            "semanahorafechamento"  => $config->horaUteis["close"],
            "sabadohoraabertura"    => $config->horaSabado["open"],
            "sabadohorafechamento"  => $config->horaSabado["close"],
            "domingohoraabertura"   => $config->horaDomingos["open"],
            "domingohorafechamento" => $config->horaDomingos["close"],
            "feriadohoraabertura"   => $config->horaFeriado["open"],
            "feriadohorafechamento" => $config->horaFeriado["close"],
            "uf"                    => $config->uf,
            "latitude"              => $latLng->location->lat,
            "longitude"             => $latLng->location->lng,
            "enderecocompleto"      => "$config->ruaDesc - $config->numero, $config->bairroDesc, $config->cidadeDesc - $config->uf",
            "avaliacao"             => 0,
            "telefone"              => $config->telefone,
            "delivery_time_start"   => $config->tempoEntregaMin,
            "delivery_time_end"     => $config->tempoEntregaMax,
            "password"              => $post["password"],
            "cidade_desc"           => $config->cidadeDesc,
            "bairro_desc"           => $config->bairroDesc,
            "rua_desc"              => $config->ruaDesc,
        ];

        $data["config"] = [
            "api_authorization" => $post["api_authorization"],
            "api_url"           => $config->api_url,
            "keygooglemaps"     => $config->keygooglemaps
        ];

        return $data;
    }

    // public function getTokenToken(Request $request)
    // {
    //     $data = $request->all();

    //     $this->authAttempt($data);

    //     $token = $this->getToken($data);

    //     return responseSuccess($token);
    // }

    private function getToken($data)
    {
        $oauthCon = new OauthClientController();

        if (!$this->user->oauth->first()) {
            $oauthCon->exclude($this->user->idate);
            $oauthCon->store($this->user, $data["password"]);
        }

        $oauth = $this->user->oauth->first();

        $token = $oauthCon->getAuthorizationToken($this->user, $oauth, $data["password"]);

        return $token->token_type . " " . $token->access_token;
    }

    private function getLatLng(AppConfig $config)
    {
        $latLgn = getGMapsLatLgn($config->uf, $config->cidadeDesc, $config->bairroDesc, $config->ruaDesc, $config->numero, $config->keygooglemaps, $config->cep);

        if ($latLgn->location_type === "not found" || !isset($latLgn->location->lat) && !isset($latLgn->location->lng)) {
            throw new Exception("Impossível localizar a latitude e longitude da sua empresa, " .
                "verifique seu endereço e suas credencias do Google Maps e tente novamente: " . $latLgn->error);
        }

        return $latLgn;
    }

    public function testTokenApi()
    {
        return responseSuccess([], "Success!");
    }

    public function testTokenERP()
    {
        try {
            $config = new AppConfig();
            $config->setConfig();

            $url = $config->api_url;

            $url = (substr($url, -1) != '/' ? $url . '/' : $url) . "api/";

            $api = new ApiResources($url);

            $api->setAuthorizationCode($config->api_authorization);

            $response = $api->request([], "testTokenERP");

            return responseSuccess($response, "Success!");
        } catch (Exception $e) {
            $code = $e->getCode();
            if ($code === 401) {
                $msg = "Usuário não autorizado a enviar a requisição, " .
                    "certifique-se de que o token e a url estão corretos. Cód. Erro " . $code .
                    ". Mensagem: " . substr($e->getMessage(), 0, 150);
                throw new Exception($msg);
            } else {
                throw new Exception($e->getMessage());
            }
        }
    }

    public function createOrder(Request $request)
    {
        try {
            $auth = new AuthController();

            $authData = [
                "email"     => env("DEFAULT_USER_SYSTEM"),
                "password"  => env("DEFAULT_PASSWORD_SYSTEM")
            ];

            $logged = $auth->loginFromApi($authData);

            $this->throwIf(!$logged, "Wrong username or password!", 401);

            $msg = "Incorrect values: ";
            $data = $request->only($this->paramsLink);

            $this->throwIf(
                !array_key_exists("results", $data),
                $msg . "the param \"results\" is necessary."
            );
            $this->throwIf(
                !array_key_exists("user_id", $data),
                $msg . "the param \"user_id\" is necessary."
            );
            $resultsApi = collect(json_decode($data["results"]));
            $order = $resultsApi->get("pedido");

            $processor = new MobileAppProcessor();
            return $processor->createOrder($order);
        } catch (Exception $ex) {
            return responseError($ex->getMessage() . " File" . $ex->getFile() . " Line: " . $ex->getLine());
        }
    }

    public function lastPosition()
    {
        try {
            //$user = auth('api')->user(); // ? Recupera usuario atraves do guard da API
            $order_id = getInputOrFail("pedido_id");
            $proc = new MobileAppProcessor();

            return responseSuccess($proc->getLastPosition($order_id));
        } catch (Exception $ex) {
            return responseError($ex->getMessage());
        }
    }

    public function notify()
    {
        $msg =  urldecode(Input::get("message", "erro desconhecido"));
        if (Input::get("level", false)) {
            Util::notify($msg, Input::get("level"));
            Util::log($msg, Input::get("level"));
        } else {
            Util::notify($msg);
            Util::log($msg);
        }
        return responseSuccess("OK");
    }

    public function updateAppAddress(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->only($this->paramsLink);
            $resultsApi = collect(json_decode($data["results"]));

            MobileRepository::updateClientAddress($resultsApi->get("cliente_id"), $resultsApi);

            DB::commit();
            return responseSuccess([], "OK");
        } catch (Exception $e) {
            DB::rollback();
            return responseError($e->getMessage());
        }
    }

    public function cancelOrder()
    {
        DB::beginTransaction();
        try {
            $isPixPedido = true;
            $pedido_id = getInputOrFail("id");
            $pedido = Pixpedido::where("pedidoapi_id", $pedido_id)->first();

            if (is_null($pedido)) {
                $pedido = Pedido::whereApiPedidoId($pedido_id)->first();
                $isPixPedido = false;
            }

            $this->throwIf(!$pedido, "Pedido não encontrado");

            if ($isPixPedido) {
                $this->cancelPixPedido($pedido);
            } else {
                $pedido->update(["pedidosituacao_id" => getInputOrFail("situacao")]);
            }

            if ($isPixPedido) {
                Util::log("Pedido PIX Código: [$pedido->id] código api: [$pedido_id] cancelado pelo usuário.");

                $pedido->delete();
            } else {
                Util::notify("Pedido Código: [$pedido->id] cancelado pelo usuário.", "info");
            }

            DB::commit();

            return responseSuccess();
        } catch (Exception $e) {
            DB::rollback();
            Util::notify("Erro ao cancelar pedido: " . $e->getCode() . " " . $e->getMessage());
            return responseError($e->getMessage());
        }
    }

    public function orderHistory()
    {
        try {
            DB::beginTransaction();

            $produtosApi = collect(json_decode(getInputOrFail("produtos")));
            $cliente_id = getInputOrFail("cliente_id");

            $processor = new MobileAppProcessor();
            $history = $processor->getClientHistory($produtosApi, $cliente_id);

            DB::commit();
            return responseSuccess(utf8FormatJson($history));
        } catch (RejectedException $e) {
            DB::rollback();
            return responseReject($e->getMessage());
        } catch (Exception $e) {
            DB::rollback();
            return responseError($e->getMessage());
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function infoGB()
    {
        try {
            $gb = MobileRepository::getGBByCode(getInputOrFail("codigo"));
            if (is_null($gb)) {
                return responseReject("Código de vale gás não encontrado nesta revenda.");
            } else {
                return $this->checkSitGB($gb);
            }
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    private function checkSitGB($gb)
    {
        $msg = "%s, entre em contato com a revenda.";
        switch ($gb->situacao) {
            case "Baixado":
                $msg = str_replace("%s", "Vale gás já utilizado em outra venda", $msg);
                return responseReject($msg);
            case "Cancelado":
                $msg = str_replace("%s", "Vale gás foi cancelado", $msg);
                return responseReject($msg);
            case "Vendido":
                $msg = str_replace("%s", "Vale gás não permitido para vendas", $msg);
                return responseReject($msg);
            case "Pré-Venda":
                $msg = str_replace("%s", "Vale gás não permitido para vendas", $msg);
                return responseReject($msg);
            case "Impresso Pré-Venda":
                $msg = str_replace("%s", "Vale gás não permitido para vendas", $msg);
                return responseReject($msg);
            case "Impresso":
                return responseSuccess($gb);
        }
    }

    public function migrateAddresses(Request $request)
    {
        try {
            DB::beginTransaction();

            $enderecos = $request->get("enderecos");

            $processor = new MobileAppProcessor();
            $processor->migrateAddress($enderecos);

            DB::commit();

            return responseSuccess([], "Ruas e bairros criados com sucesso");
        } catch (Exception $e) {
            DB::rollback();
            return responseError($e->getMessage(), 500);
        }
    }

    public function migrateClients(Request $request)
    {
        try {
            DB::beginTransaction();

            $auth = new AuthController();

            $authData = [
                "email"     => env("DEFAULT_USER_SYSTEM"),
                "password"  => env("DEFAULT_PASSWORD_SYSTEM")
            ];

            $logged = $auth->loginFromApi($authData);

            $this->throwIf(!$logged, "Wrong username or password!", 401);

            $data = $request->get("clientes");

            $processor = new MobileAppProcessor();
            $processor->migrateClients($data);

            DB::commit();

            return responseSuccess([], "Clientes migrados com sucesso");
        } catch (Exception $e) {
            DB::rollback();
            return responseError($e->getMessage(), 500);
        }
    }

    public function linkConvenio(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->all();

            $clienteApi = json_decode($data["results"]);

            $processor = new MobileAppProcessor();

            $cliente = $processor->checkConveniado($clienteApi);

            $processor->checkForApiId($cliente, $clienteApi);

            $cliData["api_id"] = $clienteApi->id;
            $cliData["endereco_app"] = null;
            $cliData["nome_app"] = null;
            $cliData["latitude_app"] = null;
            $cliData["longitude_app"] = null;

            $cliente->update($cliData);

            DB::commit();
            return responseSuccess($cliente);
        } catch (RejectedException $e) {
            DB::rollback();

            if ($e->getCode() === 101) {
                Util::notify("Erro de Cliente ao se Cadastrar: " . $e->getMessage());
                $withFile = " File: " . $e->getFile() . " Line: " . $e->getLine();
            }

            return responseReject($e->getMessage() . $withFile, $e->getCode());
        } catch (Exception $ex) {
            return responseError($ex->getMessage());
        }
    }

    public function getPix()
    {
        try {
            $pedido_id = getInputOrFail("pedido_id");
            info("getting pix info for " . $pedido_id);

            $processor = new MobileAppProcessor();

            return responseSuccess($processor->getPix($pedido_id));
        } catch (Exception $ex) {
            if ($ex->getCode() == 103) {
                Util::log($ex);

                return responseError("PIX Expirado");
            }

            if ($ex->getCode() != 422) {
                Util::notify("Pedido api: {$pedido_id} Pagamento não está pago e pedido não foi encontrado na fila de aguardo de pagamento!");
                Util::log($ex);

                return responseError("Pedido não encontrado!");
            }

            return responseError($ex->getMessage(), $ex->getCode());
        }
    }

    private function cancelPixPedido($pedido)
    {
        // $transaction = Pixtransaction::where("pixpedido_id", $pedido->id)->first();

        // if (!is_null($transaction)) $transaction->delete();

        Pixtransaction::where("pixpedido_id", $pedido->id)->delete();
    }
}
