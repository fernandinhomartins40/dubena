<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfrecebidaitemsAddIbscbsFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfrecebidaitems', function (Blueprint $table) {
            $table->string ('cstibscbs', 3)->nullable()->default(null);
            $table->string ('clastribibscbs', 6)->nullable()->default(null);
            $table->decimal('vbcibscbs', 15, 4)->nullable()->default(null);
            $table->decimal('predbcibscbs', 5, 2)->nullable()->default(null);
            $table->decimal('pibsuf', 5, 2)->nullable()->default(null);
            $table->decimal('vibsuf', 15, 4)->nullable()->default(null);
            $table->decimal('pibsmun', 5, 2)->nullable()->default(null);
            $table->decimal('vibsmun', 15, 4)->nullable()->default(null);
            $table->decimal('vbccbs', 15, 4)->nullable()->default(null);
            $table->decimal('pcbs', 5, 2)->nullable()->default(null);
            $table->decimal('vcbs', 15, 4)->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfrecebidaitems', function (Blueprint $table) {
            $table->dropColumn('cstibscbs');
            $table->dropColumn('clastribibscbs');
            $table->dropColumn('vbcibscbs');
            $table->dropColumn('predbcibscbs');
            $table->dropColumn('pibsuf');
            $table->dropColumn('vibsuf');
            $table->dropColumn('pibsmun');
            $table->dropColumn('vibsmun');
            $table->dropColumn('vbccbs');
            $table->dropColumn('pcbs');
            $table->dropColumn('vcbs');
        });
    }
}
