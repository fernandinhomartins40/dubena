<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cache da malha viária vinda do OpenStreetMap (Overpass).
 *
 * Sustenta duas ferramentas da aba Cercas: fechar uma quadra pelas ruas ao
 * redor (o operador clica dentro, o sistema acha o contorno) e conferir se o
 * contorno de uma cerca segue rua.
 *
 * GLOBAL (sem empresa_id/RLS de propósito), pelo mesmo motivo de
 * `monitora_vias_cache`: traçado de rua é fato geográfico público.
 *
 * O Overpass é gratuito, então o cache não economiza dinheiro — economiza os
 * ~2 s de cada consulta e respeita a política de uso justo de um serviço
 * comunitário. Sem ele, cada clique do operador seria uma consulta nova.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitora_malha_cache', function (Blueprint $t) {
            $t->id();
            // Retângulo arredondado para grade de 0,01° (~1,1 km): cliques na
            // mesma vizinhança caem na mesma chave e reaproveitam o download.
            $t->string('regiao', 64)->unique();
            $t->json('vias');
            $t->unsignedInteger('hits')->default(0);
            $t->timestamps();
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $role = 'erp_app';
        if (DB::selectOne('SELECT 1 AS ok FROM pg_roles WHERE rolname = ?', [$role]) === null) {
            return;
        }

        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON monitora_malha_cache TO {$role}");
        DB::statement("GRANT USAGE, SELECT, UPDATE ON SEQUENCE monitora_malha_cache_id_seq TO {$role}");
    }

    public function down(): void
    {
        Schema::dropIfExists('monitora_malha_cache');
    }
};
