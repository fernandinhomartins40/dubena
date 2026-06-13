<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNfemitidasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nfemitidas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('nfoperacao_id')->nullable()->default(null);

            $table->string('chaveacesso', 47)->nullable()->default(null);
            $table->unsignedInteger('chaveacessodv')->nullable()->default(null);
            $table->unsignedInteger('cfop')->nullable()->default(null);
            $table->string('descricaooperacao', 60)->nullable()->default(null);
            $table->unsignedInteger('formapagamento')->nullable()->default(null);
            $table->string('nfmodelo', 2);
            $table->string('nfserie', 4);
            $table->unsignedInteger('nfnumero');
            $table->dateTime('datahoraemissao');
            $table->dateTime('datahoraentradasaida')->nullable()->default(null);
            $table->unsignedInteger('tipo')->nullable()->default(null);

            $table->unsignedInteger('nftipoimpressao')->nullable()->default(null);
            $table->unsignedInteger('nftipoemissao');
            $table->unsignedInteger('nftipoambiente');
            $table->unsignedInteger('nfefinalidade');//1=NF-e normal; 2=NF-e complementar; 3=NF-e de ajuste; 4=Devolução de mercadoria;
            $table->unsignedInteger('nfprocessoemissao');//0-emissão de NF-e com aplicativo do contribuinte; 1-emissão de NF-e avulsa pelo Fisco; 2-emissão de NF-e avulsa, pelo contribuinte com seu certificado digital, através do site do Fisco; 3-emissão NF-e pelo contribuinte com aplicativo fornecido pelo Fisco;
            $table->string('nfversaoprocessamento', 4);

            $table->string('emitcnpj', 14)->nullable()->default(null);
            $table->string('emitcpf', 14)->nullable()->default(null);
            $table->string('emitrazaosocial', 60)->nullable()->default(null);
            $table->string('emitnomefantasia', 60)->nullable()->default(null);
            $table->string('emitendereco', 60)->nullable()->default(null);
            $table->string('emitnumero', 20)->nullable()->default(null);
            $table->string('emitcomplemento', 60)->nullable()->default(null);
            $table->string('emitbairro', 60)->nullable()->default(null);
            $table->string('emitcidade', 40)->nullable()->default(null);
            $table->unsignedInteger('emitcidade_id')->nullable()->default(null);
            $table->string('emitcidadenome', 60)->nullable()->default(null);
            $table->unsignedInteger('emitcidadecodigoibge')->nullable()->default(null);
            $table->string('emituf', 2)->nullable()->default(null);
            $table->unsignedInteger('emitufcodigoibge')->nullable()->default(null);
            $table->unsignedInteger('emitcep')->nullable()->default(null);
            $table->string('emitpaisnome', 60)->nullable()->default(null);
            $table->unsignedInteger('emitpaiscodigoibge')->nullable()->default(null);
            $table->string('emittelefone', 10)->nullable()->default(null);
            $table->string('emitie', 14)->nullable()->default(null);
            $table->string('emitinscricaomunicipal', 50)->nullable()->default(null);
            $table->string('emitcnae', 50)->nullable()->default(null);

            $table->string('destcpf', 14)->nullable()->default(null);
            $table->string('destcnpj', 14)->nullable()->default(null);
            $table->string('destrazaosocial', 60)->nullable()->default(null);
            $table->string('destendereco', 60)->nullable()->default(null);
            $table->string('destnumero', 20)->nullable()->default(null);
            $table->string('destcomplemento', 60)->nullable()->default(null);
            $table->string('destbairro', 60)->nullable()->default(null);
            $table->unsignedInteger('destcidade_id')->nullable()->default(null);
            $table->string('destcidadenome', 60)->nullable()->default(null);
            $table->unsignedInteger('destcidadecodigoibge')->nullable()->default(null);
            $table->string('destuf', 2)->nullable()->default(null);
            $table->unsignedInteger('destcep')->nullable()->default(null);
            $table->unsignedInteger('destpaiscodigoibge')->nullable()->default(null);
            $table->string('destpaisnome', 60)->nullable()->default(null);
            $table->string('desttelefone', 10)->nullable()->default(null);
            $table->unsignedInteger('destie')->nullable()->default(null);
            $table->unsignedInteger('destindicadorie')->nullable()->default(null);
            $table->string('destemail', 100)->nullable()->default(null);

            $table->unsignedInteger('fretemodalidade')->nullable()->default(null);
            $table->string('fretecpf', 14)->nullable()->default(null);
            $table->string('fretecnpj', 14)->nullable()->default(null);
            $table->string('freterazaosocial', 60)->nullable()->default(null);
            $table->string('freteenderecocompl', 60)->nullable()->default(null);
            $table->string('fretecidadenome', 60)->nullable()->default(null);
            $table->string('fretuf', 2)->nullable()->default(null);
            $table->string('fretie', 14)->nullable()->default(null);
            $table->string('freteplaca', 8)->nullable()->default(null);
            $table->string('freteplacauf', 2)->nullable()->default(null);
            $table->text('informacaocomplementar')->nullable()->default(null);
            $table->text('informacaoadicionalfisco')->nullable()->default(null);

            $table->unsignedInteger('cliente_id')->nullable()->default(null);
            $table->unsignedInteger('codcnf')->nullable()->default(null);
            $table->unsignedInteger('codcdv')->nullable()->default(null);
            $table->unsignedInteger('codcrt')->nullable()->default(null);
            $table->unsignedInteger('nitem')->nullable()->default(null);
            $table->decimal('vbc', 12, 2)->nullable()->default(null);
            $table->decimal('vicms', 12, 2)->nullable()->default(null);
            $table->decimal('vbcst', 12, 2)->nullable()->default(null);
            $table->decimal('vst', 12, 2)->nullable()->default(null);
            $table->decimal('vprod', 12, 2)->nullable()->default(null);
            $table->decimal('vfrete', 12, 2)->nullable()->default(null);
            $table->decimal('vseg', 12, 2)->nullable()->default(null);
            $table->decimal('vdesc', 12, 2)->nullable()->default(null);
            $table->decimal('vii', 12, 2)->nullable()->default(null);
            $table->decimal('vipi', 12, 2)->nullable()->default(null);
            $table->decimal('vpis', 12, 2)->nullable()->default(null);
            $table->decimal('vcofins', 12, 2)->nullable()->default(null);
            $table->decimal('voutro', 12, 2)->nullable()->default(null);
            $table->decimal('vnf', 12, 2)->nullable()->default(null);

            $table->text('xml')->nullable()->default(null);
            $table->text('xmlretorno')->nullable()->default(null);

            $table->unsignedInteger('nfsituacao_id')->nullable()->default(null);
            $table->string('protocolo', 100)->nullable()->default(null);
            $table->string('protocoloretornocancelamento', 100)->nullable()->default(null);
            $table->text('xmlretornocancelamento')->nullable()->default(null);

            $table->dateTime('contigenciadatahora')->nullable()->default(null);
            $table->string('contigenciajustificativa', 250)->nullable()->default(null);

            $table->text('dpecxmlretorno')->nullable()->default(null);
            $table->string('dpecregistro', 100)->nullable()->default(null);
            $table->dateTime('dpecregistrodatahora')->nullable()->default(null);
            $table->string('dpecid', 100)->nullable()->default(null);

            $table->text('cancelamentoevexmlretorno')->nullable()->default(null);
            $table->string('cancelamentoeveprotocoloret', 100)->nullable()->default(null);
            $table->string('cancelamentomotivo', 250)->nullable()->default(null);

            $table->unsignedInteger('numeroreciboenvio')->nullable()->default(null);
            $table->string('statusevento', 100)->nullable()->default(null);

            $table->unsignedInteger('formavendacodigo')->nullable()->default(null);
            $table->unsignedInteger('financeiro_id')->nullable()->default(null);
            $table->unsignedInteger('fretefinanceiro_id')->nullable()->default(null);
            $table->unsignedInteger('planoconta_id')->nullable()->default(null);
            $table->unsignedInteger('centrocusto_id')->nullable()->default(null);
            $table->unsignedInteger('freteplanoconta_id')->nullable()->default(null);
            $table->unsignedInteger('fretecentrocusto_id')->nullable()->default(null);
            $table->unsignedInteger('setor_id')->nullable()->default(null);
            $table->unsignedInteger('user_id')->nullable()->default(null);

            $table->decimal('comissao', 5, 2)->nullable()->default(null);

            $table->unsignedInteger('fretecliente_id')->nullable()->default(null);
            $table->unsignedInteger('condicaopagamento_id')->nullable()->default(null);

            $table->decimal('vbcfunrural', 12, 2)->nullable()->default(null);
            $table->decimal('vpfunrural', 12, 2)->nullable()->default(null);
            $table->decimal('vfunrural', 12, 2)->nullable()->default(null);

            $table->text('xmlretornocompleto')->nullable()->default(null);
            $table->text('xmlretornocompletopath')->nullable()->default(null);
            $table->text('xmlretornoeventocartacorrecao')->nullable()->default(null);
            $table->string('protocoloretevecartacorrecao', 100)->nullable()->default(null);
            $table->string('chaveacessoref', 47)->nullable()->default(null);

            $table->unsignedInteger('nfsituacaoanterior_id')->nullable()->default(null);
            $table->string('epecprotocolo', 100)->nullable()->default(null);
            $table->text('epecxml')->nullable()->default(null);
            $table->string('epecstatusevento', 100)->nullable()->default(null);

            $table->unsignedInteger('presencacomprador')->nullable()->default(null);
            $table->unsignedInteger('emissao')->nullable()->default(null);
            $table->string('descricaofinanceiro', 100)->nullable()->default(null);
            $table->boolean('inutilizarcancelar')->default(false);
            $table->dateTime('datahoraautorizacao')->nullable()->default(null);

            $table->boolean('fretemaisnf')->default(false);
            $table->boolean('existerateio')->default(false);

            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('nfoperacao_id')->references('id')->on('nfoperacaos');
            $table->foreign('emitcidade_id')->references('id')->on('cidades');
            $table->foreign('destcidade_id')->references('id')->on('cidades');
            $table->foreign('cliente_id')->references('id')->on('clientes');
            $table->foreign('nfsituacao_id')->references('id')->on('nfsituacaos');
            $table->foreign('financeiro_id')->references('id')->on('financeiros');
            $table->foreign('fretefinanceiro_id', 'nfemit_financeiros_foreign')->references('id')->on('financeiros');
            $table->foreign('planoconta_id')->references('id')->on('planocontas');
            $table->foreign('centrocusto_id')->references('id')->on('centrocustos');
            $table->foreign('freteplanoconta_id', 'nfemit_planocontas_foreign')->references('id')->on('planocontas');
            $table->foreign('fretecentrocusto_id', 'nfemit_centrocustos_foreign')->references('id')->on('centrocustos');
            $table->foreign('setor_id')->references('id')->on('setors');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('fretecliente_id', 'nfemit_clientes_foreign')->references('id')->on('clientes');
            $table->foreign('condicaopagamento_id', 'nfemit_condpag_foreign')->references('id')->on('condicaopagamentos');
            $table->foreign('nfsituacaoanterior_id', 'nfemit_nfsituacaos_foreign')->references('id')->on('nfsituacaos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('nfemitidas');
    }
}
