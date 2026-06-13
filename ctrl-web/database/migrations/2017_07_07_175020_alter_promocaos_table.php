<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPromocaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('promocaos', function (Blueprint $table) {
            $table->dropColumn('produto_id');
            $table->dropColumn('premioproduto_id');
        });

        Schema::table('promocaos', function (Blueprint $table) {
          $table->unsignedInteger('produto_id')->nullable();
          $table->unsignedInteger('premioproduto_id')->nullable();
          $table->foreign('produto_id')->references('id')->on('produtos');
          $table->foreign('premioproduto_id')->references('id')->on('produtos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('promocaos', function (Blueprint $table) {
            //
        });
    }
}
