<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Stub explícito (C1) para endpoints que a SPA já consome mas cujo MÓDULO será
 * entregue numa fase futura do PLANO_CONCLUSAO_REESCRITA.md.
 *
 * Responde 501 (Not Implemented) com um payload JSON VÁLIDO e vazio, de modo que:
 *  - a tela da SPA abre sem erro de rede (não há 404 de contrato);
 *  - fica explícito (no corpo e no header) em que fase o módulo chega;
 *  - o teste de contrato passa (a rota existe), sem fingir que funciona.
 *
 * Cada rota stub declara a fase via parâmetro default `fase`.
 */
class StubController extends Controller
{
    public function naoImplementado(string $fase = 'Cx', string $modulo = ''): JsonResponse
    {
        return response()->json([
            'data' => [],
            'message' => trim("Módulo {$modulo} será entregue na fase {$fase}."),
            'fase' => $fase,
            'implementado' => false,
        ], 501)->header('X-Fase-Planejada', $fase);
    }
}
