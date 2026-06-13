<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEmpresainutilizacaos1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresainutilizacaos', function (Blueprint $table) {
            $table->dropColumn(['xmlenvio', 'xmlretorno']);
        });
        Schema::table('empresainutilizacaos', function (Blueprint $table) {
            $table->text('xmlenvio')->nullable()->default(null);
            $table->text('xmlretorno')->nullable()->default(null);
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
