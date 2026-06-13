<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterConfiguracoesgerais2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('configuracoesgerais', function (Blueprint $table) {
            $table->string("remembermails")->nullable()->default(null);
            $table->text('emailremetente', 100)->nullable();
            $table->text('emailnomeremente', 100)->nullable();
            $table->text('emailusuario', 100)->nullable();
            $table->text('emailsenha', 100)->nullable();
            $table->text('emailservidorsmtp', 100)->nullable();
            $table->text('emailportasmtp', 10)->nullable();
            $table->text('emailassunto', 100)->nullable();
            $table->text('emailcorpo', 1000)->nullable();
            $table->boolean('emailrequerautenticacao')->nullable()->default(0);
            $table->boolean('emailrequerconexaotls')->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('configuracoesgerais', function (Blueprint $table) {
            $table->dropColumn("remembermails");
            $table->dropColumn('emailremetente');
            $table->dropColumn('emailnomeremente');
            $table->dropColumn('emailusuario');
            $table->dropColumn('emailsenha');
            $table->dropColumn('emailservidorsmtp');
            $table->dropColumn('emailportasmtp');
            $table->dropColumn('emailrequerautenticacao');
            $table->dropColumn('emailrequerconexaotls');
            $table->dropColumn('emailassunto');
            $table->dropColumn('emailcorpo');
        });
    }
}
