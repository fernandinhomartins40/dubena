<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropUnecessarycolumnsNfparcelasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfemitidaparcelas', function (Blueprint $table) {
            $table->dropColumn(['moradiaria', 'valormulta', 'valorjuros', 'baixado']);
        });
        Schema::table('nfrecebidaparcelas', function (Blueprint $table) {
            $table->dropColumn(['moradiaria', 'valormulta', 'valorjuros', 'baixado']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfemitidaparcelas', function (Blueprint $table) {
            $table->decimal('moradiaria', 15, 4)->default(0);
            $table->decimal('valormulta', 15, 4)->default(0);
            $table->decimal('valorjuros', 15, 4)->default(0);
            $table->boolean('baixado')->default(false);
        });
        Schema::table('nfrecebidaparcelas', function (Blueprint $table) {
            $table->decimal('moradiaria', 15, 4)->default(0);
            $table->decimal('valormulta', 15, 4)->default(0);
            $table->decimal('valorjuros', 15, 4)->default(0);
            $table->boolean('baixado')->default(false);
        });
    }
}
