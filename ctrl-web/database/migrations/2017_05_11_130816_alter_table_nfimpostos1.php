<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableNfimpostos1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfimpostos', function (Blueprint $table) {
            $table->dropColumn('reducaoicms');
            $table->dropColumn('pfreducaoicms');
            $table->dropColumn('mva');
            $table->dropColumn('pfmva');
            $table->dropColumn('mvareduzido');
            $table->dropColumn('pftaxafecop');
            $table->dropColumn('pfinformacoesadicional');
            $table->dropColumn('pfinformacoesadicionalfisco');
            $table->dropColumn('informacoesadicional');
            $table->dropColumn('informacoesadicionalfisco');
            $table->dropColumn('piscofinsgeracredito');
            $table->dropColumn('piscofinsnatreceita');
            $table->dropColumn('piscofinstipobccredito');
            $table->dropColumn('piscofinstipocredito');              
            $table->dropColumn('pfnfpisaliqcred');
            $table->dropColumn('nfpisaliqcred');
            $table->dropColumn('nfcofinsaliqcred');
        });
        Schema::table('nfimpostos', function (Blueprint $table) {
            $table->decimal('reducaoicms',3,2)->nullable();
            $table->decimal('pfreducaoicms',3,2)->nullable();
            $table->decimal('mva',3,2)->nullable();
            $table->decimal('pfmva',3,2)->nullable();
            $table->decimal('mvareduzido',3,2)->nullable();
            $table->decimal('pftaxafecop',3,2)->nullable();
            $table->string('pfinformacoesadicional',250)->nullable();
            $table->string('informacoesadicional',250)->nullable();
            $table->decimal('pfnfpisaliqcred',3,2)->nullable();
            $table->decimal('nfpisaliqcred',3,2)->nullable();
            $table->decimal('nfcofinsaliqcred',3,2)->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfimpostos', function (Blueprint $table) {
            //
        });
    }
}
