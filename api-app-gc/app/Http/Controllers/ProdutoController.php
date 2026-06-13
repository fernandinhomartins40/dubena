<?php

namespace App\Http\Controllers;

use App\Helpers\Util;
use App\Http\Requests\ProdutoRequest;
use App\Repository\ProdutoRepository as Produto;
use App\Repository\UserRepository as User;
use DB;
use Exception;
use App\Repository\ProdutoImportacaoRepository as ProdutoImportacao;
use Input;

class ProdutoController extends Controller
{

    public function index()
    {
        $products = Produto::all();
        Util::convertImagesToBase64($products);
        $data = ["products" => $products, "jsFile" => "products", "pageTitle" => "Produtos"];
        return view("produtos.index", $data);
    }

    /**
     * @param ProdutoRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function store(ProdutoRequest $request)
    {
        try {
            $data = $this->validateData($request);
            return responseSuccess(Produto::create($data));
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param ProdutoRequest $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ProdutoRequest $request, $id)
    {
        try {
            $data = $this->validateData($request);
            return responseSuccess(Produto::findOrFail($id)->update($data));
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param ProdutoRequest $request
     * @return array
     */
    private function validateData(ProdutoRequest $request)
    {
        $data = $request->only($this->getFieldsStoreUp());
        if (! isset($data["ativo"])) {
            $data["ativo"] = false;
        }
        if (isset($data["produtocategoria_id"]) && $data["produtocategoria_id"] === "null") {
            $data["produtocategoria_id"] = null;
        }
        $image = $request->get("img");
        if ($image) {
            $data["thumbnail"] = base64_decode(str_replace("data:image/png;base64,", "", $image));
        } else {
            $data["thumbnail"] = null;
        }
        return strNullToNullValue($data);
    }

    /**
     * @return array
     */
    private function getFieldsStoreUp()
    {
        return array_flatten(Produto::getFillable());
    }

    /**
     * @param bool $onOpen
     * @param bool $onlyGasPovo
     * @return ProdutoImportacao[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function getToOrder($onOpen = false, $onlyGasPovo = false)
    {
        try {
            $produtogp_id = null;
            if ($onlyGasPovo) {
                $produtogp_id = $this->user->produtogp_id;
            }

            $produtos = ProdutoImportacao::getToOrder($this->user->id, $produtogp_id);
            Util::convertImagesToBase64($produtos);
            if ($onOpen) {
                return $produtos;
            }
            return responseSuccess($produtos);
        } catch (Exception $e) {
            if ($onOpen) {
                throw $e;
            }
            return responseError($e->getMessage());
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getToLink()
    {
        try {
            $linkedUser = User::getLinked(getOrFail("user_id"));
            $data = [];
            $data["all"] = Produto::getToLink();
            $data["linked"] = ProdutoImportacao::getLinked($linkedUser->id);

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

            $linkedUser = User::getLinked($data["user_id"]);
            ProdutoImportacao::link($data["linked"], $linkedUser->id);

            DB::commit();
            return responseSuccess([], "Produtos vinculados com sucesso!");
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }
}
