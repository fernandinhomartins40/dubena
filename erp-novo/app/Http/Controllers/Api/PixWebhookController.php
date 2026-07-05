<?php

namespace App\Http\Controllers\Api;

use App\Domain\Cobranca\PixService;
use App\Domain\Integracao\IntegracaoTenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Webhook PÚBLICO do PIX (o PSP chama de fora) — N7 / multi-tenant.
 *
 * Segurança em camadas (S-1 da auditoria):
 *   1) segredo compartilhado da URL/header (autentica o chamador) — sempre;
 *   2) ASSINATURA HMAC do PSP sobre o CORPO CRU — validada com o segredo DA EMPRESA
 *      da cobrança (resolvida pelo txid), com fallback para o env da plataforma;
 *   3) o PixService valida estado/valor/idempotência antes de confirmar.
 *
 * Multi-tenant: o webhook é público (sem tenant resolvido). Ele descobre a empresa
 * pelo txid da cobrança e valida o HMAC DAQUELA empresa — um webhook não confirma,
 * nem forja assinatura, para a cobrança de outra empresa.
 */
class PixWebhookController extends Controller
{
    public function __construct(
        private PixService $service,
        private IntegracaoTenant $integracao,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        // 1) Autenticação do chamador (segredo compartilhado, fora do código).
        $segredo = config('services.pix.webhook_secret');
        if ($segredo && ! hash_equals($segredo, (string) $request->header('X-Webhook-Token'))) {
            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        // Resolve a empresa DA COBRANÇA pelo txid (o corpo já foi lido cru p/ o HMAC).
        $txid = (string) $request->input('txid', '');
        $empresaId = $txid !== '' ? $this->service->empresaIdDoTxid($txid) : null;

        // 2) Assinatura HMAC-SHA256 sobre o corpo CRU, com o segredo DA EMPRESA.
        if (! $this->hmacValido($request, $empresaId)) {
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
     * O segredo é o DA EMPRESA da cobrança (multi-tenant); se a empresa não tem HMAC
     * próprio, cai no env da plataforma. Sem NENHUM segredo, a checagem é NO-OP — a
     * camada 1 (segredo compartilhado) segue valendo. Assinar o corpo cru impede
     * replay adulterado e prova a origem, o que o segredo estático sozinho não faz.
     */
    private function hmacValido(Request $request, ?int $empresaId): bool
    {
        // Segredo da empresa da cobrança; fallback env da plataforma.
        $hmacSecret = $empresaId !== null
            ? ($this->integracao->pix($empresaId)['webhook_hmac_secret'] ?? null)
            : null;
        $hmacSecret ??= config('services.pix.webhook_hmac_secret');

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
