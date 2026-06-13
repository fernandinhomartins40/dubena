<?php

namespace App\Api\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Helpers\Util;
use Illuminate\Http\Request;
use App\Api\Resources\ApiResources;
use App\Api\Models\OauthClient;
use App\Api\Repository\UserRepository;
use App\Api\Models\User;
use DB;
use Exception;
use GuzzleHttp;
use Illuminate\Http\JsonResponse;
use Input;
use InvalidArgumentException;
use stdClass;

class SecretController extends Controller
{

    /**
     * @return JsonResponse
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    public function getToken()
    {
        // FASE 1 (segurança — S2): a app_key era derivada de sha1(APP_KEY).
        // Como a APP_KEY vazou no repositório do app, qualquer um gerava token.
        // Agora compara com um segredo PRÓPRIO de app (APP_TOKEN_KEY), em tempo
        // constante (hash_equals). O contrato com o app (param `app_key`) é
        // preservado — apenas o valor esperado muda (rotacionar a chave + app).
        // Fallback para sha1(APP_KEY) só se APP_TOKEN_KEY não estiver definida,
        // para não quebrar ambientes antigos durante a transição.
        $expected = env('APP_TOKEN_KEY');
        if (empty($expected)) {
            $expected = sha1(env('APP_KEY'));
        }
        $provided = (string) Input::get('app_key', '');

        if (! hash_equals((string) $expected, $provided)) {
            return response()->json([
                'msg'       => 'invalid app_key',
                'status'    => 'NOK'
            ], 404);
        } else {
            try {

                $user = User::findOrFail(env('DEFAULT_USER_ID'));

                $token = $user->createToken(str_random())->accessToken;

                $response = (object) [
                    "token_type"    => "Bearer",
                    "access_token"  => $token,
                    "client_id"     => env('DEFAULT_USER_ID')
                ];

                return response()->json([
                    'data'      => $response,
                    'status'    => 'OK'
                ], 200);
            } catch (Exception $e) {
                return response()->json([
                    'msg' => $e->getMessage(),
                    'status' => 'NOK'
                ], 400);
            }
        }
    }

    public function testToken()
    {
        return responseSuccess([]);
    }

    /**
     * @return JsonResponse
     * @throws GuzzleHttp\Exception\GuzzleException
     * @throws Exception
     */
    public function onOpenApp()
    {
        DB::beginTransaction();
        try {
            $data = new stdClass();
            $data->error_message = "";

            try {
                // $ip = $request->ip();
                $clientC = new ClienteController();
                $client = $clientC->get(true);
                if ($client instanceof JsonResponse) {
                    return $client;
                }
                if (!is_null(Input::get("internal_build_number"))) {
                    $client->appbuildnumber = Input::get("internal_build_number");
                } else {
                    $client->appbuildnumber = null;
                }
                $client->save();
                $data->novodispositivo = $client->acessadonovodispositivo;
                $data->pushregistration_id = $client->pushregistration_id;

                // $clientC->createUpdateAccess($client, $ip);
            } catch (Exception $e) {
                return responseReject($e->getMessage(), "NOT_FOUND");
            }

            $company = new EmpresaController();
            try {
                $data->companies = $company->get(is_null($client->enderecopadrao_id) ? 0 : $client->enderecopadrao_id);
                if (is_string($data->companies)) {
                    $data->error_message = $data->companies;
                    $data->companies = [];
                }
            } catch (Exception $e) {
                $data->companies = [];
                Util::notify($e->getMessage());
            }

            try {
                $data->coupon = CouponsController::available(is_null($client->id) ? 0 : $client->id);
            } catch (Exception $e) {
                $data->coupon = null;
                Util::notify($e->getMessage());
            }

            $address = new EnderecoController();
            $data->address = $address->get(true);

            $order = new PedidoController();
            $data->order = $order->track(true);

            DB::commit();
            return responseSuccess($data);
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    /**
     * @return JsonResponse|mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    public function testTokenERP()
    {
        try {
            $params = Input::all();
            if (!isset($params["authorization"]) || (isset($params["authorization"]) && !$params["authorization"])) {
                throw new InvalidArgumentException("Token não informado");
            }
            if (!isset($params["id"]) || (isset($params["id"]) && !$params["id"])) {
                throw new InvalidArgumentException("Código do usuário não informado");
            }
            return responseSuccess($this->testTokenAPI($params["authorization"], $params["id"], $params["url"]));
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param $token
     * @param $user_id
     * @param $url
     * @return mixed
     * @throws GuzzleHttp\Exception\GuzzleException|Exception
     */
    public function testTokenAPI($token, $user_id, $url)
    {
        $user = User::findOrFail($user_id);

        $api = new ApiResources($url . "api/", $user);

        $api->setAuthorizationCode($token);

        return $api->request([], "testTokenAPI");
    }

    public function destroy()
    {
        try {
            $client = OauthClient::find(Input::get('id', 0));

            if (!$client) {
                throw new Exception("API Client não encontrado.");
            }
            $client->delete();

            return response()->json([
                'data'  => 'OK',
                'msg'   => 'Sucesso!',
                'status' => 'OK'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'msg' => $e->getMessage()
            ], 400);
        }
    }

    public function testTokenFromApi(Request $request)
    {
        try {
            $post = $request->all();

            $this->authAttempt($post);

            $user = UserRepository::whereEmail($post["email"])->first();

            $token = $user->erp_authorization;

            $url = substr($user->erpurl, -1) != '/' ? $user->erpurl . '/' : $user->erpurl;

            $api = new ApiResources($url . "api/app/", $user);

            $api->setAuthorizationCode($token);

            $response = $api->request([], "testFromApi");

            return responseSuccess([], $response->msg);
        } catch (\Exception $ex) {
            return responseError($ex->getMessage(), 401);
        }
    }

    private function authAttempt($data)
    {
        $unauthorized = false;

        if (!isset($data["email"]))
            $unauthorized = true;
        if (!isset($data["password"]))
            $unauthorized = true;

        if ($unauthorized)
            throw new \Exception("The email or password is missing!", 401);


        $attempt = \Auth::attempt([
            "email"     => $data["email"],
            "password"  => $data["password"]
        ]);

        if (!$attempt)
            throw new \Exception("Wrong password or user!", 401);
    }
}

