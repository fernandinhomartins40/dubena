<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClienteenderecos1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->table('clienteenderecos', function (Blueprint $table) {
            $table->string('bairro', 100);
            $table->string('uf', 100);
            $table->string('cidade', 100);
            $table->string('pontoreferencia', 100)->nullable()->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->table('clienteenderecos', function (Blueprint $table) {
            $table->dropColumn('bairro', 'uf', 'cidade', 'pontoreferencia');
        });
    }
}
