<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N9 — Fiscal (NF-e / NFC-e / CF-e). PORTADO, não reinventado.
 *
 * O cálculo de imposto e a montagem de XML são lógica legislada (portada); aqui
 * ficam os registros: nota + itens + impostos (por item) + numeração e config
 * fiscal. Valores em decimal nativo; numeração com lock (anti-duplicidade).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Configuração fiscal por empresa (série, ambiente, CSC NFC-e, regime).
        Schema::create('config_fiscais', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->unique()->constrained('empresas')->cascadeOnDelete();
            $t->unsignedSmallInteger('ambiente')->default(2);   // 1=produção, 2=homologação
            $t->unsignedInteger('serie_nfe')->default(1);
            $t->unsignedInteger('serie_nfce')->default(1);
            $t->unsignedSmallInteger('regime_tributario')->default(1); // 1=Simples,3=Normal
            $t->string('csc_id', 10)->nullable();
            $t->text('csc_token')->nullable();                  // segredo (encrypted)
            $t->timestamps();
        });

        Schema::create('notas_fiscais', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();

            $t->string('modelo', 4);            // 55=NF-e, 65=NFC-e, 59=CF-e
            $t->char('tipo', 1)->default('S');  // S=saída, E=entrada
            $t->unsignedInteger('serie');
            $t->unsignedBigInteger('numero');
            $t->string('chave', 44)->nullable()->unique();
            $t->string('protocolo', 30)->nullable();

            // Totais (decimal nativo).
            $t->decimal('valor_produtos', 14, 2)->default(0);
            $t->decimal('valor_desconto', 14, 2)->default(0);
            $t->decimal('valor_frete', 14, 2)->default(0);
            $t->decimal('valor_icms', 14, 2)->default(0);
            $t->decimal('valor_icms_st', 14, 2)->default(0);
            $t->decimal('valor_ipi', 14, 2)->default(0);
            $t->decimal('valor_pis', 14, 2)->default(0);
            $t->decimal('valor_cofins', 14, 2)->default(0);
            $t->decimal('valor_total', 14, 2)->default(0);

            // RASCUNHO, EMITIDA, AUTORIZADA, CANCELADA, REJEITADA, DENEGADA.
            $t->string('situacao', 20)->default('RASCUNHO');
            $t->text('motivo_rejeicao')->nullable();
            $t->dateTime('emitida_em')->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'modelo', 'serie']);
            $t->unique(['empresa_id', 'modelo', 'serie', 'numero']);
        });

        Schema::create('nota_itens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('nota_fiscal_id')->constrained('notas_fiscais')->cascadeOnDelete();
            $t->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $t->unsignedInteger('numero_item');
            $t->decimal('quantidade', 14, 3);
            $t->decimal('valor_unitario', 14, 4);
            $t->decimal('valor_total', 14, 2);
            $t->decimal('desconto', 14, 2)->default(0);

            // Tributação do item.
            $t->string('cfop', 4)->nullable();
            $t->string('cst_icms', 3)->nullable();
            $t->decimal('bc_icms', 14, 2)->default(0);
            $t->decimal('aliq_icms', 7, 4)->default(0);
            $t->decimal('valor_icms', 14, 2)->default(0);
            $t->decimal('aliq_pis', 7, 4)->default(0);
            $t->decimal('valor_pis', 14, 2)->default(0);
            $t->decimal('aliq_cofins', 7, 4)->default(0);
            $t->decimal('valor_cofins', 14, 2)->default(0);
            $t->decimal('aliq_ipi', 7, 4)->default(0);
            $t->decimal('valor_ipi', 14, 2)->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_itens');
        Schema::dropIfExists('notas_fiscais');
        Schema::dropIfExists('config_fiscais');
    }
};
