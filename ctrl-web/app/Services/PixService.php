<?php

namespace App\Services;

use App\Empresaconfig;
use App\Enums\PixStatus;
use App\Helpers\Utils\Util;
use App\Http\Resources\ApiResources;
use App\Jobs\ProcessPixPedido;
use App\Pedido;
use App\Pixpedido;
use App\Pixtransaction;
use App\Services\CarbonCustom as Carbon;
use stdClass;

class PixService
{

    /**
     * @var Pedido|Pixpedido $pedido
     */
    private $pedido;

    /**
     * @var Empresaconfig $config
     */
    private $config;

    /**
     * @var bool $isAppPix
     */
    private $isAppPix;

    /**
     * @var ApiResources $api
     */
    private $api;

    /**
     * @var string correlationId
     */
    private $uuid;

    /**
     * @var int $expiracao
     */
    private $expirationSeconds = 7 * 60;

    /**
     * @var string $base
     */
    private $base = "pix_recebimentos_conciliacoes_v2_ext/v2/cobrancas_imediata_pix";

    /**
     * @var string $base
     */
    private $baseProd = "pix_recebimentos/v2/cob";


    function __construct($pedido = null, Empresaconfig $config = null, bool $isAppPix = false)
    {
        $this->uuid = guid4();

        $this->pedido = $pedido;

        $this->isAppPix = $isAppPix;

        if (is_null($pedido)) {
            $this->config = $config;
        } else {
            $this->config = Empresaconfig::whereEmpresaId($this->pedido->empresa_id)->first();
        }

        $url = env("ITAU_AUTH_API");

        // if (env("APP_ENV") == "local") {
        //     $url = env("ITAU_TEST_API");
        // }

        $this->api = new ApiResources($url);
    }

    private function getUriCobranca()
    {
        // if (env("APP_ENV") == "local") return "sandboxapi/" . $this->base;

        return $this->baseProd;
    }

    private function getUriQrCode($cobrancaId)
    {
        $uri = "/$cobrancaId/qrcode";

        // if (env("APP_ENV") == "local") return "sandboxapi/{$this->base}" . $uri;

        return $this->baseProd . $uri;
    }

    private function resetClient()
    {
        // $env = env("APP_ENV");
        $url = env("ITAU_PROD_API");

        // if ($env == "production") $url = env("ITAU_PROD_API");
        // else if ($env == "homologation") $url = env("ITAU_HOMOLOG_API");

        $this->api->setClient($url);
    }


    public function getTransaction($throwIfNotFound = false)
    {
        $this->checkExpired();

        $withAuth = true;

        $transaction = $this->getExistingTransaction();

        if (is_null($transaction) && !$throwIfNotFound) {
            $withAuth = false;
            $transaction = $this->createInvoice();
            info("transaction created: " . $transaction->id);
        }

        if (is_null($transaction) && $throwIfNotFound) {
            info("transaction does not exists, throwin." . PHP_EOL);
            throw new \Exception("Transação não encontrada, pode ter sido expirado.", 103);
        }

        $qr_code = $this->getQrCode($transaction->txid, $withAuth);

        if (isset($qr_code->data)) {
            $qr_code = $qr_code->data;
        }

        $qr_code->pixcopiaecola = $transaction->pixcopiaecola;

        info("returning qr code" . PHP_EOL);
        return $qr_code;
    }

    public function processNewKey($newKey, $oldKey)
    {
        $newKey = empty($newKey) ? null : decrypt($newKey);
        $oldKey = empty($oldKey) ? null : decrypt($oldKey);

        if ($newKey == $oldKey) return;

        if (!is_null($oldKey)) $this->deleteOldWebhook($oldKey);

        $this->createNewWebhook($newKey);
    }

    public function processReturn($data)
    {
        // FASE 1 (segurança — S3): antes este método marcava o pedido como PAGO
        // confiando 100% no payload do webhook, sem validar valor nem usar
        // bindings (havia SQLi no whereRaw). Correções:
        //   - valida estrutura do payload;
        //   - usa binding parametrizado (sem SQLi);
        //   - CONFERE valor pago == valor da cobrança antes de concluir.
        if (!isset($data["pix"]) || !is_array($data["pix"])) {
            Util::log("Webhook PIX: payload inválido (sem array 'pix').");
            return;
        }

        $transactions = $data["pix"];
        Util::log("Processing pix return: " . json_encode($transactions) . PHP_EOL);
        foreach ($transactions as $pix) {
            if (!isset($pix["txid"]) || !isset($pix["valor"])) {
                Util::log("Webhook PIX: item sem txid/valor — ignorado.");
                continue;
            }

            $transaction = Pixtransaction::where("txid", $pix["txid"]);

            if (isset($pix["pedido_id"])) {
                $pedido_id = $pix["pedido_id"];
                // Binding parametrizado (corrige o SQLi do whereRaw com interpolação).
                $transaction = $transaction->where(function ($q) use ($pedido_id) {
                    $q->where("pedido_id", $pedido_id)
                      ->orWhere("pixpedido_id", $pedido_id);
                });
            }

            $transaction = $transaction->first();

            if (is_null($transaction)) {
                $e2e = isset($pix["endToEndId"]) ? $pix["endToEndId"] : "-";
                Util::log("Transação com txid: {$pix["txid"]} e endToEndId: {$e2e} não encontrada!");
                continue;
            }

            // FASE 1: confere se o valor pago bate com o valor da cobrança.
            // Comparação monetária com tolerância de 1 centavo.
            $valorPago    = (float) $pix["valor"];
            $valorCobrado = (float) $transaction->valor;
            if (abs($valorPago - $valorCobrado) > 0.001) {
                Util::log("Webhook PIX: valor divergente para txid {$pix["txid"]} " .
                    "(pago: {$valorPago}, cobrado: {$valorCobrado}) — NÃO concluído.");
                continue;
            }

            $this->completeReturn($transaction, $pix);

            if (!is_null($transaction->pixpedido_id)) {
                $pixpedido = Pixpedido::find($transaction->pixpedido_id);

                Util::log("Despachando job para criar pedido do app");

                dispatch(new ProcessPixPedido($pixpedido->pedidoapi_id));
            }
        }
    }

