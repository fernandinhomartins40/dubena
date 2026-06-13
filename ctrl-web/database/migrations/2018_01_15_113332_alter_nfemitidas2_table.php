<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfemitidas2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfemitidas', function (Blueprint $table) {
            $table->dropColumn(['setor_id', 'emitcidade', 'comissao', 'formavendacodigo']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfemitidas', function (Blueprint $table) {
            $table->unsignedInteger('setor_id')->nullable()->default(null);
            $table->foreign('setor_id')->references('id')->on('setors');
            $table->decimal('comissao', 5, 2)->nullable()->default(null);
            $table->string('emitcidade', 40)->nullable()->default(null);
            $table->unsignedInteger('formavendacodigo')->nullable()->default(null);
        });
    }
}
