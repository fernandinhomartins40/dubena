<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfrecebidaitemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfrecebidaitems', function (Blueprint $table) {
            $table->dropColumn('cstconfins', 'vbcconfins');
        });
        
        Schema::table('nfrecebidaitems', function (Blueprint $table) {
            $table->unsignedInteger('cstcofins')->default(0);
            $table->decimal('vbccofins', 15, 4)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfrecebidaitems', function (Blueprint $table) {
            $table->dropColumn('cstcofins', 'vbccofins');
        });
        
        Schema::table('nfrecebidaitems', function (Blueprint $table) {
            $table->unsignedInteger('cstconfins')->default(0);
            $table->decimal('vbcconfins', 15, 4)->default(0);
        });
    }
}