    public function checkPixComplete($pedido_id)
    {
        $status = PixStatus::Ativa;

        $transaction = Pixtransaction::where("expires_at", ">=", Carbon::now())
            ->where("status", $status);

        if ($this->isAppPix) {
            $transaction = $transaction->where("pixpedido_id", $pedido_id);
        } else {
            $transaction = $transaction->where("pedido_id", $pedido_id);
        }

        $transaction = $transaction->first();

        if (is_null($transaction)) return false;

        $transaction = Pixtransaction::find($transaction->id);

        $transaction = $this->requestTransactionStatus($transaction);

        return $transaction->status == PixStatus::Concluida;
    }

    private function authenticate()
    {
        $this->api->setMethod("POST");

        $client_id = decrypt($this->config->client_id);
        $client_secret = decrypt($this->config->client_secret);

        $payload = [
            "client_id"     => $client_id,
            "client_secret" => $client_secret,
            "grant_type"    => "client_credentials"
        ];

        try {
            $uri = "api/oauth/token";

            // if (env("APP_ENV") == "local") {
            //     $uri = "api/jwt";
            // }

            $response = $this->api->prepareAndSend($payload, $uri, true);

            $this->resetClient();

            $headers = [
                "x-itau-correlationID"   => $this->uuid,
            ];

            // if (env("APP_ENV") == "local") {
            //     $headers["x-sandbox-token"] = $response->access_token;
            // } else {
            $headers["Authorization"] = "Bearer " . $response->access_token;
            $headers["x-itau-apikey"] = $client_id;
            // }

            $this->api->setHeader($headers);
        } catch (\Exception $ex) {
            Util::notify("Error ao autenticar a API do Pix: " . $ex->getMessage(), "error");
            throw new \Exception("Error ao autenticar a API do Pix");
        }
    }

    private function checkExpired()
    {
        if (!$this->isAppPix) return;

        info("checking for pix expiration");
        $transactions = Pixtransaction::where("expires_at", "<", now("America/Sao_Paulo"))
            ->where("pixpedido_id", $this->pedido->id)
            ->where("status", PixStatus::Ativa)
            ->get();

        if ($transactions->isEmpty()) {
            info("pix not expired or doesn't exists");
            return;
        }

        throw new \Exception("Pix expirado", 422);
    }

    private function getExistingTransaction()
    {
        info("checking for transaction");
        $transaction = Pixtransaction::where("expires_at", ">", now("America/Sao_Paulo"));

        if ($this->isAppPix) {
            $transaction = $transaction->where("pixpedido_id", $this->pedido->id);
        } else {
            $transaction = $transaction->where("pedido_id", $this->pedido->id);
        }

        $transaction = $transaction->first();

        if (is_null($transaction)) {
            info("transaction not found");
            return null;
        }

        info("transaction found: " . $transaction->id . " status: " . $transaction->status);

        if ($transaction->status == PixStatus::Concluida)
            throw new \Exception("Error transação de PIX para este pedido já foi concluída!");

        if ($transaction->status != PixStatus::Ativa) return null;

        $now = now("America/Sao_Paulo");
        info("second check for expiration now: " . $now . " transaction expiration " . $transaction->expires_at);

        if (
            $transaction->valor != $this->pedido->valorvenda
            || $now->greaterThan($transaction->expires_at)
        ) {
            info("pix expired" . PHP_EOL);
            $transaction = null;
        }

        return $transaction;
    }

    private function createInvoice()
    {
        $this->authenticate();

        $this->api->setMethod("POST");

        $response = $this->createUpdate();

        return $this->saveTransactioon($response);
    }

    // TODO Entender qual a utilidade do metodo PATCH
    // private function updateInvoice($transaction)
    // {
    //     $this->authenticate();

    //     $this->api->setMethod("PATCH");

    //     $response = $this->request($transaction->cobranca_id);

    //     return $this->saveTransactioon($response, $transaction);
    // }

