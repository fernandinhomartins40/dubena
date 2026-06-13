<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfemitidaitems2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfemitidaitems', function (Blueprint $table) {
            $table->decimal('customedio', 16, 8)->default(0);
            $table->dropColumn('comissao');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfemitidaitems', function (Blueprint $table) {
            $table->dropColumn('customedio');
            $table->decimal('comissao', 5, 2)->nullable()->default(null);
        });
    }
}
