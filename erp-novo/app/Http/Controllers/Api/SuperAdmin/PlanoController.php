<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Domain\Saas\RecursoCatalogo;
use App\Domain\Saas\SuperAdminService;
use App\Http\Controllers\Controller;
use App\Models\Saas\Plano;
use App\Models\Saas\PlanoLimite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Planos (global) pelo SuperAdmin (P4): CRUD do catálogo de planos + seus recursos
 * (feature-flags). O catálogo de recursos disponíveis vem do RecursoCatalogo.
 */
class PlanoController extends Controller
{
    public function __construct(private SuperAdminService $service) {}

    /** GET /superadmin/planos */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Plano::query()->with('recursos')->orderBy('preco_mensal')->get()
                ->map(fn (Plano $p) => [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    'nome' => $p->nome,
                    'descricao' => $p->descricao,
                    'preco_mensal' => (float) $p->preco_mensal,
                    'ativo' => (bool) $p->ativo,
                    // F2-04: a UI precisa distinguir oferta de transição —
                    // um plano de R$ 0,00 com tudo incluso, sem rótulo, lê-se
                    // como o melhor negócio da grade.
                    'transitorio' => (bool) $p->transitorio,
                    'recursos' => $p->recursos->pluck('recurso_chave'),
                    // chave => teto. `null` = ilimitado; chave ausente idem.
                    'limites' => PlanoLimite::query()->where('plano_id', $p->id)
                        ->pluck('valor', 'limite_chave'),
                ]),
            // Catálogo de recursos disponíveis (para a UI montar os checkboxes).
            'catalogo_recursos' => collect(RecursoCatalogo::comDescricoes())
                ->map(fn ($desc, $chave) => ['chave' => $chave, 'descricao' => $desc])->values(),
            // Catálogo de limites: a UI monta um campo numérico por chave, e
            // vazio significa ilimitado.
            'catalogo_limites' => collect(RecursoCatalogo::LIMITES)
                ->map(fn ($desc, $chave) => ['chave' => $chave, 'descricao' => $desc])->values(),
        ]);
    }

    /** POST /superadmin/planos */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);

        return response()->json(['data' => $this->service->salvarPlano($d['plano'], $d['recursos'], null, $d['limites'])], 201);
    }

    /** PUT /superadmin/planos/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $d = $this->validar($request);

        return response()->json(['data' => $this->service->salvarPlano($d['plano'], $d['recursos'], $id, $d['limites'])]);
    }

    /** @return array{plano: array<string,mixed>, recursos: list<string>, limites: array<string,int|null>|null} */
    private function validar(Request $request): array
    {
        $d = $request->validate([
            'slug' => 'required|string|max:60',
            'nome' => 'required|string|max:120',
            'descricao' => 'nullable|string|max:255',
            'preco_mensal' => 'required|numeric|min:0',
            'ativo' => 'nullable|boolean',
            'recursos' => 'present|array',
            'recursos.*' => 'string',
            // `limites` ausente mantém o que já existe; presente substitui.
            // Valor nulo é ILIMITADO — por isso `nullable`, e não `min:0` só.
            'limites' => 'sometimes|array',
            'limites.*' => 'nullable|integer|min:0',
        ]);

        return [
            'plano' => [
                'slug' => $d['slug'], 'nome' => $d['nome'], 'descricao' => $d['descricao'] ?? null,
                'preco_mensal' => $d['preco_mensal'], 'ativo' => $d['ativo'] ?? true,
            ],
            'recursos' => $d['recursos'],
            'limites' => $d['limites'] ?? null,
        ];
    }
}
