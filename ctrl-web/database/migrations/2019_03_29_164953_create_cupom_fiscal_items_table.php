<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCupomFiscalItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cuponsfiscaisitens', function (Blueprint $table) {
            $table->increments('id');

            //campos SAT
            $table->double("vprod", 18, 8)->nullable()->default(null);
            $table->double("vitem", 18, 8)->nullable()->default(null);
            $table->double("vratdesc", 18, 8)->nullable()->default(null);
            $table->double("vratacr", 18, 8)->nullable()->default(null);
            $table->double("vicms", 18, 8)->nullable()->default(null);
            $table->double("vpis", 18, 8)->nullable()->default(null);
            $table->double("vcofins", 18, 8)->nullable()->default(null);

            //Campos AC NÃO OBRIGATÓRIOS
            $table->string("cean")->nullable()->default(null);
            $table->string("ncm")->nullable()->default(null);
            $table->string("cest")->nullable()->default(null);
            $table->double("vdesc", 18, 8)->nullable()->default(null);
            $table->double("voutro", 18, 8)->nullable()->default(null);
            $table->double("vitem12741", 18, 8)->nullable()->default(null);
            $table->string("infadprod", 500)->nullable()->default(null);

            //Campos AC OBRIGATÓRIOS
            // (campos de icms, pis e cofins aceitam null por conta das diferentes tags mandadas de acordo com cst)
            $table->string("xprod");
            $table->string("cfop", 4);
            $table->string("ucom", 5);
            $table->double("qcom", 18, 8);
            $table->double("vuncom", 18, 8);
            $table->string("indregra", 1);
            $table->string("csticms", 3);
            $table->string("cstpis", 3)->nullable()->default(null);
            $table->string("cstcofins", 3)->nullable()->default(null);
            $table->string("icmsorig", 1)->nullable()->default(null);
            $table->double("picms", 18, 8)->nullable()->default(null);
            $table->double("vbcpis", 18, 8)->nullable()->default(null);
            $table->double("ppis", 18, 8)->nullable()->default(null);
            $table->double("qbcprodpis", 18, 8)->nullable()->default(null);
            $table->double("valiqprodpis", 18, 8)->nullable()->default(null);
            $table->double("vbccofins", 18, 8)->nullable()->default(null);
            $table->double("pcofins", 18, 8)->nullable()->default(null);
            $table->double("qbcprodcofins", 18, 8)->nullable()->default(null);
            $table->double("valiqprodcofins", 18, 8)->nullable()->default(null);

            $table->unsignedInteger("nitem");

            //foreign keys
            $table->unsignedInteger('cupomfiscal_id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('nfimposto_id');
            $table->unsignedInteger('nfoperacao_id');
            $table->unsignedInteger('setor_id');
            $table->unsignedInteger("cprod");

            //não necessariamente vai no xml o campo mas é necessario para controle do sistema
            $table->string("cprodanp")->nullable()->defaut(null);
            $table->double("vaproxtribest", 18, 8)->nullable()->default(null);
            $table->double("vaproxtribmun", 18, 8)->nullable()->default(null);
            $table->double("vaproxtribfed", 18, 8)->nullable()->default(null);

            $table->foreign('cupomfiscal_id')->references('id')->on('cuponsfiscais');
            $table->foreign('nfoperacao_id')->references('id')->on('nfoperacaos');
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('nfimposto_id')->references('id')->on('nfimpostos');
            $table->foreign('cprod')->references('id')->on('produtos');
            $table->foreign('setor_id')->references('id')->on('setors');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cuponsfiscaisitens');
    }
}
