<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPromocaos2Table extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::table('promocaos', function (Blueprint $table) {
            $table->dropColumn('quantidadepedidos');
            $table->dropColumn('quantidadepremios');
        });
        Schema::table('promocaos', function (Blueprint $table) {
          $table->unsignedInteger('quantidadepedidos')->nullable()->default('0');
          $table->unsignedInteger('quantidadepremios')->nullable()->default('0');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }

}
