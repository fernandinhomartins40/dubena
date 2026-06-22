<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N11 — Monitora (GPS) como BOUNDED CONTEXT isolado (pode virar serviço próprio).
 *
 * Tabelas com prefixo `monitora_` para deixar a fronteira explícita. Posições são
 * append-only (histórico); `monitora_ultima_posicao` é o snapshot por veículo
 * (leitura rápida, mantido na ingestão). Cercas = geofencing (centro + raio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitora_veiculos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('placa', 10);
            $t->string('descricao')->nullable();
            $t->string('imei', 30)->nullable();        // identificador do rastreador
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['empresa_id', 'placa']);
            $t->index('imei');
        });

        Schema::create('monitora_posicoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('veiculo_id')->constrained('monitora_veiculos')->cascadeOnDelete();
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->decimal('velocidade', 6, 2)->default(0);   // km/h
            $t->unsignedSmallInteger('direcao')->nullable(); // graus 0-359
            $t->boolean('ignicao')->default(false);
            $t->dateTime('registrado_em');
            $t->timestamps();
            $t->index(['veiculo_id', 'registrado_em']);
        });

        Schema::create('monitora_ultima_posicao', function (Blueprint $t) {
            $t->id();
            $t->foreignId('veiculo_id')->unique()->constrained('monitora_veiculos')->cascadeOnDelete();
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->decimal('velocidade', 6, 2)->default(0);
            $t->boolean('ignicao')->default(false);
            $t->dateTime('registrado_em');
            $t->timestamps();
        });

        Schema::create('monitora_cercas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->string('descricao');
            $t->decimal('centro_lat', 10, 7);
            $t->decimal('centro_lng', 10, 7);
            $t->decimal('raio_metros', 10, 2);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        Schema::create('monitora_rotas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('veiculo_id')->nullable()->constrained('monitora_veiculos')->nullOnDelete();
            $t->string('descricao');
            $t->date('data')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitora_rotas');
        Schema::dropIfExists('monitora_cercas');
        Schema::dropIfExists('monitora_ultima_posicao');
        Schema::dropIfExists('monitora_posicoes');
        Schema::dropIfExists('monitora_veiculos');
    }
};
