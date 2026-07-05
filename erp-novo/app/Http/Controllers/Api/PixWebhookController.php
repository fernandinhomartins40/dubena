<?php

namespace App\Http\Controllers\Api;

use App\Domain\Cobranca\PixService;
use App\Domain\Integracao\IntegracaoTenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Webhook PÚBLICO do PIX (o PSP chama de fora) — N7 / multi-tenant.
 *
 * Segurança em camadas (S-1 da auditoria + fail-closed da FASE 1 do
 * PLANO_SEGURANCA_MULTITENANT_APPS):
 *   1) segredo compartilhado da URL/header (autentica o chamador) — sempre;
 *   2) ASSINATURA HMAC do PSP sobre o CORPO CRU — validada com o segredo DA EMPRESA
 *      da cobrança (resolvida pelo txid); empresa com PIX próprio NÃO herda o env;
 *   3) o PixService valida estado/valor/idempotência antes de confirmar.
 *
 * FAIL-CLOSED em produção: sem NENHUM segredo verificável (nem camada 1, nem
 * HMAC), o webhook REJEITA — senão qualquer um que conheça txid+valor da própria
 * cobrança a confirmaria sem pagar. Em dev/homolog/CI o comportamento permissivo
 * é mantido (bancos sem HMAC / suíte).
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
        $segredo = (string) config('services.pix.webhook_secret', '');
        if ($segredo !== '' && ! hash_equals($segredo, (string) $request->header('X-Webhook-Token'))) {
            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        // Resolve a empresa DA COBRANÇA pelo txid (o corpo já foi lido cru p/ o HMAC).
        $txid = (string) $request->input('txid', '');
        $empresaId = $txid !== '' ? $this->service->empresaIdDoTxid($txid) : null;

        // Produção: txid desconhecido é rejeitado ANTES de qualquer validação —
        // não há cobrança nossa, não há o que confirmar (e nada de cair no env).
        if ($empresaId === null && app()->isProduction()) {
            Log::warning('pix.webhook: txid desconhecido rejeitado', ['txid' => $txid, 'ip' => $request->ip()]);

            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        // 2) Assinatura HMAC-SHA256 sobre o corpo CRU, com o segredo DA EMPRESA.
        if (! $this->hmacValido($request, $empresaId, $segredo !== '')) {
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
     * Resolução do segredo (fail-closed onde é dinheiro de verdade):
     *  - empresa com HMAC próprio → usa o DELA (nunca o env);
     *  - empresa SEM HMAC mas COM PIX próprio configurado, em produção → REJEITA
     *    (herdaria um segredo que não é da empresa — o PSP dela não assina com ele);
     *  - sem HMAC nenhum: em produção só passa se a camada 1 (segredo compartilhado)
     *    autenticou o chamador; sem NENHUMA verificação → rejeita e loga. Fora de
     *    produção mantém NO-OP (CI/homolog).
     */
    private function hmacValido(Request $request, ?int $empresaId, bool $chamadorAutenticado): bool
    {
        $credEmpresa = $empresaId !== null ? $this->integracao->pix($empresaId) : null;
        $hmacSecret = $credEmpresa['webhook_hmac_secret'] ?? null;

        if ($hmacSecret === null || $hmacSecret === '') {
            // Empresa opera PIX próprio sem HMAC configurado → não herda o env em produção.
            if (app()->isProduction() && $empresaId !== null && $this->integracao->pixConfigurado($empresaId)) {
                Log::warning('pix.webhook: empresa com PIX próprio sem HMAC — rejeitado (fail-closed)', [
                    'empresa_id' => $empresaId,
                ]);

                return false;
            }

            $hmacSecret = config('services.pix.webhook_hmac_secret');
        }

        if (empty($hmacSecret)) {
            // Nenhum HMAC disponível. Em produção, exige que ao menos a camada 1
            // tenha autenticado o chamador; sem nenhuma verificação → fail-closed.
            if (app()->isProduction() && ! $chamadorAutenticado) {
                Log::error('pix.webhook: NENHUM segredo configurado em produção — webhook rejeitado (fail-closed)', [
                    'empresa_id' => $empresaId,
                ]);

                return false;
            }

            return true; // dev/homolog/CI, ou chamador já autenticado pela camada 1
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
