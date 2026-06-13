<?php

namespace App\Http\Controllers;

use App\Empresaconfig;
use App\Enums\PixStatus;
use App\Pedido;
use App\Helpers\Utils\Util;
use App\Pixtransaction;
use App\Services\PixService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PixController extends Controller
{

    public function generatePix(Request $request)
    {
        $data = $request->all();

        $pedido_id = $data["pedido_id"];

        $pedido = Pedido::findOrFail($pedido_id);

        $condicao = $pedido->condicaopagamento;

        // ? Utilizando o tPag da NFC-e para saber se é PIX ou não
        // ? 17 - Pagamento Instantâneo (PIX)
        if ($condicao->nfc_tpag != "17") {
            throw new \Exception("Para gerar o QR Code a forma de pagamento deve ser PIX.");
        }

        try {
            $service = new PixService($pedido);

            return responseSuccess($service->getTransaction());
        } catch (\Exception $ex) {
            Util::notify("Error ao gerar o PIX: {$ex->getMessage()}");

            return responseError($ex->getMessage());
        }
    }

    public function webhook(Request $request)
    {
        switch ($request->method()) {
            case 'GET':
                return responseSuccess();
            case "POST":
                return $this->webhookPix($request);
        }
    }

    public function webhookPix(Request $request)
    {
        // FASE 1 (segurança — S3): valida a origem do webhook antes de processar.
        // O Itaú permite configurar a URL do webhook com um segredo no path/header
        // e exige mTLS; aqui validamos um token compartilhado (PIX_WEBHOOK_TOKEN)
        // enviado no header. Sem token configurado, nega (fail-safe).
        if (! $this->validWebhookToken($request)) {
            Util::log("Webhook PIX: requisição não autorizada (token inválido/ausente).");
            return response()->json(["status" => "unauthorized"], 401);
        }

        $data = $request->all();

        $service = new PixService();

        $service->processReturn($data);

        return responseSuccess();
    }

    /**
     * Valida o token do webhook PIX em tempo constante.
     */
    private function validWebhookToken(Request $request)
    {
        $expected = (string) env('PIX_WEBHOOK_TOKEN', '');
        if ($expected === '') {
            return false; // fail-safe: sem segredo configurado, nega.
        }
        $provided = (string) $request->header('X-Webhook-Token', $request->input('token', ''));
        return hash_equals($expected, $provided);
    }

    public function checkPixComplete(Request $request)
    {
        $pedido_id = $request->get("pedido_id");

        $config = Empresaconfig::from("empresaconfigs conf")
            ->join("pedidos ped", "ped.empresa_id", "conf.empresa_id")
            ->where("ped.id", $pedido_id)
            ->selectRaw("conf.*")
            ->first();

        $service = new PixService(null, $config);

        return responseSuccess([
            "istransactioncomplete" => $service->checkPixComplete($pedido_id)
        ]);
    }

    public function index(Request $request)
    {
        return $this->getPixParcelas($request);
    }

    public function baixar($parcelas)
    {
        $tipo_lancamento = 'R';
        return view('financeiro.contasreceber', compact('tipo_lancamento', 'parcelas'));
    }

    private function getPixParcelas($request)
    {
        $init = $request->get("inicio", now());
        $end = $request->get("fim", now());

        $inicio = Carbon::parse($init)->startOfDay();
        $fim = Carbon::parse($end)->endOfDay();

        $transactions = Pixtransaction::from("pixtransactions as pix")
            ->join("pedidos as ped", "pix.pedido_id", "ped.id")
            ->join("clientes as cli", "ped.cliente_id", "cli.id")
            ->join("financeiros as fin", "ped.financeiro_id", "fin.id")
            ->join("financeiroparcelas as par", "par.financeiro_id", "fin.id")
            ->where("pix.status", PixStatus::Concluida)
            ->whereBetween("pix.created_at", [$inicio, $fim])
            ->where("par.baixado", 0)
            ->select(
                "par.id as parcela_id",
                "cli.nome as cliente",
                "ped.datahoraprevisaoentrega as venda",
                "pix.datapagamento as pagamento",
                "pix.txid",
                "pix.endtoendid",
                "pix.status",
                "pix.valorpago",
                "par.valor"
            )
            ->get();

        $transactions = $transactions->map(function ($item) {
            $item["venda"] = requestDataOracle($item["venda"]);
            $item["pagamento"] = requestDataOracle($item["pagamento"]);
            $item["valor"] = requestNumeroDecimalOracle($item["valor"]);
            $item["valorpago"] = requestNumeroDecimalOracle($item["valorpago"]);

            return $item;
        });

        return view("pix.index", compact("transactions"));
    }
}
