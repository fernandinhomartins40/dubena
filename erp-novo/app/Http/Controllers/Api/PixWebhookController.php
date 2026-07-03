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
 * Segurança em camadas (S-1 da auditoria):
 *   1) segredo compartilhado da URL/header (autentica o chamador) — sempre;
 *   2) ASSINATURA HMAC do PSP sobre o CORPO CRU — quando configurada, garante
 *      integridade/autenticidade (o segredo compartilhado sozinho não prova que o
 *      corpo não foi adulterado nem que veio do PSP);
 *   3) o PixService valida estado/valor/idempotência antes de confirmar.
 * O HMAC só é exigido quando PIX_WEBHOOK_HMAC_SECRET está definido — então bancos
 * sem HMAC (ou o CI) seguem só na camada 1, e ligar o HMAC é uma flag de env.
 */
class PixWebhookController extends Controller
{
    public function __construct(private PixService $service)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        // 1) Autenticação do chamador (segredo compartilhado, fora do código).
        $segredo = config('services.pix.webhook_secret');
        if ($segredo && ! hash_equals($segredo, (string) $request->header('X-Webhook-Token'))) {
            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        // 2) Assinatura HMAC-SHA256 sobre o corpo CRU (quando configurada).
        if (! $this->hmacValido($request)) {
            return response()->json(['message' => 'Assinatura inválida.'], 401);
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

    /**
     * Verifica a assinatura HMAC-SHA256 do PSP sobre o CORPO CRU da requisição.
     *
     * O PSP assina `HMAC_SHA256(corpo, segredo)` e envia o hex no header (default
     * `X-Webhook-Signature`; o nome é configurável para casar com o PSP). Sem
     * segredo configurado, a checagem é NO-OP (retorna true) — a camada 1 (segredo
     * compartilhado) segue valendo. Assinar o corpo cru é o que impede replay
     * adulterado e prova a origem, o que o segredo estático sozinho não faz.
     */
    private function hmacValido(Request $request): bool
    {
        $hmacSecret = config('services.pix.webhook_hmac_secret');
        if (empty($hmacSecret)) {
            return true; // HMAC desligado (bancos sem HMAC / CI)
        }

        $header = (string) config('services.pix.webhook_signature_header', 'X-Webhook-Signature');
        $enviada = (string) $request->header($header, '');
        if ($enviada === '') {
            return false;
        }

        // Corpo CRU (não o array validado) — a assinatura é sobre os bytes recebidos.
        $esperada = hash_hmac('sha256', $request->getContent(), (string) $hmacSecret);

        // Alguns PSPs prefixam o esquema ("sha256="); tolera ambos.
        $enviada = str_starts_with($enviada, 'sha256=') ? substr($enviada, 7) : $enviada;

        return hash_equals($esperada, $enviada);
    }
}
