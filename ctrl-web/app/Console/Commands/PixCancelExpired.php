<?php

namespace App\Console\Commands;

use DB;
use App\Pixtransaction;
use App\Enums\PixStatus;
use Illuminate\Console\Command;
use App\Http\Resources\ApiResources;

class PixCancelExpired extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pix:expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks for expired pix orders and cancels them';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $pedidos = Pixtransaction::from("pixtransactions pix")
            ->join("pixpedidos ped", "pix.pixpedido_id", "ped.id")
            ->where("pix.status", PixStatus::Ativa)
            ->whereNull("pix.pedido_id")
            ->where("pix.expires_at", "<", now("America/Sao_Paulo"))
            ->select("pix.id", "ped.pedidoapi_id")
            ->get();

        if ($pedidos->isEmpty()) {
            return;
        }

        $pix_ids = $pedidos->pluck("id")->toArray();
        $api_ids = $pedidos->pluck("pedidoapi_id")->toArray();

        DB::beginTransaction();
        try {
            info("expired pixes" . json_encode($pix_ids) . " orders: " . json_encode($api_ids));
            ApiResources::sendExpiredPix($api_ids);

            Pixtransaction::whereIn("id", $pix_ids)->update(["status" => PixStatus::RemovidaRecebedor]);

            DB::commit();

            $now = now("America/Sao_Paulo")->format("Y-m-d H:i:s");

            $this->info("[$now]: " . count($pix_ids) . " Expired. Orders: " . json_encode($api_ids));
        } catch (\Exception $ex) {
            DB::rollBack();
            $this->info("Failed to send expired orders with msg: {$ex->getMessage()}");
        }
    }
}
