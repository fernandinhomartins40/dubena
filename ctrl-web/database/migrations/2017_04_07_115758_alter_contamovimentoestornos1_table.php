<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterContamovimentoestornos1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('contamovimentoestornos', function($table)
      {
		$table->unsignedInteger('contamovimentotipo_id')->nullable()->default(null);
        $table->unsignedInteger('contafechamento_id')->nullable()->default(null);
		$table->unsignedInteger('financeiroparcela_id')->nullable()->default(null);
		$table->unsignedInteger('contatransferencia_id')->nullable()->default(null);
		$table->unsignedInteger('conta_id')->nullable()->default(null);
		$table->dateTime('datahorabaixa');
		  $table->decimal('valor', 15, 4)->default(0);
		  $table->decimal('multa', 15, 4)->default(0);
		  $table->decimal('juros', 15, 4)->default(0);
		  $table->decimal('desconto', 15, 4)->default(0);
		  $table->decimal('valorefetivado', 15, 4)->default(0);
		  $table->string('pagarreceber', 1);//'P'-Pagar, 'R'-Receber
		  $table->boolean('ativo')->default(true);
		
        $table->string('origem',5)->nullable()->default(null);
        $table->string('descricao')->nullable()->default(null);
		
        $table->foreign('contafechamento_id')->references('id')->on('contafechamentos');
		$table->foreign('contamovimentotipo_id', 'contamovimento_foreign')->references('id')->on('contamovimentotipos');
		$table->foreign('financeiroparcela_id')->references('id')->on('financeiroparcelas');
		$table->foreign('contatransferencia_id')->references('id')->on('contatransferencias');
		$table->foreign('conta_id')->references('id')->on('contas');
		
		// FASE 3: dropForeign por nome Oracle → por coluna, tolerante.
		try { $table->dropForeign(['contamovimento_id']); } catch (\Exception $e) {}
		$table->dropColumn('contamovimento_id');
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
