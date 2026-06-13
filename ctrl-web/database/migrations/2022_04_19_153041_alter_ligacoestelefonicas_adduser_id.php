<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterLigacoestelefonicasAdduserId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ligacoestelefonicas', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->nullable();
            $table->boolean("atendida")->nullable()->default(false);

            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ligacoestelefonicas', function (Blueprint $table) {
            //
        });
    }
}
