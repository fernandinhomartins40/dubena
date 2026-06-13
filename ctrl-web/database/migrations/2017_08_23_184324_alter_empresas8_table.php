<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEmpresas8Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->float('latitude', 8,15)->nullable()->default(null);
            $table->float('longitude', 8,15)->nullable()->default(null);
        });
        Schema::table('empresas', function (Blueprint $table) {
            $table->float('latitude', 8,15)->nullable()->default(null)->change();
            $table->float('longitude', 8,15)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresas', function (Blueprint $table) {
            //
        });
    }
}
