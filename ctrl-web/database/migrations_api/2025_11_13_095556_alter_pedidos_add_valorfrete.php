<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPedidosAddValorfrete extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->table('pedidos', function (Blueprint $table) {
            $table->decimal('valorfrete', 15, 4)->nullable()->default(null);
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
            $table->dropColumn('valorfrete', 15, 4);
        });
    }
}
