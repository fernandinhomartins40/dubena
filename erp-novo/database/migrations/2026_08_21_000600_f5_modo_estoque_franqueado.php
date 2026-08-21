<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * F5 — como o franqueado carrega mercadoria: consignação ou compra.
 *
 * **A regra do cliente.** Os dois modelos coexistem na rede, e o modo é **fixo
 * por franqueado** (não por operação nem por produto): quem trabalha consignado
 * sempre consigna, quem compra sempre compra.
 *
 * **O que muda em cada caso.**
 *  - *consignação*: a mercadoria continua sendo da EMPRESA, apenas em poder do
 *    franqueado. O estoque sai do depósito para o saldo dele, e só vira venda
 *    (baixa patrimonial) quando ele entrega ao cliente. O que sobra volta.
 *  - *compra*: o franqueado comprou; a mercadoria é dele. A saída do depósito JÁ
 *    é a venda da empresa para ele, e o que ele faz depois não move o estoque da
 *    rede.
 *
 * A distinção importa para o fiscal e para a prestação de contas: no consignado
 * a empresa ainda responde pelo botijão que está na rua.
 *
 * **Por que `setor_estoque_id` e não uma tabela de saldo nova.** O
 * `EstoqueService` já movimenta por SETOR, com lock e histórico
 * (`EstoqueService::movimentar:40`). Dar um setor próprio ao franqueado reusa
 * essa máquina inteira em vez de criar um segundo controle de saldo que
 * precisaria ser conciliado com o primeiro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colaboradores', function (Blueprint $t) {
            // null = não carrega mercadoria (vendedor industrial, administrativo).
            $t->string('modo_estoque', 20)->nullable()->after('vinculo');

            // Depósito que representa o que está em poder desta pessoa.
            $t->foreignId('setor_estoque_id')->nullable()->after('modo_estoque')
                ->constrained('setores')->nullOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE colaboradores ADD CONSTRAINT colaboradores_modo_estoque_check
                 CHECK (modo_estoque IS NULL OR modo_estoque IN ('consignacao','compra'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE colaboradores DROP CONSTRAINT IF EXISTS colaboradores_modo_estoque_check');
        }

        Schema::table('colaboradores', function (Blueprint $t) {
            $t->dropConstrainedForeignId('setor_estoque_id');
            $t->dropColumn('modo_estoque');
        });
    }
};
