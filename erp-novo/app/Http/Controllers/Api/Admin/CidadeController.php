<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Saas\CidadeService;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Saas\CidadePlataforma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cidades da plataforma (P3) — catálogo (cidades_plataforma) e o vínculo
 * empresa↔cidade (em quais cidades a empresa ativa atua). O catálogo é GLOBAL;
 * o vínculo é da empresa ATIVA (tenant). Permissão 'cidade.*'.
 *
 * Cidade é dimensão de descoberta/relatório — NÃO afeta o isolamento por empresa,
 * que segue por Grupo→Empresa + RLS.
 */
class CidadeController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(
        private CidadeService $service,
        private TenantContext $tenant,
    ) {}

    /** GET /admin/cidades — catálogo de cidades da plataforma. */
    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cidade.view');

        $rows = CidadePlataforma::query()
            ->when(trim((string) $request->query('q', '')), fn ($q, $b) => $q->where('nome', 'ilike', '%'.$b.'%'))
            ->orderBy('uf')->orderBy('nome')->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cidade.create');

        return response()->json(['data' => $this->service->salvar($this->validar($request))], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'cidade.edit');

        return response()->json(['data' => $this->service->salvar($this->validar($request), $id)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'cidade.delete');
        CidadePlataforma::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Cidade excluída.']);
    }

    /** GET /admin/empresas/{id}/cidades — cidades em que a empresa atua. */
    public function cidadesDaEmpresa(Request $request, int $empresaId): JsonResponse
    {
        $this->autorizar($request, 'empresa.view');
        $empresa = $this->empresaDoGrupo($request, $empresaId);

        return response()->json(['data' => $empresa->cidadesPlataforma()->orderBy('nome')->get(['cidades_plataforma.id', 'nome', 'uf'])]);
    }

    /** PUT /admin/empresas/{id}/cidades — define as cidades em que a empresa atua. */
    public function definirCidadesDaEmpresa(Request $request, int $empresaId): JsonResponse
    {
        $this->autorizar($request, 'empresa.edit');
        $empresa = $this->empresaDoGrupo($request, $empresaId);

        $d = $request->validate([
            'cidade_ids' => 'present|array',
            'cidade_ids.*' => 'integer|exists:cidades_plataforma,id',
        ]);

        $this->service->definirCidadesDaEmpresa($empresa, $d['cidade_ids']);

        return response()->json(['data' => $empresa->cidadesPlataforma()->orderBy('nome')->get(['cidades_plataforma.id', 'nome', 'uf'])]);
    }

    /** @return array<string,mixed> */
    private function validar(Request $request): array
    {
        return $request->validate([
            'nome' => 'required|string|max:255',
            'uf' => 'required|string|size:2',
            'cod_ibge' => 'nullable|integer',
            'centro_lat' => 'nullable|numeric|between:-90,90',
            'centro_lng' => 'nullable|numeric|between:-180,180',
            'ativo' => 'nullable|boolean',
        ]);
    }

    /** Resolve a empresa garantindo que pertence ao grupo do usuário (anti-cross-tenant). */
    private function empresaDoGrupo(Request $request, int $empresaId): Empresa
    {
        $user = $request->user();
        $grupo = (int) ($user->empresa?->grupo_id ?? $user->grupo_id);

        return Empresa::query()->where('grupo_id', $grupo)->findOrFail($empresaId);
    }
}
