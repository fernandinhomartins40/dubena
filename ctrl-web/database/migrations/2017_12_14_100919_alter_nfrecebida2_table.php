<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfrecebida2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfrecebidas', function (Blueprint $table) {
            $table->text('xmlassinado')->nullable()->default(null);
            $table->string('planocontadescricao', 50)->nullable()->default('');
            $table->string('naturezasped', 2)->nullable()->default(null);
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
