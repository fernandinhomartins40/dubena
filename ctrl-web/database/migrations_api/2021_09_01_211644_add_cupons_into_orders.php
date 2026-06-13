<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCuponsIntoOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->table('pedidos', function (Blueprint $table) {
            $table->unsignedInteger("cupom_id")->nullable()->default(null);

            $table->foreign('cupom_id')->references('id')->on('cupons');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->table('pedidos', function (Blueprint $table) {
            $table->dropForeign('pedidos_cupom_id_foreign');
            $table->dropColumn('cupom_id');
        });
    }
}
