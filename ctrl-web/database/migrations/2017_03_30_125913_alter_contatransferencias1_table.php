<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterContatransferencias1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
    Schema::table('contatransferencias', function($table)
      {
        $table->unsignedInteger('contamovimentotipo_id')->nullable()->default(null);
        $table->foreign('contamovimentotipo_id')->references('id')->on('contamovimentotipos');
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
