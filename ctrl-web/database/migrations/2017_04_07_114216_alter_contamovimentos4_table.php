<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterContamovimentos4Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contamovimentos', function (Blueprint $table) {
			// FASE 3: dropForeign por nome Oracle → por coluna, tolerante.
			try { $table->dropForeign(['contamovimentotipo_id']); } catch (\Exception $e) {}
            $table->dropColumn('contamovimentotipo_id');
		});
        Schema::table('contamovimentos', function (Blueprint $table) {
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
