<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterContamovimentos1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('contamovimentos', function($table)
      {
        $table->unsignedInteger('contafechamento_id')->nullable()->default(null);
        $table->foreign('contafechamento_id')->references('id')->on('contafechamentos');
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
