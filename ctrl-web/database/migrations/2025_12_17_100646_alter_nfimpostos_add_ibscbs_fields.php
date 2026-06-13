<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfimpostosAddIbscbsFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
       public function up()
    {
        Schema::table('nfimpostos', function (Blueprint $table) {
            $table->unsignedInteger("nfcstibscbs_id")->nullable();
            $table->unsignedInteger("nfclastribibscbs_id")->nullable();
            $table->decimal("nfibscbsbase", 8, 3)->nullable();
            $table->decimal("nfibsufaliq", 8, 3)->nullable();
            $table->decimal("nfibsufaliqmono", 8, 3)->nullable();
            $table->decimal("nfibsmunaliq", 8, 3)->nullable();
            $table->decimal("nfibsmunaliqmono", 8, 3)->nullable();
            $table->unsignedInteger("pfcstibscbs_id")->nullable();
            $table->unsignedInteger("pfclastribibscbs_id")->nullable();
            $table->decimal("pfibscbsbase", 8, 3)->nullable();
            $table->decimal("pfibsufaliq", 8, 3)->nullable();
            $table->decimal("pfibsufaliqmono", 8, 3)->nullable();
            $table->decimal("pfibsmunaliq", 8, 3)->nullable();
            $table->decimal("pfibsmunaliqmono", 8, 3)->nullable();
            $table->decimal("nfcbsaliq", 8, 3)->nullable();
            $table->decimal("nfcbsaliqmono", 8, 3)->nullable();
            $table->decimal("pfcbsaliq", 8, 3)->nullable();
            $table->decimal("pfcbsaliqmono", 8, 3)->nullable();
            $table->foreign('nfcstibscbs_id')->references('id')->on('nfcsts');
            $table->foreign('pfcstibscbs_id')->references('id')->on('nfcsts');
            $table->foreign('nfclastribibscbs_id')->references('id')->on('nfclastribs');
            $table->foreign('pfclastribibscbs_id')->references('id')->on('nfclastribs');

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
            $table->dropColumn("nfcstibscbs_id");
            $table->dropColumn("nfclastribibscbs_id");
            $table->dropColumn("nfibscbsbase");
            $table->dropColumn("nfibsufaliq");
            $table->dropColumn("nfibsufaliqmono");
            $table->dropColumn("nfibsmunaliq");
            $table->dropColumn("nfibsmunaliqmono");
            $table->dropColumn("pfcstibscbs_id");
            $table->dropColumn("pfclastribibscbs_id");
            $table->dropColumn("pfibscbsbase");
            $table->dropColumn("pfibsufaliq");
            $table->dropColumn("pfibsufaliqmono");
            $table->dropColumn("pfibsmunaliq");
            $table->dropColumn("pfibsmunaliqmono");
            $table->dropColumn("nfcbsaliq");
            $table->dropColumn("nfcbsaliqmono");
            $table->dropColumn("pfcbsaliq");
            $table->dropColumn("pfcbsaliqmono");

        });
    }
}
