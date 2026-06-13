<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMenuUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('menu_user', function (Blueprint $table) {
            $table->boolean('visualizar')->default(0);
            $table->boolean('criar')->default(0);
            $table->boolean('editar')->default(0);
            $table->boolean('deletar')->default(0);
            $table->unsignedInteger('empresa_id')->nullable();
            
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('menu_user', function (Blueprint $table) {
            //
        });
    }
}
