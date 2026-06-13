<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEmpresaconfigsAddConvenionfFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->unsignedInteger('contaconvenionf_id')->nullable()->default(null);
            $table->foreign('contaconvenionf_id')->references('id')->on('contas');
             $table->integer('presencacompradorconvenionf')->nullable()->default(null);
            $table->integer('fretemodalidadeconvenionf')->nullable()->default(null);

            $table->unsignedInteger('transportadorconvenionf_id')->nullable()->default(null);
            $table->foreign('transportadorconvenionf_id')->references('id')->on('clientes');

            $table->unsignedInteger('nfoperacaoconvenio_id')->nullable()->default(null);
            $table->foreign('nfoperacaoconvenio_id')->references('id')->on('nfoperacaos')->onUpdate('cascade');

            $table->unsignedInteger('ccfreteconvenio_id')->nullable()->default(null);
            $table->unsignedInteger('pcfreteconvenio_id')->nullable()->default(null);

            $table->foreign('ccfreteconvenio_id')->references('id')->on('centrocustos')->onUpdate('cascade');
            $table->foreign('pcfreteconvenio_id')->references('id')->on('planocontas')->onUpdate('cascade');

            $table->unsignedInteger('ccconvenio_id')->nullable()->default(null);
            $table->unsignedInteger('pcconvenio_id')->nullable()->default(null);

            $table->foreign('ccconvenio_id')->references('id')->on('centrocustos')->onUpdate('cascade');
            $table->foreign('pcconvenio_id')->references('id')->on('planocontas')->onUpdate('cascade');

            $table->unsignedInteger('setorconvenio_id')->nullable()->default(null);
            $table->foreign('setorconvenio_id')->references('id')->on('setors')->onUpdate('cascade');

            $table->unsignedInteger('veiculoconvenio_id')->nullable()->default(null);
            $table->foreign('veiculoconvenio_id')->references('id')->on('veiculos')->onUpdate('cascade');

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
            $table->dropColumn("contaconvenionf_id");
            $table->dropColumn("presencacompradorconvenionf");
            $table->dropColumn("fretemodalidadeconvenionf");
            $table->dropColumn("transportadorconvenionf_id");
            $table->dropColumn("nfoperacaoconvenio_id");
            $table->dropColumn("ccfreteconvenio_id");
            $table->dropColumn("pcfreteconvenio_id");
            $table->dropColumn("ccconvenio_id");
            $table->dropColumn("pcconvenio_id");
            $table->dropColumn("setorconvenio_id");
             $table->dropColumn("veiculoconvenio_id");
        });
    }
}
