<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCuponsfiscaisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cuponsfiscais', function (Blueprint $table) {
            $table->increments("id");

            //ide
            $table->string("qti_cnpj", 14);
            $table->string("signac");
            $table->string("numerocaixa");

            //campos preenchidos pelo SAT
            $table->string("assinaturaqrcode")->nullable()->default(null);
            $table->string("tpamb")->nullable()->default(null);
            $table->string("cdv")->nullable()->default(null);
            $table->date("hemi")->nullable()->default(null);
            $table->time("demi")->nullable()->default(null);
            $table->string("ncfe")->nullable()->default(null);
            $table->string("nseriesat")->nullable()->default(null);
            $table->char("mod", 2)->nullable()->default(null);
            $table->string("cnf")->nullable()->default(null);
            $table->smallInteger("cuf")->nullable()->default(null);

            //emit ac
            $table->string("emitcnpj", 14);
            $table->string("emitie", 20);
            $table->string("emitim", 20)->nullable()->default(null);
            $table->string("emitindratissqn");
            $table->string("emitcregtribissqn")->nullable()->default(null);

            //emit sat
            $table->string("emitxnome")->nullable()->default(null);
            $table->string("emitxfant")->nullable()->default(null);
            $table->char("emitcregtrib", 1)->nullable()->default(null);

            //dest ac
            $table->string("destcnpj", 14)->nullable()->default(null);
            $table->string("destcpf", 11)->nullable()->default(null);
            $table->string("destxnome");


            //entrega ac
            $table->string("destxlgr");
            $table->string("destnro");
            $table->string("destxcpl")->nullable()->default(null);
            $table->string("destxbairro");
            $table->string("destxmun");
            $table->string("destuf", 2);


            //icmstot sat
            $table->double("icmsvicms", 18, 8)->nullable()->default(null);
            $table->double("icmsvprod", 18, 8)->nullable()->default(null);
            $table->double("icmsvdesc", 18, 8)->nullable()->default(null);
            $table->double("icmsvpis", 18, 8)->nullable()->default(null);
            $table->double("icmsvcofins", 18, 8)->nullable()->default(null);
            $table->double("icmsvpisst", 18, 8)->nullable()->default(null);
            $table->double("icmsvcofinsst", 18, 8)->nullable()->default(null);
            $table->double("icmsvoutro", 18, 8)->nullable()->default(null);
            $table->double("icmsvcfe", 18, 8)->nullable()->default(null);

            //issqntot sat
            $table->double("issqnvbc", 18, 8)->nullable()->default(null);
            $table->double("issqnviss", 18, 8)->nullable()->default(null);
            $table->double("issqnvpis", 18, 8)->nullable()->default(null);
            $table->double("issqnvcofins", 18, 8)->nullable()->default(null);
            $table->double("issqnvpisst", 18, 8)->nullable()->default(null);
            $table->double("issqnvcofinsst", 18, 8)->nullable()->default(null);

            $table->double( "vdescsubtot", 18, 8)->nullable()->default();
            $table->double("vacressubtot", 18, 8)->nullable()->default();
            $table->double( "vcfelei12741", 18, 8)->nullable()->default();

            //campos preenchidos pelo usuário - não obrigatórios
            $table->string("infcpl")->nullable()->default(null);

            //campos preenchidos pelo usuário - não obrigatórios
            $table->integer("status")->default(1);
            $table->string("status_descricao")->default(1);

            $table->timestamps();

            $table->text('xml')->nullable()->default(null);
            $table->text('xmlretorno')->nullable()->default(null);
            $table->string('protocolo', 100)->nullable()->default(null);
            $table->string('protocoloretornocancelamento', 100)->nullable()->default(null);
            $table->text('xmlretornocancelamento')->nullable()->default(null);
            $table->text('produtosJson')->nullable()->default(null);

            //foreign keys
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('cliente_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('nfoperacao_id');
            $table->unsignedInteger('planoconta_id')->nullable()->default(null);
            $table->unsignedInteger('financeiro_id')->nullable()->default(null);
            $table->unsignedInteger('centrocusto_id')->nullable()->default(null);
            $table->unsignedInteger('condicaopagamento_id')->nullable()->default(null);

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('cliente_id')->references('id')->on('clientes');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('nfoperacao_id')->references('id')->on('nfoperacaos');
            $table->foreign('planoconta_id')->references('id')->on('planocontas');
            $table->foreign('financeiro_id')->references('id')->on('financeiros');
            $table->foreign('centrocusto_id')->references('id')->on('centrocustos');
            $table->foreign('condicaopagamento_id')->references('id')->on('condicaopagamentos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('cuponsfiscais');
    }
}
