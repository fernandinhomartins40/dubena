<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableNfcest1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfcests', function (Blueprint $table) {
             $table->dropColumn('ncm');
             $table->dropColumn('descricao');
        });
        Schema::table('nfcests', function (Blueprint $table) {
            $table->string('ncm',8)->nullable();
            $table->text('descricao',700);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfcests', function (Blueprint $table) {
            //
        });
    }
}
