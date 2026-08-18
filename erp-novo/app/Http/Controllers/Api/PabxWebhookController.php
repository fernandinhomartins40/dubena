<?php

namespace App\Http\Controllers\Api;

use App\Domain\Telefonia\TelefoniaService;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook do PABX (T4.4) — recebe o aviso de chamada entrante.
 *
 * **Autenticação: segredo dedicado, fail-closed.**
 *
 * O legado autenticava a API com `sha1(APP_KEY)` — e a auditoria (§4 §2.6-7)
 * registra que a `APP_KEY` do legado **vazou no repositório**. O plano é
 * explícito: *"com autenticação — não repita o `sha1(APP_KEY)` do legado"*.
 *
 * Aqui o segredo é uma variável própria (`PABX_WEBHOOK_SECRET`), comparada com
 * `hash_equals` (tempo constante), e **sem ela configurada o endpoint recusa
 * tudo**. Isso é deliberado e diferente do webhook PIX, que tolera segredo
 * vazio: o PIX tem duas outras camadas (HMAC do corpo + validação de estado
 * contra o PSP), e este não tem nenhuma. Um endpoint público que grava linha no
 * banco sem segredo é convite a inundar a fila do atendimento.
 */
class PabxWebhookController extends Controller
{
    public function __construct(private TelefoniaService $service)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $segredo = (string) config('services.pabx.webhook_secret', '');

        if ($segredo === '') {
            Log::warning('pabx: webhook chamado sem PABX_WEBHOOK_SECRET configurado — recusado');

            return response()->json(['message' => 'Integração de telefonia não configurada.'], 503);
        }

        if (! hash_equals($segredo, (string) $request->header('X-Pabx-Token'))) {
            Log::warning('pabx: token invalido', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        $d = $request->validate([
            'empresa_id' => 'required|integer|exists:empresas,id',
            'telefone' => 'required|string|max:30',
            'ramal' => 'nullable|string|max:20',
        ]);

        $empresa = Empresa::query()->find((int) $d['empresa_id']);

        $r = $this->service->receber(
            (int) $d['empresa_id'],
            $empresa?->grupo_id !== null ? (int) $empresa->grupo_id : null,
            $d['telefone'],
            $d['ramal'] ?? null,
        );

        // Devolve o que achou: alguns PABX exibem o nome no visor do aparelho.
        return response()->json([
            'data' => [
                'chamada_id' => $r['chamada']->id,
                'clientes' => $r['clientes'],
            ],
        ], 201);
    }
}
