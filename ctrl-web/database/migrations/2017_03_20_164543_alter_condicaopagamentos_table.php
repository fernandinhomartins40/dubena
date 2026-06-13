<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterCondicaopagamentosTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('condicaopagamentos', function (Blueprint $table) {
            $table->dropColumn(['cartao', 'boleto', 'prazo', 'nfc_tpag', 'padrao']); 
            $table->string('tipo', 1); //0 - à vista, 1 - à prazo, 2 - Cartão à prazo, 3 - Cartão à vista
            $table->unsignedInteger('num_parcelas')->nullable(true);
            $table->unsignedInteger('dias_primeira');//em pagamento à vista, só aparece ele
            $table->unsignedInteger('intervalo')->nullable(true);
            $table->unsignedInteger('taxa')->nullable(true);//em pagamento à vista no cartão, esse cara aparece
        });

        //à vista: dias primeira
        //à prazo: no parcelas, intervalo (int)
        //cartão prazo: no parcelas, dias primeira, intervalo e Taxa
        //cartão à vista: dias primeira e taxa
        //se a parcela for 1, o campo intervalo pode ser 0, senão o campo se torna obrigatório  
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
