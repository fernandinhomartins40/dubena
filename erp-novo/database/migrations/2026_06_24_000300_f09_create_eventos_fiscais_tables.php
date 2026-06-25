<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F09 — eventos fiscais: inutilização de faixa de numeração e carta de correção
 * (CCE). O legado registrava em `empresainutilizacaos` e `nfemitidacartacorrecao`;
 * aqui em duas tabelas tenant-scoped (empresa_id), rastreáveis e auditáveis.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inutilizacoes_fiscais')) {
            Schema::create('inutilizacoes_fiscais', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $t->unsignedSmallInteger('modelo');         // 55 / 65
                $t->unsignedSmallInteger('serie');
                $t->unsignedInteger('numero_inicial');
                $t->unsignedInteger('numero_final');
                $t->string('justificativa', 255);
                $t->string('protocolo', 30)->nullable();
                $t->boolean('homologada')->default(false);
                $t->string('motivo', 255)->nullable();
                $t->timestamps();
                $t->index(['empresa_id', 'modelo', 'serie']);
            });
        }

        if (! Schema::hasTable('cartas_correcao')) {
            Schema::create('cartas_correcao', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $t->foreignId('nota_fiscal_id')->constrained('notas_fiscais')->cascadeOnDelete();
                $t->unsignedSmallInteger('sequencia');       // nSeqEvento
                $t->text('correcao');
                $t->string('protocolo', 30)->nullable();
                $t->boolean('registrada')->default(false);
                $t->string('motivo', 255)->nullable();
                $t->timestamps();
                $t->unique(['nota_fiscal_id', 'sequencia']);
                $t->index('empresa_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cartas_correcao');
        Schema::dropIfExists('inutilizacoes_fiscais');
    }
};
