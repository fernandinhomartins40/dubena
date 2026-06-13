<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterConfiguracoesgerais3Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('configuracoesgerais', function (Blueprint $table) {
            $table->string("rtcnpj", 20)->default(null)->nullable();
            $table->string("rtcontato", 200)->default(null)->nullable();
            $table->string("rtemail", 150)->default(null)->nullable();
            $table->string("rttelefone", 20)->default(null)->nullable();
            $table->string("rtidcsrt", 255)->default(null)->nullable();
            $table->string("rtcsrt", 255)->default(null)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('configuracoesgerais', function (Blueprint $table) {
            $table->dropColumn("rtcnpj");
            $table->dropColumn("rtcontato");
            $table->dropColumn("rtemail");
            $table->dropColumn("rttelefone");
            $table->dropColumn("rtidcsrt");
            $table->dropColumn("rtcsrt");
        });
    }
}
