<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N6 — Caixa / Conta / Cheque. O coração do saldo (risco máximo do plano).
 *
 * SALDO AUDITÁVEL (princípio #5): conta.saldo_atual é DERIVÁVEL de contamovimentos
 * (Σ contamovimentos.valor = conta.saldo_atual). Toda baixa/transferência/estorno
 * passa pelo CaixaService, que grava o movimento assinado e atualiza o saldo no
 * mesmo lock. Fechamentos guardam saldo inicial/final por período.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->string('tipo', 20)->default('CAIXA'); // CAIXA, BANCO, CARTAO
            $t->foreignId('banco_id')->nullable()->constrained('bancos')->nullOnDelete();
            $t->string('agencia', 20)->nullable();
            $t->string('numero', 30)->nullable();
            $t->decimal('saldo_inicial', 14, 2)->default(0);
            $t->decimal('saldo_atual', 14, 2)->default(0);
            $t->boolean('fechado')->default(false); // caixa aberto/fechado
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index(['empresa_id', 'descricao']);
        });

        // Movimento de conta — quantidade ASSINADA (+entrada / -saída).
        Schema::create('contamovimentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $t->foreignId('contamovimentotipo_id')->nullable()->constrained('contamovimentotipos')->nullOnDelete();
            $t->foreignId('financeiroparcela_id')->nullable()->constrained('financeiroparcelas')->nullOnDelete();
            $t->string('tipo', 20);                  // BAIXA, TRANSFERENCIA, ESTORNO, AJUSTE, ABERTURA
            $t->char('pagarreceber', 1)->nullable(); // P / R
            $t->decimal('valor', 14, 2);             // ASSINADO
            $t->decimal('saldo_resultante', 14, 2);
            $t->decimal('juros', 14, 2)->default(0);
            $t->decimal('multa', 14, 2)->default(0);
            $t->decimal('desconto', 14, 2)->default(0);
            $t->string('descricao', 255)->nullable();
            $t->string('origem', 40)->nullable();
            $t->unsignedBigInteger('origem_id')->nullable();
            $t->foreignId('estorno_de_id')->nullable()->constrained('contamovimentos')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('datahora');
            $t->timestamps();
            $t->index(['conta_id', 'datahora']);
            $t->index(['origem', 'origem_id']);
        });

        Schema::create('contafechamentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('abertura');
            $t->dateTime('fechamento')->nullable();
            $t->decimal('saldo_inicial', 14, 2)->default(0);
            $t->decimal('saldo_final', 14, 2)->nullable();
            $t->boolean('aberto')->default(true);
            $t->timestamps();
            $t->index(['conta_id', 'abertura']);
        });

        // Cheques (recebidos de clientes / emitidos a fornecedores).
        Schema::create('cheques', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->foreignId('banco_id')->nullable()->constrained('bancos')->nullOnDelete();
            $t->char('especie', 1)->default('R');     // R=recebido, E=emitido
            $t->string('numero', 30)->nullable();
            $t->string('agencia', 20)->nullable();
            $t->string('conta_corrente', 30)->nullable();
            $t->string('titular', 200)->nullable();
            $t->decimal('valor', 14, 2);
            $t->date('bom_para')->nullable();
            // Estado: CARTEIRA, DEPOSITADO, COMPENSADO, DEVOLVIDO, REPASSADO, CANCELADO.
            $t->string('situacao', 20)->default('CARTEIRA');
            $t->timestamps();
            $t->index(['empresa_id', 'especie', 'situacao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
        Schema::dropIfExists('contafechamentos');
        Schema::dropIfExists('contamovimentos');
        Schema::dropIfExists('contas');
    }
};
