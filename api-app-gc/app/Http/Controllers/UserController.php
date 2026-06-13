<?php

namespace App\Http\Controllers;

use App\{
    Helpers\Util,
    Http\Requests\UserRequest,
    Repository\GeneralConfigRepository,
    Repository\UserPolylinesRepository as Polylines,
    Repository\UserRepository as User
};
use App\Repository\ProdutoImportacaoRepository;
use Auth;
use DB;
use Exception;
use Hash;
use Illuminate\Auth\AuthenticationException;
use Input;
use Session;

class UserController extends Controller
{

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $users = User::all();
        $uf = Util::getUf();
        return view("user.index", compact("users", "uf"));
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getToLink()
    {
        try {
            return responseSuccess($this->user);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function password($id)
    {
        try {
            $user = User::find($id);

            if (! app('hash')->check(Input::get("old_password", ""), $user->password)) {
                throw new Exception("A senha antiga está errada");
            }
            $pass1 = Input::get("new_password", null);
            if (! $pass1) {
                throw new Exception("O campo Nova Senha é obrigatório");
            }
            $pass2 = Input::get("repeat_password", null);
            if (! $pass2) {
                throw new Exception("O campo Confirmação da Senha é obrigatório");
            }
            if ($pass1 !== $pass2) {
                throw new Exception("As senhas não conferem");
            }
            $user->update([
                "password" => app("hash")->make($pass2)
            ]);
            return responseSuccess(User::all());
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param UserRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(UserRequest $request)
    {
        try {
            $data = $request->only($this->getFieldsStoreUp());
            $data["erpurl"] = " ";
            $data["erpempresa_id"] = "0";
            $data["avaliacao"] = 2.5;
            $data["telefone"] = " ";
            $data["password"] = app('hash')->make($data["password"]);
            return responseSuccess(User::create($data));
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param UserRequest $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UserRequest $request, $id)
    {
        try {
            $data = $request->only(["name", "email", "admin", "ativo",]);
            $user = User::find($id)->update($data);
            return responseSuccess($user);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function link()
    {
        try {
            $user = Auth::user();
            $data = $this->validateData(Input::all(), $user->id);

            if (isset($data["password"])) {
                unset($data["password"]);
            }
            if (isset($data["name"])) {
                unset($data["name"]);
            }
            if (isset($data["email"])) {
                unset($data["email"]);
            }
            $user->update($data);

            return responseSuccess($user->id);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param $data
     * @param null $id
     * @return mixed
     * @throws Exception
     */
    public function validateData($data, $id = null)
    {
        $msg = "Informada hora de abertura :element sem a hora de fechamento.";
        $this->throwIf(
            isset($data["semanahoraabertura"]) && ! isset($data["semanahorafechamento"]),
            str_replace(":element", "dos dias úteis", $msg)
        );

        $this->throwIf(
            isset($data["sabadohoraabertura"]) && ! isset($data["sabadohorafechamento"]),
            str_replace(":element", "de sábado", $msg)
        );

        $this->throwIf(
            isset($data["domingohoraabertura"]) && ! isset($data["domingohorafechamento"]),
            str_replace(":element", "de domingo", $msg)
        );

        $this->throwIf(
            isset($data["feriadohoraabertura"]) && ! isset($data["feriadohorafechamento"]),
            str_replace(":element", "dos feriados", $msg)
        );

        $msg = "Informada data de fechamento :element sem a data de abertura.";
        $this->throwIf(
            ! isset($data["semanahoraabertura"]) && isset($data["semanahorafechamento"]),
            str_replace(":element", "da semana", $msg)
        );

        $this->throwIf(
            ! isset($data["sabadohoraabertura"]) && isset($data["sabadohorafechamento"]),
            str_replace(":element", "de sábado", $msg)
        );

        $this->throwIf(
            ! isset($data["domingohoraabertura"]) && isset($data["domingohorafechamento"]),
            str_replace(":element", "de domingo", $msg)
        );

        $this->throwIf(
            ! isset($data["feriadohoraabertura"]) && isset($data["feriadohorafechamento"]),
            str_replace(":element", "dos feriados", $msg)
        );

        $used = User::getByServiceUser($data["serviceuser_id"], $id);
        if ($used !== null) {
            $this->throwIf(
                $used->count() > 0,
                "Já existe um usuário com esse código de serviço sendo utilizado: " . $used->name
            );
        }

        $msg = "O campo :field é obrigatório";
        $this->throwIf(
            ! isset($data["fantasia"]),
            str_replace(":field", "Nome da Empresa no Aplicativo", $msg)
        );

        $this->throwIf(
            ! isset($data["uf"]),
            str_replace(":field", "UF", $msg)
        );

        $this->throwIf(
            ! isset($data["erpempresa_id"]),
            str_replace(":field", "Cód. Empresa", $msg)
        );

        $this->throwIf(
            ! isset($data["latitude"]),
            str_replace(":field", "Latitude", $msg)
        );

        $this->throwIf(
            ! isset($data["longitude"]),
            str_replace(":field", "Longitude", $msg)
        );

        $this->throwIf(
            ! isset($data["enderecocompleto"]),
            "Endereço Completo não informado"
        );

        //        $this->throwIf(
        //            ! isset($data[""]),
        //            str_replace(":field", "", $msg)
        //        );

        return strNullToNullValue($data);
    }

    /**
     * @param UserRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getToken(UserRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->only("id", "password", "createNew");

            $user = User::findOrFail($data["id"]);

            if (! Hash::check($data["password"], $user->password)) {
                throw new AuthenticationException("Senha do usuário incorreta!");
            }

            $oauthController = new OauthClientController();
            if (! $user->oauth->first() && ! $data["createNew"]) {
                throw new Exception("Nenhum token foi encontrado para este usuário! " .
                    " Marque o campo \"Gerar Novo Token\" e tente novamente");
            } elseif ($data["createNew"]) {
                $oauthController->exclude($user->id);
                $oauthController->store($user, $data["password"]);
            }
            DB::commit();
            $oauth = $user->oauth->first();

            $token = $oauthController->getAuthorizationToken($user, $oauth, $data["password"]);

            return responseSuccess([
                "authorization" => $token->token_type . " " . $token->access_token
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function polylines()
    {
        try {
            $user = (int) Input::get("user_id", 0);
            return responseSuccess(Polylines::byUser($user));
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function storePolylines()
    {
        try {
            DB::beginTransaction();

            $polygons = json_decode(Input::get("coords", "[]"));

            Polylines::savePoligons(Auth::user()->id, $polygons);

            DB::commit();
            return responseSuccess([]);
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    public function updateGasPovo()
    {
        $user = Auth::user();
        $data = request()->all();

        DB::beginTransaction();
        try {
            $produto = ProdutoImportacaoRepository::where("user_id", $user->id)
                ->where("erp_id", $data["produto_id"])
                ->first();

            if (is_null($produto)) {
                throw new \Exception("Produto não encontrado.");
            }

            $user->update([
                "valorfretegp" => $data["valorfrete"],
                "produtogp_id" => $produto->produto_id,
            ]);

            DB::commit();

            return responseSuccess([]);
        } catch (\Exception $e) {
            DB::rollBack();

            return responseError($e->getMessage());
        }
    }

    /**
     * @return array
     */
    private function getFieldsStoreUp()
    {
        return array_flatten(User::getFillable());
    }
}
