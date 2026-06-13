<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterContafechamentos1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('contafechamentos', function($table)
      {
        $table->unsignedInteger('abertura_user_id')->nullable()->default(null);
        $table->unsignedInteger('fechamento_user_id')->nullable()->default(null);
        $table->foreign('abertura_user_id')->references('id')->on('users');
        $table->foreign('fechamento_user_id')->references('id')->on('users');
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
