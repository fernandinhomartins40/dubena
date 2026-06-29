<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Domain\Saas\LicencaService;
use App\Domain\Saas\SuperAdminService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Empresas (cross-tenant) pelo SuperAdmin (P4): listar todas, suspender/reativar,
 * definir plano/assinatura e overrides de recurso. Toda mutação passa pelo
 * SuperAdminService (auditado). NÃO expõe dados operacionais do tenant (clientes,
 * financeiro) — só metadados de plataforma (plano, status, ativo).
 */
class EmpresaController extends Controller
{
    public function __construct(private SuperAdminService $service) {}

    /** GET /superadmin/empresas */
    public function index(Request $request): JsonResponse
    {
        $busca = trim((string) $request->query('q', '')) ?: null;

        return response()->json(['data' => $this->service->empresas($busca)]);
    }

    /** POST /superadmin/empresas/{id}/suspender */
    public function suspender(int $id): JsonResponse
    {
        $this->service->suspenderEmpresa($id);

        return response()->json(['message' => 'Empresa suspensa.']);
    }

    /** POST /superadmin/empresas/{id}/reativar */
    public function reativar(int $id): JsonResponse
    {
        $this->service->reativarEmpresa($id);

        return response()->json(['message' => 'Empresa reativada.']);
    }

    /** PUT /superadmin/empresas/{id}/assinatura — define plano/assinatura. */
    public function definirAssinatura(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'plano_id' => 'required|integer|exists:planos,id',
            'status' => 'nullable|in:trial,ativa,inadimplente,cancelada',
            'inicio' => 'nullable|date',
            'fim' => 'nullable|date|after_or_equal:inicio',
            'trial_ate' => 'nullable|date',
        ]);

        $assinatura = $this->service->definirAssinatura($id, (int) $d['plano_id'], $d);

        return response()->json(['data' => $assinatura], 201);
    }

    /** PUT /superadmin/empresas/{id}/assinatura/status */
    public function alterarStatus(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['status' => 'required|in:trial,ativa,inadimplente,cancelada']);
        $assinatura = $this->service->alterarStatusAssinatura($id, $d['status']);

        abort_if($assinatura === null, 404, 'Empresa sem assinatura.');

        return response()->json(['data' => $assinatura]);
    }

    /** GET /superadmin/empresas/{id}/recursos — recursos efetivos da empresa. */
    public function recursos(int $id, LicencaService $licenca): JsonResponse
    {
        return response()->json(['data' => $licenca->recursosEfetivos($id)]);
    }

    /** PUT /superadmin/empresas/{id}/override — liga/desliga um recurso. */
    public function override(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'recurso_chave' => 'required|string',
            'habilitado' => 'required|boolean',
        ]);

        $override = $this->service->definirOverride($id, $d['recurso_chave'], (bool) $d['habilitado']);

        return response()->json(['data' => $override], 201);
    }

    /** DELETE /superadmin/empresas/{id}/override/{chave} */
    public function removerOverride(int $id, string $chave): JsonResponse
    {
        $this->service->removerOverride($id, $chave);

        return response()->json(['message' => 'Override removido.']);
    }
}
