<?php

namespace App\Api\Http\Controllers;

use App\Http\Controllers\Controller;

use DB;
use Input;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use App\Api\Resources\ApiResources;
use App\Api\Services\CarbonCustom as Carbon;
use App\{
    Access as AppAccess,
    Http\Requests\ClienteRequest,
    Repository\ClienteRepository,
    Repository\ClienteTelefoneRepository,
    Repository\UserRepository as User
};

class ClienteController extends Controller
{

    /**
     * @param ClienteRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function store(ClienteRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $this->validateData($request);

            $data["telefoneantigo"] = null;
            $dataPhone = $request->only('telefone');

            $phone = ClienteTelefoneRepository::firstPhone($dataPhone['telefone']);

            if ($phone) {
                $cliente = ClienteRepository::find($phone->cliente_id);

                $acessadonovodispositivo = replaceAccents($cliente->primeironome) !== replaceAccents($data["primeironome"]);

                if ($acessadonovodispositivo) {
                    $clienteNovo = $this->newClientWithPhone($data, $dataPhone, true);
                    $cliente->update([
                        "acessadonovodispositivo"   => $acessadonovodispositivo,
                        "pushregistration_id"       => null,
                        "telefoneantigo"            => $dataPhone['telefone'],
                        "conveniado"                => 0,
                        "cpf"                       => null,
                    ]);
                    $cliente = $clienteNovo;
                } else {
                    $cliente->update($data);
                }
            } else {
                $cliente = $this->newClientWithPhone($data, $dataPhone);
            }

            $this->setTelefone($cliente, $dataPhone['telefone']);

            if ($data["conveniado"])
                $this->processClienteConveniado($cliente);

            DB::commit();
            return responseSuccess($cliente, "Cliente criado com sucesso!");
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    /**
     * @param $data
     * @param $dataPhone
     * @param bool $has
     * @return ClienteRepository|\Illuminate\Database\Eloquent\Model
     */
    private function newClientWithPhone($data, $dataPhone, $has = false)
    {
        $cliente = null;
        if ($has) {
            // Endpoint público: telefone/nome parametrizados (eram interpolados → SQLi).
            $cliente = ClienteRepository::whereRaw(
                "telefoneantigo = ? AND primeironome = ?",
                [$dataPhone['telefone'], firstWord($data["nome"])]
            )->first();
        }

        $data["acessadonovodispositivo"] = 0;

        if ($cliente) {
            $cliente->update($data);
        } else {
            $cliente = ClienteRepository::create($data);
        }

        $dataPhone['cliente_id'] = $cliente->id;

        ClienteTelefoneRepository::createIfNotExists($dataPhone, $has);

        return $cliente;
    }

