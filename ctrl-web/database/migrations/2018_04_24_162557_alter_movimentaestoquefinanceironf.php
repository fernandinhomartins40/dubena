<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterMovimentaestoquefinanceironf extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfemitidaitems', function (Blueprint $table) {
            $table->unsignedInteger('movimentaestoque')->nullable()->default(false);
        });
        
        Schema::table('nfrecebidaitems', function (Blueprint $table) {
            $table->unsignedInteger('movimentaestoque')->nullable()->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {   
        Schema::table('nfemitidaitems', function (Blueprint $table) {
            $table->dropColumn('movimentaestoque');
        });
        
        Schema::table('nfrecebidaitems', function (Blueprint $table) {
            $table->dropColumn('movimentaestoque');
        });
    }
}
