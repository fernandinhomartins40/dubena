<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterUsers2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn("horaabertura");
            $table->dropColumn("horafechamento");
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean("permiteagendamento")->default(false);
            $table->string("fantasia", 100)->nullable()->default(null);

            $table->time("semanahoraabertura")->nullable()->default(null);
            $table->time("semanahorafechamento")->nullable()->default(null);
            $table->time("sabadohoraabertura")->nullable()->default(null);
            $table->time("sabadohorafechamento")->nullable()->default(null);
            $table->time("domingohoraabertura")->nullable()->default(null);
            $table->time("domingohorafechamento")->nullable()->default(null);
            $table->time("feriadohoraabertura")->nullable()->default(null);
            $table->time("feriadohorafechamento")->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time("horaabertura");
            $table->time("horafechamento");
            $table->dropColumn("permiteagendamento");
            $table->dropColumn("fantasia");
            $table->dropColumn("semanahoraabertura");
            $table->dropColumn("semanahorafechamento");
            $table->dropColumn("sabadohoraabertura");
            $table->dropColumn("sabadohorafechamento");
            $table->dropColumn("domingohoraabertura");
            $table->dropColumn("domingohorafechamento");
            $table->dropColumn("feriadohoraabertura");
            $table->dropColumn("feriadohorafechamento");
        });
    }
}
