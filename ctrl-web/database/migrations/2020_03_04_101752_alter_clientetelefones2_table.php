<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClientetelefones2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clientetelefones', function (Blueprint $table) {
            DB::statement("alter table clientetelefones add constraint clientetelefones_uniq unique(empresa_id, telefone)");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clientetelefones', function (Blueprint $table) {
            DB::statement("alter table clientetelefones drop constraint clientetelefones_uniq");
        });
    }
}
