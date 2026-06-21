<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estados (UFs) — cadastro nacional, sem tenant. Tabela de exemplo do N0 para
 * provar o pipeline do ETL ponta-a-ponta. (Legado: estados.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('uf', 2)->unique();
            $table->string('descricao');
            $table->unsignedInteger('cod_ibge')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados');
    }
};
