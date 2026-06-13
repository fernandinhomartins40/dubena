<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterUsers5Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE users ADD COLUMN thumbnail LONGBLOB DEFAULT NULL");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn("caminhoimagem");
            $table->float("avaliacao", 3,2);
            $table->string("telefone", 20);
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
            $table->dropColumn("thumbnail");
            $table->dropColumn("avaliacao");
            $table->dropColumn("telefone");
            $table->string("caminhoimagem")->nullable()->default(null);
        });
    }
}
