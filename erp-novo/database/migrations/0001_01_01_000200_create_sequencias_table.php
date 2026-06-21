<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sequências atômicas (numeração com lock) — base da numeração fiscal anti-duplicidade.
 * Chave por escopo, ex.: "nfe:empresa:12:serie:1".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequencias', function (Blueprint $table) {
            $table->id();
            $table->string('chave')->unique();
            $table->unsignedBigInteger('valor')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequencias');
    }
};
