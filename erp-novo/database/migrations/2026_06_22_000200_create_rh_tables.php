<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C5 — RH / Colaborador.
 *
 * Núcleo do módulo de pessoas: cargos, colaboradores (+ família, recessos) e a
 * REGRA DE COMISSÃO (colaborador_comissoes + exceções), portada do legado
 * (tipocomissao 1=percentual / 2=repasse, com variantes app e exceções por segmento).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->decimal('salario_base', 12, 2)->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['grupo_id', 'descricao']);
        });

        Schema::create('colaboradores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('cargo_id')->nullable()->constrained('cargos')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // login do colaborador
            $t->string('nome');
            $t->string('cpf', 11)->nullable();
            $t->string('rg', 20)->nullable();
            $t->date('data_nascimento')->nullable();
            $t->date('data_admissao')->nullable();
            $t->date('data_desligamento')->nullable();
            $t->string('telefone', 20)->nullable();
            $t->boolean('entregador')->default(false);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index(['empresa_id', 'ativo']);
        });

        Schema::create('colaborador_familias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $t->string('nome');
            $t->string('parentesco', 40)->nullable();
            $t->date('data_nascimento')->nullable();
            $t->timestamps();
        });

        Schema::create('colaborador_recessos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $t->string('tipo', 30)->default('ferias'); // ferias/licenca/afastamento
            $t->date('inicio');
            $t->date('fim');
            $t->string('observacao')->nullable();
            $t->timestamps();
        });

        // Regra de comissão: (colaborador × produto × setor × condição) → % ou valor.
        Schema::create('colaborador_comissoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $t->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $t->foreignId('setor_id')->nullable()->constrained('setores')->nullOnDelete();
            $t->foreignId('condicaopagamento_id')->nullable()->constrained('condicaopagamentos')->nullOnDelete();
            $t->unsignedTinyInteger('tipo_comissao')->default(1); // 1=percentual, 2=repasse(valor fixo)
            $t->decimal('percentual', 8, 4)->default(0);
            $t->decimal('empresa_valor', 12, 2)->default(0); // valor que fica para a empresa (repasse)
            $t->decimal('percentual_app', 8, 4)->nullable();  // variante para pedidos do app
            $t->decimal('empresa_valor_app', 12, 2)->nullable();
            $t->date('data_inicio')->nullable();
            $t->date('data_fim')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index(['colaborador_id', 'ativo']);
        });

        Schema::create('comissao_excecoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('colaborador_comissao_id')->constrained('colaborador_comissoes')->cascadeOnDelete();
            $t->foreignId('segmento_id')->nullable()->constrained('segmentos')->nullOnDelete();
            $t->unsignedTinyInteger('tipo_excecao')->default(1); // 1=percentual, 2=repasse
            $t->decimal('valor_excecao', 12, 4)->default(0);
            $t->decimal('valor_excecao_app', 12, 4)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comissao_excecoes');
        Schema::dropIfExists('colaborador_comissoes');
        Schema::dropIfExists('colaborador_recessos');
        Schema::dropIfExists('colaborador_familias');
        Schema::dropIfExists('colaboradores');
        Schema::dropIfExists('cargos');
    }
};
