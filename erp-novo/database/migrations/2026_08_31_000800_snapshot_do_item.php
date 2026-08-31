<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F3-03 — o item da venda congela o que o produto ERA naquele momento.
 *
 * `pedidoitens` e `nota_itens` guardam `produto_id`, quantidade e preço. O preço
 * está congelado, e isso está certo. A DESCRIÇÃO não — ela é lida do produto na
 * hora de exibir.
 *
 * Consequência no pedido: renomear um produto reescreve o histórico. O pedido de
 * três meses atrás passa a dizer que o cliente comprou algo que não existia com
 * aquele nome.
 *
 * Consequência na nota fiscal, que é pior: `XmlNfeBuilder` monta o `xProd` do
 * XML lendo `$item->produto?->descricao`. Depois de autorizada, a NF-e é
 * imutável na SEFAZ — mas uma reimpressão de DANFE, ou uma carta de correção,
 * passa a mostrar a descrição NOVA. **O papel deixa de bater com o XML
 * autorizado**, e isso é divergência fiscal, não detalhe de tela.
 *
 * `nota_itens` já congela CFOP, CST e alíquotas. O snapshet de descrição, NCM e
 * unidade completa a mesma ideia: o documento fiscal tem de ser reconstruível a
 * partir do que foi gravado, sem depender do cadastro de hoje.
 *
 * ## A conversão preenche com o valor ATUAL
 *
 * Para as linhas antigas, o melhor disponível é o cadastro de hoje — e é
 * explicitamente o que elas já usam ao exibir. Preencher não piora nada: troca
 * "lê o atual toda vez" por "leu o atual uma vez, e a partir daqui congela".
 *
 * O que não se pode fazer é fingir que o valor gravado é histórico. Ele não é,
 * para as linhas antigas, e por isso a coluna nasce nullable: `null` continua
 * significando "não foi capturado", e não "produto sem nome".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidoitens', function (Blueprint $t) {
            $t->string('descricao_snapshot')->nullable()->after('produto_id');
        });

        Schema::table('nota_itens', function (Blueprint $t) {
            $t->string('descricao_snapshot')->nullable()->after('produto_id');
            // NCM e unidade entram no XML e definem tributação. Um produto
            // reclassificado depois faria a reimpressão divergir do autorizado.
            $t->string('ncm_snapshot', 10)->nullable()->after('descricao_snapshot');
            $t->string('unidade_snapshot', 10)->nullable()->after('ncm_snapshot');
        });

        $this->preencherComOAtual();
    }

    /**
     * Preenche as linhas existentes com o cadastro de hoje.
     *
     * Feito em SQL por tabela (e não linha a linha) porque `nota_itens` numa
     * base real tem centenas de milhares de linhas, e uma migration que demora
     * minutos numa janela de deploy é um problema por si só.
     */
    private function preencherComOAtual(): void
    {
        // Subselect correlacionado, e nao UPDATE-FROM: Postgres e sqlite
        // escrevem o segundo de formas diferentes, e a conversao precisa dar o
        // mesmo resultado nos dois bancos.
        DB::statement(
            'UPDATE pedidoitens SET descricao_snapshot = ('
            .'SELECT descricao FROM produtos WHERE produtos.id = pedidoitens.produto_id'
            .') WHERE descricao_snapshot IS NULL'
        );

        DB::statement(
            'UPDATE nota_itens SET descricao_snapshot = ('
            .'SELECT descricao FROM produtos WHERE produtos.id = nota_itens.produto_id'
            .') WHERE descricao_snapshot IS NULL'
        );

        if (Schema::hasColumn('produtos', 'ncm')) {
            DB::statement(
                'UPDATE nota_itens SET ncm_snapshot = ('
                .'SELECT ncm FROM produtos WHERE produtos.id = nota_itens.produto_id'
                .') WHERE ncm_snapshot IS NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::table('pedidoitens', function (Blueprint $t) {
            $t->dropColumn('descricao_snapshot');
        });

        Schema::table('nota_itens', function (Blueprint $t) {
            $t->dropColumn(['descricao_snapshot', 'ncm_snapshot', 'unidade_snapshot']);
        });
    }
};
