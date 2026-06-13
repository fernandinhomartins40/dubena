<?php

namespace App\Http\Controllers;

use App\Helpers\Util;
use App\Http\Requests\CondicaoPagamentoRequest;
use App\Repository\{
    CondicaoPagamentoRepository as CondicaoPagamento,
    CondPgtoImportacaoRepository as CondPgtoImportacao,
    PedidoRepository,
    ProdutoImportacaoRepository,
    ProdutoPrecoRepository as ProdutoPreco,
    UserRepository as User
};
use App\Http\Resources\ApiResources;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Exception;
use Input;
use DB;

class CondicaoPagamentoController extends Controller
{

    public function index()
    {
        $products = CondicaoPagamento::all();
        $payways = Util::getPayWays();
        $data = ["payways" => $products, "jsFile" => "payway", "paywayType" => $payways, "pageTitle" => "Condições de Pagamento"];
        return view("condicaopagamento.index", $data);
    }

    /**
     * @param CondicaoPagamentoRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function store(CondicaoPagamentoRequest $request)
    {
        try {
            $data = $this->validateData($request);
            return responseSuccess(CondicaoPagamento::create($data));
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param CondicaoPagamentoRequest $request
     * @param $id
     * @return JsonResponse
     */
    public function update(CondicaoPagamentoRequest $request, $id)
    {
        try {
            $data = $this->validateData($request);
            return responseSuccess(CondicaoPagamento::findOrFail($id)->update($data));
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param CondicaoPagamentoRequest $request
     * @return array
     */
    private function validateData(CondicaoPagamentoRequest $request)
    {
        $data = $request->only($this->getFieldsStoreUp());
        if (! isset($data["ativo"])) {
            $data["ativo"] = false;
        }
        return strNullToNullValue($data);
    }

    /**
     * @return array
     */
    private function getFieldsStoreUp()
    {
        return array_flatten(CondicaoPagamento::getFillable());
    }

    /**
     * @param bool $onOpen
     * @return JsonResponse|mixed
     * @throws Exception
     */
    public function getToOrder($onOpen = false, $withOnline = false, $withPix = false, $onlyGasPovo = false)
    {
        try {
            $results = CondPgtoImportacao::getToOrder($this->user->id, $withOnline, $withPix, $onlyGasPovo);
            if ($onOpen) {
                return $results;
            }
            return responseSuccess($results);
        } catch (Exception $e) {
            if ($onOpen) {
                throw $e;
            }
            return responseError($e->getMessage());
        }
    }

    /**
     * @return JsonResponse
     */
    public function getToLink()
    {
        try {
            $linkedUser = User::getLinked(getOrFail("user_id"));
            $data = [];
            $data["all"] = CondicaoPagamento::getToLink();
            $data["linked"] = CondPgtoImportacao::getLinked($linkedUser->id);

            return responseSuccess($data);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @return JsonResponse
     * @throws Exception
     */
    public function linkFrom()
    {
        try {
            DB::beginTransaction();
            $data = Input::only($this->paramsLink);

            $linkedUser = User::getLinked($data["user_id"]);
            CondPgtoImportacao::link($data["linked"], $linkedUser->id);

            DB::commit();
            return responseSuccess([], "Condições de Pagamento vinculadas com sucesso!");
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    /**
     * @return JsonResponse
     * @throws Exception
     */
    public function linkPricesFrom()
    {
        try {
            DB::beginTransaction();
            $data = Input::only($this->paramsLink);

            $linkedUser = User::getLinked($data["user_id"]);
            ProdutoPreco::link($data["linked"], $linkedUser->id);

            DB::commit();
            return responseSuccess([], "Preços vinculados com sucesso!");
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    /**
     * @param bool $onOpen
     * @return JsonResponse|mixed
     * @throws Exception
     */
    public function getPrices($onOpen = false, $onlyGasPovo = false)
    {
        try {
            $results = CondPgtoImportacao::getPrices($this->user->id, $onlyGasPovo);
            if ($onOpen) {
                return $results;
            }
            return responseSuccess($results);
        } catch (Exception $e) {
            if ($onOpen) {
                throw $e;
            }
            return responseError($e->getMessage());
        }
    }

    /**
     * @return JsonResponse
     * @throws Exception
     */
    public function getPricesToLink()
    {
        try {
            $linkedUser = User::getLinked(getOrFail("user_id"));

            $data = [];
            $data["prices"] = ProdutoPreco::getLinked($linkedUser->id);
            $data["products"] = ProdutoImportacaoRepository::getLinkedPrices($linkedUser->id);
            $data["payments"] = CondPgtoImportacao::getLinkedPrices($linkedUser->id);

            return responseSuccess($data);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @return JsonResponse
     * @throws GuzzleException
     */
    public function coupon()
    {
        try {
            $code = getOrFail("q", "", "Tipo de cupom não informado");
            switch ($code) {
                case "gasbolso":
                    return $this->infoGB();
                default:
                    throw new Exception("Cupom inválido");
                    break;
            }
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @return JsonResponse
     * @throws GuzzleException
     */
    private function infoGB()
    {
        try {
            $code = getOrFail("c", "", "Código do cupom não informado");
            $user = User::getLinked(getOrFail("resseler"));
            $formParams = [
                "codigo" => $code
            ];
            $resources = new ApiResources($user->erpurl . "api/", $user);
            $response = $resources->getData($formParams, "payment/infoGB");

            if (! ($response instanceof Collection)) {
                return responseReject(
                    ! is_string($response)
                        ? "Erro desconhecido ao validar as informações do vale gás, entre em contato com a revenda"
                        : $response
                );
            }

            if (PedidoRepository::hasOrderGB($response->get("codigo"), $user->id)) {
                $msg = "Nossos sistemas registrararam que alguém já realizou uma venda ainda não finalizada " .
                    "com o vale gás informado. Se não o fez, entre em contato com a revenda";
                return responseReject($msg);
            }

            $prod = ProdutoImportacaoRepository::getByErpId($response->get("produto_id"), $user->id);
            if (! $prod) {
                $msg = "O produto vinculado a este vale gás não está disponível para vendas no aplicativo, " .
                    "entre em contato com a revenda.";
                return responseReject($msg);
            }
            $response->put("produto_id", $prod->produto_id);

            return responseSuccess($response);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }
}
