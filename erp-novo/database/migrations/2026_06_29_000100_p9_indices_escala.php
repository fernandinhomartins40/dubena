<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P9 — índices para escala (centenas de cidades / milhares de empresas).
 *
 * Não introduz PostGIS (extensão de produção, provisionada na VPS — ver o plano).
 * Em vez disso, prepara o caminho PORTÁVEL que já roda em Postgres e sqlite:
 *
 *  - empresas(latitude, longitude): suporta o PRÉ-FILTRO por bounding-box do
 *    MarketplaceService — recorta o conjunto de candidatas no banco antes do
 *    cálculo de Haversine em PHP (que continua sendo o passo de precisão).
 *  - empresa_cidade(cidade_plataforma_id): acelera "empresas que atuam na cidade X"
 *    (descoberta multi-cidade do P3).
 *
 * Idempotente o suficiente para o ciclo migrate:fresh do CI; os índices são
 * criados só se as colunas existirem (compat com bases parciais).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumns('empresas', ['latitude', 'longitude'])) {
            Schema::table('empresas', function (Blueprint $t) {
                $t->index(['latitude', 'longitude'], 'empresas_lat_lng_idx');
            });
        }

        if (Schema::hasTable('empresa_cidade') && Schema::hasColumn('empresa_cidade', 'cidade_plataforma_id')) {
            Schema::table('empresa_cidade', function (Blueprint $t) {
                $t->index('cidade_plataforma_id', 'empresa_cidade_cidade_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumns('empresas', ['latitude', 'longitude'])) {
            Schema::table('empresas', function (Blueprint $t) {
                $t->dropIndex('empresas_lat_lng_idx');
            });
        }

        if (Schema::hasTable('empresa_cidade')) {
            Schema::table('empresa_cidade', function (Blueprint $t) {
                $t->dropIndex('empresa_cidade_cidade_idx');
            });
        }
    }
};
