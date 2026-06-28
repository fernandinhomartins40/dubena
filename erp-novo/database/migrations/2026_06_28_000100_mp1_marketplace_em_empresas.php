<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MP1 — Marketplace de gás. A empresa passa a poder PARTICIPAR do app marketplace
 * (descoberta por geolocalização). `app_marketplace_ativo` controla a adesão;
 * `raio_entrega_km` é o fallback de cobertura quando a empresa não tem cerca
 * (geofence) cadastrada — aí a descoberta usa o raio a partir da matriz (lat/lng).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $t) {
            $t->boolean('app_marketplace_ativo')->default(false)->after('ativo');
            $t->decimal('raio_entrega_km', 6, 2)->nullable()->after('app_marketplace_ativo');
            $t->index('app_marketplace_ativo');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $t) {
            $t->dropIndex(['app_marketplace_ativo']);
            $t->dropColumn(['app_marketplace_ativo', 'raio_entrega_km']);
        });
    }
};
