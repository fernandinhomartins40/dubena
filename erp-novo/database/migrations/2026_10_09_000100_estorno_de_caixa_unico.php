<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Um movimento de caixa só pode ser estornado UMA vez.
 *
 * `CaixaService::estornar` impedia estornar um estorno, mas não estornar duas
 * vezes o mesmo movimento: o dinheiro era revertido em dobro e a conferência
 * Σ movimentos = saldo continuava batendo, porque os dois estornos são
 * movimentos reais. O serviço agora recusa; este índice é a garantia no banco,
 * para qualquer caminho que escreva fora dele.
 *
 * Parcial (`WHERE estorno_de_id IS NOT NULL`): sem o filtro, todos os
 * movimentos que não são estorno colidiriam entre si. A sintaxe é a mesma no
 * PostgreSQL e no sqlite dos testes.
 *
 * Aditiva e sem backfill. Se o banco já tiver dois estornos para o mesmo
 * movimento, a criação FALHA de propósito: é dinheiro revertido em dobro, e
 * resolver qual dos dois vale é decisão de quem conhece o caixa — apagar um
 * aqui esconderia o erro.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX contamovimentos_estorno_unico ON contamovimentos (estorno_de_id) '
            .'WHERE estorno_de_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS contamovimentos_estorno_unico');
    }
};
