<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableVendaativaocorrencias extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        Schema::table('vendaativaocorrencias', function (Blueprint $table) {
            $table->dropColumn('observacao');
        });

        Schema::table('vendaativaocorrencias', function (Blueprint $table) {
            $table->string('observacao',500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vendaativaocorrencias', function (Blueprint $table) {
            //
        });
    }
}
