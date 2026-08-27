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
 * Contenção F0-04/A-12.5: o segredo da instalação é amarrado à empresa indicada
 * por `PABX_EMPRESA_ID`; sem ambos configurados o endpoint recusa tudo. Assim, o
 * detentor do segredo não escolhe outro tenant no corpo. A substituição canônica
 * será uma IntegrationAccount por owner, com rotação e healthcheck.
 */
class PabxWebhookController extends Controller
{
    public function __construct(private TelefoniaService $service)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $segredo = (string) config('services.pabx.webhook_secret', '');
        $empresaConfigurada = (int) config('services.pabx.empresa_id', 0);

        if ($segredo === '' || $empresaConfigurada <= 0) {
            Log::warning('pabx: webhook sem segredo ou owner configurado — recusado');

            return response()->json(['message' => 'Integração de telefonia não configurada.'], 503);
        }

        if (! hash_equals($segredo, (string) $request->header('X-Pabx-Token'))) {
            Log::warning('pabx: token invalido', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        $d = $request->validate([
            'empresa_id' => 'required|integer',
            'telefone' => 'required|string|max:30',
            'ramal' => 'nullable|string|max:20',
        ]);

        if ((int) $d['empresa_id'] !== $empresaConfigurada) {
            Log::warning('pabx: tentativa de usar segredo em empresa não vinculada', [
                'empresa_id' => (int) $d['empresa_id'],
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        $empresa = Empresa::query()->findOrFail($empresaConfigurada);

        $r = $this->service->receber(
            $empresaConfigurada,
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
