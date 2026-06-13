<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableTrocaoleos2 extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('veiculotrocaoleos', function(Blueprint $table) {
            $table->dropColumn('alertaantes');
            $table->dropColumn('kmalertaantes');
        });
        Schema::table('veiculotrocaoleos', function(Blueprint $table) {
            $table->decimal('kmalertaantes',13,3)->nullable();
            $table->boolean('alertaantes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        //
    }

}
