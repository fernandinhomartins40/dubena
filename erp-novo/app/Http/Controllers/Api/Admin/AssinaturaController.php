<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Saas\LicencaService;
use App\Domain\Saas\RecursoCatalogo;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Saas\Assinatura;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Assinatura do PRÓPRIO tenant (P2) — somente leitura para o admin da empresa.
 * Mostra o plano/assinatura corrente e os recursos efetivos (o que está
 * habilitado). A GESTÃO cross-tenant de planos/assinaturas é do SuperAdmin (P4).
 *
 * Não exige permissão específica: qualquer usuário autenticado da empresa pode
 * ver o que sua empresa contratou (não há dado sensível além do nome do plano).
 */
class AssinaturaController extends Controller
{
    public function __construct(
        private LicencaService $licenca,
        private TenantContext $tenant,
    ) {}

    /** GET /api/admin/assinatura — plano/assinatura e recursos da empresa ativa. */
    public function show(Request $request): JsonResponse
    {
        $empresaId = $this->tenant->requireEmpresaId();

        $assinatura = Assinatura::query()
            ->with('plano')
            ->where('empresa_id', $empresaId)
            ->whereIn('status', [Assinatura::STATUS_TRIAL, Assinatura::STATUS_ATIVA])
            ->orderByDesc('id')
            ->first();

        $efetivos = $this->licenca->recursosEfetivos($empresaId);

        return response()->json(['data' => [
            'assinatura' => $assinatura ? [
                'status' => $assinatura->status,
                'vigente' => $assinatura->vigente(),
                'inicio' => $assinatura->inicio?->toDateString(),
                'fim' => $assinatura->fim?->toDateString(),
                'trial_ate' => $assinatura->trial_ate?->toDateString(),
                'plano' => $assinatura->plano ? [
                    'slug' => $assinatura->plano->slug,
                    'nome' => $assinatura->plano->nome,
                    'preco_mensal' => (float) $assinatura->plano->preco_mensal,
                ] : null,
            ] : null,
            // Recursos efetivos + catálogo completo (para a UI marcar o que tem/não tem).
            'recursos' => $efetivos,
            'catalogo' => collect(RecursoCatalogo::comDescricoes())
                ->map(fn ($desc, $chave) => [
                    'chave' => $chave,
                    'descricao' => $desc,
                    'habilitado' => in_array($chave, $efetivos, true),
                ])->values(),
        ]]);
    }
}
