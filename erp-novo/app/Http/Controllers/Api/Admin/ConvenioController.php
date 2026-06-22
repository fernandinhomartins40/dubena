<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Satelite\ConvenioFechamentoService;
use App\Http\Controllers\Controller;
use App\Models\Satelite\Convenio;
use App\Models\Satelite\ConvenioFechamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Convênios + fechamento mensal — N8.
 */
class ConvenioController extends Controller
{
    public function __construct(private ConvenioFechamentoService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'convenio.view');

        return response()->json(['data' => Convenio::query()->with('cliente:id,nome')->orderBy('descricao')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'convenio.edit');
        $d = $request->validate([
            'cliente_id' => 'required|integer|exists:clientes,id',
            'descricao' => 'required|string|max:255',
            'dia_fechamento' => 'nullable|integer|min:1|max:31',
            'dia_vencimento' => 'nullable|integer|min:1|max:31',
            'ativo' => 'nullable|boolean',
        ]);

        return response()->json(['data' => Convenio::create($d)], 201);
    }

    /** POST /convenios/{id}/fechar */
    public function fechar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'convenio.edit');
        $d = $request->validate([
            'periodo_inicio' => 'required|date',
            'periodo_fim' => 'required|date|after_or_equal:periodo_inicio',
            'num_parcelas' => 'nullable|integer|min:1',
        ]);

        $convenio = Convenio::query()->findOrFail($id);
        $fechamento = $this->service->fechar($convenio, $d['periodo_inicio'], $d['periodo_fim'], (int) ($d['num_parcelas'] ?? 1));

        return response()->json(['data' => $fechamento], 201);
    }

    public function fechamentos(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'convenio.view');

        return response()->json([
            'data' => ConvenioFechamento::query()->where('convenio_id', $id)->orderByDesc('periodo_inicio')->get(),
        ]);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
