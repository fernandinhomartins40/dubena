<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F12 — Frota: registro de entrada/saída de veículo (controle de pátio/km por
 * jornada) e documentos do veículo (CRLV, seguro, etc.) com vencimento. Ambos
 * escopados por empresa (via empresa_id, herdado do veículo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('veiculo_entradas_saidas')) {
            Schema::create('veiculo_entradas_saidas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $t->foreignId('veiculo_id')->constrained('veiculos')->cascadeOnDelete();
                $t->foreignId('colaborador_id')->nullable()->constrained('colaboradores')->nullOnDelete();
                $t->string('tipo', 10);                 // SAIDA | ENTRADA
                $t->dateTime('datahora');
                $t->unsignedInteger('km')->nullable();
                $t->string('observacao', 255)->nullable();
                $t->timestamps();
                $t->index(['empresa_id', 'veiculo_id', 'datahora']);
            });
        }

        if (! Schema::hasTable('veiculo_documentos')) {
            Schema::create('veiculo_documentos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $t->foreignId('veiculo_id')->constrained('veiculos')->cascadeOnDelete();
                $t->string('tipo', 60);                 // CRLV, Seguro, etc.
                $t->string('numero', 60)->nullable();
                $t->date('emissao')->nullable();
                $t->date('vencimento')->nullable();
                $t->string('observacao', 255)->nullable();
                $t->timestamps();
                $t->index(['empresa_id', 'veiculo_id']);
                $t->index('vencimento');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculo_documentos');
        Schema::dropIfExists('veiculo_entradas_saidas');
    }
};
