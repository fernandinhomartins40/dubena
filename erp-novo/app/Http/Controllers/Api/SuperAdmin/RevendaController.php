<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Domain\Saas\TenantProvisioningService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ProvisionarRevendaRequest;
use Illuminate\Http\JsonResponse;

/**
 * PIE P2-A-01 — criação de revenda pela plataforma. A regra mora no
 * `TenantProvisioningService`; a trilha da plataforma é gravada lá.
 */
class RevendaController extends Controller
{
    /** POST /superadmin/revendas — o convite do dono volta SÓ nesta resposta. */
    public function store(ProvisionarRevendaRequest $request, TenantProvisioningService $service): JsonResponse
    {
        return response()->json(['data' => $service->provisionar($request->validated())], 201);
    }
}
