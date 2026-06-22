<?php

namespace App\Http\Controllers\Api;

use App\Domain\Cobranca\PixService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Webhook PÚBLICO do PIX (o PSP chama de fora) — N7.
 *
 * Segurança em camadas (correção S3 do plano):
 *   1) valida o segredo compartilhado da URL/header (autentica o chamador);
 *   2) o PixService valida estado/valor/idempotência antes de confirmar.
 * Em produção, somar verificação de assinatura HMAC/mTLS do PSP.
 */
class PixWebhookController extends Controller
{
    public function __construct(private PixService $service)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        // 1) Autenticação do chamador (segredo fora do código).
        $segredo = config('services.pix.webhook_secret');
        if ($segredo && ! hash_equals($segredo, (string) $request->header('X-Webhook-Token'))) {
            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        $d = $request->validate([
            'txid' => 'required|string',
            'valor' => 'required|numeric',
            'e2eid' => 'nullable|string',
        ]);

        try {
            $cobranca = $this->service->processarWebhook($d);
        } catch (ValidationException $e) {
            // Responde 422 sem vazar detalhes internos; o PSP reenviará se for o caso.
            return response()->json(['message' => 'Webhook rejeitado.', 'erros' => $e->errors()], 422);
        }

        return response()->json(['situacao' => $cobranca->situacao->value]);
    }
}
