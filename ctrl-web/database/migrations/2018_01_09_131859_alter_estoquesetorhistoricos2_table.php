<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEstoquesetorhistoricos2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('estoquesetorhistoricos', function (Blueprint $table) {
            $table->decimal('customedio', 15, 8)->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('estoquesetorhistoricos', function (Blueprint $table) {
            $table->dropColumn('customedio');
        });
    }
}
