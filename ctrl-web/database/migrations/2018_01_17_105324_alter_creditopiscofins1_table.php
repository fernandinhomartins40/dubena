<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterCreditopiscofins1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('creditopiscofins', function (Blueprint $table) {
            $table->dropColumn('codigo');
        });

        Schema::table('creditopiscofins', function (Blueprint $table) {
            $table->integer('parent_identificador')->nullable();
            $table->string('codigo',3)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
        Schema::table('creditopiscofins', function (Blueprint $table) {
            $table->dropColumn('parent_identificador');
            $table->dropColumn('codigo');
        });

        Schema::table('creditopiscofins', function (Blueprint $table) {
            $table->integer('codigo');
        });
    }
}
