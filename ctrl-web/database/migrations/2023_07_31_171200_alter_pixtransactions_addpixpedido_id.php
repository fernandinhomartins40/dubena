<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPixtransactionsAddpixpedidoId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table("pixtransactions", function (Blueprint $table) {
            $table->unsignedInteger("pixpedido_id")->nullable();
            $table->unsignedInteger("pedido_id")->nullable()->change();

            $table->foreign("pixpedido_id")->references("id")->on("pixpedidos")->onDelete("set null");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table("pixtransactions", function (Blueprint $table) {
            $table->dropColumn("pixpedido_id");
        });
    }
}
