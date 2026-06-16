<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// Classe ANÔNIMA (L8): par homônimo de 2016_06_05_174855_alter_cidades_table
// (mesmo nome base → colisão de classe no L8). Anônima evita a redeclaração sem
// renomear o arquivo (registro em `migrations` inalterado → não re-executa em prod).
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      // NO-OP: esta migration tentava criar a FK cidades.uf → estados ANTES de a
      // coluna `uf` existir (ela só é adicionada, junto com a MESMA FK, em
      // 2016_10_25_124243_alter_cidades3_table). Num migrate do zero (Postgres)
      // isso falha: "column uf does not exist". A FK correta vem da alter_cidades3.
      // Em bancos já migrados esta migration já consta aplicada → esvaziar não
      // re-executa nada; só conserta o migrate-do-zero (CI/deploy limpo).
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
};
