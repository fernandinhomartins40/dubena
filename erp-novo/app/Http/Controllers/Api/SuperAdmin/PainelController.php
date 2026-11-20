<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Domain\Saas\AuditoriaPlataforma;
use App\Domain\Saas\CidadeService;
use App\Domain\Saas\SuperAdminService;
use App\Http\Controllers\Controller;
use App\Models\Saas\CidadePlataforma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Painel do SuperAdmin (P4): dashboard agregado, cidades da plataforma (catálogo
 * global) e a trilha de auditoria cross-tenant (platform_audit_logs).
 */
class PainelController extends Controller
{
    public function __construct(private SuperAdminService $service) {}

    /** GET /superadmin/dashboard */
    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => $this->service->dashboard()]);
    }

    /** GET /superadmin/auditoria — trilha de ações cross-tenant (paginada). */
    public function auditoria(Request $request): JsonResponse
    {
        $logs = DB::table('platform_audit_logs')
            ->leftJoin('platform_admins', 'platform_admins.id', '=', 'platform_audit_logs.platform_admin_id')
            ->when($request->query('empresa_id'), fn ($q, $e) => $q->where('platform_audit_logs.empresa_id', $e))
            ->when($request->query('acao'), fn ($q, $a) => $q->where('platform_audit_logs.acao', $a))
            ->orderByDesc('platform_audit_logs.id')
            ->limit(200)
            ->get([
                'platform_audit_logs.id', 'platform_audit_logs.acao', 'platform_audit_logs.empresa_id',
                'platform_audit_logs.entidade', 'platform_audit_logs.entidade_id',
                'platform_audit_logs.ip', 'platform_audit_logs.criado_em',
                'platform_admins.nome as admin',
            ]);

        return response()->json(['data' => $logs]);
    }

    // ── Cidades da plataforma (catálogo global) ──

    /** GET /superadmin/cidades */
    public function cidades(): JsonResponse
    {
        return response()->json(['data' => CidadePlataforma::query()->orderBy('uf')->orderBy('nome')->get()]);
    }

    /** POST /superadmin/cidades */
    public function cidadeStore(Request $request, CidadeService $cidades, AuditoriaPlataforma $auditoria): JsonResponse
    {
        $cidade = $cidades->salvar($this->validarCidade($request));
        // P2-E-10: o catálogo é global — mudar a cidade muda a descoberta de toda revenda.
        $auditoria->registrar('cidade.salva', null, 'cidades_plataforma', $cidade->id, null, $cidade->only(['nome', 'uf', 'ativo']));

        return response()->json(['data' => $cidade], 201);
    }

    /** PUT /superadmin/cidades/{id} */
    public function cidadeUpdate(Request $request, int $id, CidadeService $cidades, AuditoriaPlataforma $auditoria): JsonResponse
    {
        $antes = CidadePlataforma::query()->findOrFail($id)->only(['nome', 'uf', 'centro_lat', 'centro_lng', 'ativo']);
        $cidade = $cidades->salvar($this->validarCidade($request), $id);
        $auditoria->registrar('cidade.atualizada', null, 'cidades_plataforma', $id, $antes, $cidade->only(array_keys($antes)));

        return response()->json(['data' => $cidade]);
    }

    /** DELETE /superadmin/cidades/{id} */
    public function cidadeDestroy(int $id, AuditoriaPlataforma $auditoria): JsonResponse
    {
        $cidade = CidadePlataforma::query()->findOrFail($id);
        $cidade->delete();
        $auditoria->registrar('cidade.excluida', null, 'cidades_plataforma', $id, $cidade->only(['nome', 'uf']));

        return response()->json(['message' => 'Cidade excluída.']);
    }

    /** @return array<string,mixed> */
    private function validarCidade(Request $request): array
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
}
