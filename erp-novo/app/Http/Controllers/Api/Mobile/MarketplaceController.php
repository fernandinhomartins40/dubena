<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Mobile\MarketplaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marketplace de gás (MP1) — descoberta PÚBLICA de empresas por geolocalização.
 * O cliente informa o endereço (lat/lng) e recebe as empresas que atendem ali.
 * Rota pública (sem auth) com rate-limit; nenhum dado sensível é exposto.
 */
class MarketplaceController extends Controller
{
    public function __construct(private MarketplaceService $marketplace) {}

    /** POST /app/v1/marketplace/empresas — empresas que atendem o ponto. */
    public function empresas(Request $request): JsonResponse
    {
        $d = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $empresas = $this->marketplace->empresasNoPonto((float) $d['latitude'], (float) $d['longitude']);

        return response()->json(['data' => $empresas]);
    }
}
