<?php

namespace App\Api\Http\Controllers;

use App\Http\Controllers\Controller;

use App\{
    Helpers\Util,
    Http\Requests\EnderecoRequest,
    Repository\ClienteRepository,
    Repository\EnderecoRepository as Endereco
};
use App\Api\Resources\ApiResources;
use DB;
use Exception;

class EnderecoController extends Controller
{

    /**
     * @param EnderecoRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function store(EnderecoRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $this->validateData($request);

            $endereco = Endereco::create($data);
            ClienteRepository::updateFavoriteAddress($endereco->cliente_id, $endereco->id);

            DB::commit();

            return responseSuccess($endereco, 'Endereço criado com sucesso!');
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function makeFavorite()
    {
        try {

            $cliente_id = getOrFail("cliente_id", null, "Código do cliente inválido");
            $id = getOrFail("id", null, "Código do endereço inválido");

            $address = ClienteRepository::find($id);
            $this->throwIf(
                $address && !$address->ativo,
                "Endereço não pode ser favoritado pois não foi encontrado no banco de dados"
            );

            ClienteRepository::updateFavoriteAddress($cliente_id, $id);

            DB::commit();

            return responseSuccess([], 'Novo endereço favorito selecionado com sucesso!');
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    /**
     * @param EnderecoRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(EnderecoRequest $request)
    {
        try {
            $data = $this->validateData($request);

            $id = $request->get('id');

            if (!$id) {
                throw new Exception("Código não encontrado na base de dados.");
            }

            $endereco = Endereco::findOrFail($id);

            $endereco->update($data);
            ClienteRepository::updateFavoriteAddress($endereco->cliente_id, $id);

            return responseSuccess($endereco, 'Endereço atualizado com sucesso!');
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param bool $onOpen
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function get($onOpen = false)
    {
        try {

            if ($onOpen) {
                $cliente_id = getOrFail('cliente_id', 0, "Cliente não encontrado.");
                $cliente = ClienteRepository::findOrFail($cliente_id);
                return Endereco::find($cliente->enderecopadrao_id);
            }

            $id = getOrFail('address_id', 0, "Endereço não encontrado.");
            $endereco = Endereco::findOrFail($id);

            return responseSuccess($endereco, 'Endereço consultado com sucesso!');
        } catch (Exception $e) {
            if ($onOpen) {
                throw $e;
            }
            return responseError($e->getMessage());
        }
    }

    public function getStandard()
    {
        $cliente_id = getOrFail('cliente_id', 0, "Cliente não encontrado.");

        $cliente = ClienteRepository::findOrFail($cliente_id);

        $address = Endereco::find($cliente->enderecopadrao_id);

        return responseSuccess($address);
    }

    /**
     * @param bool $onOpen
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function getAll($onOpen = false)
    {
        try {

            $id = getOrFail('cliente_id', 0, 'Código do cliente inválido');

            $enderecos = Endereco::getByClient($id);

            $cliente = ClienteRepository::findOrFail($id);

            $this->setDefault($cliente->enderecopadrao_id, $enderecos);

            if ($onOpen) {
                return $enderecos;
            }

            return responseSuccess($enderecos);
        } catch (Exception $e) {
            if ($onOpen) {
                throw $e;
            }
            return responseError($e->getMessage());
        }
    }

    private function setDefault($id, &$addresses)
    {
        //TODO verificar se haverá utilidade
        //        foreach ($addresses as &$address) {
        //            $address->favorito = 1;
        //            if ($address->id === $id ) {
        //                $address->favorito = 1;
        //                break;
        //            }
        //            $address->favorito = 0;
        //        }
    }

    public function destroy()
    {
        try {
            DB::beginTransaction();
            $id = getOrFail('id', 0, "Endereço não encontrado.");

            $this->throwIf(ClienteRepository::getByEndereco($id)->count() > 0, "Este endereço não pode ser excluido pois está sendo usado como favorito");

            $endereco = Endereco::findOrFail($id)->update(["ativo" => false]);

            DB::commit();
            return responseSuccess($endereco, 'Endereço excluído com sucesso!');
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    /**
     * @param EnderecoRequest $request
     * @return array
     * @throws Exception
     */
    private function validateData(EnderecoRequest $request)
    {
        $data = $request->only($this->getFieldsStoreUp());
        if (array_key_exists("numero", $data)) {
            $contains = false;
            for ($i = 0; $i < strlen($data["numero"]); $i++) {
                $char = $data["numero"][$i];
                if ((int) $char === 0 && $char !== "0") {
                    $contains = true;
                }
            }
            if ($contains) {
                throw new Exception("Número inválido");
            }
        }
        if (!isset($data["latitude"]) || !isset($data["longitude"])) {
            throw new Exception(
                "Não foi possível encontrar a sua latitude e longitude. " .
                    "Tente voltar à tela do mapa e cadastre o endereço novamente"
            );
        }
        $data["ativo"] = true;

        if (isset($data["complemento"]) && strlen($data["complemento"]) > 50) {
            info("Complemento do cliente_id: " . $data["cliente_id"] . " extrapolou o limite: " . $data["complemento"]);
            $data["complemento"] = str_limit($data["complemento"], 50);
        }

        return strNullToNullValue($data);
    }

    /**
     * @return array
     */
    private function getFieldsStoreUp()
    {
        return array_flatten(Endereco::getFillable());
    }

    public function migrate()
    {
        try {

            $enderecos = Endereco::getForMigration();

            $user = auth("api")->user();

            $url = substr($user->erpurl, -1) !== '/' ? $user->erpurl . '/' : $user->erpurl;
            $api = new ApiResources($url . "api/", $user);

            $api->post([
                "enderecos" => $enderecos->toJson()
            ], "address/migrate");

            return responseSuccess([], "Endereços migrados com sucesso");
        } catch (Exception $ex) {
            return responseError($ex->getMessage(), 500);
        }
    }
}

