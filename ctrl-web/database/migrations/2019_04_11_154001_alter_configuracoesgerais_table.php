<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterConfiguracoesgeraisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('configuracoesgerais', function (Blueprint $table) {
            $table->string("satcnpjprod", 14)->nullable()->default(null);
            $table->string("satcnpjhomolog", 14)->nullable()->default(null);
            $table->string("satsignacprod", 14)->nullable()->default(null);
            $table->string("satsignachomolog", 250)->nullable()->default(null);
            $table->string("satemitcnpjhomolog", 250)->nullable()->default(null);
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
            $table->dropColumn("satcnpjprod");
            $table->dropColumn("satcnpjhomolog");
            $table->dropColumn("satsignachomolog");
            $table->dropColumn("satsignacprod");
            $table->dropColumn("satemitcnpjhomolog");
        });
    }
}
