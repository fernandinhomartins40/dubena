<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Telefonia\TelefoniaService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use App\Rules\ExisteNoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bina no atendimento (T4.4) — fila de chamadas para o operador.
 *
 * ⚠️ Condicionada à decisão do dono: o plano pergunta se o call-center usa bina.
 * Ver `TelefoniaService` para o raciocínio e o que remover se a resposta for não.
 */
class TelefoniaController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private TelefoniaService $service) {}

    /** GET /telefonia/fila — o que está tocando agora. */
    public function fila(Request $request): JsonResponse
    {
        // Atender chamada é operar pedido: quem atende o telefone é quem vende.
        $this->autorizar($request, 'pedido.view');

        return response()->json([
            'data' => $this->service->fila((int) $request->user()->empresa_id),
        ]);
    }

    /** POST /telefonia/chamadas/{id}/atender */
    public function atender(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'pedido.view');

        $d = $request->validate(['cliente_id' => ['nullable', 'integer', new ExisteNoTenant(Cliente::class)]]);

        return response()->json([
            'data' => $this->service->atender(
                $id,
                (int) $request->user()->id,
                isset($d['cliente_id']) ? (int) $d['cliente_id'] : null,
            ),
        ]);
    }

    /** POST /telefonia/chamadas/{id}/rejeitar */
    public function rejeitar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'pedido.view');

        $d = $request->validate(['motivo' => 'nullable|string|max:255']);

        return response()->json([
            'data' => $this->service->rejeitar($id, (int) $request->user()->id, $d['motivo'] ?? null),
        ]);
    }

    /** GET /telefonia/buscar?telefone= — quem é este número. */
    public function buscar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'pedido.view');

        $d = $request->validate(['telefone' => 'required|string|max:30']);

        return response()->json([
            'data' => $this->service->clientesPorTelefone(
                (int) $request->user()->empresa_id,
                $d['telefone'],
            ),
        ]);
    }
}