    /**
     * @return JsonResponse
     */
    public function getToLink()
    {
        try {
            $linkedUser = User::getLinked(getOrFail("user_id"));

            $data = [];
            $data["linked"] = ClienteRepository::getToLink($linkedUser->id);

            return responseSuccess($data);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param Request|ClienteRequest $request
     * @return array
     * @throws Exception
     */
    private function validateData($request)
    {
        $data = $request->only('nome', 'cpf', 'user_id', 'datanascimento', 'sexo', 'pushregistration_id', 'conveniado', 'gasdopovo');
        $id = $request->get('id');

        try {
            if (array_key_exists('datanascimento', $data) && $data['datanascimento'] && $data['datanascimento'] != "null") {
                $data['datanascimento'] = Carbon::createFromFormat('d/m/Y', $data['datanascimento'])->format('Y-m-d');
            }
        } catch (Exception $e) {
            throw new Exception("A data de nascimento passada não pode ser validada: " . $data['datanascimento']);
        }
        $data["user_id"] = null;
        $data["cpf"] = isset($data["cpf"]) ? onlyNumbers($data["cpf"]) : null;
        $data["conveniado"] = isset($data["conveniado"]) && $data["conveniado"];
        $data["gasdopovo"] = isset($data["gasdopovo"]) && $data["gasdopovo"];
        $data["ativo"] = 1;
        $data["primeironome"] = firstWord($data["nome"]);

        $this->validateCpf($data["cpf"], $id);

        return strNullToNullValue($data);
    }

    /**
     * @param ClienteRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function update(ClienteRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $this->validateData($request);

            $id = $request->get('id');

            if (!$id) {
                return responseReject("Código não encontrado na base de dados.");
            }

            $cliente = ClienteRepository::findOrFail($id);

            $dataPhone = $request->only('telefone');
            $cliente->load("phone");

            if ($dataPhone["telefone"] && onlyNumbers($cliente->phone->telefone) != onlyNumbers($dataPhone["telefone"])) {
                $this->updateOldClientPhone($dataPhone["telefone"]);
                $cliente->phone->update([
                    "telefone" => $cliente->phone->telefone
                ]);
            }

            $cliente->update($data);

            $dataPhone['cliente_id'] = $cliente->id;

            ClienteTelefoneRepository::createIfNotExists($dataPhone);

            $this->setTelefone($cliente, $dataPhone['telefone']);

            if ($data["conveniado"])
                $this->processClienteConveniado($cliente);

            DB::commit();

            return responseSuccess($cliente, "Cliente atualizado com sucesso!");
        } catch (Exception $e) {
            DB::rollBack();
            if ($e->getCode() === 66666) {
                return responseReject($e->getMessage());
            } else {
                return responseError($e->getMessage(), 400);
            }
        }
    }

    /**
     * @param bool $onOpen
     * @return JsonResponse|Collection|ClienteRepository
     * @throws Exception
     */
    public function get($onOpen = false)
    {
        try {
            if ($onOpen) {

                $cliente = ClienteRepository::find(getOrFail("cliente_id"));
                if (!$cliente) {
                    return responseReject("Cliente não encontrado com o telefone informado.", "NOT_FOUND");
                }
                if ($cliente->primeironome === "NOT_FOUND: " . getOrFail("cliente_id")) {
                    return responseReject("Cliente não encontrado, conta foi excluída", "NOT_FOUND");
                }
                return $cliente;
            } else {
                $telefone = str_replace("--", " ", Input::get('telefone', null));
                $name = Input::get("firstName", "");
                $name = firstWord(str_replace('--', ' ', $name));

                $this->throwIf(!$name, "O nome deve ser informado");
                $this->throwIf(!$telefone, "O telefone deve ser informado");

                $tel = ClienteTelefoneRepository::firstPhone($telefone);
                if (!$tel) {
                    return responseReject("Cliente não encontrado com o telefone informado.", "NOT_FOUND");
                }
                $id = $tel->cliente_id;
            }

            $cliente = ClienteRepository::find($id);

            if (!$cliente) {
                return responseReject("Cliente nao encontrado com o telefone informado.", "NOT_FOUND");
            }

            if (firstWord($name) !== strtoupper($cliente->primeironome)) {
                return responseReject("Telefone ja vinculado a outro cliente", "DUPLICATED");
            }

            $fullName = Input::get("name", null);

            if (!is_null($fullName)) $cliente->update(["nome" => $fullName]);

            $this->setTelefone($cliente, $telefone);

            return responseSuccess($cliente);
        } catch (Exception $e) {
            if ($onOpen) {
                throw $e;
            }
            if ($e->getCode() === 66666) {
                return responseReject($e->getMessage());
            } else {
                return responseError($e->getMessage());
            }
        }
    }

    public function getById()
    {
        $client_id = request()->get("cliente_id");

        $client = ClienteRepository::find($client_id);

        return responseSuccess($client);
    }

    /**
     * @return JsonResponse
     * @throws Exception
     */
    public function updatePhone()
    {
        try {

            DB::beginTransaction();
            $telefoneantigo = Input::get("telefoneantigo", null);
            $telefone = Input::get("telefone", null);
            $cliente_id = Input::get("cliente_id", null);

            $this->throwIf(!isset($cliente_id), "Código do cliente não informado");
            $this->throwIf(!isset($telefone), "Telefone não informado");
            $this->throwIf(!isset($telefoneantigo), "Telefone antigo não informado");

            $this->updateOldClientPhone($telefoneantigo);

            ClienteRepository::findOrFail($cliente_id)->update([
                "acessadonovodispositivo" => false
            ]);

            $phone = ClienteTelefoneRepository::byClientAndNumber($telefoneantigo, $cliente_id);

            if ($phone) {
                $phone->update([
                    "telefone" => "old_telefone"
                ]);
            }

            DB::commit();
            return responseSuccess([]);
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    /**
     * @param $old
     */
    private function updateOldClientPhone($old)
    {
        $phone = ClienteTelefoneRepository::firstPhone($old);
        if ($phone) {
            ClienteRepository::find($phone->cliente_id)->update([
                "telefoneantigo"            => $old,
                "acessadonovodispositivo"   => true
            ]);
        }
    }

    /**
     * @return JsonResponse
     */
    public function destroy()
    {
        try {
            DB::beginTransaction();
            $id = getOrFail('id', 0, "Cliente não encontrado.");

            ClienteRepository::findOrFail($id)->update([
                "enderecopadrao_id"     => null,
                "nome"                  => "Cliente inexistente",
                "cpf"                   => null,
                "datanascimento"        => null,
                "pushregistration_id"   => null,
                "primeironome"          => "NOT_FOUND: " . $id,
            ]);

            ClienteTelefoneRepository::whereClienteId($id)->update([
                "telefone" => " "
            ]);

            DB::commit();
            return responseSuccess([], 'Endereço excluído com sucesso!');
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function setPushToken(Request $request)
    {
        try {

            $cliente_id = (int) $request->get("cliente_id");
            $this->throwIf(!$cliente_id, "Código do cliente inválido");

            $token = $request->get("pushregistration_id");
            $this->throwIf(!$token, "Token informado é inválido");

            ClienteRepository::findOrFail($cliente_id)->update(["pushregistration_id" => $token]);
            ClienteRepository::where("id", "<>", $cliente_id)->wherePushregistrationId($token)->update(["pushregistration_id" => null]);
            return responseSuccess([]);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    public function createUpdateAccess($client, $ip)
    {
        $access = AppAccess::where("cliente_id", $client->id)->first();

        if ($ip == "::1") $ip = "127.0.0.1";

        if (is_null($access)) {
            $access = new AppAccess();
            $access->cliente_id = $client->id;
        }

        $this->storeAccess($access, $ip);
    }

    public function isLatestBuild($ip)
    {
        $ip = DB::connection()->getPdo()->quote($ip);
        $access = AppAccess::where("ip", DB::raw("INET_ATON($ip)"))
            ->whereRaw("TIMESTAMPDIFF(SECOND, updated_at, now()) <= 15")
            ->first();

        if (is_null($access)) return false;

        $cliente = ClienteRepository::findOrFail($access->cliente_id);

        return !is_null($cliente->appbuildnumber);
    }

    private function storeAccess($access, $ip)
    {
        $ip = DB::connection()->getPdo()->quote($ip);
        $access->ip = DB::raw("INET_ATON($ip)");

        $access->save();
    }

    /**
     * @param $cliente
     * @param $sent
     */
    private function setTelefone(&$cliente, $sent)
    {
        if ($sent) {
            $cliente->telefone = $sent;
        } else {
            try {
                $cliente->telefone = ClienteTelefoneRepository::whereClienteId($cliente->id)->first()->telefone;
            } catch (Exception $e) {
                $cliente->telefone = null;
            }
        }
    }

    public function migrate()
    {
        try {
            $clientes = ClienteRepository::getForMigration();

            $user = auth("api")->user();

            $url = substr($user->erpurl, -1) !== '/' ? $user->erpurl . '/' : $user->erpurl;
            $api = new ApiResources($url . "api/", $user);

            $api->post([
                "clientes" => $clientes->toJson()
            ], "client/migrate");

            return responseSuccess([], "Clientes migrados com sucesso");
        } catch (Exception $ex) {
            return responseError($ex->getMessage(), 500);
        }
    }

    private function validateCpf($cpf, $id)
    {
        if (empty($cpf)) return;

        $exists = ClienteRepository::where("cpf", $cpf);

        if ($id) {
            $exists = $exists->where("id", "<>", $id);
        }

        $exists = $exists->exists();

        if ($exists) throw new \Exception("Já existe um cadastro com o CPF informado.");
    }

    private function processClienteConveniado($cliente)
    {
        $user = auth("api")->user();

        $response = $this->linkto($user, json_encode($cliente), "convenio");

        \Log::info(json_encode($response));

        if (isset($response->msg) && $response->status !== "OK") {
            throw new Exception($response->msg);
        }
    }
}

