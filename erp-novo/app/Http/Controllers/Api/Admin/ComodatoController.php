<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Satelite\ComodatoPdfService;
use App\Domain\Satelite\ComodatoService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Satelite\Comodato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Comodato (vasilhame emprestado) — N8.
 */
class ComodatoController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private ComodatoService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'comodato.view');

        $rows = Comodato::query()->with(['cliente:id,nome', 'produto:id,descricao'])
            ->when($request->query('situacao'), fn ($b, $s) => $b->where('situacao', $s))
            ->orderByDesc('data_emprestimo')->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'comodato.edit');
        $d = $request->validate([
            'cliente_id' => 'required|integer|exists:clientes,id',
            'produto_id' => 'required|integer|exists:produtos,id',
            'setor_id' => 'nullable|integer|exists:setores,id',
            'quantidade' => 'required|numeric|gt:0',
            'data_emprestimo' => 'nullable|date',
        ]);
        $d['empresa_id'] = $request->user()->empresa_id;
        $d['grupo_id'] = $request->user()->grupo_id;

        return response()->json(['data' => $this->service->emprestar($d, $request->user()->id)], 201);
    }

    /**
     * GET /comodatos/{id}/contrato — o documento que protege o vasilhame.
     *
     * Permissao de leitura: imprimir nao altera nada. Quem consulta o comodato
     * precisa poder gerar o contrato para colher a assinatura.
     */
    public function contrato(Request $request, int $id, ComodatoPdfService $pdf): \Illuminate\Http\Response
    {
        $this->autorizar($request, 'comodato.view');

        $comodato = Comodato::query()->findOrFail($id);

        return response($pdf->contrato($comodato), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="comodato-'.$comodato->id.'.pdf"',
        ]);
    }

    /** POST /comodatos/{id}/devolver */
    public function devolver(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'comodato.edit');
        $d = $request->validate(['quantidade' => 'required|numeric|gt:0']);

        $comodato = Comodato::query()->findOrFail($id);
        $atualizado = $this->service->devolver($comodato, (float) $d['quantidade'], $request->user()->id);

        return response()->json(['data' => $atualizado]);
    }
}
