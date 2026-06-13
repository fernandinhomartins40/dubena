<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEmpresaconfig3 extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->dropColumn('operacaodisk');
            $table->dropColumn('contadevolucaocheck');
            $table->dropColumn('emailremetente');
            $table->dropColumn('emailnomeremente');
            $table->dropColumn('emailusuario');
            $table->dropColumn('emailsenha');
            $table->dropColumn('emailservidorsmtp');
            $table->dropColumn('emailportasmtp');
            $table->dropColumn('emailrequerautenticacao');
            $table->dropColumn('emailrequerconexaotls');
            $table->dropColumn('emailassunto');
            $table->dropColumn('emailcorpo');
        });

        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->text('emailremetente', 100)->nullable();
            $table->text('emailnomeremente', 100)->nullable();
            $table->text('emailusuario', 100)->nullable();
            $table->text('emailsenha', 100)->nullable();
            $table->text('emailservidorsmtp', 100)->nullable();
            $table->text('emailportasmtp', 10)->nullable();
            $table->text('emailassunto', 100)->nullable();
            $table->text('emailcorpo', 1000)->nullable();
            $table->boolean('emailrequerautenticacao')->nullable()->default(0);
            $table->boolean('emailrequerconexaotls')->nullable()->default(0);
            
            $table->unsignedInteger('operacaodisk')->nullable();
            $table->unsignedInteger('contadevolucaocheque')->nullable();
            $table->unsignedInteger('pedidostatuspadrao')->nullable();
            $table->unsignedInteger('pccartao_id')->nullable();
            $table->unsignedInteger('pcreceitadesconto_id')->nullable();
            $table->unsignedInteger('pcrecetajuro_id')->nullable();
            $table->unsignedInteger('pcdespesasdesconto_id')->nullable();
            $table->unsignedInteger('pcdespesasjuro_id')->nullable();
            $table->unsignedInteger('ccvalegas_id')->nullable();
            $table->unsignedInteger('pcvalegas_id')->nullable();

            $table->foreign('operacaodisk')->references('id')->on('nfoperacaos')->onUpdate('cascade');
            $table->foreign('contadevolucaocheque')->references('id')->on('contas')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('pedidostatuspadrao')->references('id')->on('pedidosituacaos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('pccartao_id')->references('id')->on('planocontas')->onUpdate('cascade');
            $table->foreign('pcreceitadesconto_id')->references('id')->on('planocontas')->onUpdate('cascade');
            $table->foreign('pcrecetajuro_id')->references('id')->on('planocontas')->onUpdate('cascade');
            $table->foreign('pcdespesasdesconto_id')->references('id')->on('planocontas')->onUpdate('cascade');
            $table->foreign('pcdespesasjuro_id')->references('id')->on('planocontas')->onUpdate('cascade');
            $table->foreign('ccvalegas_id')->references('id')->on('centrocustos')->onUpdate('cascade');
            $table->foreign('pcvalegas_id')->references('id')->on('planocontas')->onupdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            //
        });
    }

}
