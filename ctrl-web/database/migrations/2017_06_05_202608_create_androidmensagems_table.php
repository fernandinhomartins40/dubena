<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAndroidmensagemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('androidmensagems', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('user_id')->nullable()->default(null);
            $table->unsignedInteger('android_id')->nullable()->default(null);
            $table->unsignedInteger('tipo')->nullable()->default(null);
            $table->string('situacao', 1)->nullable()->default(null);
            $table->string('descricao')->nullable()->default(null);

            $table->timestamps();
            $table->foreign('android_id')->references('id')->on('androids')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onUpdate('cascade');
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('androidmensagems');
    }
}
