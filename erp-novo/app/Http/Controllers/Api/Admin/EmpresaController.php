<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Empresa\EnderecoEmpresaSync;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
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
    use AutorizaPorPermissao;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->autorizar($request, 'empresa.view');

        $query = $this->doGrupo($request);

        // O seletor do cabeçalho consome esta lista, então ela precisa conter
        // só o que o usuário REALMENTE acessa — mostrar uma filial que ele não
        // pode abrir vira um erro na cara dele. `support` continua vendo todas.
        $user = $request->user();
        if ($user !== null && ! $user->support && method_exists($user, 'empresasVisiveis')) {
            $query->whereIn('id', $user->empresasVisiveis($this->grupoDoUsuario($request)));
        }

        return EmpresaResource::collection($query->orderBy('razao_social')->get());
    }

    public function show(Request $request, int $id): EmpresaResource
    {
        $this->autorizar($request, 'empresa.view');

        return new EmpresaResource(
            $this->doGrupo($request)
                ->with(['cidadeCadastro', 'bairroCadastro', 'rua', 'regiao'])
                ->findOrFail($id),
        );
    }

    public function store(EmpresaRequest $request): JsonResponse
    {
        $this->autorizar($request, 'empresa.create');

        // O texto do endereço é derivado das FKs: a DANFE imprime a string, e
        // sem isto ela sairia vazia mesmo com a cidade escolhida no formulário.
        $empresa = Empresa::create(array_merge(
            app(EnderecoEmpresaSync::class)->aplicar($request->validated()),
            ['grupo_id' => $this->grupoDoUsuario($request)],
        ));

        return (new EmpresaResource(
            $empresa->load(['cidadeCadastro', 'bairroCadastro', 'rua', 'regiao']),
        ))->response()->setStatusCode(201);
    }

    public function update(EmpresaRequest $request, int $id): EmpresaResource
    {
        $this->autorizar($request, 'empresa.edit');

        $empresa = $this->doGrupo($request)->findOrFail($id);
        $empresa->update(app(EnderecoEmpresaSync::class)->aplicar($request->validated()));

        return new EmpresaResource(
            $empresa->refresh()->load(['cidadeCadastro', 'bairroCadastro', 'rua', 'regiao']),
        );
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

        // A empresa de ORIGEM vira vínculo permanente antes da troca.
        //
        // `podeAcessarEmpresa` aceita a empresa padrão (`users.empresa_id`) OU
        // uma da pivot. Como esta ação MUDA a padrão, sem gravar o vínculo o
        // usuário perderia o acesso à empresa de onde saiu — ficaria preso na
        // nova, com 403 ao tentar voltar. Regra da plataforma, não configuração
        // de tenant: vale para qualquer rede, presente ou futura.
        $origem = (int) $user->empresa_id;
        if ($origem > 0 && $origem !== (int) $empresa->id) {
            $user->empresas()->syncWithoutDetaching([$origem]);
        }

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
}
