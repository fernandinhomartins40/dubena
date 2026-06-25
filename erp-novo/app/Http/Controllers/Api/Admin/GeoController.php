<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Geográfico (cidades/bairros/ruas) — N2. Base do endereço do cliente.
 * Rotas /geo/{entidade} paginadas { data, meta }. Escopo por grupo automático.
 */
class GeoController extends Controller
{
    /** @var array<string, array{model: class-string, regras: array<string,string>, filtros: list<string>}> */
    private const ENTIDADES = [
        'cidades' => [
            'model' => Cidade::class,
            'regras' => ['descricao' => 'required|string|max:255', 'uf' => 'required|string|size:2', 'cod_ibge' => 'nullable|integer', 'ativo' => 'nullable|boolean'],
            'filtros' => ['uf'],
        ],
        'bairros' => [
            'model' => Bairro::class,
            'regras' => ['descricao' => 'required|string|max:255', 'cidade_id' => 'required|integer|exists:cidades,id', 'ativo' => 'nullable|boolean'],
            'filtros' => ['cidade_id'],
        ],
        'ruas' => [
            'model' => Rua::class,
            'regras' => ['descricao' => 'required|string|max:255', 'cidade_id' => 'required|integer|exists:cidades,id', 'cep' => 'nullable|string|max:10', 'ativo' => 'nullable|boolean'],
            'filtros' => ['cidade_id'],
        ],
    ];

    public function index(Request $request, string $entidade): JsonResponse
    {
        $cfg = $this->cfg($request, $entidade, 'cliente.view');

        $query = $cfg['model']::query()
            ->when(trim((string) $request->query('q', '')), fn (Builder $b, $q) => $b->where('descricao', 'ilike', '%'.$q.'%'))
            ->orderBy('descricao');

        foreach ($cfg['filtros'] as $filtro) {
            $valor = $request->query($filtro);
            if ($valor !== null && $valor !== '') {
                $query->where($filtro, $valor);
            }
        }

        $p = $query->paginate(20);

        // Contrato { data, meta } esperado pela SPA.
        return response()->json([
            'data' => $p->items(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page' => $p->lastPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
            ],
        ]);
    }

    public function store(Request $request, string $entidade): JsonResponse
    {
        $cfg = $this->cfg($request, $entidade, 'cliente.create');
        $dados = $request->validate($cfg['regras']);

        return response()->json(['data' => $cfg['model']::create($dados)], 201);
    }

    public function update(Request $request, string $entidade, int $id): JsonResponse
    {
        $cfg = $this->cfg($request, $entidade, 'cliente.edit');
        $registro = $cfg['model']::query()->findOrFail($id);
        $registro->update($request->validate($cfg['regras']));

        return response()->json(['data' => $registro->refresh()]);
    }

    public function destroy(Request $request, string $entidade, int $id): JsonResponse
    {
        $cfg = $this->cfg($request, $entidade, 'cliente.delete');
        $cfg['model']::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Registro excluído.']);
    }

    /**
     * GET /cadastros/inconsistencias?tipo=ruas|bairros|todas — prováveis duplicatas
     * de rua/bairro por similaridade de nome na mesma cidade (F11). Substitui o
     * UTL_MATCH (Oracle) do legado por similaridade agnóstica de banco.
     */
    public function inconsistencias(Request $request, \App\Domain\Apoio\InconsistenciaService $service): JsonResponse
    {
        abort_unless($request->user()->temPermissao('cidade.view'), 403, 'Sem permissão.');
        $grupoId = (int) $request->user()->grupo_id;
        $tipo = (string) $request->query('tipo', 'todas');

        $pares = match ($tipo) {
            'ruas' => $service->ruas($grupoId),
            'bairros' => $service->bairros($grupoId),
            default => $service->todas($grupoId),
        };

        return response()->json(['data' => $pares]);
    }

    /** @return array{model: class-string, regras: array<string,string>, filtros: list<string>} */
    private function cfg(Request $request, string $entidade, string $permissao): array
    {
        abort_unless(isset(self::ENTIDADES[$entidade]), 404, 'Entidade geográfica desconhecida.');
        abort_unless($request->user()->temPermissao($permissao), 403, 'Sem permissão.');

        return self::ENTIDADES[$entidade];
    }
}
