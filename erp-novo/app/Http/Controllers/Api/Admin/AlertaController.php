<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Alerta\AlertaService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Alerta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Central de alertas — a fila de averiguação da equipe.
 */
class AlertaController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private AlertaService $service) {}

    /**
     * GET /alertas — a fila, ordenada por urgência.
     *
     * Default: só o que ainda pede ação. Uma central que abre mostrando
     * resolvido junto com aberto obriga a filtrar antes de trabalhar.
     */
    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'alerta.view');

        $rows = Alerta::query()
            ->with(['cliente:id,nome', 'responsavel:id,name'])
            ->when($request->query('origem'), fn ($b, $o) => $b->where('origem', $o))
            ->when($request->query('severidade'), fn ($b, $s) => $b->where('severidade', $s))
            ->when(
                $request->query('situacao'),
                fn ($b, $s) => $b->where('situacao', $s),
                fn ($b) => $b->whereIn('situacao', [Alerta::ABERTO, Alerta::EM_ANALISE]),
            )
            ->orderByRaw("case severidade when 'ALTA' then 1 when 'MEDIA' then 2 else 3 end")
            ->orderByDesc('ocorrencias')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return response()->json([
            'data' => $rows,
            'resumo' => $this->service->resumo((int) $request->user()->empresa_id),
        ]);
    }

    /** POST /alertas/{id}/assumir — alguém vai averiguar. */
    public function assumir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'alerta.triar');

        $alerta = Alerta::query()->findOrFail($id);

        return response()->json(['data' => $this->service->assumir($alerta, $request->user()->id)]);
    }

    /**
     * POST /alertas/{id}/encerrar — o desfecho.
     *
     * `resolucao` é obrigatória: um alerta de suspeita de desvio patrimonial
     * fechado sem justificativa não deixa nada para a próxima pessoa que
     * perguntar por que aquele cliente saiu da fila.
     */
    public function encerrar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'alerta.triar');

        $d = $request->validate([
            'situacao' => 'required|in:RESOLVIDO,IGNORADO',
            'resolucao' => 'required|string|max:2000',
        ]);

        $alerta = Alerta::query()->findOrFail($id);

        return response()->json([
            'data' => $this->service->encerrar($alerta, $d['situacao'], $d['resolucao'], $request->user()->id),
        ]);
    }
}
