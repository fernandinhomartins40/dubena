<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C7c — NF de entrada (recebida). Importa o XML do fornecedor (Standardize),
 * registra a nota + itens e movimenta estoque (entrada) e financeiro (a pagar)
 * pelos Services existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nf_recebidas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete(); // fornecedor
            $t->string('chave', 44)->nullable()->unique();
            $t->string('numero', 20)->nullable();
            $t->string('serie', 5)->nullable();
            $t->string('emitente_cnpj', 14)->nullable();
            $t->string('emitente_nome')->nullable();
            $t->date('data_emissao')->nullable();
            $t->decimal('valor_produtos', 14, 2)->default(0);
            $t->decimal('valor_total', 14, 2)->default(0);
            $t->string('situacao', 20)->default('importada'); // importada/processada
            $t->boolean('movimentou_estoque')->default(false);
            $t->foreignId('financeiro_id')->nullable()->constrained('financeiros')->nullOnDelete();
            $t->text('xml')->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'situacao']);
        });

        Schema::create('nf_recebida_itens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('nf_recebida_id')->constrained('nf_recebidas')->cascadeOnDelete();
            $t->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete(); // casado por código, se houver
            $t->string('codigo_fornecedor')->nullable();
            $t->string('descricao');
            $t->string('ncm', 8)->nullable();
            $t->string('cfop', 4)->nullable();
            $t->decimal('quantidade', 14, 4)->default(0);
            $t->decimal('valor_unitario', 14, 6)->default(0);
            $t->decimal('valor_total', 14, 2)->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nf_recebida_itens');
        Schema::dropIfExists('nf_recebidas');
    }
};
