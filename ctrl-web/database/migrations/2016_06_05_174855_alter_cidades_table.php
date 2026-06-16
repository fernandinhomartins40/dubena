<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// Classe ANÔNIMA (L8): havia DOIS arquivos com nome base "alter_cidades_table"
// (este e 2016_06_07_124314), que no L8 resolvem para a mesma classe e colidem
// ("Cannot declare class ... already in use") num migrate do zero. Migration
// anônima elimina o nome de classe sem renomear o arquivo — o registro na tabela
// `migrations` continua o mesmo, então NÃO re-executa em bancos já migrados.
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //DB::statement("ALTER TABLE `cidades`ADD COLUMN `uf` VARCHAR(2) NOT NULL DEFAULT ''");
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
