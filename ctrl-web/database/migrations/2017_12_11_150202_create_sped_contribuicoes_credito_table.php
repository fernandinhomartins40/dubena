<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSpedContribuicoesCreditoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spedcontribuicoescreditos', function (Blueprint $table) {
            $table->increments('id');
            // FASE 3: o 2º arg do unsignedInteger é $autoIncrement (bool); o '4'
            // ligava auto-increment + nullable, conflitando no Postgres. Removido.
            $table->unsignedInteger("registro")->nullable()->default(null);
            $table->unsignedInteger("grupo_id");
            $table->unsignedInteger("empresa_id");
            $table->date("per_apu_cred")->nullable()->default(null);
            $table->unsignedInteger("orig_cred")->nullable()->default(null); // FASE 3: removido 2º arg espúrio
            $table->string('cnpj_suc', 15)->nullable()->default(null);
            $table->string('cod_cred', 3)->nullable()->default(null);
            $table->decimal("vl_cred_apu", 12,2)->nullable()->default(null);
            $table->decimal("vl_cred_ext_apu", 12,2)->nullable()->default(null);
            $table->decimal("vl_tot_cred_apu", 12,2)->nullable()->default(null);
            $table->decimal("vl_cred_desc_pa_ant", 12,2)->nullable()->default(null);
            $table->decimal("vl_cred_per_pa_ant", 12,2)->nullable()->default(null);
            $table->decimal("vl_cred_dcomp_pa_ant", 12,2)->nullable()->default(null);
            $table->decimal("sd_cred_disp_efd", 12,2)->nullable()->default(null);
            $table->decimal("vl_cred_desc_efd", 12,2)->nullable()->default(null);
            $table->decimal("vl_cred_per_efd", 12,2)->nullable()->default(null);
            $table->decimal("vl_cred_dcomp_efd", 12,2)->nullable()->default(null);
            $table->decimal("vl_cred_trans", 12,2)->nullable()->default(null);
            $table->decimal("vl_cred_out", 12,2)->nullable()->default(null);
            $table->decimal("sld_cred_fim", 12,2)->nullable()->default(null);

            $table->timestamps();
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('spedcontribuicoescreditos');
    }
}
