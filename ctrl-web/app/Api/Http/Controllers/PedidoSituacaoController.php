<?php

namespace App\Api\Http\Controllers;

use App\Http\Controllers\Controller;

use App\{Helpers\Util,
    Http\Requests\PedidoSituacaoRequest,
    Repository\PedidoSituacaoImportacaoRepository as SituacaoImportacao,
    Repository\PedidoSituacaoRepository as PedidoSituacao,
    Repository\UserRepository};
use DB;
use Exception;
use Input;

class PedidoSituacaoController extends Controller
{
    public function index()
    {
        $status = PedidoSituacao::all();
        $statusDefault = Util::getStatus();
        $data = [
            "status"        => $status,
            "jsFile"        => "pedidosituacao",
            "statusDefault" => $statusDefault,
            "pageTitle"     => "Status de Pedidos"
        ];
        return view("pedidosituacao.index", $data);
    }

    /**
     * @param PedidoSituacaoRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function store(PedidoSituacaoRequest $request)
    {
        try {
            $data = $this->validateData($request);
            return responseSuccess(PedidoSituacao::create($data));
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param PedidoSituacaoRequest $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(PedidoSituacaoRequest $request, $id)
    {
        try {
            $data = $this->validateData($request);
            return responseSuccess(PedidoSituacao::findOrFail($id)->update($data));
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param PedidoSituacaoRequest $request
     * @return array
     * @throws Exception
     */
    private function validateData(PedidoSituacaoRequest $request)
    {
        $data = $request->only($this->getFieldsStoreUp());
        if (! isset($data["ativo"])) {
            $data["ativo"] = false;
        }
        $this->throwIf(! isset($data["info"]), "Informe a descrição informal do Status");
        return strNullToNullValue($data);
    }

    /**
     * @return array
     */
    private function getFieldsStoreUp()
    {
        return array_flatten(PedidoSituacao::getFillable());
    }
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getToLink()
    {
        try {
            $linkedUser = UserRepository::getLinked(getOrFail("user_id"));
            $data = [];
            $data["all"] = PedidoSituacao::getToLink();
            $data["linked"] = SituacaoImportacao::getLinked($linkedUser->id);

            return responseSuccess($data);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function linkFrom()
    {
        try {
            DB::beginTransaction();
            $data = Input::only($this->paramsLink);

            $linkedUser = UserRepository::getLinked($data["user_id"]);
            SituacaoImportacao::link($data["linked"], $linkedUser->id);

            DB::commit();
            return responseSuccess([], "Situações de Pedidos vinculadas com sucesso!");
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }
}
