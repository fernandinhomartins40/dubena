<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C6 — Frota / Veículos (de negócio; distinto de monitora_veiculos do GPS).
 *
 * Veículo + histórico operacional (abastecimentos, trocas de óleo, pneus). O
 * VeiculoService deriva consumo médio (km/l) dos abastecimentos e alerta de troca
 * de óleo por km rodado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veiculo_tipos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['grupo_id', 'descricao']);
        });

        Schema::create('tipo_combustiveis', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['grupo_id', 'descricao']);
        });

        Schema::create('veiculos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('veiculotipo_id')->nullable()->constrained('veiculo_tipos')->nullOnDelete();
            $t->foreignId('tipocombustivel_id')->nullable()->constrained('tipo_combustiveis')->nullOnDelete();
            $t->string('placa', 10);
            $t->string('descricao');
            $t->string('renavam', 20)->nullable();
            $t->unsignedInteger('km_atual')->default(0);
            $t->unsignedInteger('km_troca_oleo')->nullable();   // intervalo recomendado (km)
            $t->unsignedInteger('km_ultima_troca_oleo')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index(['empresa_id', 'ativo']);
        });

        Schema::create('veiculo_abastecimentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('veiculo_id')->constrained('veiculos')->cascadeOnDelete();
            $t->date('data');
            $t->unsignedInteger('km');
            $t->decimal('litros', 10, 3);
            $t->decimal('valor_litro', 10, 3)->nullable();
            $t->decimal('valor_total', 12, 2)->nullable();
            $t->boolean('tanque_cheio')->default(true);
            $t->timestamps();
            $t->index(['veiculo_id', 'km']);
        });

        Schema::create('veiculo_trocas_oleo', function (Blueprint $t) {
            $t->id();
            $t->foreignId('veiculo_id')->constrained('veiculos')->cascadeOnDelete();
            $t->date('data');
            $t->unsignedInteger('km');
            $t->decimal('valor', 12, 2)->nullable();
            $t->string('observacao')->nullable();
            $t->timestamps();
        });

        Schema::create('veiculo_pneus', function (Blueprint $t) {
            $t->id();
            $t->foreignId('veiculo_id')->constrained('veiculos')->cascadeOnDelete();
            $t->string('posicao', 20)->nullable(); // dianteiro-esq, etc.
            $t->string('marca')->nullable();
            $t->date('data_instalacao')->nullable();
            $t->unsignedInteger('km_instalacao')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculo_pneus');
        Schema::dropIfExists('veiculo_trocas_oleo');
        Schema::dropIfExists('veiculo_abastecimentos');
        Schema::dropIfExists('veiculos');
        Schema::dropIfExists('tipo_combustiveis');
        Schema::dropIfExists('veiculo_tipos');
    }
};
