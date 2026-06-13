<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableTelefones extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('agenciatelefones', function (Blueprint $table) {
            $table->dropColumn('whatsapp');
        });
        Schema::table('colaboradortelefones', function (Blueprint $table) {
            $table->dropColumn('whatsapp');
        });
        Schema::table('clientetelefones', function (Blueprint $table) {
            $table->dropColumn('movel');
        });
        Schema::table('colaboradortelefones', function (Blueprint $table) {
            $table->dropColumn('movel');
        });
        Schema::table('agenciatelefones', function (Blueprint $table) {
            $table->dropColumn('movel');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clientetelefones', function (Blueprint $table) {
            //
        });
    }
}
