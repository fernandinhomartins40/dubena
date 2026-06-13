<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClientes2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('clientes', function (Blueprint $table) {
          $table->string('rguf', 2)->nullable()->default(null)->change();
          $table->unsignedInteger('bairro_id')->nullable()->default(null)->change();
      });
          // DB::statement("ALTER TABLE `clientes` CHANGE COLUMN `rguf` `rguf` VARCHAR(2) NULL COLLATE 'utf8_unicode_ci'");
          // DB::statement("ALTER TABLE `clientes`
          // 	CHANGE COLUMN `bairro_id` `bairro_id` INT(10) UNSIGNED NULL");
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
