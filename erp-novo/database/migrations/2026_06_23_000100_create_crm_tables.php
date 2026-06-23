<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C10 — módulos satélite de CRM/gestão do legado:
 *  - Pós-venda (pesquisa de satisfação ligada a pedido/cliente)
 *  - Promoção (campanhas com vigência)
 *  - Sorteio (números/ganhadores)
 *  - Meta de venda (por colaborador/período)
 *  - Checklist (modelo + execução com itens)
 *
 * Escopo por empresa/grupo conforme a natureza. Tudo CRUD + consultas simples.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Pós-venda ──
        Schema::create('pos_vendas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $t->date('data');
            $t->unsignedTinyInteger('nota')->nullable();       // 0–10 (NPS)
            $t->string('canal', 30)->nullable();               // telefone/whatsapp/email
            $t->text('observacao')->nullable();
            $t->string('situacao', 20)->default('pendente');   // pendente/realizado
            $t->timestamps();
            $t->index(['empresa_id', 'situacao']);
        });

        // ── Promoção ──
        Schema::create('promocoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->date('inicio');
            $t->date('fim');
            $t->decimal('desconto_percentual', 8, 2)->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index(['grupo_id', 'ativo']);
        });

        // ── Sorteio + números ──
        Schema::create('sorteios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->date('data_sorteio')->nullable();
            $t->string('situacao', 20)->default('aberto');     // aberto/sorteado
            $t->string('numero_sorteado', 20)->nullable();
            $t->timestamps();
        });
        Schema::create('sorteio_numeros', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sorteio_id')->constrained('sorteios')->cascadeOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->string('numero', 20);
            $t->timestamps();
            $t->unique(['sorteio_id', 'numero']);
        });

        // ── Metas de venda ──
        Schema::create('meta_vendas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('colaborador_id')->nullable()->constrained('colaboradores')->nullOnDelete();
            $t->string('competencia', 7);                       // YYYY-MM
            $t->decimal('meta_valor', 14, 2)->default(0);
            $t->decimal('realizado_valor', 14, 2)->default(0);  // pode ser recalculado
            $t->timestamps();
            $t->index(['empresa_id', 'competencia']);
        });

        // ── Checklist (modelo + execução) ──
        Schema::create('checklists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('checklist_perguntas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('checklist_id')->constrained('checklists')->cascadeOnDelete();
            $t->string('pergunta');
            $t->unsignedInteger('ordem')->default(0);
            $t->timestamps();
        });
        Schema::create('checklist_execucoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('checklist_id')->constrained('checklists')->cascadeOnDelete();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->date('data');
            $t->timestamps();
        });
        Schema::create('checklist_respostas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('checklist_execucao_id')->constrained('checklist_execucoes')->cascadeOnDelete();
            $t->foreignId('checklist_pergunta_id')->constrained('checklist_perguntas')->cascadeOnDelete();
            $t->boolean('conforme')->default(true);
            $t->string('observacao')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_respostas');
        Schema::dropIfExists('checklist_execucoes');
        Schema::dropIfExists('checklist_perguntas');
        Schema::dropIfExists('checklists');
        Schema::dropIfExists('meta_vendas');
        Schema::dropIfExists('sorteio_numeros');
        Schema::dropIfExists('sorteios');
        Schema::dropIfExists('promocoes');
        Schema::dropIfExists('pos_vendas');
    }
};
