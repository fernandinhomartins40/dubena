<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterFinanceiroparcelas5Table extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('financeiroparcelas', function (Blueprint $table) {
            $table->unsignedInteger('financeirotaxa_id')->nullable()->default(null);

            $table->foreign('financeirotaxa_id')->references('id')->on('financeiros');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('financeiroparcelas', function (Blueprint $table) {
            $table->dropColumn("financeirotaxa_id");
        });
    }

}
