<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema NOVO — Grupo (rede de empresas) e Empresa (tenant operacional / revenda).
 *
 * Substitui empresas_grupos + empresas do legado. Valores monetários/percentuais
 * que no legado eram string ("R$ ..."/"12,50 %") aqui são tipos numéricos.
 * Config fiscal detalhada da empresa virá em tabela separada (N1: empresa_configs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->string('descricao');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->restrictOnDelete();

            // Identificação
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->string('nome_informal')->nullable();
            $table->string('cnpj', 14)->nullable();
            $table->string('inscricao_estadual', 30)->nullable();
            $table->string('inscricao_municipal', 30)->nullable();

            // Endereço (normalizado virá em N1; aqui o essencial do tenant)
            $table->string('cep', 8)->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('cidade')->nullable();
            $table->string('bairro')->nullable();
            $table->string('endereco')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('telefone1', 20)->nullable();
            $table->string('telefone2', 20)->nullable();

            // Geolocalização (numérico, não string)
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->boolean('matriz')->default(false);
            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->index('grupo_id');
            $table->index('cnpj');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('grupos');
    }
};
