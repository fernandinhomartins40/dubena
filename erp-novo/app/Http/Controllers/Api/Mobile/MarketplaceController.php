<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Mobile\MarketplaceService;
use App\Domain\Saas\CidadeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marketplace de gás (MP1/P3) — descoberta PÚBLICA por geolocalização.
 * O cliente informa o endereço (lat/lng) e recebe as empresas que atendem ali,
 * com a cidade resolvida (P3). Rotas públicas (sem auth) com rate-limit; nenhum
 * dado sensível é exposto (só nome/telefone/distância para escolha de pedido).
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

    /**
     * GET /app/v1/marketplace/cidades — cidades ativas atendidas pela plataforma (P3).
     * Inclui o CENTRO (lat/lng) para o app ancorar a descoberta quando o usuário
     * nega o GPS — a busca sem GPS é por cidade ESCOLHIDA, nunca por default fixo.
     */
    public function cidades(CidadeService $cidades): JsonResponse
    {
        $data = $cidades->ativas()->map(fn ($c) => [
            'id' => $c->id,
            'nome' => $c->nome,
            'uf' => $c->uf,
            'latitude' => $c->centro_lat !== null ? (float) $c->centro_lat : null,
            'longitude' => $c->centro_lng !== null ? (float) $c->centro_lng : null,
        ]);

        return response()->json(['data' => $data]);
    }
}