    private function createUpdate($cobrancaId = null)
    {
        $uri = $this->getUriCobranca();

        $isCreate = is_null($cobrancaId);

        if (!$isCreate) $uri .= "/$cobrancaId";

        $payload = $this->formPayload($isCreate);

        return $this->request($uri, $payload);
    }

    public function createNewWebhook($key)
    {
        $this->authenticate();

        $this->api->setMethod("PUT");

        $uri = "pix_recebimentos/v2/webhook/$key";

        $payload = new stdClass();
        $payload->webhookUrl = route("webhook.pix");

        $this->request($uri, $payload);

        Util::log("Webhook de chave do pix criada");
    }

    private function deleteOldWebhook($key)
    {
        $this->authenticate();

        $this->api->setMethod("DELETE");

        $uri = "pix_recebimentos/v2/webhook/$key";

        $this->request($uri);

        Util::log("Webhook de chave antiga do pix deletada");
    }

    private function getQrCode($cobrancaId, $withAuth)
    {
        if ($withAuth) {
            $this->authenticate();
        }

        $this->api->setMethod("GET");

        // $uri = "pix_recebimentos_ext_v2/v2/cob/$txid/qrcode";
        $uri = $this->getUriQrCode($cobrancaId);

        return $this->request($uri);
    }

    private function requestTransactionStatus($transaction)
    {
        $this->authenticate();

        $uri = $this->getUriCobranca();

        $uri .= "/{$transaction->txid}";

        $this->api->setMethod("GET");

        $result = $this->request($uri);

        $transaction = $this->processReturnFromPolling($transaction, $result);

        return $transaction;
    }

    private function request($uri, $payload = null)
    {
        try {
            return $this->api->prepareAndSend($payload, $uri);
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            $error_response = $ex->getResponse();
            Util::log($error_response->getBody()->getContents() . PHP_EOL);
            $error = json_decode($error_response->getBody()->getContents());

            $message = "Erro em se comunicar com o itaú.";
            if (isset($error->title)) {
                $message = "Error com a transação da API Pix: {$error->title}";
            } else if (isset($error->message)) {
                $message = $error->message;
            }

            if (isset($error->violacoes)) {
                $violations = array_reduce($error->violacoes, function ($acc, $item) {
                    return $acc . "Campo: {$item->propriedade}, Erro: {$item->razao}";
                });

                $message .= " Motivo: $violations";
            }

            Util::notify($message);

            throw new \Exception($message);
        } catch (\Exception $ex) {
            Util::notify($ex->getMessage());

            throw new \Exception("Houve um erro ao se comunicar com o Itau!");
        }
    }

    private function formPayload($isCreate)
    {
        $payload = new stdClass();

        if ($isCreate) $payload->chave = decrypt($this->config->chavepix);

        $payload->calendario = new stdClass();
        $payload->calendario->expiracao = $this->expirationSeconds;
        $payload->valor = new stdClass();
        $payload->valor->original = sprintf('%0.2f', $this->pedido->valorvenda);

        $info = new stdClass();
        $info->nome = "pedido_id";
        $info->valor = $this->pedido->id;


        $payload->infoAdicionais = [$info];

        return $payload;
    }

    private function saveTransactioon($response, $updTransaction = null)
    {
        $transaction = new Pixtransaction();
        $transaction->cobranca_id = isset($response->id_cobranca_imediata_pix)
            ? $response->id_cobranca_imediata_pix
            : null;

        if (!is_null($updTransaction)) {
            $transaction = $updTransaction;
        }

        if ($this->isAppPix) {
            $transaction->pixpedido_id = $this->pedido->id;
        } else {
            $transaction->pedido_id = $this->pedido->id;
        }
        $transaction->txid = $response->txid;
        $transaction->expires_at = Carbon::now()->addSeconds($this->expirationSeconds);
        $transaction->loc_id = $response->loc->id;
        $transaction->loc_tipo = $response->loc->tipoCob;
        $transaction->loc_criacao = Carbon::parse($response->calendario->criacao);
        $transaction->location = isset($response->location) ? $response->location : $transaction->location;
        $transaction->status = $response->status;
        $transaction->valor = $response->valor->original;
        $transaction->correlation_id = $this->uuid;
        $transaction->pixcopiaecola = isset($response->pixCopiaECola) ? $response->pixCopiaECola : $transaction->pixCopiaECola;
        $transaction->revisao = $response->revisao;

        $transaction->save();

        return $transaction;
    }

    private function completeReturn($transaction, $pix)
    {
        $date = Carbon::parse($pix["horario"]);

        $transaction->update([
            "endtoendid"    => $pix["endToEndId"],
            "valorpago"     => $pix["valor"],
            "datapagamento" => $date,
            "status"        => PixStatus::Concluida
        ]);

        Util::log("Transação com txid: {$pix["txid"]} e endToEndId: {$pix["endToEndId"]} processada!", "sucess");

        return $transaction;
    }

    private function processReturnFromPolling($transaction, $result)
    {
        if ($transaction->status == $result->status) return $transaction;

        if (!isset($result->pix)) return $transaction;

        $pix = $result->pix[0];

        $transaction = $this->completeReturn($transaction, (array) $pix);

        return $transaction;
    }
}
