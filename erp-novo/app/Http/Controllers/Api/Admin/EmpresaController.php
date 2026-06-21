<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmpresaRequest;
use App\Http\Resources\EmpresaResource;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Empresas do grupo (entidade-tenant) — N1.
 * Lista escopada pelo GRUPO do usuário; `ativar` troca o tenant ativo
 * (equivalente ao EmpresaController::change() do legado).
 */
class EmpresaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->autorizar($request, 'empresa.view');

        $empresas = $this->doGrupo($request)->orderBy('razao_social')->get();

        return EmpresaResource::collection($empresas);
    }

    public function show(Request $request, int $id): EmpresaResource
    {
        $this->autorizar($request, 'empresa.view');

        return new EmpresaResource($this->doGrupo($request)->findOrFail($id));
    }

    public function store(EmpresaRequest $request): JsonResponse
    {
        $this->autorizar($request, 'empresa.create');

        $empresa = Empresa::create(array_merge(
            $request->validated(),
            ['grupo_id' => $this->grupoDoUsuario($request)],
        ));

        return (new EmpresaResource($empresa))->response()->setStatusCode(201);
    }

    public function update(EmpresaRequest $request, int $id): EmpresaResource
    {
        $this->autorizar($request, 'empresa.edit');

        $empresa = $this->doGrupo($request)->findOrFail($id);
        $empresa->update($request->validated());

        return new EmpresaResource($empresa->refresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'empresa.delete');

        $this->doGrupo($request)->findOrFail($id)->delete();

        return response()->json(['message' => 'Empresa excluída.']);
    }

    /** Troca o tenant ativo para esta empresa (persistido no usuário). */
    public function ativar(Request $request, int $id, TenantContext $tenant): JsonResponse
    {
        $empresa = $this->doGrupo($request)->findOrFail($id);
        $user = $request->user();

        abort_unless($user->podeAcessarEmpresa($empresa->id), 403, 'Sem acesso a esta empresa.');

        $user->forceFill(['empresa_id' => $empresa->id])->save();
        $tenant->set($empresa->id, (int) $empresa->grupo_id);

        return response()->json([
            'message' => 'Empresa ativada.',
            'tenant' => ['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id],
        ]);
    }

    /** Query de empresas restrita ao grupo do usuário (sem escopo de empresa_id). */
    private function doGrupo(Request $request)
    {
        return Empresa::query()->where('grupo_id', $this->grupoDoUsuario($request));
    }

    private function grupoDoUsuario(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->empresa?->grupo_id ?? $user->grupo_id);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
