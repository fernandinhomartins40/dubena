<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEmpresaconfigsAddGasdopovo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
public function up()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->unsignedInteger('condicaopagamentofretegp_id')->nullable()->default(null);
            $table->unsignedInteger('condicaopagamentogp_id')->nullable()->default(null);
            $table->unsignedInteger('ccfretegp_id')->nullable()->default(null);
            $table->unsignedInteger('pcfretegp_id')->nullable()->default(null);
            $table->unsignedInteger('produtogp_id')->nullable()->default(null);
            $table->decimal('valorfretegp', 15, 4)->nullable()->default(null);

            $table->foreign('condicaopagamentofretegp_id', 'cpagtofretegp_fk')->references('id')->on('condicaopagamentos');
            $table->foreign('condicaopagamentogp_id', 'cpagtogp_fk')->references('id')->on('condicaopagamentos');
            $table->foreign('ccfretegp_id')->references('id')->on('centrocustos')->onUpdate('cascade');
            $table->foreign('pcfretegp_id')->references('id')->on('planocontas')->onUpdate('cascade');
            $table->foreign('produtogp_id')->references('id')->on('produtos')->onUpdate('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->dropColumn("condicaopagamentofretegp_id");
            $table->dropColumn("condicaopagamentogp_id");
            $table->dropColumn("ccfretegp_id");
            $table->dropColumn("pcfretegp_id");
            $table->dropColumn("produtogp_id");
            $table->dropColumn("valorfretegp");
        });
    }
}
