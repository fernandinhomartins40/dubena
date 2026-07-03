<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L6 — CACHE PERSISTENTE de trajetos (economia da Routes API).
 *
 * Cada traçado devolvido pela Google é salvo por CÉLULA DE GRADE (~100 m): a
 * origem e o destino são "snapados" para células; qualquer trajeto futuro que
 * comece e termine nas MESMAS células reusa a polyline salva SEM nova chamada.
 * A base cresce com o uso (ruas/trajetos/pontos recorrentes da praça) e barateia
 * a operação com o tempo — 1 chamada por par de células, para sempre.
 *
 * GLOBAL (sem empresa_id/RLS de propósito): um trajeto A→B pelas ruas é fato
 * geográfico público, não dado de tenant — compartilhar entre empresas maximiza
 * o reuso e o corte de custo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rotas_cache', function (Blueprint $t) {
            $t->id();
            // Células de grade (lat/lng arredondados a 3 casas ≈ 100 m).
            $t->string('origem_cell', 32);
            $t->string('destino_cell', 32);
            // Coordenadas exatas da PRIMEIRA consulta (referência/depuração).
            $t->decimal('origem_lat', 10, 7);
            $t->decimal('origem_lng', 10, 7);
            $t->decimal('destino_lat', 10, 7);
            $t->decimal('destino_lng', 10, 7);
            $t->text('polyline');
            $t->decimal('distancia_km', 8, 2);
            $t->decimal('duracao_min', 8, 1);
            $t->unsignedInteger('hits')->default(0);
            $t->timestamps();

            $t->unique(['origem_cell', 'destino_cell']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotas_cache');
    }
};
