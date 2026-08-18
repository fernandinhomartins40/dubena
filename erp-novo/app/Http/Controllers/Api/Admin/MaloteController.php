<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Caixa\MaloteService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fechamento de malote (T4.3) — acerto de valores do entregador.
 *
 * ⚠️ Implementado condicionalmente: o plano exige que o dono confirme se o
 * acerto físico de malote ainda acontece. Ver `MaloteService` para o raciocínio.
 */
class MaloteController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private MaloteService $service)
    {
    }

    /** GET /malotes/conferencia — o que o entregador tem a acertar no período. */
    public function conferencia(Request $request): JsonResponse
    {
        // Leitura usa a permissão do caixa: quem confere malote é quem opera
        // caixa, e o dado exposto aqui é o mesmo da tela de baixa.
        $this->autorizar($request, 'caixa.view');

        $d = $request->validate([
            'inicio' => 'required|date',
            'fim' => 'required|date|after_or_equal:inicio',
            'setor_id' => 'nullable|integer|exists:setores,id',
            'entregador_user_id' => 'nullable|integer|exists:users,id',
        ]);

        return response()->json([
            'data' => $this->service->conferir(
                (int) $request->user()->empresa_id,
                $d['inicio'],
                $d['fim'],
                isset($d['setor_id']) ? (int) $d['setor_id'] : null,
                isset($d['entregador_user_id']) ? (int) $d['entregador_user_id'] : null,
            ),
        ]);
    }

    /** POST /malotes/fechar — baixa as parcelas dos pedidos conferidos. */
    public function fechar(Request $request): JsonResponse
    {
        // Fechar movimenta dinheiro: exige a permissão de escrita do caixa.
        $this->autorizar($request, 'caixa.edit');

        $d = $request->validate([
            'pedidos' => 'required|array|min:1',
            'pedidos.*' => 'integer',
            'conta_id' => 'nullable|integer|exists:contas,id',
        ]);

        return response()->json([
            'data' => $this->service->fechar(
                (int) $request->user()->empresa_id,
                array_map('intval', $d['pedidos']),
                isset($d['conta_id']) ? (int) $d['conta_id'] : null,
                (int) $request->user()->id,
            ),
        ]);
    }
}
