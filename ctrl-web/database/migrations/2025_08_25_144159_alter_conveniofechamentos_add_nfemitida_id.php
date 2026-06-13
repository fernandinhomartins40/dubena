<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterConveniofechamentosAddNfemitidaId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('conveniofechamentos', function (Blueprint $table) {
            $table->unsignedInteger('nfemitida_id')->nullable()->default(null);
            $table->foreign('nfemitida_id')->references('id')->on('nfemitidas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('conveniofechamentos', function (Blueprint $table) {
            $table->dropColumn("nfemitida_id");
        });
    }
}
