<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfemitidaitems3Table extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfemitidaitems',
                function (Blueprint $table) {
            $table->dropColumn(['predbcicms', 'taxafecop', 'cstconfins', 'vbcconfins']);
            $table->dropColumn([
                'cean', 'ceantrib', 'vbcstret', 'vicmsstret', 'vdesc', 'vfrete', 'cprodanp',
                'qbcprod', 'modbc', 'picmsst', 'vbc', 'picms', 'vicms',
                'vbcpis', 'valiqprod', 'vcide', 'cstipi', 'vbcipi', 'pipi', 'vipi',
                'tagipi'
            ]);
        });
        Schema::table('nfemitidaitems',
                function (Blueprint $table) {
            $table->string('cean', 14)->nullable();
            $table->string('ceantrib', 14)->nullable();
            $table->decimal('vbcstret', 15, 4)->nullable();
            $table->decimal('vicmsstret', 15, 4)->nullable();
            $table->decimal('vdesc', 15, 4)->nullable();
            $table->decimal('vfrete', 15, 4)->nullable();
            $table->string('cprodanp', 9)->nullable();
            $table->decimal('qbcprod', 14, 4)->nullable();
            $table->unsignedInteger('modbc')->nullable();
            $table->decimal('picmsst', 5, 2)->nullable();
            $table->decimal('vbc', 15, 4)->nullable();
            $table->decimal('picms', 5, 2)->nullable();
            $table->decimal('vicms', 15, 4)->nullable();
            $table->string('tagipi', 10)->nullable();
            $table->unsignedInteger('cstipi')->nullable();
            $table->decimal('vbcipi', 15, 4)->nullable();
            $table->decimal('pipi', 5, 2)->nullable();
            $table->decimal('vipi', 15, 4)->nullable();
            $table->decimal('vbcpis', 15, 4)->nullable();
            $table->decimal('valiqprod', 14, 4)->nullable();
            $table->decimal('vcide', 14, 4)->nullable();

            $table->string('cest', 14)->nullable();
            $table->unsignedInteger('nitemped')->nullable();
            $table->unsignedInteger("modbcst")->nullable();
            $table->decimal("pmvast", 8, 5)->nullable();
            $table->decimal("predbcst", 8, 5)->nullable();
            $table->decimal("vbcst", 15, 5)->nullable();
            $table->decimal("vicmsst", 15, 5)->nullable();
            $table->decimal("predbc", 8, 5)->nullable();
            $table->decimal("vicmsdeson", 15, 5)->nullable();
            $table->unsignedInteger("motdesicms")->nullable();
            $table->decimal("vicmsdif", 15, 5)->nullable();
            $table->decimal("pdif", 8, 5)->nullable();
            $table->decimal("vicmsop", 15, 5)->nullable();
            $table->decimal("pbcop", 8, 5)->nullable();
            $table->decimal("vbcstdest", 15, 5)->nullable();
            $table->decimal("pcredsn", 8, 5)->nullable();
            $table->decimal("vcredicmssn", 15, 5)->nullable();
            $table->decimal("pfcp", 8, 5)->nullable();
            $table->decimal("vfcp", 15, 5)->nullable();
            $table->decimal("vbcfcp", 15, 5)->nullable();
            $table->decimal("vbcfcpst", 15, 5)->nullable();
            $table->decimal("pfcpst", 8, 5)->nullable();
            $table->decimal("vfcpst", 15, 5)->nullable();
            $table->decimal("pst", 8, 5)->nullable();
            $table->decimal("vbcfcpstret", 15, 5)->nullable();
            $table->decimal("pfcpstret", 8, 5)->nullable();
            $table->decimal("vfcpstret", 15, 5)->nullable();
            $table->unsignedInteger("cstcofins")->nullable();
            $table->decimal("vbccofins", 15, 5)->nullable();
            $table->decimal("vseg", 15, 5)->nullable();
            $table->decimal("voutro", 15, 5)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfemitidaitems',
                function (Blueprint $table) {
            $table->decimal('predbcicms', 15, 4)->nullable();
            $table->decimal('taxafecop', 14, 4)->nullable();
            $table->unsignedInteger("cstconfins")->nullable();
            $table->decimal("vbcconfins", 15, 4)->nullable();

            $table->dropColumn([
                "modbcst", "pmvast", "predbcst", "vbcst", "vicmsst", "predbc", "vicmsdeson",
                "motdesicms", "vicmsdif", "pdif", "vicmsop", "pbcop", "vbcstdest", "pcredsn",
                "vcredicmssn", "pfcp", "vfcp", "vbcfcp", "vbcfcpst", "pfcpst", "vfcpst", "pst",
                "vbcfcpstret", "pfcpstret", "vfcpstret", 'cstcofins', 'vbccofins', 'vseg', 'voutro',
                'nitemped', 'cest'
            ]);
        });
    }

}
