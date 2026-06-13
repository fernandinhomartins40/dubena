<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClientes16Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedInteger("cod_ctrl")->nullable()->default(null);
            $table->string("observacoes", 1024)->change();

            DB::statement("alter table clientes add constraint clientes_unq unique(empresa_id, numero, rua_id,complemento, bairro_id)");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn("cod_ctrl");
            $table->string("observacoes", 255)->change();

            DB::statement("alter table clientes drop constraint clientes_unq");
        });
    }
}
