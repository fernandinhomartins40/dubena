<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Apoio\InconsistenciaService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
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
    use AutorizaPorPermissao;

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
    public function inconsistencias(Request $request, InconsistenciaService $service): JsonResponse
    {
        // Geográfico é base do endereço do cliente — gerido sob a permissão de cliente
        // (mesma usada no CRUD deste controller). Mantém o catálogo sem chave órfã.
        $this->autorizar($request, 'cliente.view');
        $grupoId = (int) $request->user()->grupo_id;
        $tipo = (string) $request->query('tipo', 'todas');

        $pares = match ($tipo) {
            'ruas' => $service->ruas($grupoId),
            'bairros' => $service->bairros($grupoId),
            default => $service->todas($grupoId),
        };

        return response()->json(['data' => $pares]);
    }

    /**
     * POST /cadastros/inconsistencias/ignorar — marca um par como NÃO-duplicado.
     *
     * É a ação que fecha o ciclo da tela (T4.1): sem ela o detector repete os
     * mesmos falsos positivos indefinidamente e a fila nunca esvazia. Espelha o
     * `ignorarRua`/`ignorarBairro` do legado.
     */
    public function ignorarInconsistencia(Request $request, InconsistenciaService $service): JsonResponse
    {
        // Escrita exige permissão de EDIÇÃO, não a de leitura usada no GET acima.
        $this->autorizar($request, 'cliente.edit');

        $dados = $request->validate([
            'tipo' => ['required', 'string', 'in:rua,bairro'],
            'item_id' => ['required', 'integer', 'min:1'],
            'item_ignorado_id' => ['required', 'integer', 'min:1', 'different:item_id'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        try {
            $novo = $service->ignorarPar(
                tipo: $dados['tipo'],
                itemId: (int) $dados['item_id'],
                itemIgnoradoId: (int) $dados['item_ignorado_id'],
                grupoId: (int) $user->grupo_id,
                empresaId: $user->empresa_id !== null ? (int) $user->empresa_id : null,
                userId: (int) $user->id,
                motivo: $dados['motivo'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            // Id de outro tenant ou inexistente: 422, não 500.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => ['ignorado' => true, 'novo' => $novo],
            'message' => $novo ? 'Par marcado como distinto.' : 'Este par já estava ignorado.',
        ]);
    }

    /** DELETE /cadastros/inconsistencias/ignorar — devolve o par à fila. */
    public function reconsiderarInconsistencia(Request $request, InconsistenciaService $service): JsonResponse
    {
        $this->autorizar($request, 'cliente.edit');

        $dados = $request->validate([
            'tipo' => ['required', 'string', 'in:rua,bairro'],
            'item_id' => ['required', 'integer', 'min:1'],
            'item_ignorado_id' => ['required', 'integer', 'min:1', 'different:item_id'],
        ]);

        $removido = $service->reconsiderarPar(
            $dados['tipo'],
            (int) $dados['item_id'],
            (int) $dados['item_ignorado_id'],
            (int) $request->user()->grupo_id,
        );

        return response()->json([
            'data' => ['reconsiderado' => $removido],
            'message' => $removido ? 'Par devolvido à fila.' : 'Este par não estava ignorado.',
        ]);
    }

    /** @return array{model: class-string, regras: array<string,string>, filtros: list<string>} */
    private function cfg(Request $request, string $entidade, string $permissao): array
    {
        abort_unless(isset(self::ENTIDADES[$entidade]), 404, 'Entidade geográfica desconhecida.');
        $this->autorizar($request, $permissao);

        return self::ENTIDADES[$entidade];
    }
}
