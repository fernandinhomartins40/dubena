<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C11 — cupom fiscal (SAT/CFe), MCMM (controle de movimentação de material) e
 * gestão documental + bens (com depreciação).
 *
 * A EMISSÃO real do SAT/CFe é gate regional (agente SAT/SEFAZ-SP); aqui o
 * registro e o estado são modelados e testáveis; a transmissão fica para o gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Cupom fiscal (SAT/CFe) ──
        Schema::create('cupons_fiscais', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $t->string('numero', 20)->nullable();
            $t->string('chave', 44)->nullable();
            $t->decimal('valor_total', 14, 2)->default(0);
            $t->string('situacao', 20)->default('rascunho'); // rascunho/emitido/cancelado
            $t->timestamp('emitido_em')->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'situacao']);
        });
        Schema::create('cupom_fiscal_itens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cupom_fiscal_id')->constrained('cupons_fiscais')->cascadeOnDelete();
            $t->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $t->decimal('quantidade', 14, 4)->default(1);
            $t->decimal('valor_unitario', 14, 4)->default(0);
            $t->decimal('valor_total', 14, 2)->default(0);
            $t->timestamps();
        });

        // ── MCMM (movimentação de material) ──
        Schema::create('mcmms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->date('data');
            $t->string('descricao');
            $t->string('tipo', 20)->default('entrada'); // entrada/saida
            $t->decimal('quantidade', 14, 4)->default(0);
            $t->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $t->timestamps();
        });

        // ── Gestão documental ──
        Schema::create('documentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->string('tipo', 40)->nullable();          // contrato/alvará/licença/...
            $t->string('descricao');
            $t->string('numero', 60)->nullable();
            $t->date('emissao')->nullable();
            $t->date('validade')->nullable();
            $t->string('arquivo_path')->nullable();      // storage privado
            $t->timestamps();
            $t->index(['empresa_id', 'validade']);
        });

        // ── Bens + depreciação ──
        Schema::create('empresa_bens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->string('descricao');
            $t->date('data_aquisicao')->nullable();
            $t->decimal('valor_aquisicao', 14, 2)->default(0);
            $t->decimal('taxa_depreciacao_anual', 6, 2)->default(0); // % a.a.
            $t->decimal('valor_residual', 14, 2)->default(0);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_bens');
        Schema::dropIfExists('documentos');
        Schema::dropIfExists('mcmms');
        Schema::dropIfExists('cupom_fiscal_itens');
        Schema::dropIfExists('cupons_fiscais');
    }
};
