<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPixtransactionsAddidcobranca extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pixtransactions', function (Blueprint $table) {
            $table->string("cobranca_id", 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pixtransactions', function (Blueprint $table) {
            $table->dropColumn("cobranca_id");
        });
    }
}
