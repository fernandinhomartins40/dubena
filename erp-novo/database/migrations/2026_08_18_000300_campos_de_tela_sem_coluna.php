<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colunas que as telas já editavam e o banco não tinha.
 *
 * A varredura por campos que a SPA envia e o backend descarta encontrou, além
 * dos aliases de nome, um grupo em que a coluna simplesmente **não existia**: o
 * usuário preenchia, o `validated()` descartava, e a tela dizia "salvo com
 * sucesso". Aqui as que têm dado real no legado e destino claro.
 *
 * **Comodato — representante e vencimento.** `legado.comodatos` tem 975 linhas
 * com `datacontrato` (975/975), `nomerepresentante` (784), `cpfrepresentante`
 * (694) e `rgrepresentante` (230). O `ComodatoPdfService` imprime o contrato de
 * comodato, e um contrato sem quem o assinou não vale como documento. O ETL já
 * lia `datacontrato` para derivar `data_emprestimo`, mas descartava o resto.
 *
 * O frete GLP (`valorfretegp` e afins) NÃO entra aqui: `empresa_configs.dados`
 * é JSON e o `update()` do controller já grava qualquer chave desconhecida nele
 * — criar coluna seria duplicar o destino. Ali o defeito era outro: o ETL não
 * migrava o valor do legado (ver `EmpresaConfigMigrator`).
 *
 * Todas nullable: registro existente segue válido sem elas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comodatos', function (Blueprint $t) {
            // Quem assinou o contrato de comodato — o PDF imprime.
            $t->string('nome_representante')->nullable()->after('situacao');
            $t->string('cpf_representante', 11)->nullable()->after('nome_representante');
            $t->string('rg_representante', 20)->nullable()->after('cpf_representante');
            // `data_emprestimo` já vem de `datacontrato`; o vencimento é outra
            // data e estava sendo jogada em `data_devolucao`, que significa
            // "quando voltou" — não "quando deveria voltar".
            $t->date('data_vencimento')->nullable()->after('data_emprestimo');
        });
    }

    public function down(): void
    {
        Schema::table('comodatos', function (Blueprint $t) {
            $t->dropColumn([
                'nome_representante', 'cpf_representante', 'rg_representante', 'data_vencimento',
            ]);
        });
    }
};
