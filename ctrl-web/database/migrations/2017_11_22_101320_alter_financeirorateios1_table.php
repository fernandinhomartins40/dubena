<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterFinanceirorateios1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

      Schema::table('financeirorateios', function($table)
      {
        $table->dropColumn('percentual');
      });

      Schema::table('financeirorateios', function($table)
      {
          $table->decimal('percentual', 15, 7)->nullable()->default(null);
      });
    }


//  select 'update financeirorateios set percentual = 0' || round(r.valor/f.financeiro_valor, 7) || ' where id = ' || r.id || ';', f.id as financeiro_id, r.id, f.financeiro_valor, r.valor 
// from financeirorateios r inner join 
// (select fi.valor as financeiro_valor , fi.id from financeiros fi where valor > 0) f on r.financeiro_id = f.id where valor > 0
//  order by 3,4;

// select financeiro_id, sum(r.valor) as valor_rateio, sum(percentual) 
// from financeirorateios r inner join financeiros f on f.id = r.financeiro_id group by financeiro_id order by r.FINANCEIRO_ID ;


// update financeirorateios set percentual = 0 where valor = 0;

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('financeirorateios', function (Blueprint $table) {
            //
        });
    }
}
