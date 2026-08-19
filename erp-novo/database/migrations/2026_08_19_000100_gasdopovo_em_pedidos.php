<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de venda pelo programa Gás do Povo no pedido.
 *
 * O legado tem `pedidos.gasdopovo` com **1.003 vendas** marcadas e a coluna não
 * existia aqui: a informação de que a venda saiu subsidiada se perdia na
 * migração. Sem ela não há como prestar contas do volume vendido pelo programa
 * — que é justamente o que a distribuidora e o órgão gestor cobram.
 *
 * A marca NÃO é editável na tela por decisão do legado (`PedidoUtil:337`): ela é
 * DERIVADA de duas condições no momento da criação — cliente beneficiário E
 * condição de pagamento do programa. E é travada nos dois sentidos depois disso,
 * porque mudá-la alteraria o preço subsidiado e a prestação de contas.
 *
 * Índice parcial: são ~0,25% dos pedidos (1.003 de 400 mil) e a consulta que
 * importa é sempre "os do programa". Indexar só as linhas verdadeiras deixa o
 * índice pequeno e a varredura barata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            $t->boolean('gasdopovo')->default(false)->after('condicaopagamento_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'CREATE INDEX pedidos_gasdopovo_idx ON pedidos (empresa_id, datahora) WHERE gasdopovo'
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('DROP INDEX IF EXISTS pedidos_gasdopovo_idx');
        }

        Schema::table('pedidos', function (Blueprint $t) {
            $t->dropColumn('gasdopovo');
        });
    }
};
