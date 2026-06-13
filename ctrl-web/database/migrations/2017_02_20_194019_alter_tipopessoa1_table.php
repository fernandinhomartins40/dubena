<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTipopessoa1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      // FASE 3: dropForeign por NOME fixo ('..._fk', convenção Oracle) falha no
      // Postgres, que nomeia a FK por convenção própria. Dropamos pela COLUNA
      // (o Laravel infere o nome correto por driver) e toleramos ausência.
      Schema::table('tipopessoas', function($table)
      {
        try {
            $table->dropForeign(['grupo_id']);
        } catch (\Exception $e) {
            // FK pode ter outro nome/não existir neste banco — segue.
        }
        $table->unsignedInteger('grupo_id')->nullable()->default(null)->change();

        $table->string('tipopessoacadastro', 1)->default('F');//F-Fisica J-Juridica
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
