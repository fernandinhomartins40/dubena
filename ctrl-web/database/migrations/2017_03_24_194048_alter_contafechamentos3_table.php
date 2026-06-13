<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterContafechamentos3Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('contafechamentos', function (Blueprint $table)
      {
          $table->dateTime('datahorafechamento')->nullable()->default(null);
          $table->decimal('saldofinal', 15, 4)->nullable()->default(null);

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
