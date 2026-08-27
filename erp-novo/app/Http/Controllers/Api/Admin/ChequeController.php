<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Caixa\ChequeService;
use App\Domain\Caixa\SituacaoCheque;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Caixa\Cheque;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cheques recebidos/emitidos — N6.
 */
class ChequeController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private ChequeService $service, private TenantContext $tenant) {}

    public function recebidos(Request $request): JsonResponse
    {
        return $this->listar($request, 'R');
    }

    public function emitidos(Request $request): JsonResponse
    {
        return $this->listar($request, 'E');
    }

    private function listar(Request $request, string $especie): JsonResponse
    {
        $this->autorizar($request, 'caixa.view');
        $q = trim((string) $request->query('q', ''));

        $rows = Cheque::query()->where('especie', $especie)
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('numero', 'ilike', '%'.$q.'%')->orWhere('titular', 'ilike', '%'.$q.'%')))
            ->orderByDesc('bom_para')->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'caixa.edit');
        $cheque = $this->service->criar($this->validar($request));

        return response()->json(['data' => $cheque], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'caixa.edit');
        $cheque = $this->service->atualizar(Cheque::query()->findOrFail($id), $this->validar($request, parcial: true));

        return response()->json(['data' => $cheque]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'caixa.edit');
        $this->service->excluir(Cheque::query()->findOrFail($id));

        return response()->json(['message' => 'Cheque excluído.']);
    }

    /** PUT /cheques/{id}/situacao */
    public function mudarSituacao(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'caixa.edit');
        $d = $request->validate([
            'situacao' => 'required|string',
            'conta_id' => 'nullable|integer|exists:contas,id',
        ]);
        $destino = SituacaoCheque::tryFrom($d['situacao']);
        abort_unless($destino !== null, 422, 'Situação inválida.');

        $cheque = $this->service->mudarSituacao(Cheque::query()->findOrFail($id), $destino, $d['conta_id'] ?? null, $request->user()->id);

        return response()->json(['data' => $cheque]);
    }

    /** POST /cheques/{id}/encontro-de-contas — repassa cheque p/ quitar compromisso (troco em cheque). */
    public function encontroDeContas(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'caixa.edit');
        $d = $request->validate([
            'valor_compromisso' => 'required|numeric|gte:0',
            'financeiro_parcela_id' => 'nullable|integer|exists:financeiroparcelas,id',
        ]);

        $resultado = $this->service->encontroDeContas(
            Cheque::query()->findOrFail($id),
            $this->tenant->requireEmpresaId(),
            (float) $d['valor_compromisso'],
            $d['financeiro_parcela_id'] ?? null,
            $request->user()->id,
        );

        return response()->json(['data' => $resultado]);
    }

    /** @return array<string,mixed> */
    private function validar(Request $request, bool $parcial = false): array
    {
        $req = $parcial ? 'sometimes' : 'required';

        return $request->validate([
            'especie' => "{$req}|in:R,E",
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'banco_id' => 'nullable|integer|exists:bancos,id',
            'numero' => 'nullable|string|max:30',
            'agencia' => 'nullable|string|max:20',
            'conta_corrente' => 'nullable|string|max:30',
            'titular' => 'nullable|string|max:200',
            'valor' => "{$req}|numeric|gt:0",
            'bom_para' => 'nullable|date',
        ]);
    }
}
