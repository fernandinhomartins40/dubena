<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Cobranca\PixService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Financeiro\FinanceiroParcela;
use App\Rules\ExisteNoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PIX (Itaú) — N7 (GATE). Config, criação de cobrança. O webhook é tratado em
 * rota PÚBLICA (PixWebhookController) pois o PSP chama de fora.
 */
class PixController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private PixService $service) {}

    /** GET /pix/config — status da integração (sem expor segredos). */
    public function config(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');

        return response()->json(['data' => [
            'habilitado' => (bool) config('services.pix.enabled', false),
            'psp' => config('services.pix.psp', 'itau'),
            'ambiente' => config('services.pix.ambiente', 'homologacao'),
        ]]);
    }

    /** POST /pix/cobrancas — cria cobrança imediata para uma parcela. */
    public function criar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $d = $request->validate([
            'parcela_id' => ['required', 'integer', new ExisteNoTenant(FinanceiroParcela::class)],
            'expira_segundos' => 'nullable|integer|min:60',
        ]);

        $parcela = FinanceiroParcela::query()->with('financeiro')->findOrFail($d['parcela_id']);
        $cobranca = $this->service->criarCobranca($parcela, (int) ($d['expira_segundos'] ?? 3600));

        return response()->json(['data' => $cobranca], 201);
    }
}
