<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterRastreamentoVeiculos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('users', function (Blueprint $table) {
          $table->boolean('usarastreamento')->nullable()->default(false);
      });
      Schema::table('setors', function (Blueprint $table) {
          $table->boolean('usarastreamento')->nullable()->default(false);
      });
      Schema::table('veiculos', function (Blueprint $table) {
          $table->boolean('usarastreamento')->nullable()->default(false);
      });
      Schema::table('veiculotipos', function (Blueprint $table) {
          $table->unsignedInteger('tiporastreamento')->nullable()->default(null);
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
