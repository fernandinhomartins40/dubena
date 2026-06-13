<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfrecebidas9Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfrecebidas', function (Blueprint $table) {
            $table->decimal("vfcpstret", 15, 5)->nullable();
            $table->decimal("vfcpst", 15, 5)->nullable();
            $table->decimal("vicmsufremet", 15, 5)->nullable();
            $table->decimal("vicmsufdest", 15, 5)->nullable();
            $table->decimal("vfcpufdest", 15, 5)->nullable();
            $table->decimal("vicmsdeson", 15, 5)->nullable();
            $table->decimal("vfcp", 15, 5)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfrecebidas', function (Blueprint $table) {
            $table->dropColumn(["vfcpstret", "vfcpst", "vicmsufremet", "vicmsufdest", "vfcpufdest", "vicmsdeson", 'vfcp']);
        });
    }
}
